<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\UserModel;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Models\AcademicModel;
use SchoolLive\Models\EmployeeModel;
use SchoolLive\Models\StudentModel;
use SchoolLive\Core\BaseController;

class LoginController extends BaseController {
    private $userModel;

    public function __construct() {
    parent::__construct();
    $this->userModel = new UserModel();
    }

    public function login($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();

        // Validate input
        if ((!isset($input['email']) && !isset($input['username'])) || !isset($input['password'])) {
            $this->fail('Email/Username and password are required', 400);
            return;
        }

        // Resolve user and validate credentials
        $user = $this->findUserByCredentials($input);
        if (!$user || !$this->userModel->verifyPassword($input['password'], $user['PasswordHash'])) { $this->fail('Invalid credentials',401); return; }
        if ($user['IsActive'] != 1) { $this->fail('Account is not active',403); return; }

        // Build payload and attach normalized fields
        $userData = $this->buildUserPayload($user);
        $userData = $this->attachAcademicYear($userData, $user['SchoolID']);
        $userData = $this->attachCanonicalIds($userData, $user);

        // Build minimal token claims (don't embed full user object)
        $claims = [
            'id' => $userData['id'],
            'resourceType' => $userData['resourceType'] ?? null,
            'resourceId' => $userData['resourceId'] ?? null,
            'role' => $userData['role'] ?? null,
            'school_id' => $userData['school_id'] ?? null,
            'AcademicYearID' => $userData['AcademicYearID'] ?? null,
            'username' => $userData['username'] ?? null
        ];

        $accessToken = AuthMiddleware::generateToken($claims);
        $refreshToken = AuthMiddleware::generateRefreshToken($claims);

        $this->ok('Login successful', data: [
            'user' => $userData,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer'
        ]);
    }

    // Helper: find user by email or username
    private function findUserByCredentials(array $input) {
        if (isset($input['email'])) return $this->userModel->findByEmail($input['email']);
        if (isset($input['username'])) return $this->userModel->findByUsername($input['username']);
        return null;
    }

    // Helper: build base user payload
    private function buildUserPayload(array $user): array {
        return [
            'id' => $user['UserID'],
            'username' => $user['Username'],
            'email' => $user['EmailID'],
            'role' => $user['RoleName'],
            'role_id' => $user['RoleID'],
            'school_id' => $user['SchoolID'],
            'school_name' => $user['SchoolName'],
            'first_name' => $user['FirstName'],
            'middle_name' => $user['MiddleName'],
            'last_name' => $user['LastName'],
            'is_first_login' => $user['IsFirstLogin']
        ];
    }

    // Helper: attach AcademicYearID when available
    private function attachAcademicYear(array $userData, $schoolId): array {
        try {
            $academicModel = new AcademicModel();
            $currentAcademic = $academicModel->getCurrentAcademicYear($schoolId);
            $userData['AcademicYearID'] = ($currentAcademic && isset($currentAcademic['AcademicYearID'])) ? $currentAcademic['AcademicYearID'] : null;
        } catch (\Throwable $_) {
            $userData['AcademicYearID'] = null;
        }
        return $userData;
    }

    // Helper: attach normalized canonical identifiers (tx_user_id, employee_id, student_id, resourceType, resourceId)
    private function attachCanonicalIds(array $userData, array $user): array {
        $txUserId = $user['UserID'] ?? $user['tx_user_id'] ?? $user['id'] ?? null;
        $employeeId = $user['EmployeeID'] ?? $user['tx_employee'] ?? $user['employee_id'] ?? null;
        $studentId = $user['StudentID'] ?? $user['tx_student'] ?? $user['student_id'] ?? null;

        // Resolve missing ids from related tables if possible
        if (empty($employeeId) && !empty($txUserId) && !empty($user['SchoolID'])) {
            try {
                $empModel = new EmployeeModel();
                $foundEmp = $empModel->getEmployeeId((int)$txUserId, (int)$user['SchoolID']);
                if ($foundEmp) $employeeId = $foundEmp;
            } catch (\Throwable $_) { }
        }
        if (empty($studentId) && !empty($txUserId) && !empty($user['SchoolID'])) {
            try {
                $stuModel = new StudentModel();
                $foundStu = $stuModel->getStudentByUserId((int)$txUserId, (int)$user['SchoolID']);
                if ($foundStu && isset($foundStu['StudentID'])) $studentId = $foundStu['StudentID'];
            } catch (\Throwable $_) { }
        }

        $resourceType = null;
        $resourceId = null;
        if (!empty($employeeId)) { $resourceType = 'employee'; $resourceId = $employeeId; }
        elseif (!empty($studentId)) { $resourceType = 'student'; $resourceId = $studentId; }
        else { $resourceType = 'user'; $resourceId = $txUserId; }

        $userData['tx_user_id'] = $txUserId;
        $userData['employee_id'] = $employeeId;
        $userData['student_id'] = $studentId;
        $userData['resourceType'] = $resourceType;
        $userData['resourceId'] = $resourceId;

        return $userData;
    }

