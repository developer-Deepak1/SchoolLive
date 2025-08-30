<?php
namespace SchoolLive\Models;

use PDO;

class StudentModel extends Model {
    protected $table = 'Tx_Students';

    public function createStudent($data) {
        // Map old field names to new ones
        $mappedData = [];
        if (isset($data['first_name']) && isset($data['last_name'])) {
            $mappedData['StudentName'] = trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']);
        }
        if (isset($data['student_name'])) $mappedData['StudentName'] = $data['student_name'];
        if (isset($data['gender'])) $mappedData['Gender'] = $data['gender'];
        if (isset($data['dob'])) $mappedData['DOB'] = $data['dob'];
        if (isset($data['school_id'])) $mappedData['SchoolID'] = $data['school_id'];
        if (isset($data['class_id'])) $mappedData['ClassID'] = $data['class_id'];
        if (isset($data['user_id'])) $mappedData['UserID'] = $data['user_id'];
        if (isset($data['father_name'])) $mappedData['FatherName'] = $data['father_name'];
        if (isset($data['father_contact'])) $mappedData['FatherContactNumber'] = $data['father_contact'];
        if (isset($data['mother_name'])) $mappedData['MotherName'] = $data['mother_name'];
        if (isset($data['mother_contact'])) $mappedData['MotherContactNumber'] = $data['mother_contact'];
        if (isset($data['admission_date'])) $mappedData['AdmissionDate'] = $data['admission_date'];
        
        $mappedData['CreatedAt'] = date('Y-m-d H:i:s');
        $mappedData['CreatedBy'] = $data['created_by'] ?? 'System';
        
        return $this->create($mappedData);
    }

    public function updateStudent($id, $data) {
        // Map old field names to new ones
        $mappedData = [];
        if (isset($data['first_name']) && isset($data['last_name'])) {
            $mappedData['StudentName'] = trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']);
        }
        if (isset($data['student_name'])) $mappedData['StudentName'] = $data['student_name'];
        if (isset($data['gender'])) $mappedData['Gender'] = $data['gender'];
        if (isset($data['dob'])) $mappedData['DOB'] = $data['dob'];
        if (isset($data['class_id'])) $mappedData['ClassID'] = $data['class_id'];
        if (isset($data['father_name'])) $mappedData['FatherName'] = $data['father_name'];
        if (isset($data['father_contact'])) $mappedData['FatherContactNumber'] = $data['father_contact'];
        if (isset($data['mother_name'])) $mappedData['MotherName'] = $data['mother_name'];
        if (isset($data['mother_contact'])) $mappedData['MotherContactNumber'] = $data['mother_contact'];
        if (isset($data['status'])) $mappedData['Status'] = $data['status'];
        
        $mappedData['UpdatedAt'] = date('Y-m-d H:i:s');
        $mappedData['UpdatedBy'] = $data['updated_by'] ?? 'System';
        
        return $this->update($id, $mappedData);
    }

    public function findByStudentId($student_id) {
        // Since we changed the table structure, we'll search by StudentID (primary key)
        return $this->findById($student_id);
    }

    public function getStudentsByClass($class_id) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID 
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  WHERE s.ClassID = :class_id AND s.Status = 'Active'
                  ORDER BY s.StudentName";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentsBySchool($school_id) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID 
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  WHERE s.SchoolID = :school_id AND s.Status = 'Active'
                  ORDER BY c.ClassName, s.StudentName";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $school_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentsWithClass($limit = null, $offset = null) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID,
                         sc.SchoolName
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  LEFT JOIN Tm_Schools sc ON s.SchoolID = sc.SchoolID
                  WHERE s.Status = 'Active'
                  ORDER BY s.CreatedAt DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit";
            if ($offset) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            if ($offset) {
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function searchStudents($searchTerm, $schoolId = null) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID,
                         sc.SchoolName
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  LEFT JOIN Tm_Schools sc ON s.SchoolID = sc.SchoolID
                  WHERE s.Status = 'Active' AND (
                    s.StudentName LIKE :search 
                    OR s.FatherName LIKE :search 
                    OR s.MotherName LIKE :search 
                    OR u.Username LIKE :search 
                    OR u.EmailID LIKE :search
                  )";
        
        if ($schoolId) {
            $query .= " AND s.SchoolID = :school_id";
        }
        
        $query .= " ORDER BY s.StudentName";
        
        $stmt = $this->conn->prepare($query);
        $searchParam = '%' . $searchTerm . '%';
        $stmt->bindParam(':search', $searchParam);
        
        if ($schoolId) {
            $stmt->bindParam(':school_id', $schoolId);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveStudents($schoolId = null) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID,
                         sc.SchoolName
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  LEFT JOIN Tm_Schools sc ON s.SchoolID = sc.SchoolID
                  WHERE s.Status = 'Active'";
        
        if ($schoolId) {
            $query .= " AND s.SchoolID = :school_id";
        }
        
        $query .= " ORDER BY s.StudentName";
        
        $stmt = $this->conn->prepare($query);
        
        if ($schoolId) {
            $stmt->bindParam(':school_id', $schoolId);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentAttendance($studentId, $startDate = null, $endDate = null) {
        $query = "SELECT sa.*, s.StudentName, c.ClassName 
                  FROM Tx_Students_Attendance sa
                  LEFT JOIN " . $this->table . " s ON sa.StudentID = s.StudentID
                  LEFT JOIN Tx_Classes c ON sa.ClassID = c.ClassID
                  WHERE sa.StudentID = :student_id";
        
        if ($startDate && $endDate) {
            $query .= " AND sa.Date BETWEEN :start_date AND :end_date";
        }
        
        $query .= " ORDER BY sa.Date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $studentId);
        
        if ($startDate && $endDate) {
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudentsByClassAndSchool($classId, $schoolId) {
        $query = "SELECT s.*, u.Username, u.EmailID 
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  WHERE s.ClassID = :class_id AND s.SchoolID = :school_id AND s.Status = 'Active'
                  ORDER BY s.StudentName";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $classId);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Override findAll to include joins
    public function findAll($limit = null, $offset = null) {
        return $this->getStudentsWithClass($limit, $offset);
    }

    // Override findById to include joins
    public function findById($id) {
        $query = "SELECT s.*, c.ClassName, c.ClassDisplayName, u.Username, u.EmailID,
                         sc.SchoolName
                  FROM " . $this->table . " s 
                  LEFT JOIN Tx_Classes c ON s.ClassID = c.ClassID 
                  LEFT JOIN Tx_Users u ON s.UserID = u.UserID
                  LEFT JOIN Tm_Schools sc ON s.SchoolID = sc.SchoolID
                  WHERE s.StudentID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
}
