<?php
namespace SchoolLive\Core;

abstract class BaseController {
    public function __construct() {
        // Shared initialization hook (currently empty). Controllers may safely call parent::__construct().
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

    /**
     * Fetch current authenticated user or fail with 401 if required and missing.
     */
    protected function currentUser(bool $required = true): ?array {
        $user = \SchoolLive\Middleware\AuthMiddleware::getCurrentUser();
        if (!$user && $required) { $this->fail('Unauthorized', 401); return null; }
        return $user;
    }

    /**
     * Require presence of a key in params (e.g. route params). Returns value or null (after sending error response).
     */
    protected function requireKey(array $params, string $key, ?string $label = null) {
        if (!isset($params[$key])) { $this->fail(($label ?? ucfirst($key)) . ' is required', 400); return null; }
        return $params[$key];
    }
}
