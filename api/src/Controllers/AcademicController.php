<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\AcademicModel;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;

class AcademicController extends BaseController {
    private $academicModel;

    public function __construct() {
    parent::__construct();
    $this->academicModel = new AcademicModel();
    }

    // Academic Years Methods
    public function getAcademicYears($params = []) {
        $currentUser = $this->currentUser(); if(!$currentUser) return;

        // Default behavior: return only active records. Pass is_active=0 to fetch inactive records.
        $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;

        $academicYears = $this->academicModel->getAcademicYearsBySchoolId($currentUser['school_id'], $isActive);

        $this->ok('Academic years retrieved successfully', $academicYears);
    }

    public function createAcademicYear($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    if (!$this->ensure($input, ['AcademicYearName','StartDate','EndDate'])) return;
    // Allow creation of upcoming academic years; do not enforce strict overlap here.
    if (strtotime($input['StartDate']) >= strtotime($input['EndDate'])) { $this->fail('End date must be after start date',400); return; }

    $currentUser = $this->currentUser(); if(!$currentUser) return;

    $currentyear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);

        if (!isset($input['CreatedBy'])) {
            $input['CreatedBy'] = $currentUser['username'];
        }
        if (!isset($input['SchoolID'])) {
            $input['SchoolID'] = $currentUser['school_id'];
        }

        // Default new entries to 'Upcoming' so frontend can control activation
        if (!isset($input['Status'])) {
            $input['Status'] = 'Upcoming';
        }

    // Creation of upcoming academic years is allowed; frontend should manage activation and overlaps.

        $academicYearId = $this->academicModel->createAcademicYear($input);

