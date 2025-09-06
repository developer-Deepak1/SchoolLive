<?php
namespace SchoolLive\Core;

class Router {
    private $routes = [];

    public function __construct() {
        $this->initializeRoutes();
    }

    private function initializeRoutes() {
    // Role definitions are loaded lazily when needed. Do not require the file here
    // to avoid startup failures when it's absent during lightweight runs.

    // Authentication routes (public)
    $this->routes['POST']['/api/auth/login'] = ['handler' => ['SchoolLive\Controllers\LoginController', 'login'], 'roles' => null];
    $this->routes['POST']['/api/auth/refresh'] = ['handler' => ['SchoolLive\Controllers\LoginController', 'refresh'], 'roles' => null];

    $this->routes['GET']['/api/academic/getAcademicYears'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getAcademicYears'], 'roles' => true];
    $this->routes['POST']['/api/academic/CreateAcademicYears'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'createAcademicYear'], 'roles' => true];
    $this->routes['PUT']['/api/academic/UpdateAcademicYears/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'updateAcademicYear'], 'roles' => true];
    $this->routes['DELETE']['/api/academic/DeleteAcademicYears/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'deleteAcademicYear'], 'roles' => true];

    $this->routes['GET']['/api/academic/getClasses'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getClasses'], 'roles' => true];
    $this->routes['POST']['/api/academic/CreateClasses'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'createClass'], 'roles' => true];
    $this->routes['GET']['/api/academic/classes/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getClass'], 'roles' => null];
    $this->routes['PUT']['/api/academic/classes/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'updateClass'], 'roles' => true];
    $this->routes['DELETE']['/api/academic/classes/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'deleteClass'], 'roles' => true];

    // Sections routes
    $this->routes['GET']['/api/academic/sections'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getSections'], 'roles' => true];
    $this->routes['POST']['/api/academic/sections'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'createSection'], 'roles' => true];
    $this->routes['GET']['/api/academic/sections/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getSection'], 'roles' => true];
    $this->routes['PUT']['/api/academic/sections/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'updateSection'], 'roles' => true];
    $this->routes['DELETE']['/api/academic/sections/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'deleteSection'], 'roles' => true];

    // Dashboard summary (authenticated)
    $this->routes['GET']['/api/dashboard/summary'] = ['handler' => ['SchoolLive\Controllers\DashboardController', 'summary'], 'roles' => true];
    // Student specific dashboard
    $this->routes['GET']['/api/dashboard/student'] = ['handler' => ['SchoolLive\Controllers\StudentDashboardController', 'summary'], 'roles' => true];
    // Student monthly attendance lightweight endpoint
    $this->routes['GET']['/api/dashboard/student/monthlyAttendance'] = ['handler' => ['SchoolLive\Controllers\StudentDashboardController', 'getMonthlyAttendance'], 'roles' => true];
    // Teacher specific dashboard
    $this->routes['GET']['/api/dashboard/teacher'] = ['handler' => ['SchoolLive\Controllers\TeacherDashboardController', 'getMonthlyAttendance'], 'roles' => true];

    // Academic calendar: weekly offs, holidays, reports
    $this->routes['GET']['/api/academic/getWeeklyOffs'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getWeeklyOffs'], 'roles' => true];
    $this->routes['POST']['/api/academic/setWeeklyOffs'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'setWeeklyOffs'], 'roles' => true];
    $this->routes['GET']['/api/academic/getHolidays'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getHolidays'], 'roles' => true];
    $this->routes['POST']['/api/academic/createHoliday'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'createHoliday'], 'roles' => true];
    $this->routes['POST']['/api/academic/createHolidayRange'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'createHolidayRange'], 'roles' => true];
    $this->routes['DELETE']['/api/academic/deleteHoliday/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'deleteHoliday'], 'roles' => true];
    $this->routes['PUT']['/api/academic/updateHoliday/{id}'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'updateHoliday'], 'roles' => true];
    $this->routes['GET']['/api/academic/getWeeklyReport'] = ['handler' => ['SchoolLive\Controllers\AcademicController', 'getWeeklyReport'], 'roles' => true];

    // Students routes
    $this->routes['GET']['/api/students'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'list'], 'roles' => true];
    $this->routes['POST']['/api/students'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'create'], 'roles' => true];
    $this->routes['POST']['/api/students/admission'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'admission'], 'roles' => true];
    $this->routes['GET']['/api/students/{id}'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'get'], 'roles' => true];
    $this->routes['PUT']['/api/students/{id}'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'update'], 'roles' => true];
    $this->routes['DELETE']['/api/students/{id}'] = ['handler' => ['SchoolLive\Controllers\StudentController', 'delete'], 'roles' => true];
    $this->routes['POST']['/api/students/{id}/reset-password'] = ['handler' => ['SchoolLive\\Controllers\\StudentController', 'resetPassword'], 'roles' => true];

    // Employees routes
    $this->routes['GET']['/api/employees'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'list'], 'roles' => true];
    $this->routes['POST']['/api/employees'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'create'], 'roles' => true];
    $this->routes['GET']['/api/employees/{id}'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'get'], 'roles' => true];
    $this->routes['PUT']['/api/employees/{id}'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'update'], 'roles' => true];
    $this->routes['DELETE']['/api/employees/{id}'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'delete'], 'roles' => true];
    // Reset password for an employee (admin action)
    $this->routes['POST']['/api/employees/{id}/reset-password'] = ['handler' => ['SchoolLive\\Controllers\\EmployeesController', 'resetPassword'], 'roles' => true];

    // Roles helper endpoint - lightweight implementation using UserModel's PDO to avoid creating a separate RolesController
    $this->routes['GET']['/api/roles'] = ['handler' => function($params = []) {
        // Lazy-load model and fetch roles
        $um = new \SchoolLive\Models\UserModel();
        $pdo = $um->getPdo();
    // Some databases may not have an IsActive column on Tm_Roles; avoid filtering by it to be compatible.
    $stmt = $pdo->prepare("SELECT RoleID, RoleName, RoleDisplayName FROM Tm_Roles ORDER BY RoleDisplayName");
        $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    }, 'roles' => true];

    // Attendance routes (daily mark & list)
    $this->routes['GET']['/api/attendance'] = ['handler' => ['SchoolLive\\Controllers\\AttendanceController', 'list'], 'roles' => true];
    $this->routes['POST']['/api/attendance'] = ['handler' => ['SchoolLive\\Controllers\\AttendanceController', 'save'], 'roles' => true];
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
            $route = $this->routes[$httpMethod][$uri];
            $this->callHandler($route['handler'], [], $route['roles']);
            return;
        }

    // Try pattern matching for routes with parameters
    $routesForMethod = isset($this->routes[$httpMethod]) ? $this->routes[$httpMethod] : [];
    foreach ($routesForMethod as $pattern => $handler) {
            if (strpos($pattern, '{') !== false) {
                $params = $this->matchRoute($pattern, $uri);
                if ($params !== false) {
                    // handler here may be stored directly or nested under 'handler'
                    if (is_array($handler) && isset($handler['handler'])) {
                        $this->callHandler($handler['handler'], $params, $handler['roles'] ?? null);
                    } else {
                        $this->callHandler($handler, $params, null);
                    }
                    return;
                }
            }
        }

        // Route not found
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
    }

    /**
     * Register a route programmatically.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path (e.g. '/api/foo' or '/api/items/{id}')
     * @param callable|array $handler Controller handler or callable
     * @param null|callable|array $middleware Optional middleware or roles marker. If callable, it will be invoked before the handler. If non-null and not callable it simply signals authentication is required.
     */
    public function addRoute(string $method, string $path, $handler, $middleware = null) {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }
        $this->routes[$method][rtrim($path, '/')] = ['handler' => $handler, 'roles' => $middleware];
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

    private function callHandler($handler, $params = [], $roles = null) {
        // Enforce authentication centrally when a route indicates auth is required.
        // Note: the router no longer enforces role membership; controllers or
        // other middleware should handle any finer-grained authorization.
        if ($roles !== null) {
            // If a boolean true is used, treat it as "require login" and call the AuthMiddleware handle()
            if ($roles === true) {
                if (!\SchoolLive\Middleware\AuthMiddleware::handle()) {
                    return;
                }
            // If a callable is provided in the 'roles' slot, treat it as a middleware check.
            } elseif (is_callable($roles)) {
                $result = call_user_func($roles);
                if ($result === false) {
                    return;
                }
            } else {
                // Non-callable, non-boolean 'roles' value signals authentication is required via authenticate().
                $authAuthenticate = [\SchoolLive\Middleware\AuthMiddleware::class, 'authenticate'];
                if (!call_user_func($authAuthenticate)) {
                    return;
                }
                // Intentionally do not check user role membership here.
            }
        }

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
