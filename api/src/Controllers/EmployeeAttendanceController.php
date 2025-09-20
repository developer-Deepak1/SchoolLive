<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\EmployeeAttendanceModel;
use SchoolLive\Models\EmployeeAttendanceRequestsModel;
use Exception;

class EmployeeAttendanceController extends BaseController {
    private $model;
    private $requestsModel;
    public function __construct() {
        parent::__construct();
        $this->model = new EmployeeAttendanceModel();
        $this->requestsModel = new EmployeeAttendanceRequestsModel();
    }

    // POST /api/employee/attendance/signin { date?: 'YYYY-MM-DD' }
    public function signIn($params = []) {
        if (!$this->requireMethod('POST')) return;
        $user = $this->currentUser(); if (!$user) return;
    $input = $this->input();
    $date = isset($input['date']) ? $input['date'] : date('Y-m-d');
    $schoolId = $user['school_id'] ?? null;
    // allow caller to pass employee_id explicitly (e.g., frontend supplies it); otherwise fall back to current user
    $employeeId = isset($input['employee_id']) && $input['employee_id'] ? $input['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
    $academicYearId = $user['AcademicYearID'] ?? null;
    if (!$employeeId) { $this->fail('Employee not found for user', 400); return; }

    $signInAt = date('Y-m-d H:i:s');
    $res = (array)$this->model->upsertSignIn($schoolId, $employeeId, $date, $signInAt, $user['username'] ?? 'system', $academicYearId);
    $this->ok('Signed in', $res);
    }

    // POST /api/employee/attendance/signout { date?: 'YYYY-MM-DD' }
    public function signOut($params = []) {
        if (!$this->requireMethod('POST')) return;
        $user = $this->currentUser(); if (!$user) return;
    $input = $this->input();
    $date = isset($input['date']) ? $input['date'] : date('Y-m-d');
    $schoolId = $user['school_id'] ?? null;
    $employeeId = isset($input['employee_id']) && $input['employee_id'] ? $input['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $academicYearId = $user['AcademicYearID'] ?? null;
        if (!$employeeId) { $this->fail('Employee not found for user', 400); return; }

        $signoutAt = date('Y-m-d H:i:s');
    $res = (array)$this->model->upsertSignOut($schoolId, $employeeId, $date, $signoutAt, $user['username'] ?? 'system', $academicYearId);
    $this->ok('Signed out', $res);
    }

    // GET /api/employee/attendance/today?date=YYYY-MM-DD
    public function getEmployeeAttendanceForDate($params = []) {
        $user = $this->currentUser(); if (!$user) return;
    $date = $_GET['date'] ?? date('Y-m-d');
    $schoolId = $user['school_id'] ?? null;
    // allow query param override
    $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] ? $_GET['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $academicYearId = $user['AcademicYearID'] ?? null;
        if (!$employeeId) { $this->fail('Employee not found for user', 400); return; }

        $row = $this->model->getEmployeeAttendanceForDate($schoolId, $employeeId, $date, $academicYearId);
        $this->ok('Employee attendance', $row);
    }

    // GET /api/employee/attendance/status?date=YYYY-MM-DD
    public function getEmployeeStatusToday($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $date = $_GET['date'] ?? date('Y-m-d');
        $schoolId = $user['school_id'] ?? null;
        $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] ? $_GET['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $academicYearId = $user['AcademicYearID'] ?? null;
        if (!$employeeId) { $this->fail('Employee not found for user', 400); return; }
        $res = $this->model->getEmployeeStatusToday($schoolId, $employeeId, $date, $academicYearId);
        $this->ok('Employee status', $res);
    }

    // GET /api/employee/attendance/leaveReason?date=YYYY-MM-DD
    public function getLeaveReason($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $date = $_GET['date'] ?? date('Y-m-d');
        $schoolId = $user['school_id'] ?? null;
        $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] ? $_GET['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $academicYearId = $user['AcademicYearID'] ?? null;
        if (!$employeeId) { $this->fail('Employee not found for user', 400); return; }
        $reason = $this->model->getLeaveReason($schoolId, $employeeId, $date, $academicYearId);
        $this->ok('Leave reason', ['reason' => $reason]);
    }

    // POST /api/employee/attendance/requests/create
    public function createRequest($params = []) {
        if (!$this->requireMethod('POST')) return;
        $user = $this->currentUser(); if (!$user) return;
        $input = $this->input();
        // required fields: date, request_type
        if (!isset($input['date']) || !isset($input['request_type'])) { $this->fail('date and request_type are required', 400); return; }
        $date = $input['date'];
        $type = $input['request_type'];
        $reason = $input['reason'] ?? null;
        $employeeId = $input['employee_id'] ?? ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $schoolId = $user['school_id'] ?? null;
        $academicYearId = $user['AcademicYearID'] ?? 0;
        if (!$employeeId) { $this->fail('Employee not found', 400); return; }
        $createdBy = $user['username'] ?? 'system';
    // attempt to insert (model may return 'exists_active' or reactivated id)
    $id = $this->requestsModel->createRequest($schoolId, $employeeId, $date, $type, $reason, $createdBy, $academicYearId);
    if ($id === false) { $this->fail('Failed to create request', 500); return; }
    if ($id === 'exists_active') { $this->fail('A request already exists for this date', 409); return; }
    // otherwise id is either new insert id or reactivated existing id
    $this->ok('Request created', ['id' => $id]);
    }

    // GET /api/employee/attendance/requests?employee_id=&status=
    public function listRequests($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] ? $_GET['employee_id'] : ($user['EmployeeID'] ?? $user['employee_id'] ?? null);
        $status = $_GET['status'] ?? null;
        $schoolId = $user['school_id'] ?? null;
        $academicYearId = $user['AcademicYearID'] ?? null;
        $rows = $this->requestsModel->listRequests($schoolId, $employeeId, $status, $academicYearId);
        $this->ok('Requests list', $rows);
    }

    // DELETE /api/employee/attendance/requests/{id}
    public function cancelRequest($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $user = $this->currentUser(); if (!$user) return;
        $id = $this->requireKey($params, 'id'); if (!$id) return;
        $ok = $this->requestsModel->cancelRequest($id, $user['username'] ?? 'system');
        if ($ok) $this->ok('Request cancelled', null);
        else $this->fail('Cancel failed', 400);
    }

    // POST /api/employee/attendance/requests/{id}/approve
    public function approveRequest($params = []) {
        if (!$this->requireMethod('POST')) return;
        $user = $this->currentUser(); if (!$user) return;
        $id = $this->requireKey($params, 'id'); if (!$id) return;
        $ok = $this->requestsModel->approveRequest($id, $user['username'] ?? 'system');
        if ($ok) $this->ok('Request approved', null);
        else $this->fail('Approve failed', 400);
    }

    // POST /api/employee/attendance/requests/{id}/reject
    public function rejectRequest($params = []) {
        if (!$this->requireMethod('POST')) return;
        $user = $this->currentUser(); if (!$user) return;
        $id = $this->requireKey($params, 'id'); if (!$id) return;
        $ok = $this->requestsModel->rejectRequest($id, $user['username'] ?? 'system');
        if ($ok) $this->ok('Request rejected', null);
        else $this->fail('Reject failed', 400);
    }

    // GET /api/employee/attendance/monthly?year=2025&month=9&role_id=2
    public function monthly($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $roleId = isset($_GET['role_id']) && $_GET['role_id'] !== '' ? (int)$_GET['role_id'] : null;
        $schoolId = $user['school_id'] ?? null;
        $academicYearId = $user['AcademicYearID'] ?? null;
        
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            $this->fail('Invalid year or month', 400);
            return;
        }

        try {
            $records = $this->model->getMonthlyAttendance($schoolId, $academicYearId, $year, $month, $roleId);
            $this->ok('Monthly employee attendance', ['records' => $records]);
        } catch (Exception $e) {
            error_log("Error in employee monthly attendance: " . $e->getMessage());
            // Include exception message in response for debugging (remove in production)
            $this->fail('Failed to fetch monthly attendance: ' . $e->getMessage(), 500);
        }
    }

