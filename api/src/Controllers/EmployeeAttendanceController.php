<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\EmployeeAttendanceModel;

class EmployeeAttendanceController extends BaseController {
    private $model;
    public function __construct() {
        parent::__construct();
        $this->model = new EmployeeAttendanceModel();
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
}
