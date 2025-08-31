<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\StudentModel;
use SchoolLive\Middleware\AuthMiddleware;

class StudentController {
    private StudentModel $students;

    public function __construct() {
        $this->students = new StudentModel();
        header('Content-Type: application/json');
    }

    public function list($params = []) {
        $current = AuthMiddleware::getCurrentUser();
        if (!$current) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); return; }
        $filters = [
            'class_id' => $_GET['class_id'] ?? null,
            'section_id' => $_GET['section_id'] ?? null,
            'gender' => $_GET['gender'] ?? null,
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        $data = $this->students->listStudents($current['school_id'], $current['AcademicYearID'] ?? null, $filters);
        echo json_encode(['success'=>true,'message'=>'Students retrieved','data'=>$data]);
    }

    public function get($params = []) {
        if (!isset($params['id'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Student ID required']); return; }
        $current = AuthMiddleware::getCurrentUser();
        $stu = $this->students->getStudent($params['id'], $current['school_id']);
        if (!$stu) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Student not found']); return; }
        echo json_encode(['success'=>true,'message'=>'Student retrieved','data'=>$stu]);
    }

    public function create($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); return; }
        $current = AuthMiddleware::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $required = ['StudentName','Gender','DOB','SectionID'];
        foreach ($required as $f) {
            if (empty($input[$f])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>"$f is required"]); return; }
        }
        $input['SchoolID'] = $current['school_id'];
        $input['AcademicYearID'] = $current['AcademicYearID'] ?? 1;
        $input['UserID'] = $input['UserID'] ?? 0; // Could be created separately
        $input['AdmissionDate'] = $input['AdmissionDate'] ?? date('Y-m-d H:i:s');
        $input['Status'] = $input['Status'] ?? 'Active';
        $input['CreatedBy'] = $current['username'] ?? 'System';
        $id = $this->students->createStudent($input);
        if ($id) {
            $stu = $this->students->getStudent($id, $current['school_id']);
            echo json_encode(['success'=>true,'message'=>'Student created','data'=>$stu]);
        } else {
            http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to create student']);
        }
    }

    public function update($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); return; }
        if (!isset($params['id'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Student ID required']); return; }
        $current = AuthMiddleware::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $input['UpdatedBy'] = $current['username'] ?? 'System';
        $exists = $this->students->getStudent($params['id'], $current['school_id']);
        if (!$exists) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Student not found']); return; }
        if ($this->students->updateStudent($params['id'], $current['school_id'], $input)) {
            $stu = $this->students->getStudent($params['id'], $current['school_id']);
            echo json_encode(['success'=>true,'message'=>'Student updated','data'=>$stu]);
        } else {
            http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to update student']);
        }
    }

    public function delete($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); return; }
        if (!isset($params['id'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Student ID required']); return; }
        $current = AuthMiddleware::getCurrentUser();
        $exists = $this->students->getStudent($params['id'], $current['school_id']);
        if (!$exists) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Student not found']); return; }
        if ($this->students->deleteStudent($params['id'], $current['school_id'])) {
            echo json_encode(['success'=>true,'message'=>'Student deleted']);
        } else { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to delete student']); }
    }
}
