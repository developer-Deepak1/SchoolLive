<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\StudentModel;
use SchoolLive\Models\UserModel;
use PDO;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;

class StudentController extends BaseController {
    private StudentModel $students;
    private UserModel $users;
    
    /**
     * Build a normalized user creation payload from mixed input plus overrides.
     * Accepts either camel/pascal case (FirstName) or snake case (first_name) for name parts.
     */
    private function buildUserPayload(array $input, array $overrides = []): array {
        $first = $input['FirstName'] ?? $input['first_name'] ?? null;
        $middle = $input['MiddleName'] ?? $input['middle_name'] ?? null;
        $last = $input['LastName'] ?? $input['last_name'] ?? null;
        $base = array_filter([
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
        ], function($v){ return $v !== null && $v !== ''; });
        // Merge overrides (overrides take precedence)
        return array_merge($base, $overrides);
    }

    public function __construct() {
    parent::__construct();
    $this->students = new StudentModel();
    $this->users = new UserModel();
    }

    public function list($params = []) {
    $current = $this->currentUser(); if(!$current) return;
        $filters = [
            'class_id' => $_GET['class_id'] ?? null,
            'section_id' => $_GET['section_id'] ?? null,
            'gender' => $_GET['gender'] ?? null,
            'status' => $_GET['status'] ?? null,
            'is_active' => isset($_GET['is_active']) ? $_GET['is_active'] : null,
            'search' => $_GET['search'] ?? null
        ];
        $data = $this->students->listStudents($current['school_id'], $current['AcademicYearID'] ?? null, $filters);
    $this->ok('Students retrieved', $data);
    }

    public function get($params = []) {
    $id = $this->requireKey($params,'id','Student ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $stu = $this->students->getStudent($id, $current['school_id']);
    if (!$stu) { $this->fail('Student not found',404); return; }
    $this->ok('Student retrieved', $stu);
    }

    public function create($params = []) {
    if (!$this->requireMethod('POST')) return;
    $current = $this->currentUser(); if(!$current) return;
    $input = $this->input();
        $required = ['StudentName','Gender','DOB','SectionID'];
        foreach ($required as $f) {
            if (empty($input[$f])) { $this->fail("$f is required",400); return; }
        }
        // If name parts missing, attempt to split StudentName into First/Middle/Last
        if (empty($input['FirstName']) && !empty($input['StudentName'])) {
            $parts = preg_split('/\s+/', trim($input['StudentName']));
            if ($parts) {
                $input['FirstName'] = $parts[0];
                if (count($parts) > 2) {
                    $input['MiddleName'] = implode(' ', array_slice($parts,1,-1));
                    $input['LastName'] = end($parts);
                } elseif (count($parts) === 2) {
                    $input['LastName'] = $parts[1];
                }
            }
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
            $this->ok('Student created', $stu);
        } else {
            $this->fail('Failed to create student',500);
        }
    }

    public function update($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','Student ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $input['UpdatedBy'] = $current['username'] ?? 'System';
        $exists = $this->students->getStudent($id, $current['school_id']);
        if (!$exists) { $this->fail('Student not found',404); return; }
        if ($this->students->updateStudent($id, $current['school_id'], $input)) {
            $stu = $this->students->getStudent($id, $current['school_id']);
            $this->ok('Student updated', $stu);
        } else {
            $this->fail('Failed to update student',500);
        }
    }

    public function delete($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','Student ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $exists = $this->students->getStudent($id, $current['school_id']);
        if (!$exists) { $this->fail('Student not found',404); return; }
        if ($this->students->deleteStudent($id, $current['school_id'])) {
            $this->ok('Student deleted');
    } else { $this->fail('Failed to delete student',500); }
    }

    /**
     * Admission endpoint: creates a user account and linked student record in a single request.
     * POST /students/admission
     * Body: { FirstName, MiddleName?, LastName?, Gender, DOB, SectionID, FatherName?, MotherName?, FatherContactNumber?, MotherContactNumber? }
     * Builds StudentName = FirstName [MiddleName] [LastName].
     * Generates username = schoolId + userId (after insert) and returns credentials (username/password same for first login).
     */
    public function admission($params = []) {
    if (!$this->requireMethod('POST')) return;

    $current = $this->currentUser(); if(!$current) return;
    $input = $this->input();
        $required = ['FirstName', 'Gender', 'DOB', 'SectionID'];
        foreach ($required as $f) {
            if (empty($input[$f])) { $this->fail("$f is required",400); return; }
        }

        // Compose student name
        $nameParts = [];
        foreach (['FirstName', 'MiddleName', 'LastName'] as $p) {
            if (!empty($input[$p])) { $nameParts[] = trim($input[$p]); }
        }
    $studentName = trim(implode(' ', $nameParts));
    if ($studentName === '') { $this->fail('Valid name required',400); return; }

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

            $userData = $this->buildUserPayload($input, [
                'username' => $provisionalUsername,
                'password' => $provisionalPassword,
                'role_id' => $studentRoleId,
                'school_id' => $schoolId,
                'created_by' => $current['username'] ?? 'System'
            ]);

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
                'FirstName' => $input['FirstName'] ?? null,
                'MiddleName' => $input['MiddleName'] ?? null,
                'LastName' => $input['LastName'] ?? null,
                'ContactNumber' => $input['ContactNumber'] ?? null,
                'EmailID' => $input['EmailID'] ?? null,
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
                $this->ok('Admission successful', [
                    'student' => $student,
                    'credentials' => ['username' => $finalUsername, 'password' => $finalPasswordPlain]
                ]);
            return;
        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            $this->fail('Server error: ' . $e->getMessage(),500);
            return;
        }
    }
    public function getStudentId(){
        if (!$this->requireMethod('GET')) return;
        $current = $this->currentUser(); if(!$current) return;
        $userId = $current['id'];
        $student = $this->students->getStudentByUserId($userId, $current['school_id']);
        if (!$student) { $this->fail('Student not found',404); return; }
        $this->ok('Student ID retrieved', ['student_id' => $student['StudentID']]);
        return;
    }
}