        if ($academicYearId) {
            $academicYear = $this->academicModel->getAcademicYearById($academicYearId);
            $this->ok('Academic year created successfully', [$academicYear]);
        } else { $this->fail('Failed to create academic year',500); }
    }


    public function deleteAcademicYear($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    if (!isset($params['id'])) { $this->fail('Academic Year ID is required',400); return; }
    $currentUser = $this->currentUser(); if(!$currentUser) return;
    // Soft-delete: set IsActive = 0
    $result = $this->academicModel->deleteAcademicYear($params['id'], $currentUser['school_id'], $currentUser['username']);
        if ($result['success']) { $this->ok($result['message']); }
        else {
            $status = 500;
            if (strpos($result['message'],'not found')!==false) $status = 404; elseif (strpos($result['message'],'Cannot delete active')!==false) $status = 400;
            $this->fail($result['message'],$status);
        }
    }

    public function updateAcademicYear($params = []) {
    if (!$this->requireMethod('PUT')) return;
    $id = $this->requireKey($params,'id','Academic Year ID'); if($id===null) return;
    $input = $this->input();

        // Check if academic year exists
        $existingYear = $this->academicModel->getAcademicYearById($id);
    if (!$existingYear) { $this->fail('Academic year not found',404); return; }

        // Validate dates if provided
        if (isset($input['StartDate']) && isset($input['EndDate'])) {
            if (strtotime($input['StartDate']) >= strtotime($input['EndDate'])) { $this->fail('End date must be after start date',400); return; }
        }

    $currentUser = $this->currentUser(); if(!$currentUser) return;
        
        // Set UpdatedBy to current user
        $input['UpdatedBy'] = $currentUser['username'];

        $currentacademicYear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);
    // When updating a non-current academic year, ensure its StartDate does not
    // come before the current academic year's EndDate (prevents overlap).
    if ($currentacademicYear && isset($input['StartDate']) && $id != $currentacademicYear['AcademicYearID']) {
        $currEndTs = strtotime($currentacademicYear['EndDate']);
        $startTs = strtotime($input['StartDate']);
        if ($startTs === false || $currEndTs === false) {
            $this->fail('Invalid date format for StartDate or current academic year EndDate',400); return;
        }
        if ($startTs < $currEndTs) { $this->fail('Start date must be on or after the current academic year\'s end date',400); return; }
    }

    $result = $this->academicModel->updateAcademicYear($id, $input);

    if ($result) { $updatedYear = $this->academicModel->getAcademicYearById($id); $this->ok('Academic year updated successfully',$updatedYear); }
    else { $this->fail('Failed to update academic year',500); }
    }


    public function getCurrentAcademicYear($params = []) {
    $currentUser = $this->currentUser(); if(!$currentUser) return;
        $currentYear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);

    if (!$currentYear) { $this->fail('No current academic year set',404); return; }
    $this->ok('Current academic year retrieved successfully', $currentYear);
    }

    // Weekly Offs
    public function getWeeklyOffs($params = []) {
    $currentUser = $this->currentUser(); if(!$currentUser) return;
    $academicYearId = $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $offs = $this->academicModel->getWeeklyOffsByAcademicYear($currentUser['school_id'], $academicYearId, $isActive);
        $this->ok('Weekly offs retrieved successfully', $offs);
    }

    public function setWeeklyOffs($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    $currentUser = $this->currentUser(); if(!$currentUser) return;
        if (!isset($input['AcademicYearID'])) { $this->fail('AcademicYearID is required',400); return; }
        if (!isset($input['Days']) || !is_array($input['Days'])) { $this->fail('Days array is required',400); return; }

        // Validate day numbers
        $days = array_values(array_unique(array_map('intval', $input['Days'])));
        foreach ($days as $d) {
            if ($d < 1 || $d > 7) { $this->fail('Day values must be integers between 1 and 7',400); return; }
        }

        $ok = $this->academicModel->setWeeklyOffs($currentUser['school_id'], $input['AcademicYearID'], $days, $currentUser['username']);
        if ($ok){

            $this->ok('Weekly offs updated');
        } else {
            $this->fail('Failed to update weekly offs',500);
        }
    }

    // Holidays
    public function getHolidays($params = []) {
    $currentUser = $this->currentUser(); if(!$currentUser) return;
    $academicYearId = $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $holidays = $this->academicModel->getHolidaysByAcademicYear($currentUser['school_id'], $academicYearId, $isActive);
        $this->ok('Holidays retrieved successfully', $holidays);
    }

    public function createHoliday($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    $currentUser = $this->currentUser(); if(!$currentUser) return;

        if (!$this->ensure($input, ['AcademicYearID','Date','Title','Type'])) return;

        $input['SchoolID'] = $input['SchoolID'] ?? $currentUser['school_id'];
        $input['CreatedBy'] = $input['CreatedBy'] ?? $currentUser['username'];

        $res = $this->academicModel->createHoliday($input);
        if ($res) { $holiday = $this->academicModel->getHolidayById($res); $this->ok('Holiday created successfully', $holiday); }
        else { $this->fail('Failed to create holiday',500); }
    }

    // Create a range of holidays (one row per date between StartDate and EndDate)
    public function createHolidayRange($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    $currentUser = $this->currentUser(); if(!$currentUser) return;

        // Validate required fields
        if (!$this->ensure($input, ['AcademicYearID','StartDate','EndDate','Title','Type'])) return;

        $start = $input['StartDate'];
        $end = $input['EndDate'];
        if (strtotime($start) === false || strtotime($end) === false) { $this->fail('Invalid date format for StartDate or EndDate',400); return; }
        if (strtotime($start) > strtotime($end)) { $this->fail('StartDate must be <= EndDate',400); return; }

        $input['SchoolID'] = $input['SchoolID'] ?? $currentUser['school_id'];
        $input['CreatedBy'] = $input['CreatedBy'] ?? $currentUser['username'];

        $res = $this->academicModel->createHolidayRange($input);
        if ($res !== false) {
            $this->ok('Holiday range processed', $res);
        } else {
            $this->fail('Failed to create holiday range',500);
        }
    }

    public function deleteHoliday($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    $id = $this->requireKey($params,'id','Holiday ID'); if($id===null) return;
    $currentUser = $this->currentUser(); if(!$currentUser) return;

    $result = $this->academicModel->deleteHoliday($id,$currentUser['school_id'], $currentUser['username']);
    if ($result) { $this->ok('Holiday deleted successfully'); }
    else { $this->fail('Failed to delete holiday',500); }
    }

    public function updateHoliday($params = []) {
    if (!$this->requireMethod('PUT')) return;
    $id = $this->requireKey($params,'id','Holiday ID'); if($id===null) return;
    $input = $this->input();
    $currentUser = $this->currentUser(); if(!$currentUser) return;

        // allowed fields: Date, Title, Type, AcademicYearID
        $updatePayload = [];
        if (isset($input['Date'])) $updatePayload['Date'] = $input['Date'];
        if (isset($input['Title'])) $updatePayload['Title'] = $input['Title'];
        if (isset($input['Type'])) $updatePayload['Type'] = $input['Type'];
        if (isset($input['AcademicYearID'])) $updatePayload['AcademicYearID'] = $input['AcademicYearID'];

        if (empty($updatePayload)) { $this->fail('No updatable fields provided',400); return; }

        $updatePayload['UpdatedBy'] = $currentUser['username'];

        $ok = $this->academicModel->updateHoliday($id, $updatePayload, $currentUser['school_id']);
        if ($ok) { $holiday = $this->academicModel->getHolidayById($id); $this->ok('Holiday updated successfully', $holiday); }
        else { $this->fail('Failed to update holiday',500); }
    }

    // Weekly Report (returns weekly off dates and holidays between start and end)
    public function getWeeklyReport($params = []) {
    $currentUser = $this->currentUser(); if(!$currentUser) return;
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        $academicYearId = $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;

        if (!$start || !$end) { $this->fail('start and end query parameters are required (YYYY-MM-DD)',400); return; }

        $report = $this->academicModel->getWeeklyReport($currentUser['school_id'], $academicYearId, $start, $end);
        $this->ok('Weekly report generated', $report);
    }

    // Monthly working days (for chart)
    public function getMonthlyWorkingDays($params = []) {
        $currentUser = $this->currentUser(); if(!$currentUser) return;
        $academicYearId = $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;
        if (!$academicYearId) { $this->fail('academic_year_id is required',400); return; }
        $data = $this->academicModel->getMonthlyWorkingDays((int)$academicYearId, (int)$currentUser['school_id']);
        // Return data in chart-friendly shape
        $this->ok('Monthly working days', $data);
    }
}
