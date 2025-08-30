<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\AcademicModel;
use SchoolLive\Middleware\AuthMiddleware;

class AcademicController {
    private $academicModel;

    public function __construct() {
        $this->academicModel = new AcademicModel();
        header('Content-Type: application/json');
    }

    // Academic Years Methods
    public function getAcademicYears($params = []) {
        $academicYears = $this->academicModel->findAll();

        echo json_encode([
            'success' => true,
            'message' => 'Academic years retrieved successfully',
            'data' => $academicYears
        ]);
    }

    public function createAcademicYear($params = []) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $requiredFields = ['year_name', 'start_date', 'end_date'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
                return;
            }
        }

        // Validate dates
        if (strtotime($input['start_date']) >= strtotime($input['end_date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
            return;
        }

        // Set default values
        if (!isset($input['is_current'])) {
            $input['is_current'] = 0;
        }

        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        $academicYearId = $this->academicModel->createAcademicYear($input);

        if ($academicYearId) {
            $academicYear = $this->academicModel->findById($academicYearId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Academic year created successfully',
                'data' => [
                    'academic_year' => $academicYear
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create academic year']);
        }
    }

    // Classes Methods
    public function getClasses($params = []) {
    $currentUser = AuthMiddleware::getCurrentUser();
    $classes = $this->academicModel->getAllClasses($currentUser['AcademicYearID'], $currentUser['school_id']);

        echo json_encode([
            'success' => true,
            'message' => 'Classes retrieved successfully',
            'data' => $classes
        ]);
    }

    // Sections Methods
    public function getSections($params = []) {
        $academic_year_id = $_GET['academic_year_id'] ?? null;
        $class_id = $_GET['class_id'] ?? null;

        $sections = $this->academicModel->getAllSections($academic_year_id, $class_id);

        echo json_encode([
            'success' => true,
            'message' => 'Sections retrieved successfully',
            'data' => $sections
        ]);
    }

    public function getSection($params = []) {
        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            return;
        }

        $section = $this->academicModel->getSectionById($params['id']);

        if (!$section) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Section not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Section retrieved successfully',
            'data' => $section
        ]);
    }

    public function createSection($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        $input = json_decode(file_get_contents('php://input'), true);

        $requiredFields = ['section_name', 'section_display_name', 'school_id', 'academic_year_id', 'class_id'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
                return;
            }
        }

        $sectionId = $this->academicModel->createSection($input);

        if ($sectionId) {
            $section = $this->academicModel->getSectionById($sectionId);
            echo json_encode([
                'success' => true,
                'message' => 'Section created successfully',
                'data' => ['section' => $section]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create section']);
        }
    }

    public function updateSection($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $existing = $this->academicModel->getSectionById($params['id']);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Section not found']);
            return;
        }

        $result = $this->academicModel->updateSection($params['id'], $input);

        if ($result) {
            $section = $this->academicModel->getSectionById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Section updated successfully',
                'data' => ['section' => $section]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update section']);
        }
    }

    public function deleteSection($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            return;
        }

        $existing = $this->academicModel->getSectionById($params['id']);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Section not found']);
            return;
        }

        $result = $this->academicModel->deleteSection($params['id']);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Section deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete section']);
        }
    }

    public function getClass($params = []) {
        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Class ID is required']);
            return;
        }

        $class = $this->academicModel->getClassById($params['id']);

        if (!$class) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            return;
        }

        $currentUser = AuthMiddleware::getCurrentUser();

        // If authenticated and a teacher, ensure they are assigned to this class via class_teachers mapping
        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'teacher') {
            $isAssigned = $this->academicModel->isTeacherAssigned($params['id'], $currentUser['id']);
            if (!$isAssigned) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied to this class']);
                return;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class
        ]);
    }

    public function createClass($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields - adapted to new class schema
    $requiredFields = ['ClassName', 'Stream', 'ClassCode'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            return;
            }
        }
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

        if ($classId) {
            $class = $this->academicModel->getClassById($classId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => $class
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create class']);
        }
    }

    public function updateClass($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Class ID is required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Check if class exists
        $existingClass = $this->academicModel->getClassById($params['id']);
        if (!$existingClass) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            return;
        }
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!isset($input['UpdatedBy'])) {
            $input['UpdatedBy'] = $currentUser['username'];
        }

        $result = $this->academicModel->updateClass($params['id'], $input);

        if ($result) {
            $class = $this->academicModel->getClassById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Class updated successfully',
                'data' => $class
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update class']);
        }
    }

    public function deleteClass($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Class ID is required']);
            return;
        }

        // Check if class exists
        $class = $this->academicModel->getClassById($params['id']);
        if (!$class) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            return;
        }

        $result = $this->academicModel->deleteClass($params['id']);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Class deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete class']);
        }
    }

    public function setCurrentAcademicYear($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

    

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Academic Year ID is required']);
            return;
        }

        // Check if academic year exists
        $academicYear = $this->academicModel->findById($params['id']);
        if (!$academicYear) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Academic year not found']);
            return;
        }

        $result = $this->academicModel->setCurrentAcademicYear($params['id']);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Current academic year set successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to set current academic year']);
        }
    }

    public function getCurrentAcademicYear($params = []) {
        $currentYear = $this->academicModel->getCurrentAcademicYear();

        if (!$currentYear) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No current academic year set']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Current academic year retrieved successfully',
            'data' => $currentYear
        ]);
    }
}
