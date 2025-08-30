<?php
namespace SchoolLive\Core;

class Router {
    private $routes = [];

    public function __construct() {
        $this->initializeRoutes();
    }

    private function initializeRoutes() {
        // Authentication routes
        $this->routes['POST']['/api/auth/login'] = ['SchoolLive\Controllers\LoginController', 'login'];
        $this->routes['POST']['/api/auth/register'] = ['SchoolLive\Controllers\LoginController', 'register'];
        $this->routes['POST']['/api/auth/refresh'] = ['SchoolLive\Controllers\LoginController', 'refresh'];
        
        // User routes
        $this->routes['GET']['/api/users'] = ['SchoolLive\Controllers\UserController', 'getUsers'];
        $this->routes['GET']['/api/users/{id}'] = ['SchoolLive\Controllers\UserController', 'getUser'];
        $this->routes['POST']['/api/users'] = ['SchoolLive\Controllers\UserController', 'createUser'];
        $this->routes['PUT']['/api/users/{id}'] = ['SchoolLive\Controllers\UserController', 'updateUser'];
        $this->routes['DELETE']['/api/users/{id}'] = ['SchoolLive\Controllers\UserController', 'deleteUser'];
        
        // Student routes
        $this->routes['GET']['/api/students'] = ['SchoolLive\Controllers\StudentsController', 'getStudents'];
        $this->routes['GET']['/api/students/{id}'] = ['SchoolLive\Controllers\StudentsController', 'getStudent'];
        $this->routes['POST']['/api/students'] = ['SchoolLive\Controllers\StudentsController', 'createStudent'];
        $this->routes['PUT']['/api/students/{id}'] = ['SchoolLive\Controllers\StudentsController', 'updateStudent'];
        $this->routes['DELETE']['/api/students/{id}'] = ['SchoolLive\Controllers\StudentsController', 'deleteStudent'];
        
        // Academic routes
        $this->routes['GET']['/api/academic/years'] = ['SchoolLive\Controllers\AcademicController', 'getAcademicYears'];
        $this->routes['POST']['/api/academic/years'] = ['SchoolLive\Controllers\AcademicController', 'createAcademicYear'];
        $this->routes['GET']['/api/academic/classes'] = ['SchoolLive\Controllers\AcademicController', 'getClasses'];
        $this->routes['POST']['/api/academic/classes'] = ['SchoolLive\Controllers\AcademicController', 'createClass'];
        $this->routes['PUT']['/api/academic/classes/{id}'] = ['SchoolLive\Controllers\AcademicController', 'updateClass'];
        $this->routes['DELETE']['/api/academic/classes/{id}'] = ['SchoolLive\Controllers\AcademicController', 'deleteClass'];
        
        // Attendance routes
        $this->routes['GET']['/api/attendance'] = ['SchoolLive\Controllers\AttendanceController', 'getAttendance'];
        $this->routes['POST']['/api/attendance'] = ['SchoolLive\Controllers\AttendanceController', 'markAttendance'];
        $this->routes['PUT']['/api/attendance/{id}'] = ['SchoolLive\Controllers\AttendanceController', 'updateAttendance'];
        $this->routes['DELETE']['/api/attendance/{id}'] = ['SchoolLive\Controllers\AttendanceController', 'deleteAttendance'];
        
        // Profile route
        $this->routes['GET']['/api/profile'] = ['SchoolLive\Controllers\UserController', 'getProfile'];
        
        // API Info route
        $this->routes['GET']['/api'] = [$this, 'apiInfo'];
    }

    public function dispatch() {
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }

        // Try exact match first
        if (isset($this->routes[$httpMethod][$uri])) {
            $handler = $this->routes[$httpMethod][$uri];
            $this->callHandler($handler, []);
            return;
        }

        // Try pattern matching for routes with parameters
        foreach ($this->routes[$httpMethod] ?? [] as $pattern => $handler) {
            if (strpos($pattern, '{') !== false) {
                $params = $this->matchRoute($pattern, $uri);
                if ($params !== false) {
                    $this->callHandler($handler, $params);
                    return;
                }
            }
        }

        // Route not found
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }

    private function matchRoute($pattern, $uri) {
        // Convert pattern to regex
        $regex = preg_replace('/\{(\w+)\}/', '(\d+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches); // Remove full match
            
            // Extract parameter names from pattern
            preg_match_all('/\{(\w+)\}/', $pattern, $paramNames);
            $params = [];
            
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
            
            return $params;
        }

        return false;
    }

    private function callHandler($handler, $params = []) {
        if (is_array($handler)) {
            $controller = new $handler[0]();
            $method = $handler[1];
            $controller->$method($params);
        } else if (is_callable($handler)) {
            $handler($params);
        }
    }

    public function apiInfo($params = []) {
        echo json_encode([
            'success' => true,
            'message' => 'SchoolLive API v2.0',
            'version' => '2.0.0',
            'endpoints' => [
                'Authentication' => [
                    'POST /api/auth/login' => 'User login',
                    'POST /api/auth/register' => 'User registration',
                    'POST /api/auth/refresh' => 'Refresh access token'
                ],
                'Users' => [
                    'GET /api/users' => 'Get all users (Admin/Teacher only)',
                    'GET /api/users/{id}' => 'Get user by ID',
                    'POST /api/users' => 'Create new user (Admin only)',
                    'PUT /api/users/{id}' => 'Update user (Admin only)',
                    'DELETE /api/users/{id}' => 'Delete user (Admin only)',
                    'GET /api/profile' => 'Get current user profile'
                ],
                'Students' => [
                    'GET /api/students' => 'Get all students',
                    'GET /api/students/{id}' => 'Get student by ID',
                    'POST /api/students' => 'Create new student',
                    'PUT /api/students/{id}' => 'Update student',
                    'DELETE /api/students/{id}' => 'Delete student (Admin only)'
                ],
                'Academic' => [
                    'GET /api/academic/years' => 'Get academic years',
                    'POST /api/academic/years' => 'Create academic year (Admin only)',
                    'GET /api/academic/classes' => 'Get classes',
                    'POST /api/academic/classes' => 'Create class (Admin only)',
                    'PUT /api/academic/classes/{id}' => 'Update class (Admin only)',
                    'DELETE /api/academic/classes/{id}' => 'Delete class (Admin only)'
                ],
                'Attendance' => [
                    'GET /api/attendance' => 'Get attendance records',
                    'POST /api/attendance' => 'Mark attendance',
                    'PUT /api/attendance/{id}' => 'Update attendance',
                    'DELETE /api/attendance/{id}' => 'Delete attendance (Admin only)'
                ]
            ]
        ]);
    }
}
