<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Models\ClassSectionModel;

class ClassSectionController extends BaseController {
    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = new ClassSectionModel();
    }

    // Classes
    public function getClasses($params = []) {
        $currentUser = $this->currentUser(); if(!$currentUser) return;
        $academicYearId = $_GET['AcademicYearID'] ?? $_GET['academic_year_id'] ?? $currentUser['AcademicYearID'] ?? null;
        $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
        $classes = $this->model->getAllClasses($academicYearId, $currentUser['school_id'], $isActive);
        $this->ok('Classes retrieved successfully', $classes);
    }

    public function createClass($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();
        $requiredFields = ['ClassName', 'Stream', 'ClassCode'];
        if (!$this->ensure($input, $requiredFields)) return;
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!isset($input['AcademicYearID'])) $input['AcademicYearID'] = $currentUser['AcademicYearID'];
        if (!isset($input['SchoolID'])) $input['SchoolID'] = $currentUser['school_id'];
        if (!isset($input['Username'])) $input['Username'] = $currentUser['username'];
        $classId = $this->model->createClass($input);
        if ($classId) { $class = $this->model->getClassById($classId); $this->ok('Class created successfully',$class); }
        else { $this->fail('Failed to create class',500); }
    }

    public function getClass($params = []) {
        $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;
        $class = $this->model->getClassById($id);
        if (!$class) { $this->fail('Class not found',404); return; }
        $currentUser = AuthMiddleware::getCurrentUser();
        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'teacher') {
            $isAssigned = $this->model->isTeacherAssigned($id, $currentUser['id']);
            if (!$isAssigned) { $this->fail('Access denied to this class',403); return; }
        }
        $this->ok('Class retrieved successfully', $class);
    }

    public function updateClass($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;
        $input = $this->input();
        $existingClass = $this->model->getClassById($id);
        if (!$existingClass) { $this->fail('Class not found',404); return; }
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!isset($input['UpdatedBy'])) { $input['UpdatedBy'] = $currentUser['username']; }
        $result = $this->model->updateClass($id, $input);
        if ($result) { $class = $this->model->getClassById($id); $this->ok('Class updated successfully',$class); }
        else { $this->fail('Failed to update class',500); }
    }

    public function deleteClass($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Class ID'); if($id===null) return;
        $class = $this->model->getClassById($id);
        if (!$class) { $this->fail('Class not found',404); return; }
        $result = $this->model->deleteClass($id);
        if ($result) { $this->ok('Class deleted successfully'); }
        else { $this->fail('Failed to delete class',500); }
    }

    // Sections
    public function getSections($params = []) {
        $academic_year_id = $_GET['academic_year_id'] ?? $_GET['AcademicYearID'] ?? null;
        $class_id = $_GET['class_id'] ?? $_GET['ClassID'] ?? null;
        $currentUser = $this->currentUser();
        $school_id = $_GET['school_id'] ?? $_GET['SchoolID'] ?? ($currentUser['school_id'] ?? null);
        $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1;
        $sections = $this->model->getAllSections($academic_year_id, $class_id, $school_id);
        $this->ok('Sections retrieved successfully', $sections);
    }

    public function getSection($params = []) {
        $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;
        $section = $this->model->getSectionById($id);
        if (!$section) { $this->fail('Section not found',404); return; }
        $this->ok('Section retrieved successfully', $section);
    }

    public function createSection($params = []) {
        if (!$this->requireMethod('POST')) return;
        $input = $this->input();
        if (isset($input['SectionName']) && !isset($input['section_name'])) $input['section_name'] = $input['SectionName'];
        if (isset($input['ClassID']) && !isset($input['class_id'])) $input['class_id'] = $input['ClassID'];
        if (isset($input['MaxStrength']) && !isset($input['max_strength'])) $input['max_strength'] = $input['MaxStrength'];
        if (isset($input['AcademicYearID']) && !isset($input['academic_year_id'])) $input['academic_year_id'] = $input['AcademicYearID'];
        if (isset($input['SchoolID']) && !isset($input['school_id'])) $input['school_id'] = $input['SchoolID'];
        $currentUser = $this->currentUser(); if(!$currentUser) return;
        if (!isset($input['school_id'])) $input['school_id'] = $currentUser['school_id'];
        if (!isset($input['academic_year_id'])) $input['academic_year_id'] = $currentUser['AcademicYearID'];
        if (!$this->ensure($input, ['section_name','school_id','academic_year_id','class_id'])) return;
        $sectionId = $this->model->createSection($input);
        if ($sectionId) { $section = $this->model->getSectionById($sectionId); $this->ok('Section created successfully',['section'=>$section]); }
        else { $this->fail('Failed to create section',500); }
    }

    public function updateSection($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;
        $input = $this->input();
        $existing = $this->model->getSectionById($id);
        if (!$existing) { $this->fail('Section not found',404); return; }
        if (isset($input['SectionName']) && !isset($input['section_name'])) $input['section_name'] = $input['SectionName'];
        if (isset($input['ClassID']) && !isset($input['class_id'])) $input['class_id'] = $input['ClassID'];
        if (isset($input['MaxStrength']) && !isset($input['max_strength'])) $input['max_strength'] = $input['MaxStrength'];
        if (isset($input['AcademicYearID']) && !isset($input['academic_year_id'])) $input['academic_year_id'] = $input['AcademicYearID'];
        if (isset($input['SchoolID']) && !isset($input['school_id'])) $input['school_id'] = $input['SchoolID'];
        $mapping = [
            'section_name' => 'SectionName',
            'class_id' => 'ClassID',
            'max_strength' => 'MaxStrength',
            'academic_year_id' => 'AcademicYearID',
            'school_id' => 'SchoolID'
        ];
        foreach ($mapping as $snake => $pascal) {
            if (isset($input[$snake]) && !isset($input[$pascal])) { $input[$pascal] = $input[$snake]; }
            if (isset($input[$snake])) { unset($input[$snake]); }
        }
        $result = $this->model->updateSection($id, $input);
        if ($result) { $section = $this->model->getSectionById($id); $this->ok('Section updated successfully',['section'=>$section]); }
        else { $this->fail('Failed to update section',500); }
    }

    public function deleteSection($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Section ID'); if($id===null) return;
        $existing = $this->model->getSectionById($id);
        if (!$existing) { $this->fail('Section not found',404); return; }
        $result = $this->model->deleteSection($id);
        if ($result) { $this->ok('Section deleted successfully'); }
        else { $this->fail('Failed to delete section',500); }
    }
}