    public function register($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'username', 'password', 'role_id'];
    foreach ($requiredFields as $field) { if (!isset($input[$field]) || empty($input[$field])) { $this->fail(ucfirst($field).' is required',400); return; } }

        // Validate email format if provided
    if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) { $this->fail('Invalid email format',400); return; }

        // Check if user already exists
    if ($this->userModel->findByUsername($input['username'])) { $this->fail('User already exists with this username',409); return; }

    if (isset($input['email']) && $this->userModel->findByEmail($input['email'])) { $this->fail('User already exists with this email',409); return; }

        // Validate role_id (should be valid role ID from Tm_Roles)
        $allowedRoleIds = [1, 2, 3, 4]; // super_admin, client_admin, teacher, student
    if (!in_array($input['role_id'], $allowedRoleIds)) { $this->fail('Invalid role ID',400); return; }

        // Set default values
        $input['created_by'] = 'System';

        $result = $this->userModel->createUser($input);

        if ($result) {
            $user = $this->userModel->findByUsername($input['username']);
            unset($user['PasswordHash']);
            $this->ok('User registered successfully', ['user'=>$user]);
        } else { $this->fail('Failed to register user',500); }
    }

    public function refresh($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();
        if (!isset($input['refresh_token'])) { $this->fail('Refresh token is required',400); return; }

        $userData = AuthMiddleware::validateRefreshToken($input['refresh_token']);

    if (!$userData) { $this->fail('Invalid or expired refresh token',401); return; }

        // Verify user still exists and is active
        $user = $this->userModel->findById($userData['id']);
    if (!$user || $user['IsActive'] != 1) { $this->fail('User account is no longer valid',401); return; }

    // Ensure current academic id is present and up-to-date in the token payload
    $academicModel = new AcademicModel(); 
    $currentAcademic = $academicModel->getCurrentAcademicYear($userData['school_id']);
    $userData['AcademicYearID'] = ($currentAcademic && isset($currentAcademic['AcademicYearID'])) ? $currentAcademic['AcademicYearID'] : null;

    // Use minimal claims for new tokens
    $claims = [
        'id' => $userData['id'],
        'resourceType' => $userData['resourceType'] ?? null,
        'resourceId' => $userData['resourceId'] ?? null,
        'role' => $userData['role'] ?? null,
        'school_id' => $userData['school_id'] ?? null,
        'AcademicYearID' => $userData['AcademicYearID'] ?? null,
        'username' => $userData['username'] ?? null
    ];

    $newAccessToken = AuthMiddleware::generateToken($claims);
    $newRefreshToken = AuthMiddleware::generateRefreshToken($claims);

    $this->ok('Token refreshed successfully', [
        'access_token' => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer'
    ]);
    }

    /**
     * Change password for a user.
     * PUT /api/users/{id}/password
     * Body: { oldPassword, newPassword }
     * If the requester is the same user, oldPassword is required. Admins may change without oldPassword.
     */
    public function changePassword() {
        if (!$this->requireMethod('POST')) return;

        $current = $this->currentUser(); if(!$current) return;

        $input = $this->input();
        $id= $input['userId'] ?? null;
        $old = $input['oldPassword'] ?? null;
        $new = $input['newPassword'] ?? null;

        if (empty($new)) { $this->fail('New password is required',status: 400); return; }
        if (strlen($new) < 6) { $this->fail('New password must be at least 6 characters',400); return; }

        try {
            // Fetch target user record including password hash
            $target = $this->userModel->getPdo()->prepare("SELECT UserID, PasswordHash, RoleID FROM Tx_Users WHERE UserID = :id LIMIT 1");
            $target->execute([':id' => $id]);
            $userRow = $target->fetch();
            if (!$userRow) { $this->fail('User not found',404); return; }

            if (empty($old)) { $this->fail('Old password is required',status: 400); return; }
            if (!$this->userModel->verifyPassword($old, $userRow['PasswordHash'])) { $this->fail('Old password is incorrect',status: 400); return; }
            // Update to new password
            $updated = $this->userModel->updatePassword($id, $new);
            if ($updated) {
                $this->ok(message: 'Password changed');
            } else {
                $this->fail('Failed to update password',500);
            }
            return;
        } catch (\Throwable $ex) {
            error_log('[LoginController::changePassword] ' . $ex->getMessage());
            $this->fail('Server error while changing password',status: 500);
            return;
        }
    }
}
