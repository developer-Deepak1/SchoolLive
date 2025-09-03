<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\EmployeeModel;
use SchoolLive\Models\UserModel;
use PDO;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;

class EmployeesController extends BaseController {
    private EmployeeModel $employees;
    private UserModel $users;

    public function __construct() {
        parent::__construct();
        $this->employees = new EmployeeModel();
        $this->users = new UserModel();
    }

    public function list($params = []) {
        $current = $this->currentUser(); if(!$current) return;
        $filters = [
            'role_id' => $_GET['role_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'is_active' => isset($_GET['is_active']) ? $_GET['is_active'] : null,
            'search' => $_GET['search'] ?? null
        ];
        $data = $this->employees->listEmployees($current['school_id'], $current['AcademicYearID'] ?? null, $filters);
        $this->ok('Employees retrieved', $data);
    }

    public function get($params = []) {
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $emp = $this->employees->getEmployee($id, $current['school_id']);
        if (!$emp) { $this->fail('Employee not found',404); return; }
        $this->ok('Employee retrieved', $emp);
    }

    public function create($params = []) {
        if (!$this->requireMethod('POST')) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $required = ['EmployeeName','Gender','JoiningDate'];
        foreach ($required as $f) { if (empty($input[$f])) { $this->fail("$f is required",400); return; } }
        $input['SchoolID'] = $current['school_id'];
        $input['AcademicYearID'] = $current['AcademicYearID'] ?? 1;
        $input['UserID'] = $input['UserID'] ?? 0;
        $input['CreatedBy'] = $current['username'] ?? 'System';
        $id = $this->employees->createEmployee($input);
        if ($id) {
            $emp = $this->employees->getEmployee($id, $current['school_id']);
            $this->ok('Employee created', $emp);
        } else {
            $this->fail('Failed to create employee',500);
        }
    }

    public function update($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $input['UpdatedBy'] = $current['username'] ?? 'System';
        $exists = $this->employees->getEmployee($id, $current['school_id']);
        if (!$exists) { $this->fail('Employee not found',404); return; }
        if ($this->employees->updateEmployee($id, $current['school_id'], $input)) {
            $emp = $this->employees->getEmployee($id, $current['school_id']);
            $this->ok('Employee updated', $emp);
        } else {
            $this->fail('Failed to update employee',500);
        }
    }

    public function delete($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Employee ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $exists = $this->employees->getEmployee($id, $current['school_id']);
        if (!$exists) { $this->fail('Employee not found',404); return; }
        if ($this->employees->deleteEmployee($id, $current['school_id'])) {
            $this->ok('Employee deleted');
        } else { $this->fail('Failed to delete employee',500); }
    }
}