    // GET /api/employee/attendance/details?year=2025&month=9 (Admin view - all employees)
    public function getAttendanceDetails($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $schoolId = $user['school_id'] ?? null;
        $academicYearId = $user['AcademicYearID'] ?? null;
        
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            $this->fail('Invalid year or month', 400);
            return;
        }

        try {
            $records = $this->model->getAttendanceDetailsByMonth($schoolId, $academicYearId, $year, $month);
            $this->ok('Employee attendance details', $records);
        } catch (Exception $e) {
            error_log("Error in employee attendance details: " . $e->getMessage());
            $this->fail('Failed to fetch attendance details: ' . $e->getMessage(), 500);
        }
    }

    // GET /api/employee/attendance/user-details?year=2025&month=9 (User view - current user only)
    public function getUserAttendanceDetails($params = []) {
        $user = $this->currentUser(); if (!$user) return;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $schoolId = $user['school_id'] ?? null;
        $academicYearId = $user['AcademicYearID'] ?? null;
        $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
        
        if (!$employeeId) { 
            $this->fail('Employee not found for user', 400); 
            return; 
        }
        
        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) {
            $this->fail('Invalid year or month', 400);
            return;
        }

        try {
            $records = $this->model->getUserAttendanceDetailsByMonth($schoolId, $academicYearId, $employeeId, $year, $month);
            $this->ok('User attendance details', $records);
        } catch (Exception $e) {
            error_log("Error in user attendance details: " . $e->getMessage());
            $this->fail('Failed to fetch user attendance details: ' . $e->getMessage(), 500);
        }
    }
}
