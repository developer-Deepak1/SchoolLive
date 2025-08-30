<?php
namespace SchoolLive\Models;

use PDO;

class AcademicModel extends Model {
    protected $table = 'academic_years';

    // Academic Years Methods
    public function createAcademicYear($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }

    public function updateAcademicYear($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->update($id, $data);
    }

    public function getCurrentAcademicYear() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_current = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function setCurrentAcademicYear($id) {
        // First, unset all current academic years
        $query1 = "UPDATE " . $this->table . " SET is_current = 0";
        $stmt1 = $this->conn->prepare($query1);
        $stmt1->execute();

        // Then set the specified year as current
        $query2 = "UPDATE " . $this->table . " SET is_current = 1, updated_at = NOW() WHERE id = :id";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':id', $id);
        return $stmt2->execute();
    }

    // Classes Methods
    public function createClass($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO classes (class_name, section, academic_year_id, teacher_id, max_students, description, created_at, updated_at) 
                  VALUES (:class_name, :section, :academic_year_id, :teacher_id, :max_students, :description, :created_at, :updated_at)";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $value);
        }
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateClass($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $fields = array_keys($data);
        $setClause = array_map(function($field) { return $field . ' = :' . $field; }, $fields);
        
        $query = "UPDATE classes SET " . implode(', ', $setClause) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $value);
        }
        
        return $stmt->execute();
    }

    public function getAllClasses($academic_year_id = null) {
        $query = "SELECT c.*, ay.year_name, u.first_name as teacher_first_name, u.last_name as teacher_last_name 
                  FROM classes c
                  LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                  LEFT JOIN users u ON c.teacher_id = u.id";
        
        if ($academic_year_id) {
            $query .= " WHERE c.academic_year_id = :academic_year_id";
        }
        
        $query .= " ORDER BY c.class_name, c.section";
        
        $stmt = $this->conn->prepare($query);
        
        if ($academic_year_id) {
            $stmt->bindParam(':academic_year_id', $academic_year_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getClassById($id) {
        $query = "SELECT c.*, ay.year_name, u.first_name as teacher_first_name, u.last_name as teacher_last_name 
                  FROM classes c
                  LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                  LEFT JOIN users u ON c.teacher_id = u.id
                  WHERE c.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function deleteClass($id) {
        $query = "DELETE FROM classes WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getClassesByTeacher($teacher_id) {
        $query = "SELECT c.*, ay.year_name FROM classes c
                  LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                  WHERE c.teacher_id = :teacher_id
                  ORDER BY c.class_name, c.section";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
