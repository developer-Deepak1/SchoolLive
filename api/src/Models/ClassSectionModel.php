<?php
namespace SchoolLive\Models;

use DateTime; use DateInterval; use DatePeriod; use PDO;

/**
 * Model hosting class & section DB operations.
 * This keeps class/section responsibilities separate from academic-year logic.
 */
class ClassSectionModel extends Model {
    // Classes Methods
    public function createClass($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        // Ensure UpdatedAt uses PascalCase DB column
        if (isset($data['updated_at']) && !isset($data['UpdatedAt'])) {
            $data['UpdatedAt'] = $data['updated_at'];
            unset($data['updated_at']);
        }
        $data['UpdatedAt'] = $data['UpdatedAt'] ?? date('Y-m-d H:i:s');

        $className = $data['ClassName'];
        $classCode = $data['ClassCode'];
        $stream = $data['Stream'] ;
        $academicYearId = $data['AcademicYearID'];
        $maxStrength = $data['MaxStrength'] ?? 50;
        $schoolId = $data['SchoolID'] ?? null;
        $createdBy = $data['Username'] ?? null;

        $query = "INSERT INTO Tx_Classes (ClassName, ClassCode, Stream, AcademicYearID, MaxStrength, SchoolID, CreatedAt, CreatedBy";
        if (isset($data['IsActive']) || isset($data['is_active'])) {
            $query .= ", IsActive";
        }
        $query .= ") VALUES (:class_name, :class_code, :stream, :academic_year_id, :max_strength, :school_id, :created_at, :created_by";
        if (isset($data['IsActive']) || isset($data['is_active'])) {
            $query .= ", :is_active";
        }
        $query .= ")";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_name', $className);
        $stmt->bindParam(':class_code', $classCode);
        $stmt->bindParam(':stream', $stream);
        $stmt->bindParam(':academic_year_id', $academicYearId);
        $stmt->bindParam(':max_strength', $maxStrength);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':created_at', $data['created_at']);
        $stmt->bindParam(':created_by', $createdBy);
        if (isset($data['IsActive']) || isset($data['is_active'])) {
            $isActiveVal = isset($data['IsActive']) ? $data['IsActive'] : $data['is_active'];
            $stmt->bindParam(':is_active', $isActiveVal);
        }

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateClass($id, $data) {
        $data['UpdatedAt'] = date('Y-m-d H:i:s');
        if (isset($data['is_active']) && !isset($data['IsActive'])) {
            $data['IsActive'] = $data['is_active'];
            unset($data['is_active']);
        }

        $fields = array_keys($data);
        $setClause = array_map(function($field) { return $field . ' = :' . $field; }, $fields);

        $query = "UPDATE Tx_Classes SET " . implode(', ', $setClause) . " WHERE ClassID = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $data[$field]);
        }

