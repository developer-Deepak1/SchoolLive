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
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        $academicYears = $this->academicModel->findAll();

        echo json_encode([
            'success' => true,
            'message' => 'Academic years retrieved successfully',
            'data' => $academicYears
        ]);
    }

    public function createAcademicYear($params = []) {
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

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
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        $academic_year_id = $_GET['academic_year_id'] ?? null;
        $currentUser = AuthMiddleware::getCurrentUser();

        if ($currentUser['role'] === 'teacher') {
            // Teachers can only see their assigned classes
            $classes = $this->academicModel->getClassesByTeacher($currentUser['id']);
        } else {
            $classes = $this->academicModel->getAllClasses($academic_year_id);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Classes retrieved successfully',
            'data' => $classes
        ]);
    }

    public function getClass($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

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
        
        // Check if teacher can access this class
        if ($currentUser['role'] === 'teacher' && $class['teacher_id'] != $currentUser['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied to this class']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class
        ]);
    }

    public function createClass($params = []) {
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $requiredFields = ['class_name', 'section', 'academic_year_id'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
                return;
            }
        }

        // Set default values
        if (!isset($input['max_students'])) {
            $input['max_students'] = 50;
        }

        $classId = $this->academicModel->createClass($input);

        if ($classId) {
            $class = $this->academicModel->getClassById($classId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => [
                    'class' => $class
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create class']);
        }
    }

    public function updateClass($params = []) {
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

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

        $result = $this->academicModel->updateClass($params['id'], $input);

        if ($result) {
            $class = $this->academicModel->getClassById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Class updated successfully',
                'data' => [
                    'class' => $class
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update class']);
        }
    }

    public function deleteClass($params = []) {
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

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
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

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
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

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
