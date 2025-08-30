<?php
namespace SchoolLive\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthMiddleware {
    private static $secret_key;
    private static $algorithm = 'HS256';

    public static function init() {
        $config = require __DIR__ . '/../../config/jwt.php';
        self::$secret_key = $config['secret_key'];
    }

    public static function authenticate() {
        self::init();
        
        // Get authorization header in a robust way (supports built-in server and FPM/Apache)
        $authHeader = null;

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders() ?: [];
            foreach ($allHeaders as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }

        // Fallback to $_SERVER variables (works with php -S and many servers)
        if (!$authHeader) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['Authorization'])) {
                $authHeader = $_SERVER['Authorization'];
            }
        }

        if (!$authHeader) {
            self::sendUnauthorizedResponse('Authorization header missing');
            return false;
        }

        // Extract token from Bearer token
        $token = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            self::sendUnauthorizedResponse('Token missing');
            return false;
        }

        try {
            $decoded = JWT::decode($token, new Key(self::$secret_key, self::$algorithm));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                self::sendUnauthorizedResponse('Token expired');
                return false;
            }

            // Store user info in global variable for use in controllers
            $GLOBALS['current_user'] = (array) $decoded->data;
            return true;

        } catch (Exception $e) {
            self::sendUnauthorizedResponse('Invalid token: ' . $e->getMessage());
            return false;
        }
    }

    public static function requireRole($requiredRoles) {
        if (!self::authenticate()) {
            return false;
        }

        $user = $GLOBALS['current_user'];
        
        if (is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }

        if (!in_array($user['role'], $requiredRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient permissions'
            ]);
            return false;
        }

        return true;
    }

    public static function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }

    private static function sendUnauthorizedResponse($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }

    public static function generateToken($userData, $expirationTime = null) {
        self::init();
        
        if ($expirationTime === null) {
            $expirationTime = time() + (24 * 60 * 60); // 24 hours
        }

        $payload = [
            'iss' => 'SchoolLive',
            'iat' => time(),
            'exp' => $expirationTime,
            'data' => $userData
        ];

        return JWT::encode($payload, self::$secret_key, self::$algorithm);
    }

    public static function generateRefreshToken($userData) {
        self::init();
        
        $expirationTime = time() + (30 * 24 * 60 * 60); // 30 days

        $payload = [
            'iss' => 'SchoolLive',
            'iat' => time(),
            'exp' => $expirationTime,
            'type' => 'refresh',
            'data' => $userData
        ];

        return JWT::encode($payload, self::$secret_key, self::$algorithm);
    }

    public static function validateRefreshToken($token) {
        self::init();
        
        try {
            $decoded = JWT::decode($token, new Key(self::$secret_key, self::$algorithm));
            
            // Check if it's a refresh token
            if (!isset($decoded->type) || $decoded->type !== 'refresh') {
                return false;
            }

            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false;
            }

            return (array) $decoded->data;

        } catch (Exception $e) {
            return false;
        }
    }
}
