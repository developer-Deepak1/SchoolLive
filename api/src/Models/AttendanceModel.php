<?php
namespace SchoolLive\Models;

use PDO;
use Exception;

class AttendanceModel extends Model {
    protected $table = 'attendance';

    public function markAttendance($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Check if attendance already exists for this student and date
        $existing = $this->getAttendanceByStudentAndDate($data['student_id'], $data['attendance_date']);
        
        if ($existing) {
            // Update existing attendance
            return $this->updateAttendance($existing['id'], $data);
        } else {
            // Create new attendance record
            return $this->create($data);
        }
    }

    public function updateAttendance($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->update($id, $data);
    }

    public function getAttendanceByStudentAndDate($student_id, $date) {
        $query = "SELECT * FROM " . $this->table . " WHERE student_id = :student_id AND attendance_date = :date LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAttendanceByClass($class_id, $date = null) {
        $query = "SELECT a.*, s.first_name, s.last_name, s.student_id, s.roll_number
                  FROM " . $this->table . " a
                  JOIN students s ON a.student_id = s.id
                  WHERE s.class_id = :class_id";
        
        if ($date) {
            $query .= " AND a.attendance_date = :date";
        }
        
        $query .= " ORDER BY s.roll_number, a.attendance_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        
        if ($date) {
            $stmt->bindParam(':date', $date);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentAttendance($student_id, $start_date = null, $end_date = null) {
        $query = "SELECT * FROM " . $this->table . " WHERE student_id = :student_id";
        
        if ($start_date && $end_date) {
            $query .= " AND attendance_date BETWEEN :start_date AND :end_date";
        }
        
        $query .= " ORDER BY attendance_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        
        if ($start_date && $end_date) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAttendanceReport($class_id, $start_date, $end_date) {
        $query = "SELECT 
                    s.id as student_id,
                    s.first_name,
                    s.last_name,
                    s.student_id,
                    s.roll_number,
                    COUNT(a.id) as total_days,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                    ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                  FROM students s
                  LEFT JOIN " . $this->table . " a ON s.id = a.student_id 
                    AND a.attendance_date BETWEEN :start_date AND :end_date
                  WHERE s.class_id = :class_id
                  GROUP BY s.id
                  ORDER BY s.roll_number";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function bulkMarkAttendance($attendanceData) {
        try {
            $this->conn->beginTransaction();
            
            $success = true;
            foreach ($attendanceData as $data) {
                if (!$this->markAttendance($data)) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
