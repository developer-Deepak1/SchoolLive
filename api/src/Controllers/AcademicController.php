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
    $academicYears = $this->academicModel->getAcademicYearsBySchoolId($currentUser['school_id']);
    $this->ok('Academic years retrieved successfully', $academicYears);
    }

    public function createAcademicYear($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    if (!$this->ensure($input, ['AcademicYearName','StartDate','EndDate'])) return;
    if (strtotime($input['StartDate']) >= strtotime($input['EndDate'])) { $this->fail('End date must be after start date',400); return; }

    $currentUser = $this->currentUser(); if(!$currentUser) return;

    $currentyear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);

        if (!isset($input['CreatedBy'])) {
            $input['CreatedBy'] = $currentUser['username'];
        }
        if (!isset($input['SchoolID'])) {
            $input['SchoolID'] = $currentUser['school_id'];
        }

        if (!isset($input['Status'])) {
            $input['Status'] = 'active';
        }

    // Enforce rule: the next academic year's StartDate must not be before
    // the current academic year's EndDate. We allow StartDate on the same day
    // as the current EndDate (some schools switch on the same date) or later.
    if ($currentyear && isset($input['StartDate'])) {
        $currEndTs = strtotime($currentyear['EndDate']);
        $startTs = strtotime($input['StartDate']);
        if ($startTs === false || $currEndTs === false) {
            $this->fail('Invalid date format for StartDate or current academic year EndDate',400);
            return;
        }
        if ($startTs < $currEndTs) {
            $this->fail("Start date must be on or after current academic year's end date ({$currentyear['EndDate']})",400);
            return;
        }
    }

        $academicYearId = $this->academicModel->createAcademicYear($input);

        if ($academicYearId) {
            $academicYear = $this->academicModel->getAcademicYearById($academicYearId);
            $this->ok('Academic year created successfully', [$academicYear]);
        } else { $this->fail('Failed to create academic year',500); }
    }

    // Academic Calendar endpoints
    public function getAcademicCalendar($params = []) {
        $ay = $_GET['academic_year_id'] ?? null;
        if (!$ay) { $this->fail('academic_year_id is required',400); return; }
        $calModel = new \SchoolLive\Models\AcademicCalendarModel();
        $entries = $calModel->getByAcademicYear($ay);
        $this->ok('Calendar entries retrieved successfully', $entries);
    }

    public function createAcademicCalendarEntry($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();
        if (!$this->ensure($input, ['AcademicYearID','Date','DayType','Title'])) return;
        $currentUser = $this->currentUser(); if(!$currentUser) return;
        // Do not store implicit working days. Only store exceptions like holidays, exam days, special events.
        if (isset($input['DayType']) && $input['DayType'] === 'working_day') {
            $this->fail('Working days are implicit for the academic year; only create entries for holidays, exam days or special events',400);
            return;
        }
        $input['CreatedBy'] = $currentUser['username'];
        $calModel = new \SchoolLive\Models\AcademicCalendarModel();
        // Ensure uniqueness per academic year/date is handled by DB unique constraint; catch exception in Model layer if needed
        $id = $calModel->create($input);
        if ($id) { $this->ok('Calendar entry created', ['CalendarID' => $id]); }
        else { $this->fail('Failed to create calendar entry',500); }
    }

    public function updateAcademicCalendarEntry($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Calendar ID'); if($id===null) return;
        $input = $this->input();
        $currentUser = $this->currentUser(); if(!$currentUser) return;
        // Prevent changing an entry to an implicit working_day
        if (isset($input['DayType']) && $input['DayType'] === 'working_day') {
            $this->fail('Cannot set DayType to working_day; working days are implicit for the academic year',400);
            return;
        }
        $input['UpdatedBy'] = $currentUser['username'];
        $calModel = new \SchoolLive\Models\AcademicCalendarModel();
        $res = $calModel->update($id,$input);
        if ($res) { $this->ok('Calendar entry updated'); } else { $this->fail('Failed to update calendar entry',500); }
    }

    public function deleteAcademicCalendarEntry($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Calendar ID'); if($id===null) return;
        $calModel = new \SchoolLive\Models\AcademicCalendarModel();
        $res = $calModel->delete($id);
        if ($res) { $this->ok('Calendar entry deleted'); } else { $this->fail('Failed to delete calendar entry',500); }
    }

    public function deleteAcademicYear($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    if (!isset($params['id'])) { $this->fail('Academic Year ID is required',400); return; }
    $currentUser = $this->currentUser(); if(!$currentUser) return;

        $result = $this->academicModel->deleteAcademicYear($params['id'],$currentUser['school_id']);

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
    $classes = $this->academicModel->getAllClasses($currentUser['AcademicYearID'], $currentUser['school_id']);
    $this->ok('Classes retrieved successfully', $classes);
    }

    // Sections Methods
    public function getSections($params = []) {
        $academic_year_id = $_GET['academic_year_id'] ?? null;
        $class_id = $_GET['class_id'] ?? null;

    $sections = $this->academicModel->getAllSections($academic_year_id, $class_id);
    $this->ok('Sections retrieved successfully', $sections);
    }

    public function getSection($params = []) {
    $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;

    $section = $this->academicModel->getSectionById($id);

    if (!$section) { $this->fail('Section not found',404); return; }
    $this->ok('Section retrieved successfully', $section);
    }

    public function createSection($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    if (!$this->ensure($input, ['section_name','section_display_name','school_id','academic_year_id','class_id'])) return;

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
}
