<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Models\AttendanceModel;

class AttendanceController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new AttendanceModel();
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
