<?php
namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\SchoolModel;

class SchoolsController extends BaseController {
    protected $schoolModel;

    public function __construct() {
        parent::__construct();
        $this->schoolModel = new SchoolModel();
    }

    // GET /api/schools/{id}
    public function get($params = []) {
        $id = $params['id'] ?? null;
        if (!$id) return $this->fail('Missing school id', 400);

        $row = $this->schoolModel->getSchoolById($id);
        if (!$row) return $this->fail('School not found', 404);
        return $this->ok('School retrieved', $row);
    }

    // GET /api/schools/by-user/{userId}
    public function getByUser($params = []) {
        $userId = $params['userId'] ?? null;
        if (!$userId) return $this->fail('Missing user id', 400);

        $row = $this->schoolModel->getSchoolByUserId($userId);
        if (!$row) return $this->fail('School not found for user', 404);
        return $this->ok('School retrieved', $row);
    }
}
