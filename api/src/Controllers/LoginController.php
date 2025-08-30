<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\UserModel;
use SchoolLive\Middleware\AuthMiddleware;

class LoginController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
        header('Content-Type: application/json');
    }

    public function login($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Accept both email/username for login
        if ((!isset($input['email']) && !isset($input['username'])) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email/Username and password are required']);
            return;
        }

        // Try to find user by email or username
        $user = null;
        if (isset($input['email'])) {
            $user = $this->userModel->findByEmail($input['email']);
        } elseif (isset($input['username'])) {
            $user = $this->userModel->findByUsername($input['username']);
        }

        if (!$user || !$this->userModel->verifyPassword($input['password'], $user['PasswordHash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        if ($user['IsActive'] != 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Account is not active']);
            return;
        }

        // Generate tokens
        $userData = [
            'id' => $user['UserID'],
            'username' => $user['Username'],
            'email' => $user['EmailID'],
            'role' => $user['RoleName'],
            'role_id' => $user['RoleID'],
            'school_id' => $user['SchoolID'],
            'school_name' => $user['SchoolName'],
            'first_name' => $user['FirstName'],
            'middle_name' => $user['MiddleName'],
            'last_name' => $user['LastName'],
            'is_first_login' => $user['IsFirstLogin']
        ];

        $accessToken = AuthMiddleware::generateToken($userData);
        $refreshToken = AuthMiddleware::generateRefreshToken($userData);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $userData,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function register($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'username', 'password', 'role_id'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
                return;
            }
        }

        // Validate email format if provided
        if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        // Check if user already exists
        if ($this->userModel->findByUsername($input['username'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User already exists with this username']);
            return;
        }

        if (isset($input['email']) && $this->userModel->findByEmail($input['email'])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'User already exists with this email']);
            return;
        }

        // Validate role_id (should be valid role ID from Tm_Roles)
        $allowedRoleIds = [1, 2, 3, 4]; // super_admin, client_admin, teacher, student
        if (!in_array($input['role_id'], $allowedRoleIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid role ID']);
            return;
        }

        // Set default values
        $input['created_by'] = 'System';

        $result = $this->userModel->createUser($input);

        if ($result) {
            // Get the created user (need to get the last insert ID)
            $user = $this->userModel->findByUsername($input['username']);
            
            // Remove sensitive data
            unset($user['PasswordHash']);
            
            echo json_encode([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to register user']);
        }
    }

    public function refresh($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['refresh_token'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Refresh token is required']);
            return;
        }

        $userData = AuthMiddleware::validateRefreshToken($input['refresh_token']);

        if (!$userData) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired refresh token']);
            return;
        }

        // Verify user still exists and is active
        $user = $this->userModel->findById($userData['id']);
        if (!$user || $user['IsActive'] != 1) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User account is no longer valid']);
            return;
        }

        // Generate new tokens
        $newAccessToken = AuthMiddleware::generateToken($userData);
        $newRefreshToken = AuthMiddleware::generateRefreshToken($userData);

        echo json_encode([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer'
            ]
        ]);
    }
}
