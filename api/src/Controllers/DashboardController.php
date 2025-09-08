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
    $user = $this->currentUser(); if(!$user) return;

        $schoolId = $user['school_id'];
        $academicYearId = $user['AcademicYearID'] ?? null; // may be null first login

        try {
            // Simple in-memory cache per request lifecycle (static) with 60s TTL
            static $cache = [];
            $cacheKey = $schoolId . ':' . ($academicYearId ?? 'na');
            $now = time();
            if (isset($cache[$cacheKey]) && $cache[$cacheKey]['expires'] > $now) { $this->ok('Dashboard summary fetched (cached)', $cache[$cacheKey]['payload']['data']); return; }

            $stats = $this->model->getStats($schoolId, $academicYearId);
            $attendanceOverview = $this->model->getAttendanceOverviewToday($schoolId, $academicYearId);
            $classAttendance = $this->model->getClassAttendanceToday($schoolId, $academicYearId);
            $classGender = $this->model->getClassGenderCounts($schoolId, $academicYearId);

            $payload = [
                    'stats' => $stats,
                    'charts' => [
                        'attendanceOverview' => $attendanceOverview,
                        'classAttendance' => $classAttendance,
                        'classGender' => $classGender
                    ],
                    // 'classGender' moved under charts to match frontend expectation
                    'teacherPerformance' => []
            ];
            $cache[$cacheKey] = [ 'expires' => $now + 60, 'payload' => ['data'=>$payload] ];
            $this->ok('Dashboard summary fetched', $payload);
        } catch (\Throwable $e) {
            $this->fail('Failed to load dashboard: ' . $e->getMessage(),500);
        }
    }
}
