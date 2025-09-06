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
            $studentId = $this->model->resolveStudentIdForUser(schoolId: $schoolId, studentId: $requestedStudentId);
            if (!$studentId) { $this->fail('Student profile not linked to this user',404); return; }
        }
        try {
            $avgAttendance = $this->model->getAverageAttendance($schoolId, $studentId, $academicYearId);
            $monthlyAttendance = $this->model->getMonthlyAttendance($schoolId, $studentId, $academicYearId, 12);
            $gradeDistribution = $this->model->getGradeDistribution($schoolId, $studentId, $academicYearId);
            $avgGrade = $this->model->getAverageGrade($schoolId, $studentId, $academicYearId);
            $gradeProgress = $this->model->getGradeProgress($schoolId, $studentId, $academicYearId, 12);
            $activities = $this->model->getRecentActivities($schoolId, $studentId, $academicYearId, 10);
            $events = $this->model->getUpcomingEvents($schoolId, $academicYearId, 5);
            $payload = [
                'stats' => [ 'averageAttendance' => $avgAttendance, 'averageGrade' => $avgGrade ],
                'charts' => [
                    'monthlyAttendance' => $monthlyAttendance,
                    'gradeDistribution' => $gradeDistribution,
                    'gradeProgress' => $gradeProgress
                ],
                'recentActivities' => $activities,
                'upcomingEvents' => $events
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

        if (!$this->model->validateStudentBelongsToSchool($schoolId, $requestedStudentId)) {
            $this->fail('Requested student does not belong to your school', 404); return;
        }

        $academicYearId = !empty($params['academic_year_id']) ? (int)$params['academic_year_id'] : ($user['AcademicYearID'] ?? null);
        try {
            
            $monthly = $this->model->getMonthlyAttendance($schoolId, $requestedStudentId, $academicYearId);
            $this->ok('Monthly attendance fetched', ['charts' => ['monthlyAttendance' => $monthly]]);
        } catch (\Throwable $e) {
            $this->fail('Failed to load monthly attendance: '.$e->getMessage(), 500);
        }
    }
}
?>