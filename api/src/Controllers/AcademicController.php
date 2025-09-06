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

    // Classes Methods
    public function getClasses($params = []) {
    $currentUser = $this->currentUser(); if(!$currentUser) return;
    // Accept AcademicYearID via query param (camelCase or snake_case) or fall back to current user
    $academicYearId = $_GET['AcademicYearID'] ?? $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;
    // default to active classes; accept is_active=0 to list inactive
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $classes = $this->academicModel->getAllClasses($academicYearId, $currentUser['school_id'], $isActive);
    $this->ok('Classes retrieved successfully', $classes);
    }

    // Sections Methods
    public function getSections($params = []) {
    // Accept both snake_case and camelCase query params
    $academic_year_id = $_GET['academic_year_id'] ?? $_GET['AcademicYearID'] ?? null;
    $class_id = $_GET['class_id'] ?? $_GET['ClassID'] ?? null;

    // Determine school context: prefer explicit query param but fall back to current user's school when available
    $currentUser = $this->currentUser(); // may be null if unauthenticated
    $school_id = $_GET['school_id'] ?? $_GET['SchoolID'] ?? ($currentUser['school_id'] ?? null);

    // default to active sections; accept is_active=0 to return inactive sections
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
    $sections = $this->academicModel->getAllSections($academic_year_id, $class_id, $school_id);
    $this->ok('Sections retrieved successfully', $sections);
    }

    public function getSection($params = []) {
    $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;
    // default: return only active sections
    $section = $this->academicModel->getSectionById($id);

    if (!$section) { $this->fail('Section not found',404); return; }
    $this->ok('Section retrieved successfully', $section);
    }

    public function createSection($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();

    // Normalize common camelCase keys to snake_case to be flexible for clients
    if (isset($input['SectionName']) && !isset($input['section_name'])) $input['section_name'] = $input['SectionName'];
    if (isset($input['ClassID']) && !isset($input['class_id'])) $input['class_id'] = $input['ClassID'];
    if (isset($input['MaxStrength']) && !isset($input['max_strength'])) $input['max_strength'] = $input['MaxStrength'];
    if (isset($input['AcademicYearID']) && !isset($input['academic_year_id'])) $input['academic_year_id'] = $input['AcademicYearID'];
    if (isset($input['SchoolID']) && !isset($input['school_id'])) $input['school_id'] = $input['SchoolID'];

    $currentUser = $this->currentUser(); if(!$currentUser) return;
    // Default missing school/academic year to current user's context
    if (!isset($input['school_id'])) $input['school_id'] = $currentUser['school_id'];
    if (!isset($input['academic_year_id'])) $input['academic_year_id'] = $currentUser['AcademicYearID'];

    if (!$this->ensure($input, ['section_name','school_id','academic_year_id','class_id'])) return;

        $sectionId = $this->academicModel->createSection($input);

    if ($sectionId) { $section = $this->academicModel->getSectionById($sectionId); $this->ok('Section created successfully',['section'=>$section]); }
    else { $this->fail('Failed to create section',500); }
    }

    public function updateSection($params = []) {
    if (!$this->requireMethod('PUT')) return;
    $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;
    $input = $this->input();

        $existing = $this->academicModel->getSectionById($id);
    if (!$existing) { $this->fail('Section not found',404); return; }

    // Normalize camelCase keys
    if (isset($input['SectionName']) && !isset($input['section_name'])) $input['section_name'] = $input['SectionName'];
    if (isset($input['ClassID']) && !isset($input['class_id'])) $input['class_id'] = $input['ClassID'];
    if (isset($input['MaxStrength']) && !isset($input['max_strength'])) $input['max_strength'] = $input['MaxStrength'];
    if (isset($input['AcademicYearID']) && !isset($input['academic_year_id'])) $input['academic_year_id'] = $input['AcademicYearID'];
    if (isset($input['SchoolID']) && !isset($input['school_id'])) $input['school_id'] = $input['SchoolID'];

    // Ensure update uses the database column names (PascalCase). If client sent snake_case
    // convert them to PascalCase and remove snake_case keys so the dynamic SET clause
    // doesn't try to update non-existent snake_case columns.
    $mapping = [
        'section_name' => 'SectionName',
        'class_id' => 'ClassID',
        'max_strength' => 'MaxStrength',
        'academic_year_id' => 'AcademicYearID',
        'school_id' => 'SchoolID'
    ];
    foreach ($mapping as $snake => $pascal) {
        if (isset($input[$snake]) && !isset($input[$pascal])) {
            $input[$pascal] = $input[$snake];
        }
        if (isset($input[$snake])) {
            unset($input[$snake]);
        }
    }

    $result = $this->academicModel->updateSection($id, $input);

    if ($result) { $section = $this->academicModel->getSectionById($id); $this->ok('Section updated successfully',['section'=>$section]); }
    else { $this->fail('Failed to update section',500); }
    }

    public function deleteSection($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;

        $existing = $this->academicModel->getSectionById($id);
    if (!$existing) { $this->fail('Section not found',404); return; }

    $result = $this->academicModel->deleteSection($id);

    if ($result) { $this->ok('Section deleted successfully'); }
    else { $this->fail('Failed to delete section',500); }
    }

    public function getClass($params = []) {
    $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;

    $class = $this->academicModel->getClassById($id);

    if (!$class) { $this->fail('Class not found',404); return; }

        $currentUser = AuthMiddleware::getCurrentUser();

        // If authenticated and a teacher, ensure they are assigned to this class via class_teachers mapping
        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'teacher') {
                $isAssigned = $this->academicModel->isTeacherAssigned($id, $currentUser['id']);
                if (!$isAssigned) {
        $this->fail('Access denied to this class',403); return;
            }
        }
    $this->ok('Class retrieved successfully', $class);
    }

    public function createClass($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();

    // Validate required fields - adapted to new class schema
    $requiredFields = ['ClassName', 'Stream', 'ClassCode'];
    if (!$this->ensure($input, $requiredFields)) return;
    $currentUser = AuthMiddleware::getCurrentUser();
    // Set default values
    if (!isset($input['AcademicYearID'])) {
        $input['AcademicYearID'] = $currentUser['AcademicYearID'];
    }
    if (!isset($input['SchoolID'])) {
        $input['SchoolID'] = $currentUser['school_id'];
    }
    if (!isset($input['Username'])) {
        $input['Username'] = $currentUser['username'];
    }

        $classId = $this->academicModel->createClass($input);

    if ($classId) { $class = $this->academicModel->getClassById($classId); $this->ok('Class created successfully',$class); }
    else { $this->fail('Failed to create class',500); }
    }

    public function updateClass($params = []) {
    if (!$this->requireMethod('PUT')) return;
    $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;
    $input = $this->input();

        // Check if class exists
        $existingClass = $this->academicModel->getClassById($id);
    if (!$existingClass) { $this->fail('Class not found',404); return; }
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!isset($input['UpdatedBy'])) {
            $input['UpdatedBy'] = $currentUser['username'];
        }

    $result = $this->academicModel->updateClass($id, $input);

    if ($result) { $class = $this->academicModel->getClassById($id); $this->ok('Class updated successfully',$class); }
    else { $this->fail('Failed to update class',500); }
    }

    public function deleteClass($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;

        // Check if class exists
        $class = $this->academicModel->getClassById($id);
    if (!$class) { $this->fail('Class not found',404); return; }

    $result = $this->academicModel->deleteClass($id);

    if ($result) { $this->ok('Class deleted successfully'); }
    else { $this->fail('Failed to delete class',500); }
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
}
