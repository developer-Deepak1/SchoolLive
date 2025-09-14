<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Models\AttendanceModel;
use SchoolLive\Models\AcademicCalendarModel;

class AttendanceController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AttendanceModel();
    }

    // GET /api/attendance/monthly?year=2025&month=9&class_id=...&section=...
    public function monthly($params = []) {
        $currentUser = $this->currentUser(); if (!$currentUser) return;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $section = isset($_GET['section']) && $_GET['section'] !== '' ? (int)$_GET['section'] : null;

        $schoolId = $currentUser['school_id'] ?? null;
        $academicYearId = $currentUser['AcademicYearID'] ?? null;

        // compute days in month
        $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));

        // fetch holidays for the month
    $ac = new AcademicCalendarModel();
    $holidays = $ac->getHolidays($academicYearId, $schoolId);
        $holidayMap = [];
        foreach ($holidays as $h) {
            $d = isset($h['Date']) ? explode('T', $h['Date'])[0] : (isset($h['date']) ? explode('T', $h['date'])[0] : null);
            if (!$d) continue;
            if (strpos($d, sprintf('%04d-%02d-', $year, $month)) === 0) $holidayMap[$d] = $h;
        }

        // aggregate per student by calling model->getDaily for each day
        $students = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $rows = $this->model->getDaily($schoolId, $academicYearId, $date, $section, true);
            // rows contain students for that date; ensure we have an entry per student
            foreach ($rows as $r) {
                $sid = $r['StudentID'] ?? null;
                if (!$sid) continue;
                if (!isset($students[$sid])) {
                    $students[$sid] = [
                        'StudentID' => $sid,
                        'StudentName' => trim((($r['FirstName']??'') . ' ' . ($r['MiddleName']??'') . ' ' . ($r['LastName']??''))),
                        'ClassName' => $r['ClassName'] ?? $r['Class'] ?? null,
                        'statuses' => array_fill(0, $daysInMonth, null),
                    ];
                }
                // if holiday, mark as 'Holiday' else use actual Status or null
                if (isset($holidayMap[$date])) $students[$sid]['statuses'][$d-1] = 'Holiday';
                else $students[$sid]['statuses'][$d-1] = $r['Status'] ?? null;
            }
            // for students not in rows, if holiday mark holiday
            if (isset($holidayMap[$date])) {
                foreach ($students as &$s) { if ($s['statuses'][$d-1] === null) $s['statuses'][$d-1] = 'Holiday'; }
                unset($s);
            }
        }

        // Return array of students
        $out = array_values($students);
        echo json_encode(['success' => true, 'records' => $out, 'daysInMonth' => $daysInMonth]);
    }

    // GET /api/attendance?date=YYYY-MM-DD&section=123
    public function list($params = []) {
        $currentUser = $this->currentUser(); if (!$currentUser) return;
        $date = $_GET['date'] ?? date('Y-m-d');
        $section = isset($_GET['section']) && $_GET['section'] !== '' ? (int)$_GET['section'] : null;
        $includeInactive = isset($_GET['include_inactive']) ? (bool)$_GET['include_inactive'] : false;

        $schoolId = $currentUser['school_id'] ?? null;
        $academicYearId = $currentUser['AcademicYearID'] ?? null;

        $rows = $this->model->getDaily($schoolId, $academicYearId, $date, $section, $includeInactive);

    // Determine who recorded attendance for this date/section (if any)
    $mv = $this->model->getAttendanceMeta($schoolId, $date, $section, $academicYearId);
    $meta = $mv ? ['takenBy' => $mv['CreatedBy'] ?? null, 'takenAt' => $mv['CreatedAt'] ?? null] : null;

        // Frontend expects { records: [...], meta: {...} }
        echo json_encode(['success' => true, 'records' => $rows, 'meta' => $meta]);
    }

    // POST /api/attendance { date, entries: [{StudentID, Status, Remarks?}, ...] }
    public function save($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();
        if (!isset($input['date']) || !isset($input['entries']) || !is_array($input['entries'])) {
            $this->fail('date and entries are required', 400); return;
        }

        $currentUser = $this->currentUser(); if (!$currentUser) return;
        $schoolId = $currentUser['school_id'] ?? null;
        $academicYearId = $currentUser['AcademicYearID'] ?? null;

    // Optional class/section can be provided by the client to indicate context
    $classId = isset($input['class_id']) ? (int)$input['class_id'] : null;
    $sectionId = isset($input['section_id']) ? (int)$input['section_id'] : null;

    $res = $this->model->batchUpsert($schoolId, $academicYearId, $input['date'], $input['entries'], $currentUser['username'] ?? 'system', $classId, $sectionId);

        echo json_encode(['success' => true, 'summary' => $res['summary'], 'results' => $res['results']]);
    }
}
