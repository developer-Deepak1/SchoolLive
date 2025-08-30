<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\AttendanceModel;
use SchoolLive\Middleware\AuthMiddleware;

class AttendanceController {
    private $attendanceModel;

    public function __construct() {
        $this->attendanceModel = new AttendanceModel();
        header('Content-Type: application/json');
    }

    public function getAttendance($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        $class_id = $_GET['class_id'] ?? null;
        $student_id = $_GET['student_id'] ?? null;
        $date = $_GET['date'] ?? null;
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;
        $report = $_GET['report'] ?? null;

        if ($report === 'true' && $class_id && $start_date && $end_date) {
            // Get attendance report
            $attendance = $this->attendanceModel->getAttendanceReport($class_id, $start_date, $end_date);
        } else if ($student_id) {
            // Get attendance for specific student
            $attendance = $this->attendanceModel->getStudentAttendance($student_id, $start_date, $end_date);
        } else if ($class_id) {
            // Get attendance for specific class
            $attendance = $this->attendanceModel->getAttendanceByClass($class_id, $date);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Either class_id or student_id is required']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Attendance retrieved successfully',
            'data' => $attendance
        ]);
    }

    public function markAttendance($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Check if it's bulk attendance marking
        if (isset($input['attendance_records']) && is_array($input['attendance_records'])) {
            return $this->markBulkAttendance($input['attendance_records']);
        }

        // Single attendance marking
        $requiredFields = ['student_id', 'attendance_date', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
                return;
            }
        }

        // Validate status
        $allowedStatuses = ['present', 'absent', 'late', 'excused'];
        if (!in_array($input['status'], $allowedStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)]);
            return;
        }

        // Validate date format
        if (!strtotime($input['attendance_date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
            return;
        }

        // Add teacher ID from current user
        $currentUser = AuthMiddleware::getCurrentUser();
        $input['marked_by'] = $currentUser['id'];

        $result = $this->attendanceModel->markAttendance($input);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully',
                'data' => [
                    'attendance_id' => $result
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
        }
    }

    private function markBulkAttendance($attendanceRecords) {
        $currentUser = AuthMiddleware::getCurrentUser();
        $errors = [];
        $validRecords = [];

        // Validate all records first
        foreach ($attendanceRecords as $index => $record) {
            $recordErrors = [];

            // Check required fields
            $requiredFields = ['student_id', 'attendance_date', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || empty($record[$field])) {
                    $recordErrors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
            }

            // Validate status
            $allowedStatuses = ['present', 'absent', 'late', 'excused'];
            if (isset($record['status']) && !in_array($record['status'], $allowedStatuses)) {
                $recordErrors[] = 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses);
            }

            // Validate date format
            if (isset($record['attendance_date']) && !strtotime($record['attendance_date'])) {
                $recordErrors[] = 'Invalid date format. Use YYYY-MM-DD';
            }

            if (count($recordErrors) > 0) {
                $errors[$index] = $recordErrors;
            } else {
                // Add teacher ID
                $record['marked_by'] = $currentUser['id'];
                $validRecords[] = $record;
            }
        }

        if (count($errors) > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Validation errors in attendance records',
                'errors' => $errors
            ]);
            return;
        }

        $result = $this->attendanceModel->bulkMarkAttendance($validRecords);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Bulk attendance marked successfully',
                'data' => [
                    'records_processed' => count($validRecords)
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark bulk attendance']);
        }
    }

    public function updateAttendance($params = []) {
        if (!AuthMiddleware::requireRole(['admin', 'teacher'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Check if attendance record exists
        $existingAttendance = $this->attendanceModel->findById($params['id']);
        if (!$existingAttendance) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
            return;
        }

        // Validate status if provided
        if (isset($input['status'])) {
            $allowedStatuses = ['present', 'absent', 'late', 'excused'];
            if (!in_array($input['status'], $allowedStatuses)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)]);
                return;
            }
        }

        // Add teacher ID from current user
        $currentUser = AuthMiddleware::getCurrentUser();
        $input['marked_by'] = $currentUser['id'];

        $result = $this->attendanceModel->updateAttendance($params['id'], $input);

        if ($result) {
            $attendance = $this->attendanceModel->findById($params['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data' => [
                    'attendance' => $attendance
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
        }
    }

    public function deleteAttendance($params = []) {
        if (!AuthMiddleware::requireRole(['admin'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
            return;
        }

        // Check if attendance record exists
        $attendance = $this->attendanceModel->findById($params['id']);
        if (!$attendance) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
            return;
        }

        $result = $this->attendanceModel->delete($params['id']);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Attendance record deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete attendance record']);
        }
    }
}
