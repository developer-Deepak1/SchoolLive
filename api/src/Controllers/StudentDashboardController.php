<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\StudentDashboardModel;

class StudentDashboardController extends BaseController {
    private StudentDashboardModel $model;
    public function __construct() { parent::__construct(); $this->model = new StudentDashboardModel(); }

    /** GET /api/dashboard/student */
    public function summary($params = []) {
        $user = $this->currentUser(); if(!$user) return;
        $schoolId = (int)$user['school_id'];
        $academicYearId = $user['AcademicYearID'] ?? null;
    // Support multiple possible key names for user id coming from token/user payload
    $userId = (int)($user['id'] ?? $user['user_id'] ?? $user['UserID'] ?? 0);
        if ($userId <= 0) { $this->fail('User context missing',400); return; }
        $studentId = $this->model->resolveStudentIdForUser($schoolId, $userId);
        if (!$studentId) { $this->fail('Student profile not linked to this user',404); return; }
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
}
?>