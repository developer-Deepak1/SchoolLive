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

         // fetch holidays and weekly offs for the month from AcademicCalendarModel
    $ac = new AcademicCalendarModel();
    $holidays = $ac->getHolidays($academicYearId, $schoolId);
    $weeklyOffs = $ac->getWeeklyOffs($academicYearId, $schoolId);
        $holidayMap = [];
        foreach ($holidays as $date => $holidayInfo) {
            // holidays from getHolidays() are already keyed by date
            $d = explode('T', $date)[0]; // ensure we have just the date part
            if (strpos($d, sprintf('%04d-%02d-', $year, $month)) === 0) {
                $holidayMap[$d] = $holidayInfo;
            }
        }
        // aggregate per student by calling model->getDaily for each day
        $students = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $rows = $this->model->getDaily($schoolId, $academicYearId, $date, $section, true);
            
            // Check if this date is a weekly off
            $dow = (int)date('N', strtotime($date)); // 1=Monday..7=Sunday
            $isWeeklyOff = in_array($dow, $weeklyOffs, true);
            
            // Check if there's a holiday for this date and what type
            $holidayInfo = isset($holidayMap[$date]) ? $holidayMap[$date] : null;
            $isHoliday = $holidayInfo !== null;
            $holidayType = $holidayInfo ? ($holidayInfo['type'] ?? 'Holiday') : null;
            
            // Business logic:
            // 1. If it's a regular holiday -> mark as Holiday
            // 2. If it's a weekly off BUT there's a "WorkingDay" holiday -> consider attendance
            // 3. If it's a weekly off with no holiday OR "Holiday" type -> mark as Holiday
            $shouldMarkAsHoliday = false;
            if ($isHoliday && $holidayType === 'Holiday') {
                $shouldMarkAsHoliday = true; // Regular holiday
            } elseif ($isWeeklyOff && (!$isHoliday || $holidayType !== 'WorkingDay')) {
                $shouldMarkAsHoliday = true; // Weekly off (unless overridden by WorkingDay)
            }
            
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
                
                // Apply business logic for attendance marking
                // Holidays and weekly-offs take precedence over any recorded attendance
                if ($shouldMarkAsHoliday) {
                    $students[$sid]['statuses'][$d-1] = 'Holiday';
                } else {
                    // Normalize status values to a small set server-side
                    $raw = $r['Status'] ?? null;
                    $norm = null;
                    if ($raw !== null) {
                        $rs = strtolower(trim($raw));
                        if ($rs === 'p' || $rs === 'present') $norm = 'Present';
                        elseif ($rs === 'l' || $rs === 'leave') $norm = 'Leave';
                        elseif ($rs === 'h' || $rs === 'halfday' || $rs === 'half-day' || $rs === 'half day') $norm = 'HalfDay';
                        elseif ($rs === 'a' || $rs === 'absent') $norm = 'Absent';
                        else $norm = ucfirst($rs);
                    }
                    $students[$sid]['statuses'][$d-1] = $norm;
                }
            }
            
            // For students not in rows, apply the same holiday/weekly-off logic
            if ($shouldMarkAsHoliday) {
                foreach ($students as &$s) { 
                    if ($s['statuses'][$d-1] === null) $s['statuses'][$d-1] = 'Holiday'; 
                }
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
    $meta = $mv ? ['takenBy' => $mv['CreatedByName'] ?? null, 'takenAt' => $mv['CreatedAt'] ?? null] : null;

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
