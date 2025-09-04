# Security Issues Detailed Report

## 1. JWT Secret Key Management (CRITICAL)

### Current Implementation
```php
// api/config/jwt.php
return [
    'secret_key' => 'your-secret-key-here-change-this-in-production-schoollive-2025',
    'algorithm' => 'HS256',
    'access_token_expiry' => 3600, // 1 hour
    'refresh_token_expiry' => 2592000 // 30 days
];
```

### Issues
- Secret key is hardcoded and visible in source code
- Key appears to be a placeholder, not a cryptographically secure secret
- No key rotation mechanism
- Same key used for all environments

### Recommended Fix
```php
// api/config/jwt.php
return [
    'secret_key' => $_ENV['JWT_SECRET_KEY'] ?? getenv('JWT_SECRET_KEY'),
    'algorithm' => 'HS256',
    'access_token_expiry' => (int)($_ENV['JWT_ACCESS_EXPIRY'] ?? 3600),
    'refresh_token_expiry' => (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000)
];
```

### Environment Setup
```bash
# .env file (not committed to version control)
JWT_SECRET_KEY=your-very-long-randomly-generated-secret-key-here-at-least-256-bits
JWT_ACCESS_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000
```

## 2. Global Variable Usage (HIGH)

### Current Implementation
```php
// api/src/Middleware/AuthMiddleware.php:70
$GLOBALS['current_user'] = (array) $decoded->data;

// Usage in controllers
$user = $GLOBALS['current_user'] ?? null;
```

### Issues
- Thread safety concerns
- Global state pollution
- Difficult to test
- No encapsulation

### Recommended Fix
```php
// Create a Request context class
class RequestContext {
    private static $instance;
    private $user;
    
    public static function getInstance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setUser(array $user): void {
        $this->user = $user;
    }
    
    public function getUser(): ?array {
        return $this->user;
    }
}

// In AuthMiddleware
RequestContext::getInstance()->setUser((array) $decoded->data);

// In BaseController
protected function currentUser(): ?array {
    return RequestContext::getInstance()->getUser();
}
```

## 3. Input Validation Gaps (HIGH)

### Current Issues
- Missing validation in many controller methods
- Direct binding of user input to database queries
- No centralized validation rules

### Example Vulnerable Code
```php
// api/src/Controllers/StudentController.php
$filters = [
    'class_id' => $_GET['class_id'] ?? null,
    'section_id' => $_GET['section_id'] ?? null,
    'gender' => $_GET['gender'] ?? null,
    'status' => $_GET['status'] ?? null,
    'search' => $_GET['search'] ?? null
];
$data = $this->students->listStudents($current['school_id'], $current['AcademicYearID'] ?? null, $filters);
```

### Recommended Fix
```php
// Create a Validator class
class InputValidator {
    public static function validateStudentFilters(array $input): array {
        $rules = [
            'class_id' => 'integer|min:1',
            'section_id' => 'integer|min:1', 
            'gender' => 'string|in:M,F,O',
            'status' => 'string|in:active,inactive',
            'search' => 'string|max:255'
        ];
        
        // Implement validation logic
        return $validated;
    }
}

// In controller
$filters = InputValidator::validateStudentFilters($_GET);
```

## 4. SQL Injection Prevention (HIGH)

### Current Risk Areas
- Dynamic query construction
- User input directly concatenated to SQL

### Example Risk
```php
// In some model methods, potential for dynamic SQL construction
$query = "SELECT * FROM students WHERE " . $whereClause;
```

### Prevention Measures
1. Always use prepared statements
2. Validate input types before using in queries
3. Use parameterized queries for all user input
4. Implement query builder with automatic escaping

```php
// Safe approach
$query = "SELECT * FROM students WHERE class_id = ? AND status = ?";
$stmt = $this->conn->prepare($query);
$stmt->execute([$classId, $status]);
```

## 5. Authentication Header Handling (MEDIUM)

### Current Implementation Analysis
The AuthMiddleware does a good job handling various header formats, but could be improved:

```php
// Good: Handles multiple header formats
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders() ?: [];
    foreach ($allHeaders as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}
```

### Recommendations
- Log failed authentication attempts
- Implement rate limiting for failed attempts
- Add IP-based blocking for suspicious activity
- Enhance token validation with additional checks

## 6. Frontend Token Storage (MEDIUM)

### Current Implementation
```typescript
// localStorage usage for token storage
setToken(token: string, key: string = 'token') {
    localStorage.setItem(key, token);
}
```

### Security Concerns
- Accessible via JavaScript (XSS vulnerability)
- Persists across browser sessions
- No secure flag equivalent

### Recommended Improvements
1. Consider httpOnly cookies for token storage
2. Implement token expiry checks on frontend
3. Add CSRF protection for cookie-based tokens
4. Use sessionStorage for shorter-lived tokens

```typescript
// Improved approach with expiry
setToken(token: string, expiryMinutes: number = 60) {
    const expiry = new Date();
    expiry.setMinutes(expiry.getMinutes() + expiryMinutes);
    
    const tokenData = {
        token: token,
        expiry: expiry.toISOString()
    };
    
    sessionStorage.setItem('authToken', JSON.stringify(tokenData));
}

getToken(): string | null {
    const stored = sessionStorage.getItem('authToken');
    if (!stored) return null;
    
    const tokenData = JSON.parse(stored);
    if (new Date() > new Date(tokenData.expiry)) {
        sessionStorage.removeItem('authToken');
        return null;
    }
    
    return tokenData.token;
}
```

## Implementation Priority

1. **Immediate**: Fix JWT secret key configuration
2. **Week 1**: Implement input validation framework  
3. **Week 2**: Refactor global variable usage
4. **Week 3**: Audit and fix SQL injection vulnerabilities
5. **Week 4**: Enhance authentication security measures