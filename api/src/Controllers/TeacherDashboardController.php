<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\TeacherDashboardModel;

class TeacherDashboardController extends BaseController {
    private TeacherDashboardModel $model;
    public function __construct() { parent::__construct(); $this->model = new TeacherDashboardModel(); }

    /** GET /api/dashboard/teacher */
    public function getMonthlyAttendance($params = []) {
        $params = array_merge($_GET, $params); // merge query params with given params
        $employeeId = (int)($params['employee_id'] ?? NULL);
        if (!$employeeId) { $this->fail('Employee ID is required', 404); return; }

        $user = $this->currentUser(); if(!$user) return;
        
        $schoolId = (int)$user['school_id'];
        // Validate or resolve employee id: prefer explicit param but always validate it belongs to the school
        $employeeId = $this->model->resolveEmployeeIdForUser($schoolId, $employeeId);
        if (!$employeeId) { $this->fail('Employee profile not linked to this user', 404); return; }

        $academicYearId = !empty($params['academic_year_id']) ? (int)$params['academic_year_id'] : ($user['AcademicYearID'] ?? null);

        try {
            $monthly = $this->model->getMonthlyAttendance($schoolId, $employeeId, $academicYearId);
            $payload = [ 'charts' => [ 'monthlyAttendance' => $monthly ] ];
            $this->ok('Teacher dashboard fetched', $payload);
        } catch (\Throwable $e) {
            $this->fail('Failed to load teacher dashboard: '.$e->getMessage(), 500);
        }
    }
}
