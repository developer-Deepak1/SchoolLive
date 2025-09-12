<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\EmployeeAttendanceModel;
use SchoolLive\Models\EmployeeAttendanceRequestsModel;

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
        // attempt to insert
        $id = $this->requestsModel->createRequest($schoolId, $employeeId, $date, $type, $reason, $createdBy, $academicYearId);
        if ($id === false) { $this->fail('Failed to create request', 500); return; }
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
}
