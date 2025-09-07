<?php
namespace SchoolLive\Models;

use PDO;

class EmployeeModel extends Model {
    protected $table = 'Tx_Employees';

    public function listEmployees($schoolId, $academicYearId = null, $filters = []) {
        // Compute EmployeeName from name parts to support schemas without the legacy EmployeeName column
    $sql = "SELECT e.EmployeeID, CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName) AS EmployeeName, e.DOB, e.Gender, e.JoiningDate, e.Salary, e.Status, e.IsActive, e.RoleID, r.RoleName, u.Username
            FROM Tx_Employees e
            LEFT JOIN Tm_Roles r ON e.RoleID = r.RoleID
            LEFT JOIN Tx_Users u ON e.UserID = u.UserID
            WHERE e.SchoolID = :school";
            // By default only return active employees unless caller explicitly passes an is_active filter
            if (!isset($filters['is_active'])) {
                $sql .= " AND e.IsActive = 1";
            }
        if ($academicYearId) {
            $sql .= " AND e.AcademicYearID = :ay";
        }
        if (!empty($filters['role_id'])) {
            $sql .= " AND e.RoleID = :role_id";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.Status = :status";
        }
        if (isset($filters['is_active'])) {
            $sql .= " AND e.IsActive = :is_active";
        }
        if (!empty($filters['search'])) {
            // search across concatenated name and role
            $sql .= " AND (CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName) LIKE :search OR r.RoleName LIKE :search)";
        }
        $sql .= " ORDER BY CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        if (!empty($filters['role_id'])) $stmt->bindValue(':role_id', $filters['role_id'], PDO::PARAM_INT);
    if (!empty($filters['status'])) $stmt->bindValue(':status', $filters['status']);
    if (isset($filters['is_active'])) $stmt->bindValue(':is_active', (int)$filters['is_active'], PDO::PARAM_INT);
        if (!empty($filters['search'])) $stmt->bindValue(':search', '%' . $filters['search'] . '%');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEmployee($id, $schoolId) {
    // Build EmployeeName from name parts for consumers
    $sql = "SELECT e.*, CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName) AS EmployeeName, e.IsActive, r.RoleID AS RoleID, r.RoleName, u.Username FROM Tx_Employees e
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

    // include IsActive only if caller provided it; otherwise let DB default apply
    $fields = ['FirstName','MiddleName','LastName','ContactNumber','EmailID','Gender','DOB','SchoolID','RoleID','UserID','AcademicYearID','JoiningDate','FatherName','FatherContactNumber','MotherName','MotherContactNumber','BloodGroup','Salary','Subjects','Status','CreatedAt','CreatedBy'];
    if (array_key_exists('IsActive', $data)) {
        // insert IsActive just after Status
        $pos = array_search('Status', $fields);
        array_splice($fields, $pos + 1, 0, 'IsActive');
    }
    $placeholders = array_map(fn($f) => ':' . $f, $fields);
    $sql = 'INSERT INTO Tx_Employees (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->conn->prepare($sql);
        foreach ($fields as $f) {
            // Avoid binding empty string for nullable UserID
            if ($f === 'UserID' && (!isset($data['UserID']) || $data['UserID'] === '' || $data['UserID'] === 0)) {
                $stmt->bindValue(':' . $f, null);
            } else {
                $stmt->bindValue(':' . $f, $data[$f] ?? null);
            }
        }
        if ($stmt->execute()) return $this->conn->lastInsertId();
        return false;
    }

    public function updateEmployee($id, $schoolId, $data) {
    $allowed = ['FirstName','MiddleName','LastName','ContactNumber','EmailID','Gender','DOB','RoleID','JoiningDate','FatherName','FatherContactNumber','MotherName','MotherContactNumber','BloodGroup','Salary','Subjects','Status','IsActive','UpdatedBy'];
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

    public function deleteEmployee($id, $schoolId, $deletedBy = null) {
        // Soft-delete: set IsActive = 0 on both the employee and the linked user (if any)
        // Do both updates inside a transaction to avoid partial state.
        $pdo = $this->conn;
        try {
            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');
            // Update employee
            $sqlEmp = 'UPDATE Tx_Employees SET IsActive = 0, UpdatedAt = :updated_at';
            $paramsEmp = [':id' => $id, ':school' => $schoolId, ':updated_at' => $now];
            if ($deletedBy) {
                $sqlEmp .= ', UpdatedBy = :updated_by';
                $paramsEmp[':updated_by'] = $deletedBy;
            }
            $sqlEmp .= ' WHERE EmployeeID = :id AND SchoolID = :school';
            $stmtEmp = $pdo->prepare($sqlEmp);
            if (!$stmtEmp->execute($paramsEmp)) {
                $pdo->rollBack();
                return false;
            }

            // Find linked user id for this employee (if any)
            $stmt = $pdo->prepare('SELECT UserID FROM Tx_Employees WHERE EmployeeID = :id AND SchoolID = :school LIMIT 1');
            $stmt->execute([':id' => $id, ':school' => $schoolId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['UserID'])) {
                $userId = $row['UserID'];
                $sqlUser = 'UPDATE Tx_Users SET IsActive = 0, UpdatedAt = :updated_at, UpdatedBy = :updated_by WHERE UserID = :uid';
                $stmtUser = $pdo->prepare($sqlUser);
                if (!$stmtUser->execute([':updated_at' => $now, ':updated_by' => $deletedBy ?? null, ':uid' => $userId])) {
                    $pdo->rollBack();
                    return false;
                }
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $ex) {
            try { $pdo->rollBack(); } catch (\Throwable $_) {}
            error_log('[EmployeeModel::deleteEmployee] ' . $ex->getMessage());
            return false;
        }
    }
    public function getEmployeeId($id, $schoolId) {
        $sql = "SELECT EmployeeID FROM Tx_Employees WHERE UserID = :id AND SchoolID = :school LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}