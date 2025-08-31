<?php
namespace SchoolLive\Core;

/**
 * Lightweight Request helper to centralize common HTTP input concerns.
 */
class Request {
    private static array $jsonCache = [];

    /** Return current request method (uppercase). */
    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

    /** Convenience: compare method. */
    public static function is(string $method): bool { return self::method() === strtoupper($method); }

    /** Get decoded JSON body (memoized). Returns array, or empty array if invalid / empty. */
    public static function json(bool $forceObjectToArray = true): array {
        if (!empty(self::$jsonCache)) { return self::$jsonCache; }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') { self::$jsonCache = []; return []; }
        $data = json_decode($raw, $forceObjectToArray);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { self::$jsonCache = []; return []; }
        self::$jsonCache = $data;
        return $data;
    }

    /** Retrieve query parameter with optional default. */
    public static function query(string $key, $default = null) { return $_GET[$key] ?? $default; }

    /** Simple param extraction from mixed (route) params + query fallback. */
    public static function param(array $routeParams, string $key, $default = null) { return $routeParams[$key] ?? $_GET[$key] ?? $default; }
}

/**
 * Unified JSON response builder.
 */
class Response {
    private static bool $headerSent = false;

    private static function ensureHeader() {
        if (!self::$headerSent) {
            header('Content-Type: application/json');
            self::$headerSent = true;
        }
    }

    public static function json(array $payload, int $status = 200): void {
        self::ensureHeader();
        http_response_code($status);
        echo json_encode($payload);
    }

    public static function success(string $message, $data = null, int $status = 200): void {
        $resp = ['success' => true, 'message' => $message];
        if ($data !== null) { $resp['data'] = $data; }
        self::json($resp, $status);
    }

    public static function error(string $message, int $status, $data = null): void {
        $resp = ['success' => false, 'message' => $message];
        if ($data !== null) { $resp['data'] = $data; }
        self::json($resp, $status);
    }
}

/**
 * Simple validation utilities.
 */
class Validator {
    /** Ensure required fields present & non-empty. Returns array of missing field names. */
    public static function missing(array $data, array $required): array {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) { $missing[] = $field; }
        }
        return $missing;
    }
}

/**
 * Base controller offering common helpers to reduce duplication.
 */
abstract class BaseController {
    public function __construct() {
        // header handled lazily by Response
    }

    protected function requireMethod(string $method): bool {
        if (!Request::is($method)) {
            Response::error('Method not allowed', 405);
            return false;
        }
        return true;
    }

    protected function input(): array { return Request::json(); }

    protected function ensure(array $data, array $required): bool {
        $miss = Validator::missing($data, $required);
        if ($miss) {
            Response::error(ucfirst(str_replace('_',' ', $miss[0])) . ' is required', 400, ['missing' => $miss]);
            return false;
        }
        return true;
    }

    protected function ok(string $message, $data = null, int $status = 200): void { Response::success($message, $data, $status); }
    protected function fail(string $message, int $status, $data = null): void { Response::error($message, $status, $data); }
}
