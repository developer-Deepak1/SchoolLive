<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\StudentDashboardModel;

class StudentDashboardController extends BaseController {
    private StudentDashboardModel $model;
    public function __construct() { parent::__construct(); $this->model = new StudentDashboardModel(); }

    /** GET /api/dashboard/student */
    public function summary($params = []) {
        // merge query params so callers can pass student_id
        $params = array_merge($_GET, $params);
        $user = $this->currentUser(); if(!$user) return;
        $schoolId = (int)$user['school_id'];
        $academicYearId = $user['AcademicYearID'] ?? null;
        // Support multiple possible key names for user id coming from token/user payload
        $userId = (int)($user['id'] ?? $user['user_id'] ?? $user['UserID'] ?? 0);
        if ($userId <= 0) { $this->fail('User context missing',400); return; }

        // If a student_id is provided and the current user is an admin/teacher, allow viewing that student's dashboard
        $requestedStudentId = isset($params['student_id']) ? (int)$params['student_id'] : null;
        $studentId = null;
        if ($requestedStudentId) {
            // validate the requested student belongs to the same school
            if ($this->model->validateStudentBelongsToSchool($schoolId, studentId: $requestedStudentId)) {
                $studentId = $requestedStudentId;
            } else {
                $this->fail('Requested student does not belong to your school', 404); return;
            }
        }

        // If no privileged request, resolve student linked to the current user
        if (!$studentId) {
            // Resolve the student id linked to the current user account
            $studentId = $this->model->resolveStudentIdForUser(schoolId: $schoolId, studentId: $userId);
            if (!$studentId) { $this->fail('Student profile not linked to this user',404); return; }
        }
        try {
            $avgAttendance = $this->model->getAverageAttendance($schoolId, $studentId, $academicYearId);
            $monthlyAttendance = $this->model->getMonthlyAttendancePercentage($schoolId, $studentId, $academicYearId, 12);
            $today = $this->model->getTodayAttendance($schoolId, $studentId, $academicYearId);
            $payload = [
                'stats' => [ 'averageAttendance' => $avgAttendance ],
                'charts' => [ 'monthlyAttendance' => $monthlyAttendance ],
                'today' => $today
            ];
            $this->ok('Student dashboard fetched', $payload);
        } catch (\Throwable $e) {
            $this->fail('Failed to load student dashboard: '.$e->getMessage(),500);
        }
    }

    /** GET /api/dashboard/student/monthlyAttendance - lightweight endpoint for just monthly attendance chart */
    public function getMonthlyAttendance($params = []) {
        $params = array_merge($_GET, $params);
        $user = $this->currentUser(); if(!$user) return;
        $schoolId = (int)$user['school_id'];
        // support optional student_id param for teachers/admins
        $requestedStudentId = isset($params['student_id']) ? (int)$params['student_id'] : null;

        // If a student_id was provided, validate it belongs to the same school. If not provided, resolve linked student for the current user.
        $studentId = null;
        if ($requestedStudentId) {
            if (!$this->model->validateStudentBelongsToSchool($schoolId, $requestedStudentId)) {
                $this->fail('Requested student does not belong to your school', 404); return;
            }
            $studentId = $requestedStudentId;
        } else {
            // Resolve the student id linked to the current user account
            $userId = (int)($user['id'] ?? $user['user_id'] ?? $user['UserID'] ?? 0);
            if ($userId <= 0) { $this->fail('User context missing',400); return; }
            $studentId = $this->model->resolveStudentIdForUser(schoolId: $schoolId, studentId: $userId);
            if (!$studentId) { $this->fail('Student profile not linked to this user',404); return; }
        }

        $academicYearId = !empty($params['academic_year_id']) ? (int)$params['academic_year_id'] : ($user['AcademicYearID'] ?? null);
        try {
            $monthly = $this->model->getMonthlyAttendance($schoolId, $studentId, $academicYearId);
            $this->ok('Monthly attendance fetched', ['charts' => ['monthlyAttendance' => $monthly]]);
        } catch (\Throwable $e) {
            $this->fail('Failed to load monthly attendance: '.$e->getMessage(), 500);
        }
    }
}
?>