        return $stmt->execute();
    }

    /**
     * Check if a teacher (employee) is assigned to the given class.
     * Returns true if assigned, false otherwise.
     */
    public function isTeacherAssigned($class_id, $teacher_id) {
        $query = "SELECT COUNT(*) as cnt FROM Tx_ClassTeachers WHERE ClassID = :class_id AND EmployeeID = :teacher_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (!empty($row) && $row['cnt'] > 0);
    }

    public function getAllClasses($academic_year_id = null, $school_id = null, $active = 1) {
        $query = "SELECT c.ClassID,c.ClassName,c.ClassCode,c.Stream,c.MaxStrength,c.SchoolID,c.Status, ay.AcademicYearName, IFNULL(c.IsActive, TRUE) AS IsActive
          FROM Tx_Classes c
          INNER JOIN Tm_AcademicYears ay ON c.AcademicYearID = ay.AcademicYearID";

        $clauses = [];
        if ($academic_year_id) {
            $clauses[] = "c.AcademicYearID = :academic_year_id";
        }
        if ($school_id) {
            $clauses[] = "c.SchoolID = :school_id";
        }
        if ($active !== null) {
            $clauses[] = "IFNULL(c.IsActive, TRUE) = :active";
        }

        if (!empty($clauses)) {
            $query .= " WHERE " . implode(' AND ', $clauses);
        }

        $query .= " ORDER BY c.ClassID";
        
        $stmt = $this->conn->prepare($query);
        
        if ($academic_year_id) {
            $stmt->bindParam(':academic_year_id', $academic_year_id);
        }
        if ($school_id) {
            $stmt->bindParam(':school_id', $school_id);
        }
        if ($active !== null) {
            $stmt->bindParam(':active', $active);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getClassById($id, $active = 1) {
        $query = "SELECT c.ClassID,c.ClassName,c.ClassCode,c.Stream,c.MaxStrength,c.SchoolID,c.Status, ay.AcademicYearName, IFNULL(c.IsActive, TRUE) AS IsActive
          FROM Tx_Classes c
          INNER JOIN Tm_AcademicYears ay ON c.AcademicYearID = ay.AcademicYearID
          WHERE c.ClassID = :id";
          if ($active !== null) {
              $query .= " AND IFNULL(c.IsActive, TRUE) = :active";
          }
          $query .= " LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($active !== null) $stmt->bindParam(':active', $active);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function deleteClass($id, $deletedBy = null) {
        $query = "UPDATE Tx_Classes SET IsActive = 0, UpdatedAt = :updated_at";
        $params = [':id' => $id, ':updated_at' => date('Y-m-d H:i:s')];
        if ($deletedBy) {
            $query .= ", UpdatedBy = :updated_by";
            $params[':updated_by'] = $deletedBy;
        }
        $query .= " WHERE ClassID = :id";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function getClassesByTeacher($teacher_id) {
        $query = "SELECT c.*, ay.AcademicYearName FROM Tx_Classes c
          INNER JOIN Tx_ClassTeachers ct ON ct.ClassID = c.ClassID
          LEFT JOIN Tm_AcademicYears ay ON c.AcademicYearID = ay.AcademicYearID
          WHERE ct.EmployeeID = :teacher_id
          ORDER BY c.ClassName, c.ClassCode";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Sections methods
    public function getAllSections($academic_year_id = null, $class_id = null, $school_id = null) {
        $query = "SELECT s.*, ay.AcademicYearName, IFNULL(s.IsActive, TRUE) AS IsActive FROM Tx_Sections s
            LEFT JOIN Tm_AcademicYears ay ON s.AcademicYearID = ay.AcademicYearID";

        $clauses = [];
        if ($academic_year_id) {
            $clauses[] = "s.AcademicYearID = :academic_year_id";
        }
        if ($class_id) {
            $clauses[] = "s.ClassID = :class_id";
        }
        if ($school_id) {
            $clauses[] = "s.SchoolID = :school_id";
        }
        $active = 1;
        $clauses[] = "IFNULL(s.IsActive, TRUE) = :active";

        if (!empty($clauses)) {
            $query .= " WHERE " . implode(' AND ', $clauses);
        }

        $query .= " ORDER BY s.SectionID";

        $stmt = $this->conn->prepare($query);
        if ($academic_year_id) {
            $stmt->bindParam(':academic_year_id', $academic_year_id);
        }
        if ($class_id) {
            $stmt->bindParam(':class_id', $class_id);
        }
        if ($school_id) {
            $stmt->bindParam(':school_id', $school_id);
        }
        $stmt->bindParam(':active', $active);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getSectionById($id) {
        $query = "SELECT s.*, ay.AcademicYearName, IFNULL(s.IsActive, TRUE) AS IsActive FROM Tx_Sections s
            LEFT JOIN Tm_AcademicYears ay ON s.AcademicYearID = ay.AcademicYearID
            WHERE s.SectionID = :id AND IFNULL(s.IsActive, TRUE) = 1 LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createSection($data) {
        $data['CreatedAt'] = date('Y-m-d H:i:s');
        $data['UpdatedAt'] = date('Y-m-d H:i:s');
        $maxStrength = $data['MaxStrength'] ?? $data['max_strength'] ?? null;

        $query = "INSERT INTO Tx_Sections (SectionName, SchoolID, AcademicYearID, ClassID, MaxStrength, Status, CreatedAt, CreatedBy, UpdatedAt)
            VALUES (:section_name, :school_id, :academic_year_id, :class_id, :max_strength, :status, :created_at, :created_by, :updated_at)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_name', $data['section_name']);
        $stmt->bindParam(':school_id', $data['school_id']);
        $stmt->bindParam(':academic_year_id', $data['academic_year_id']);
        $stmt->bindParam(':class_id', $data['class_id']);
        $stmt->bindParam(':max_strength', $maxStrength);
        $status = $data['status'] ?? 'Active';
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_at', $data['CreatedAt']);
        $created_by = $data['created_by'] ?? $data['CreatedBy'] ?? 'System';
        $stmt->bindParam(':created_by', $created_by);
        $stmt->bindParam(':updated_at', $data['UpdatedAt']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateSection($id, $data) {
        if (isset($data['updated_at']) && !isset($data['UpdatedAt'])) {
            $data['UpdatedAt'] = $data['updated_at'];
            unset($data['updated_at']);
        }
        $data['UpdatedAt'] = $data['UpdatedAt'] ?? date('Y-m-d H:i:s');

        $mapping = [
            'section_name' => 'SectionName',
            'class_id' => 'ClassID',
            'max_strength' => 'MaxStrength',
            'academic_year_id' => 'AcademicYearID',
            'school_id' => 'SchoolID',
            'status' => 'Status'
        ];
        $keysToUnset = [];
        foreach ($mapping as $snake => $pascal) {
            if (isset($data[$snake]) && !isset($data[$pascal])) {
                $data[$pascal] = $data[$snake];
            }
            if (isset($data[$snake])) $keysToUnset[] = $snake;
        }
        foreach ($keysToUnset as $key) {
            unset($data[$key]);
        }

        $fields = array_keys($data);
        if (empty($fields)) {
            return false; // nothing to update
        }

        $setClause = array_map(function($field) { return $field . ' = :' . $field; }, $fields);

        $query = "UPDATE Tx_Sections SET " . implode(', ', $setClause) . " WHERE SectionID = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        foreach ($data as $field => $value) {
            $stmt->bindParam(':' . $field, $data[$field]);
        }

        return $stmt->execute();
    }

    public function deleteSection($id) {
        $query = "UPDATE Tx_Sections SET IsActive = 0, UpdatedAt = :updated_at WHERE SectionID = :id";
        $stmt = $this->conn->prepare($query);
        $updatedAt = date('Y-m-d H:i:s');
        $stmt->bindParam(':updated_at', $updatedAt);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
