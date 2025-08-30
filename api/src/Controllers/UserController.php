<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\UserModel;
use SchoolLive\Middleware\AuthMiddleware;

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
        header('Content-Type: application/json');
    }

    public function getUsers($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        $limit = $_GET['limit'] ?? null;
        $offset = $_GET['offset'] ?? null;
        $role = $_GET['role'] ?? null;

        if ($role) {
            $users = $this->userModel->getUsersByRole($role);
        } else {
            $users = $this->userModel->findAll($limit, $offset);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    public function getUser($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            return;
        }

        $user = $this->userModel->findById($params['id']);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    public function createUser($params = []) {
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
        $requiredFields = ['first_name', 'last_name', 'email', 'password', 'role'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
                return;
            }
        }

        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        // Check if user already exists
        if ($this->userModel->findByEmail($input['email'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User already exists with this email']);
            return;
        }

        // Validate role
        $allowedRoles = ['admin', 'teacher', 'student', 'parent'];
        if (!in_array($input['role'], $allowedRoles)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            return;
        }

        // Set default status if not provided
        if (!isset($input['status'])) {
            $input['status'] = 'active';
        }

        $userId = $this->userModel->createUser($input);

        if ($userId) {
            $user = $this->userModel->findById($userId);
            
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user' => $user
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create user']);
        }
    }

    public function updateUser($params = []) {
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
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Check if user exists
        $existingUser = $this->userModel->findById($params['id']);
        if (!$existingUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        // Validate email if provided
        if (isset($input['email'])) {
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                return;
            }

            // Check if email is already taken by another user
            $existingEmailUser = $this->userModel->findByEmail($input['email']);
            if ($existingEmailUser && $existingEmailUser['id'] != $params['id']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Email already taken by another user']);
                return;
            }
        }

        // Validate role if provided
        if (isset($input['role'])) {
            $allowedRoles = ['admin', 'teacher', 'student', 'parent'];
            if (!in_array($input['role'], $allowedRoles)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid role']);
                return;
            }
        }

        $result = $this->userModel->updateUser($params['id'], $input);

        if ($result) {
            $user = $this->userModel->findById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'user' => $user
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
    }

    public function deleteUser($params = []) {
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
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            return;
        }

        // Check if user exists
        $user = $this->userModel->findById($params['id']);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        $result = $this->userModel->delete($params['id']);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
    }

    public function getProfile($params = []) {
        if (!AuthMiddleware::authenticate()) {
            return;
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $user = $this->userModel->findById($currentUser['id']);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => $user
        ]);
    }
}
