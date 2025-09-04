<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\EmployeeModel;
use SchoolLive\Models\UserModel;
use PDO;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;

class EmployeesController extends BaseController {
    private EmployeeModel $employees;
    private UserModel $users;

    public function __construct() {
        parent::__construct();
        $this->employees = new EmployeeModel();
        $this->users = new UserModel();
    }

    public function list($params = []) {
        $current = $this->currentUser(); if(!$current) return;
        $filters = [
            'role_id' => $_GET['role_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            // default to only active employees unless caller explicitly passes is_active
            'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1,
            'search' => $_GET['search'] ?? null
        ];
        $data = $this->employees->listEmployees($current['school_id'], $current['AcademicYearID'] ?? null, $filters);
        $this->ok('Employees retrieved', $data);
    }

    public function get($params = []) {
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $emp = $this->employees->getEmployee($id, $current['school_id']);
        if (!$emp) { $this->fail('Employee not found',404); return; }
        $this->ok('Employee retrieved', $emp);
    }

    public function create($params = []) {
        if (!$this->requireMethod('POST')) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
    $required = ['FirstName','Gender','JoiningDate','RoleID','Salary'];
        foreach ($required as $f) { if (empty($input[$f])) { $this->fail("$f is required",400); return; } }
        $input['SchoolID'] = $current['school_id'];
        $input['AcademicYearID'] = $current['AcademicYearID'] ?? 1;
        $input['CreatedBy'] = $current['username'] ?? 'System';

        // Normalize and validate contact fields: only digits allowed
        $clean = function($v) {
            if ($v === null) return null;
            $s = preg_replace('/\D+/', '', (string)$v);
            return $s === '' ? null : $s;
        };
        $input['ContactNumber'] = $clean($input['ContactNumber'] ?? null);
        $input['FatherContactNumber'] = $clean($input['FatherContactNumber'] ?? null);
        $input['MotherContactNumber'] = $clean($input['MotherContactNumber'] ?? null);

        // enforce required numeric contacts server-side too
        if (empty($input['ContactNumber']) || strlen($input['ContactNumber']) < 10) { $this->fail('ContactNumber is required and must be at least 10 digits',400); return; }
        if (empty($input['FatherContactNumber']) || strlen($input['FatherContactNumber']) < 10) { $this->fail('FatherContactNumber is required and must be at least 10 digits',400); return; }

        // Create a provisional user first (username auto-generated if not provided),
        // then create employee, then update username to include SchoolID+EmployeeID and set initial password to same value.
        $conn = $this->employees->getPdo();
        try {
            $conn->beginTransaction();

            $userPayload = [];
            // Use explicit name parts from input; fall back to simple defaults if missing
            $first = $input['FirstName'] ?? ($input['first_name'] ?? null);
            $middle = $input['MiddleName'] ?? ($input['middle_name'] ?? null);
            $last = $input['LastName'] ?? ($input['last_name'] ?? null);

            // Map common user fields from input if present
            if (!empty($input['Username'])) $userPayload['username'] = $input['Username'];
            if (!empty($input['username'])) $userPayload['username'] = $input['username'];
            if (!empty($input['EmailID'])) $userPayload['email'] = $input['EmailID'];
            if (!empty($input['email'])) $userPayload['email'] = $input['email'];
            if (!empty($input['RoleID'])) $userPayload['role_id'] = $input['RoleID'];
            // Pass contact number from employee form to user creation so Tx_Users.ContactNumber is set
            if (!empty($input['ContactNumber'])) $userPayload['contact'] = $input['ContactNumber'];
            $userPayload['school_id'] = $input['SchoolID'];
            $userPayload['created_by'] = $input['CreatedBy'];

            // supply name parts to satisfy NOT NULL in users table
            $userPayload['first_name'] = trim((string)($first ?? '')) ?: 'User';
            if (!empty($middle)) $userPayload['middle_name'] = $middle;
            $userPayload['last_name'] = trim((string)($last ?? '')) ?: '';

            // Create provisional user (UserModel will generate a username if missing)
            // Ensure a provisional password exists so the NOT NULL PasswordHash DB column is satisfied
            if (empty($userPayload['password'])) {
                try {
                    $userPayload['password'] = bin2hex(random_bytes(4));
                } catch (\Throwable $_) {
                    $userPayload['password'] = 'pwd_' . uniqid();
                }
            }

            $userId = $this->users->createUser($userPayload);
            if (!$userId) throw new \Exception('Failed to create provisional user');

            // Assign the created user to employee payload and create employee
            $input['UserID'] = $userId;
            $empId = $this->employees->createEmployee($input);
            if (!$empId) throw new \Exception('Failed to create employee');

            // Build final username and set initial password to the same value
            $finalUsername = 's' . $input['SchoolID'] . '_e' . $empId;
            $initialPassword = $finalUsername; // per request: same password initially

            // Commit the employee transaction first to avoid lock contention between two separate PDO connections
            $conn->commit();

            // Now update username and password for the created user (outside the employee transaction)
            try {
                $hash = password_hash($initialPassword, PASSWORD_DEFAULT);
                $upd = $this->users->getPdo()->prepare("UPDATE Tx_Users SET Username = :username, PasswordHash = :ph, UpdatedAt = :updated, UpdatedBy = :updated_by WHERE UserID = :id");
                $upd->execute([':username' => $finalUsername, ':ph' => $hash, ':updated' => date('Y-m-d H:i:s'), ':updated_by' => $current['username'] ?? 'System', ':id' => $userId]);
            } catch (\Throwable $ex) {
                // Log but don't fail the whole request since employee and user were created; this avoids a permanent lock situation
                error_log('[EmployeesController::create] Failed to update created user: ' . $ex->getMessage());
            }

            // Return employee with created user info and initial password for display
            $emp = $this->employees->getEmployee($empId, $current['school_id']);
            // Attach generated credentials to response (careful in production)
            $emp['generated_username'] = $finalUsername;
            $emp['generated_password'] = $initialPassword;
            $this->ok('Employee created', $emp);
            return;
        } catch (\Throwable $e) {
            try { $conn->rollBack(); } catch (\Throwable $_) {}
            error_log('[EmployeesController::create] ' . $e->getMessage());
            // Return the error message to help debug the failure (safe to remove in production)
            $this->fail('Failed to create employee and user: ' . $e->getMessage(), 500);
            return;
        }
    }

    public function update($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $input['UpdatedBy'] = $current['username'] ?? 'System';
        $exists = $this->employees->getEmployee($id, $current['school_id']);
        if (!$exists) { $this->fail('Employee not found',404); return; }
        if ($this->employees->updateEmployee($id, $current['school_id'], $input)) {
            $emp = $this->employees->getEmployee($id, $current['school_id']);
            $this->ok('Employee updated', $emp);
        } else {
            $this->fail('Failed to update employee',500);
        }
    }

    public function delete($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $exists = $this->employees->getEmployee($id, $current['school_id']);
        if (!$exists) { $this->fail('Employee not found',404); return; }
    if ($this->employees->deleteEmployee($id, $current['school_id'], $current['username'])) {
            $this->ok('Employee deleted');
        } else { $this->fail('Failed to delete employee',500); }
    }

    /**
     * Reset an employee's credentials to the deterministic format: s{SchoolID}_e{EmployeeID}
     * POST /api/employees/{id}/reset-password
     * Requires authentication and admin/authorized role (router enforces login)
     */
    public function resetPassword($params = []) {
        if (!$this->requireMethod('POST')) return;
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;

        $emp = $this->employees->getEmployee($id, $current['school_id']);
        if (!$emp) { $this->fail('Employee not found',404); return; }

        // Derive final username and password
        $schoolId = $emp['SchoolID'] ?? $current['school_id'];
        $finalUsername = 's' . $schoolId . '_e' . $id;
        $finalPasswordPlain = $finalUsername;

        try {
            $hash = password_hash($finalPasswordPlain, PASSWORD_DEFAULT);
            $pdo = $this->users->getPdo();
            $stmt = $pdo->prepare("UPDATE Tx_Users SET Username = :u, PasswordHash = :p, UpdatedAt = :up, UpdatedBy = :ub WHERE UserID = :id");
            $stmt->execute([':u' => $finalUsername, ':p' => $hash, ':up' => date('Y-m-d H:i:s'), ':ub' => $current['username'] ?? 'System', ':id' => $emp['UserID'] ?? 0]);

            $this->ok('Password reset', ['username' => $finalUsername, 'password' => $finalPasswordPlain]);
            return;
        } catch (\Throwable $ex) {
            error_log('[EmployeesController::resetPassword] ' . $ex->getMessage());
            $this->fail('Failed to reset password: ' . $ex->getMessage(),500);
            return;
        }
    }
}
