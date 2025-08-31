<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\AcademicModel;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;
use SchoolLive\Core\Request;
use SchoolLive\Core\Response;

class AcademicController extends BaseController {
    private $academicModel;

    public function __construct() {
    parent::__construct();
    $this->academicModel = new AcademicModel();
    }

    // Academic Years Methods
    public function getAcademicYears($params = []) {
    $currentUser = AuthMiddleware::getCurrentUser();
    $academicYears = $this->academicModel->getAcademicYearsBySchoolId($currentUser['school_id']);
    $this->ok('Academic years retrieved successfully', $academicYears);
    }

    public function createAcademicYear($params = []) {
    if (!$this->requireMethod('POST')) return;
    $input = $this->input();
    if (!$this->ensure($input, ['AcademicYearName','StartDate','EndDate'])) return;
    if (strtotime($input['StartDate']) >= strtotime($input['EndDate'])) { $this->fail('End date must be after start date',400); return; }

        $currentUser = AuthMiddleware::getCurrentUser();

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

    if ($currentyear && strtotime($input['StartDate']) > strtotime($currentyear['EndDate'])) { $this->fail('Start date must be before end date of current academic year',400); return; }

        $academicYearId = $this->academicModel->createAcademicYear($input);

        if ($academicYearId) {
            $academicYear = $this->academicModel->getAcademicYearById($academicYearId);
            $this->ok('Academic year created successfully', [$academicYear]);
        } else { $this->fail('Failed to create academic year',500); }
    }

    public function deleteAcademicYear($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    if (!isset($params['id'])) { $this->fail('Academic Year ID is required',400); return; }
        $currentUser = AuthMiddleware::getCurrentUser();

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
    if (!isset($params['id'])) { $this->fail('Academic Year ID is required',400); return; }
    $input = $this->input();

        // Check if academic year exists
        $existingYear = $this->academicModel->getAcademicYearById($params['id']);
    if (!$existingYear) { $this->fail('Academic year not found',404); return; }

        // Validate dates if provided
        if (isset($input['StartDate']) && isset($input['EndDate'])) {
            if (strtotime($input['StartDate']) >= strtotime($input['EndDate'])) { $this->fail('End date must be after start date',400); return; }
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        
        // Set UpdatedBy to current user
        $input['UpdatedBy'] = $currentUser['username'];

        $currentacademicYear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);
    if ($currentacademicYear && isset($input['StartDate']) && $params['id'] != $currentacademicYear['AcademicYearID'] && strtotime($input['StartDate']) > strtotime($currentacademicYear['EndDate'])) { $this->fail('Start date must be before end date of current academic year',400); return; }

        $result = $this->academicModel->updateAcademicYear($params['id'], $input);

    if ($result) { $updatedYear = $this->academicModel->getAcademicYearById($params['id']); $this->ok('Academic year updated successfully',$updatedYear); }
    else { $this->fail('Failed to update academic year',500); }
    }

    // Classes Methods
    public function getClasses($params = []) {
    $currentUser = AuthMiddleware::getCurrentUser();
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
    if (!isset($params['id'])) { $this->fail('Section ID is required',400); return; }

        $section = $this->academicModel->getSectionById($params['id']);

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
    if (!isset($params['id'])) { $this->fail('Section ID is required',400); return; }
    $input = $this->input();

        $existing = $this->academicModel->getSectionById($params['id']);
    if (!$existing) { $this->fail('Section not found',404); return; }

        $result = $this->academicModel->updateSection($params['id'], $input);

    if ($result) { $section = $this->academicModel->getSectionById($params['id']); $this->ok('Section updated successfully',['section'=>$section]); }
    else { $this->fail('Failed to update section',500); }
    }

    public function deleteSection($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    if (!isset($params['id'])) { $this->fail('Section ID is required',400); return; }

        $existing = $this->academicModel->getSectionById($params['id']);
    if (!$existing) { $this->fail('Section not found',404); return; }

        $result = $this->academicModel->deleteSection($params['id']);

    if ($result) { $this->ok('Section deleted successfully'); }
    else { $this->fail('Failed to delete section',500); }
    }

    public function getClass($params = []) {
    if (!isset($params['id'])) { $this->fail('Class ID is required',400); return; }

        $class = $this->academicModel->getClassById($params['id']);

    if (!$class) { $this->fail('Class not found',404); return; }

        $currentUser = AuthMiddleware::getCurrentUser();

        // If authenticated and a teacher, ensure they are assigned to this class via class_teachers mapping
        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'teacher') {
                $isAssigned = $this->academicModel->isTeacherAssigned($params['id'], $currentUser['id']);
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
    if (!isset($params['id'])) { $this->fail('Class ID is required',400); return; }
    $input = $this->input();

        // Check if class exists
        $existingClass = $this->academicModel->getClassById($params['id']);
    if (!$existingClass) { $this->fail('Class not found',404); return; }
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!isset($input['UpdatedBy'])) {
            $input['UpdatedBy'] = $currentUser['username'];
        }

        $result = $this->academicModel->updateClass($params['id'], $input);

    if ($result) { $class = $this->academicModel->getClassById($params['id']); $this->ok('Class updated successfully',$class); }
    else { $this->fail('Failed to update class',500); }
    }

    public function deleteClass($params = []) {
    if (!$this->requireMethod('DELETE')) return;
    if (!isset($params['id'])) { $this->fail('Class ID is required',400); return; }

        // Check if class exists
        $class = $this->academicModel->getClassById($params['id']);
    if (!$class) { $this->fail('Class not found',404); return; }

        $result = $this->academicModel->deleteClass($params['id']);

    if ($result) { $this->ok('Class deleted successfully'); }
    else { $this->fail('Failed to delete class',500); }
    }

    public function getCurrentAcademicYear($params = []) {
        $currentUser = AuthMiddleware::getCurrentUser();
        $currentYear = $this->academicModel->getCurrentAcademicYear($currentUser['school_id']);

    if (!$currentYear) { $this->fail('No current academic year set',404); return; }
    $this->ok('Current academic year retrieved successfully', $currentYear);
    }
}
