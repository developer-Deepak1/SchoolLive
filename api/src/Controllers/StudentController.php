<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\StudentModel;
use SchoolLive\Models\UserModel;
use PDO;
use SchoolLive\Middleware\AuthMiddleware;

class StudentController {
    private StudentModel $students;
    private UserModel $users;

    public function __construct() {
    $this->students = new StudentModel();
    $this->users = new UserModel();
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

    /**
     * Admission endpoint: creates a user account and linked student record in a single request.
     * POST /students/admission
     * Body: { FirstName, MiddleName?, LastName?, Gender, DOB, SectionID, FatherName?, MotherName?, FatherContactNumber?, MotherContactNumber? }
     * Builds StudentName = FirstName [MiddleName] [LastName].
     * Generates username = schoolId + userId (after insert) and returns credentials (username/password same for first login).
     */
    public function admission($params = []) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $current = AuthMiddleware::getCurrentUser();
        if (!$current) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $required = ['FirstName', 'Gender', 'DOB', 'SectionID'];
        foreach ($required as $f) {
            if (empty($input[$f])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$f is required"]);
                return;
            }
        }

        // Compose student name
        $nameParts = [];
        foreach (['FirstName', 'MiddleName', 'LastName'] as $p) {
            if (!empty($input[$p])) { $nameParts[] = trim($input[$p]); }
        }
        $studentName = trim(implode(' ', $nameParts));
        if ($studentName === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Valid name required']); return; }

        $pdo = $this->students->getPdo();
        $schoolId = (int)$current['school_id'];
        $academicYearId = $input['AcademicYearID'] ?? ($current['AcademicYearID'] ?? 1);

        try {
            // begin transaction
            $pdo->beginTransaction();

            // resolve numeric role id for 'student'
            $roleStmt = $pdo->prepare("SELECT RoleID FROM Tm_Roles WHERE RoleName = :r LIMIT 1");
            $roleStmt->execute([':r' => 'student']);
            $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$roleRow) { throw new \Exception('Student role not configured'); }
            $studentRoleId = (int)$roleRow['RoleID'];

            // create user with provisional username and password
            $provisionalUsername = uniqid('stu_');
            $provisionalPassword = bin2hex(random_bytes(4)); // short random temp password

            $userData = [
                'first_name' => $input['FirstName'],
                'middle_name' => $input['MiddleName'] ?? null,
                'last_name' => $input['LastName'] ?? null,
                'username' => $provisionalUsername,
                'password' => $provisionalPassword,
                'role_id' => $studentRoleId,
                'school_id' => $schoolId,
                'created_by' => $current['username'] ?? 'System'
            ];

            $userId = $this->users->createUser($userData);
            if (!$userId) { throw new \Exception('Failed to create user'); }

            // final credentials: username = schoolId + userId, password = same
            $finalUsername = (string)$schoolId . (string)$userId;
            $finalPasswordPlain = $finalUsername;
            $finalPasswordHash = password_hash($finalPasswordPlain, PASSWORD_DEFAULT);

            $upd = $pdo->prepare("UPDATE Tx_Users SET Username = :u, PasswordHash = :p, IsFirstLogin = 1 WHERE UserID = :id");
            $upd->execute([':u' => $finalUsername, ':p' => $finalPasswordHash, ':id' => $userId]);

            // create student record
            $studentData = [
                'StudentName' => $studentName,
                'Gender' => $input['Gender'],
                'DOB' => $input['DOB'],
                'SectionID' => $input['SectionID'],
                'FatherName' => $input['FatherName'] ?? null,
                'FatherContactNumber' => $input['FatherContactNumber'] ?? null,
                'MotherName' => $input['MotherName'] ?? null,
                'MotherContactNumber' => $input['MotherContactNumber'] ?? null,
                'AdmissionDate' => $input['AdmissionDate'] ?? date('Y-m-d'),
                'Status' => 'Active',
                'SchoolID' => $schoolId,
                'AcademicYearID' => $academicYearId,
                'UserID' => $userId,
                'CreatedBy' => $current['username'] ?? 'System'
            ];

            $studentId = $this->students->createStudent($studentData);
            if (!$studentId) { throw new \Exception('Failed to create student'); }

            $pdo->commit();

            $student = $this->students->getStudent($studentId, $schoolId);
            echo json_encode(['success' => true, 'message' => 'Admission successful', 'data' => [
                'student' => $student,
                'credentials' => ['username' => $finalUsername, 'password' => $finalPasswordPlain]
            ]]);
            return;
        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
            return;
        }
    }
}
