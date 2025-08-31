<?php
namespace SchoolLive\Models;

use PDO;

class StudentModel extends Model {
    protected $table = 'Tx_Students';

    public function listStudents($schoolId, $academicYearId = null, $filters = []) {
        $sql = "SELECT s.StudentID, s.StudentName, s.Gender, s.DOB, s.SectionID, s.UserID, s.FatherName, s.MotherName, s.AdmissionDate, s.Status,
                       sec.SectionName, c.ClassName, c.ClassID
                FROM Tx_Students s
                LEFT JOIN Tx_Sections sec ON s.SectionID = sec.SectionID
                LEFT JOIN Tx_Classes c ON sec.ClassID = c.ClassID
                WHERE s.SchoolID = :school";
        if ($academicYearId) {
            $sql .= " AND s.AcademicYearID = :ay";
        }
        if (!empty($filters['class_id'])) {
            $sql .= " AND c.ClassID = :class_id";
        }
        if (!empty($filters['section_id'])) {
            $sql .= " AND s.SectionID = :section_id";
        }
        if (!empty($filters['gender'])) {
            $sql .= " AND s.Gender = :gender";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND s.Status = :status";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (s.StudentName LIKE :search OR s.FatherName LIKE :search OR s.MotherName LIKE :search)";
        }
        $sql .= " ORDER BY c.ClassName, sec.SectionName, s.StudentName";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) {
            $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        }
        if (!empty($filters['class_id'])) {
            $stmt->bindValue(':class_id', $filters['class_id'], PDO::PARAM_INT);
        }
        if (!empty($filters['section_id'])) {
            $stmt->bindValue(':section_id', $filters['section_id'], PDO::PARAM_INT);
        }
        if (!empty($filters['gender'])) {
            $stmt->bindValue(':gender', $filters['gender']);
        }
        if (!empty($filters['status'])) {
            $stmt->bindValue(':status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $stmt->bindValue(':search', '%' . $filters['search'] . '%');
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStudent($id, $schoolId) {
        $sql = "SELECT s.*, sec.SectionName, c.ClassName, c.ClassID
                FROM Tx_Students s
                LEFT JOIN Tx_Sections sec ON s.SectionID = sec.SectionID
                LEFT JOIN Tx_Classes c ON sec.ClassID = c.ClassID
                WHERE s.StudentID = :id AND s.SchoolID = :school LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createStudent($data) {
        $data['CreatedAt'] = date('Y-m-d H:i:s');
        $fields = [
            'StudentName','Gender','DOB','SchoolID','SectionID','UserID','AcademicYearID','FatherName','FatherContactNumber','MotherName','MotherContactNumber','AdmissionDate','Status','CreatedAt','CreatedBy'
        ];
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $sql = 'INSERT INTO Tx_Students (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->conn->prepare($sql);
        foreach ($fields as $f) {
            $stmt->bindValue(':' . $f, $data[$f] ?? null);
        }
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateStudent($id, $schoolId, $data) {
        $allowed = ['StudentName','Gender','DOB','SectionID','FatherName','FatherContactNumber','MotherName','MotherContactNumber','Status','UpdatedBy'];
        $set = [];
        $params = [':id' => $id, ':school' => $schoolId];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[] = $field . ' = :' . $field;
                $params[':' . $field] = $data[$field];
            }
        }
        if (empty($set)) return false;
        $params[':UpdatedAt'] = date('Y-m-d H:i:s');
        $set[] = 'UpdatedAt = :UpdatedAt';
        $sql = 'UPDATE Tx_Students SET ' . implode(', ', $set) . ' WHERE StudentID = :id AND SchoolID = :school';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteStudent($id, $schoolId) {
        $sql = 'DELETE FROM Tx_Students WHERE StudentID = :id AND SchoolID = :school';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
