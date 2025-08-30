<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\StudentModel;
use SchoolLive\Middleware\AuthMiddleware;

class StudentsController {
    private $studentModel;

    public function __construct() {
        $this->studentModel = new StudentModel();
        header('Content-Type: application/json');
    }

    public function getStudents($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        $limit = $_GET['limit'] ?? null;
        $offset = $_GET['offset'] ?? null;
        $class_id = $_GET['class_id'] ?? null;
        $search = $_GET['search'] ?? null;
        $status = $_GET['status'] ?? null;

        if ($search) {
            $students = $this->studentModel->searchStudents($search);
        } else if ($class_id) {
            $students = $this->studentModel->getStudentsByClass($class_id);
        } else if ($status === 'active') {
            $students = $this->studentModel->getActiveStudents();
        } else {
            $students = $this->studentModel->getStudentsWithClass($limit, $offset);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => $students
        ]);
    }

    public function getStudent($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }

        $student = $this->studentModel->findById($params['id']);

        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Student retrieved successfully',
            'data' => $student
        ]);
    }

    public function createStudent($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'student_id', 'class_id'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
                return;
            }
        }

        // Check if student_id already exists
        if ($this->studentModel->findByStudentId($input['student_id'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
            return;
        }

        // Validate email if provided
        if (isset($input['email']) && !empty($input['email'])) {
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                return;
            }
        }

        // Set default values
        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        if (!isset($input['admission_date'])) {
            $input['admission_date'] = date('Y-m-d');
        }

        $studentId = $this->studentModel->createStudent($input);

        if ($studentId) {
            $student = $this->studentModel->findById($studentId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'student' => $student
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create student']);
        }
    }

    public function updateStudent($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Check if student exists
        $existingStudent = $this->studentModel->findById($params['id']);
        if (!$existingStudent) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }

        // Validate student_id if provided
        if (isset($input['student_id'])) {
            $existingStudentId = $this->studentModel->findByStudentId($input['student_id']);
            if ($existingStudentId && $existingStudentId['id'] != $params['id']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Student ID already taken by another student']);
                return;
            }
        }

        // Validate email if provided
        if (isset($input['email']) && !empty($input['email'])) {
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                return;
            }
        }

        $result = $this->studentModel->updateStudent($params['id'], $input);

        if ($result) {
            $student = $this->studentModel->findById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => [
                    'student' => $student
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update student']);
        }
    }

    public function deleteStudent($params = []) {
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
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }

        // Check if student exists
        $student = $this->studentModel->findById($params['id']);
        if (!$student) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }

        $result = $this->studentModel->delete($params['id']);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
        }
    }
}
