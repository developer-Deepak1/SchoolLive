<?php
namespace SchoolLive\Core;

class Response {
    private static bool $headerSent = false;

    private static function ensureHeader(): void {
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
