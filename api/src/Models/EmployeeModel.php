<?php
namespace SchoolLive\Models;

use PDO;

class EmployeeModel extends Model {
    protected $table = 'Tx_Employees';

    public function listEmployees($schoolId, $academicYearId = null, $filters = []) {
        $sql = "SELECT e.EmployeeID, e.EmployeeName, e.DOB, e.Gender, e.JoiningDate, e.Salary, e.Status, r.RoleName, u.Username
            FROM Tx_Employees e
            LEFT JOIN Tm_Roles r ON e.RoleID = r.RoleID
            LEFT JOIN Tx_Users u ON e.UserID = u.UserID
            WHERE e.SchoolID = :school";
        if ($academicYearId) {
            $sql .= " AND e.AcademicYearID = :ay";
        }
        if (!empty($filters['role_id'])) {
            $sql .= " AND e.RoleID = :role_id";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.Status = :status";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (e.EmployeeName LIKE :search OR r.RoleName LIKE :search)";
        }
        $sql .= " ORDER BY e.EmployeeName";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        if (!empty($filters['role_id'])) $stmt->bindValue(':role_id', $filters['role_id'], PDO::PARAM_INT);
        if (!empty($filters['status'])) $stmt->bindValue(':status', $filters['status']);
        if (!empty($filters['search'])) $stmt->bindValue(':search', '%' . $filters['search'] . '%');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEmployee($id, $schoolId) {
        $sql = "SELECT e.*, r.RoleName, u.Username FROM Tx_Employees e
            LEFT JOIN Tm_Roles r ON e.RoleID = r.RoleID
            LEFT JOIN Tx_Users u ON e.UserID = u.UserID
            WHERE e.EmployeeID = :id AND e.SchoolID = :school LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createEmployee($data) {
        $data['CreatedAt'] = date('Y-m-d H:i:s');
        if (empty($data['EmployeeName']) && !empty($data['FirstName'])) {
            $parts = [];
            foreach (['FirstName','MiddleName','LastName'] as $n) { if (!empty($data[$n])) { $parts[] = trim($data[$n]); } }
            if (!empty($parts)) { $data['EmployeeName'] = trim(implode(' ', $parts)); }
        }

        $fields = ['EmployeeName','FirstName','MiddleName','LastName','ContactNumber','EmailID','Gender','DOB','SchoolID','RoleID','UserID','AcademicYearID','JoiningDate','Salary','Subjects','Status','CreatedAt','CreatedBy'];
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $sql = 'INSERT INTO Tx_Employees (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->conn->prepare($sql);
        foreach ($fields as $f) {
            $stmt->bindValue(':' . $f, $data[$f] ?? null);
        }
        if ($stmt->execute()) return $this->conn->lastInsertId();
        return false;
    }

    public function updateEmployee($id, $schoolId, $data) {
        $allowed = ['EmployeeName','FirstName','MiddleName','LastName','ContactNumber','EmailID','Gender','DOB','RoleID','JoiningDate','Salary','Subjects','Status','UpdatedBy'];
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
        $sql = 'UPDATE Tx_Employees SET ' . implode(', ', $set) . ' WHERE EmployeeID = :id AND SchoolID = :school';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteEmployee($id, $schoolId) {
        $sql = 'DELETE FROM Tx_Employees WHERE EmployeeID = :id AND SchoolID = :school';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
