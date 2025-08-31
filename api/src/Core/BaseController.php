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
}
