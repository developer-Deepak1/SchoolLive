<?php
namespace SchoolLive\Core;

class Request {
    private static array $jsonCache = [];

    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public static function is(string $method): bool { return self::method() === strtoupper($method); }

    public static function json(bool $forceObjectToArray = true): array {
        if (!empty(self::$jsonCache)) { return self::$jsonCache; }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') { self::$jsonCache = []; return []; }
        $data = json_decode($raw, $forceObjectToArray);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { self::$jsonCache = []; return []; }
        self::$jsonCache = $data;
        return $data;
    }

    public static function query(string $key, $default = null) { return $_GET[$key] ?? $default; }
    public static function param(array $routeParams, string $key, $default = null) { return $routeParams[$key] ?? $_GET[$key] ?? $default; }
}
