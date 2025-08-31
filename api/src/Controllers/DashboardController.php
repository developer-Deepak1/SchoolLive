<?php
namespace SchoolLive\Controllers;

use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Models\DashboardModel;
use SchoolLive\Core\BaseController;

class DashboardController extends BaseController {
    private $model;

    public function __construct() {
    parent::__construct();
    $this->model = new DashboardModel();
    }

    /**
     * GET /api/dashboard/summary
     * Returns aggregated stats & minimal chart datasets for the dashboard.
     */
    public function summary($params = []) {
    $user = AuthMiddleware::getCurrentUser();
    if (!$user) { $this->fail('Unauthorized',401); return; }

        $schoolId = $user['school_id'];
        $academicYearId = $user['AcademicYearID'] ?? null; // may be null first login

        try {
            // Simple in-memory cache per request lifecycle (static) with 60s TTL
            static $cache = [];
            $cacheKey = $schoolId . ':' . ($academicYearId ?? 'na');
            $now = time();
            if (isset($cache[$cacheKey]) && $cache[$cacheKey]['expires'] > $now) { echo json_encode($cache[$cacheKey]['payload']); return; }

            $stats = $this->model->getStats($schoolId, $academicYearId);
            $attendanceOverview = $this->model->getAttendanceOverviewToday($schoolId, $academicYearId);
            $classAttendance = $this->model->getClassAttendanceToday($schoolId, $academicYearId);
            $enrollmentTrend = $this->model->getEnrollmentTrend($schoolId, $academicYearId);
            $gradeDistribution = $this->model->getGradeDistribution($schoolId, $academicYearId);
            $revenueBreakdown = $this->model->getRevenueBreakdown($schoolId, $academicYearId);
            $recentActivities = $this->model->getRecentActivities($schoolId, $academicYearId);
            $upcomingEvents = $this->model->getUpcomingEvents($schoolId, $academicYearId);
            $classGender = $this->model->getClassGenderCounts($schoolId, $academicYearId);
            $topClasses = $this->model->getTopClasses($schoolId, $academicYearId, 5);

            $response = [
                'success' => true,
                'message' => 'Dashboard summary fetched',
                'data' => [
                    'stats' => $stats,
                    'charts' => [
                        'attendanceOverview' => $attendanceOverview,
                        'enrollmentTrend' => $enrollmentTrend,
                        'gradeDistribution' => $gradeDistribution,
                        'revenue' => $revenueBreakdown,
                        'classAttendance' => $classAttendance,
                        'classGender' => $classGender
                    ],
                    // 'classGender' moved under charts to match frontend expectation
                    'recentActivities' => $recentActivities,
                    'topClasses' => $topClasses,
                    'upcomingEvents' => $upcomingEvents,
                    'teacherPerformance' => []
                ]
            ];
            $cache[$cacheKey] = [ 'expires' => $now + 60, 'payload' => $response ];
            echo json_encode($response);
        } catch (\Throwable $e) {
            $this->fail('Failed to load dashboard: ' . $e->getMessage(),500);
        }
    }
}
