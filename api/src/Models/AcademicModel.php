<?php
namespace SchoolLive\Models;

use DateTime; use DateInterval; use DatePeriod; use PDO;

class AcademicModel extends Model {
    protected $table = 'Tm_AcademicYears';

    // Academic Years Methods
    public function createAcademicYear($data) {
        $data['CreatedAt'] = date('Y-m-d H:i:s');
        
        // Extract fields supporting both PascalCase and snake_case
        $academicYearName = $data['AcademicYearName'];
        $startDate = $data['StartDate'] ;
        $endDate = $data['EndDate'];
        $schoolId = $data['SchoolID'] ;
        $status = $data['Status'];
        $createdBy = $data['CreatedBy'];

        // If an academic year with same name already exists for this school,
        // reactivate it (soft) and update dates/status instead of creating duplicate.
        $checkQ = "SELECT AcademicYearID, IFNULL(IsActive, TRUE) AS IsActive FROM Tm_AcademicYears WHERE AcademicYearName = :academic_year_name AND SchoolID = :school_id LIMIT 1";
        $checkSt = $this->conn->prepare($checkQ);
        $checkSt->bindParam(':academic_year_name', $academicYearName);
        $checkSt->bindParam(':school_id', $schoolId);
        $checkSt->execute();
        $existing = $checkSt->fetch();
        if ($existing) {
            $existingId = $existing['AcademicYearID'];
            // If already active, just return the ID
            if (isset($existing['IsActive']) && $existing['IsActive']) {
                return $existingId;
            }
            // Otherwise reactivate and update date range/status
            $upd = $this->conn->prepare("UPDATE Tm_AcademicYears SET StartDate = :start_date, EndDate = :end_date, Status = :status, IsActive = 1, UpdatedAt = :updated_at, UpdatedBy = :updated_by WHERE AcademicYearID = :id");
            $now = date('Y-m-d H:i:s');
            $upd->bindParam(':start_date', $startDate);
            $upd->bindParam(':end_date', $endDate);
            $upd->bindParam(':status', $status);
            $upd->bindParam(':updated_at', $now);
            $upd->bindParam(':updated_by', $createdBy);
            $upd->bindParam(':id', $existingId);
            if ($upd->execute()) return $existingId;
            return false;
        }

        $query = "INSERT INTO Tm_AcademicYears (AcademicYearName, StartDate, EndDate, SchoolID, Status, CreatedAt, CreatedBy)
                  VALUES (:academic_year_name, :start_date, :end_date, :school_id, :status, :created_at, :created_by)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':academic_year_name', $academicYearName);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_at', $data['CreatedAt']);
        $stmt->bindParam(':created_by', $createdBy);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateAcademicYear($id, $data) {
        $data['UpdatedAt'] = date('Y-m-d H:i:s');
        
        // Build the SET clause dynamically based on provided data
        $allowedFields = ['AcademicYearName', 'StartDate', 'EndDate', 'Status', 'UpdatedAt', 'UpdatedBy'];
        $setFields = [];
        $params = [':id' => $id];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $setFields[] = $field . ' = :' . $field;
                $params[':' . $field] = $data[$field];
            }
        }
        
        if (empty($setFields)) {
            return false; // No fields to update
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $setFields) . " WHERE AcademicYearID = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function getAcademicYearById($id, $active = 1) {
        $query = "SELECT AcademicYearID, AcademicYearName, StartDate, EndDate, Status, IFNULL(IsActive, TRUE) AS IsActive
                  FROM " . $this->table . " 
                  WHERE AcademicYearID = :id";
        if ($active !== null) {
            $query .= " AND IFNULL(IsActive, TRUE) = :active";
        }
        $query .= " LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($active !== null) $stmt->bindParam(':active', $active);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAcademicYearsBySchoolId($schoolId, $active = 1) {
    $query = "SELECT AcademicYearID, AcademicYearName, StartDate, EndDate, Status, IFNULL(IsActive, TRUE) AS IsActive FROM " . $this->table . " 
          WHERE SchoolID = :school_id";
        if ($active !== null) {
            $query .= " AND IFNULL(IsActive, TRUE) = :active";
        }
        $query .= " ORDER BY StartDate";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        if ($active !== null) $stmt->bindParam(':active', $active);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCurrentAcademicYear($schoolId, $active = 1) {
        // Prefer explicitly marked current academic year if present
        $row = [];
        try {
            $query = "SELECT AcademicYearID,AcademicYearName,StartDate,EndDate,Status, IFNULL(IsActive, TRUE) AS IsActive FROM " . $this->table . " WHERE SchoolID = :schoolId and Status = 'active'";
            if ($active !== null) {
                $query .= " AND IFNULL(IsActive, TRUE) = :active";
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':schoolId', $schoolId);
            if ($active !== null) $stmt->bindParam(':active', $active);
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        } catch (\PDOException $e) {
            // If the IsCurrent column doesn't exist, SQLSTATE is 42S22. In that case
            // fall through to the date-based and fallback queries below instead of
            // bubbling an exception (prevents runtime failures when schema is older).
            if ($e->getCode() !== '42S22') {
                throw $e;
            }
        }
        
        // Fallback: find academic year covering today's date
    $query2 = "SELECT *, IFNULL(IsActive, TRUE) AS IsActive FROM " . $this->table . " WHERE StartDate <= CURDATE() AND EndDate >= CURDATE()";
    if ($active !== null) $query2 .= " AND IFNULL(IsActive, TRUE) = :active";
    $query2 .= " LIMIT 1";
    $stmt2 = $this->conn->prepare($query2);
    if ($active !== null) $stmt2->bindParam(':active', $active);
    $stmt2->execute();
    $row2 = $stmt2->fetch();
        if ($row2) {
            return $row2;
        }

        // Final fallback: most recent academic year
    $query3 = "SELECT *, IFNULL(IsActive, TRUE) AS IsActive FROM " . $this->table . "";
        if ($active !== null) $query3 .= " WHERE IFNULL(IsActive, TRUE) = :active";
        $query3 .= " ORDER BY StartDate DESC LIMIT 1";
        $stmt3 = $this->conn->prepare($query3);
        if ($active !== null) $stmt3->bindParam(':active', $active);
        $stmt3->execute();
        return $stmt3->fetch();
    }

    public function deleteAcademicYear($id, $schoolId, $deletedBy = null) {
        // Soft-delete: set IsActive = 0 instead of physical delete
        // First check if the academic year exists and get its status
        $query = "SELECT AcademicYearID, Status, IFNULL(IsActive, TRUE) AS IsActive FROM " . $this->table . " WHERE AcademicYearID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $academicYear = $stmt->fetch();

        if (!$academicYear) {
            return ['success' => false, 'message' => 'Academic year not found'];
        }

        $currentacademicYear = $this->getCurrentAcademicYear($schoolId);
        
        // Prevent soft-deleting the current active academic year
        if (strtolower($academicYear['Status']) === 'active' && $academicYear['AcademicYearID'] == $currentacademicYear['AcademicYearID']) {
            return ['success' => false, 'message' => 'Cannot delete active academic year. Please change status first.'];
        }

        $updateQuery = "UPDATE " . $this->table . " SET IsActive = 0, UpdatedAt = :updated_at";
        $params = [':id' => $id, ':updated_at' => date('Y-m-d H:i:s')];
        if ($deletedBy) {
            $updateQuery .= ", UpdatedBy = :updated_by";
            $params[':updated_by'] = $deletedBy;
        }
        $updateQuery .= " WHERE AcademicYearID = :id";

        $updateStmt = $this->conn->prepare($updateQuery);
        if ($updateStmt->execute($params)) {
            return ['success' => true, 'message' => 'Academic year soft-deleted (IsActive set to 0)'];
        } else {
            return ['success' => false, 'message' => 'Failed to soft-delete academic year'];
        }
    }

    // Classes Methods
    public function createClass($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        // Ensure UpdatedAt uses PascalCase DB column
        if (isset($data['updated_at']) && !isset($data['UpdatedAt'])) {
            $data['UpdatedAt'] = $data['updated_at'];
            unset($data['updated_at']);
        }
        $data['UpdatedAt'] = $data['UpdatedAt'] ?? date('Y-m-d H:i:s');

        // New schema: Tx_Classes table stores class metadata. Teacher assignment lives in Tx_ClassTeachers table.
        // Accept incoming keys in either PascalCase (ClassName) or snake_case (class_name) for compatibility.
        $className = $data['ClassName'];
        $classCode = $data['ClassCode'];
        $stream = $data['Stream'] ;
        $academicYearId = $data['AcademicYearID'];
        $maxStrength = $data['MaxStrength'] ?? 50;
        $schoolId = $data['SchoolID'] ?? null;
        $createdBy = $data['Username'] ?? null;

        // Include IsActive when provided to allow creating disabled/enabled explicitly. Otherwise DB default applies.
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
        // Allow IsActive to be updated if provided (snake_case or PascalCase)
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

    $query .= " ORDER BY c.ClassName, c.ClassCode";
        
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
    // soft-delete the class
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
    // Use class_teachers mapping to find classes assigned to the given teacher (employee)
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
        // default to active records when not explicitly requested otherwise
        $active = 1;
        $clauses[] = "IFNULL(s.IsActive, TRUE) = :active";

        if (!empty($clauses)) {
            $query .= " WHERE " . implode(' AND ', $clauses);
        }

        $query .= " ORDER BY s.SectionName";

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

    // Weekly Offs and Holidays
    public function getWeeklyOffsByAcademicYear($schoolId, $academicYearId = null, $active = 1) {
        $query = "SELECT WeeklyOffID, AcademicYearID, DayOfWeek, IFNULL(IsActive, TRUE) AS IsActive FROM Tx_WeeklyOffs WHERE SchoolID = :school_id";
        if ($academicYearId) $query .= " AND AcademicYearID = :academic_year_id";
        if ($active !== null) $query .= " AND IFNULL(IsActive, TRUE) = :active";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        if ($academicYearId) $stmt->bindParam(':academic_year_id', $academicYearId);
        if ($active !== null) $stmt->bindParam(':active', $active);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setWeeklyOffs($schoolId, $academicYearId, $daysArray, $username = null) {
        // daysArray expected as array of integers (1-7)
        try {
            $this->conn->beginTransaction();
            // Reactivate existing weekly off rows or insert new ones. Preserve history rather than deleting.
            $created = date('Y-m-d H:i:s');
            $now = $created;
            $check = $this->conn->prepare("SELECT WeeklyOffID, IsActive FROM Tx_WeeklyOffs WHERE SchoolID = :school AND AcademicYearID = :ay AND DayOfWeek = :dow LIMIT 1");
            $ins = $this->conn->prepare("INSERT INTO Tx_WeeklyOffs (AcademicYearID, SchoolID, DayOfWeek, CreatedBy, CreatedAt, IsActive) VALUES (:ay, :school, :dow, :user, :created, 1)");
            $upd = $this->conn->prepare("UPDATE Tx_WeeklyOffs SET IsActive = 1, UpdatedBy = :user, UpdatedAt = :updated WHERE WeeklyOffID = :id");

            // Build a set of desired days
            $desired = array_map('intval', array_values(array_unique($daysArray)));

            foreach ($desired as $d) {
                $dow = (int)$d;
                $check->execute([':school' => $schoolId, ':ay' => $academicYearId, ':dow' => $dow]);
                $row = $check->fetch();
                if ($row && isset($row['WeeklyOffID'])) {
                    if (intval($row['IsActive']) === 1) continue; // already active
                    $upd->execute([':user' => $username, ':updated' => $now, ':id' => $row['WeeklyOffID']]);
                } else {
                    $ok = $ins->execute([':ay' => $academicYearId, ':school' => $schoolId, ':dow' => $dow, ':user' => $username, ':created' => $created]);
                    if ($ok === false) {
                        $err = $ins->errorInfo();
                        throw new \Exception('Insert weekly off failed: ' . ($err[2] ?? json_encode($err)));
                    }
                }
            }
            // Deactivation of weekly offs not included in the incoming list is handled below
            // Deactivate any existing weekly offs for this school+academic year that were NOT included in the incoming list
            // If daysArray is empty, deactivate all; otherwise build a NOT IN clause
            $updatedAt = $now;
            if (empty($daysArray)) {
                $deact = $this->conn->prepare("UPDATE Tx_WeeklyOffs SET IsActive = 0, UpdatedAt = :updated, UpdatedBy = :user WHERE SchoolID = :school AND AcademicYearID = :ay AND IFNULL(IsActive, TRUE) = 1");
                $deact->execute([':updated' => $updatedAt, ':user' => $username, ':school' => $schoolId, ':ay' => $academicYearId]);
            } else {
                // build placeholders for NOT IN
                $placeholders = [];
                $params = [':school' => $schoolId, ':ay' => $academicYearId, ':updated' => $updatedAt, ':user' => $username];
                foreach ($daysArray as $i => $d) {
                    $ph = ':d' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = (int)$d;
                }
                $notIn = implode(', ', $placeholders);
                $deactSql = "UPDATE Tx_WeeklyOffs SET IsActive = 0, UpdatedAt = :updated, UpdatedBy = :user WHERE SchoolID = :school AND AcademicYearID = :ay AND DayOfWeek NOT IN (" . $notIn . ") AND IFNULL(IsActive, TRUE) = 1";
                $deact = $this->conn->prepare($deactSql);
                $deact->execute($params);
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            try { $this->conn->rollBack(); } catch (\Throwable $ee) {}
            // Log the database error for debugging (do not expose sensitive info to clients)
            error_log('[AcademicModel::setWeeklyOffs] ' . $e->getMessage());
            return false;
        }
    }

    public function getHolidaysByAcademicYear($schoolId, $academicYearId = null, $active = 1) {
        $query = "SELECT HolidayID, AcademicYearID, Date, Title, Type, IFNULL(IsActive, TRUE) AS IsActive FROM Tx_Holidays WHERE SchoolID = :school_id";
        if ($academicYearId) $query .= " AND AcademicYearID = :academic_year_id";
        if ($active !== null) $query .= " AND IFNULL(IsActive, TRUE) = :active";
        $query .= " ORDER BY Date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        if ($academicYearId) $stmt->bindParam(':academic_year_id', $academicYearId);
        if ($active !== null) $stmt->bindParam(':active', $active);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createHoliday($data) {
        // Upsert-like behaviour: if a holiday exists for the same School+AcademicYear+Date,
        // reactivate or update it instead of inserting a duplicate (avoids unique key errors).
        $school = $data['SchoolID'] ?? null;
        $ay = $data['AcademicYearID'] ?? null;
        $date = $data['Date'] ?? null;
        $title = $data['Title'] ?? null;
        $type = $data['Type'] ?? null;
        $user = $data['CreatedBy'] ?? null;
        $now = date('Y-m-d H:i:s');

        // Look for existing holiday
        $checkSql = "SELECT HolidayID, IFNULL(IsActive, TRUE) AS IsActive FROM Tx_Holidays WHERE SchoolID = :school AND AcademicYearID = :ay AND Date = :date LIMIT 1";
        $check = $this->conn->prepare($checkSql);
        $check->bindValue(':school', $school);
        $check->bindValue(':ay', $ay);
        $check->bindValue(':date', $date);
        $check->execute();
        $existing = $check->fetch();

        if ($existing && isset($existing['HolidayID'])) {
            // Update existing row and set IsActive = 1 (reactivate) and update fields
            $updSql = "UPDATE Tx_Holidays SET Title = :title, Type = :type, IsActive = 1, UpdatedAt = :updated";
            $params = [':title' => $title, ':type' => $type, ':updated' => $now, ':id' => $existing['HolidayID'], ':school' => $school];
            if ($user) {
                $updSql .= ", UpdatedBy = :user";
                $params[':user'] = $user;
            }
            $updSql .= " WHERE HolidayID = :id AND SchoolID = :school";
            $upd = $this->conn->prepare($updSql);
            if ($upd->execute($params)) return $existing['HolidayID'];
            return false;
        }

        // No existing holiday - insert a new one
        $insSql = "INSERT INTO Tx_Holidays (AcademicYearID, SchoolID, Date, Title, Type, CreatedBy, CreatedAt) VALUES (:ay, :school, :date, :title, :type, :user, :created)";
        $ins = $this->conn->prepare($insSql);
        $ins->bindValue(':ay', $ay);
        $ins->bindValue(':school', $school);
        $ins->bindValue(':date', $date);
        $ins->bindValue(':title', $title);
        $ins->bindValue(':type', $type);
        $ins->bindValue(':user', $user);
        $ins->bindValue(':created', $now);
        if ($ins->execute()) return $this->conn->lastInsertId();
        return false;
    }

    public function getHolidayById($id) {
        $query = "SELECT HolidayID, AcademicYearID, SchoolID, Date, Title, Type FROM Tx_Holidays WHERE HolidayID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function deleteHoliday($id, $schoolId, $deletedBy = null) {
        $query = "UPDATE Tx_Holidays SET IsActive = 0, UpdatedAt = :updated_at";
        $params = [':id' => $id, ':updated_at' => date('Y-m-d H:i:s')];
        if ($deletedBy) {
            $query .= ", UpdatedBy = :updated_by";
            $params[':updated_by'] = $deletedBy;
        }
        $query .= " WHERE HolidayID = :id AND SchoolID = :school_id";
        $params[':school_id'] = $schoolId;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function updateHoliday($id, $data, $schoolId) {
        // Only allow updating certain fields
        $allowed = ['Date', 'Title', 'Type', 'AcademicYearID', 'UpdatedBy'];
        $set = [];
        $params = [':id' => $id, ':school_id' => $schoolId];
        foreach ($allowed as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = :$f";
                $params[':' . $f] = $data[$f];
            }
        }
        if (empty($set)) return false;
        // Always set UpdatedAt
        $set[] = "UpdatedAt = :UpdatedAt";
        $params[':UpdatedAt'] = date('Y-m-d H:i:s');

        $query = "UPDATE Tx_Holidays SET " . implode(', ', $set) . " WHERE HolidayID = :id AND SchoolID = :school_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function getWeeklyReport($schoolId, $academicYearId, $start, $end) {
        // Build a list of dates between start and end and mark weekly offs and holidays
        $report = [];
        $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));

        // Load weekly offs and holidays to minimize queries
        $offs = $this->getWeeklyOffsByAcademicYear($schoolId, $academicYearId);
        $offDays = array_map(function($r){ return (int)$r['DayOfWeek']; }, $offs);
        $holidays = $this->getHolidaysByAcademicYear($schoolId, $academicYearId);
        $holidayMap = [];
        foreach ($holidays as $h) { $holidayMap[$h['Date']] = $h; }

        foreach ($period as $dt) {
            $ymd = $dt->format('Y-m-d');
            $dow = (int)$dt->format('N'); // 1 (Mon) to 7 (Sun)
            $isOff = in_array($dow, $offDays, true);
            $isHoliday = isset($holidayMap[$ymd]);
            $report[] = [ 'date' => $ymd, 'dayOfWeek' => $dow, 'weeklyOff' => $isOff, 'holiday' => $isHoliday ? $holidayMap[$ymd] : null ];
        }
        return $report;
    }

    /**
     * Insert holidays for every date in the inclusive range StartDate..EndDate.
     * Skips dates that already exist (same SchoolID, AcademicYearID, Date).
     * Returns an array: ['createdDates'=>[], 'skippedDates'=>[]] or false on error.
     */
    public function createHolidayRange($input) {
        $schoolId = $input['SchoolID'];
        $ay = $input['AcademicYearID'];
        $title = $input['Title'];
        $type = $input['Type'];
        $start = $input['StartDate'];
        $end = $input['EndDate'];
        $createdBy = $input['CreatedBy'] ?? null;

        try {
            $this->conn->beginTransaction();

            // Prepare check and insert statements
            $check = $this->conn->prepare("SELECT COUNT(*) as cnt FROM Tx_Holidays WHERE SchoolID = :school AND AcademicYearID = :ay AND Date = :date");
            $ins = $this->conn->prepare("INSERT INTO Tx_Holidays (AcademicYearID, SchoolID, Date, Title, Type, CreatedBy, CreatedAt) VALUES (:ay, :school, :date, :title, :type, :user, :created)");
            $createdAt = date('Y-m-d H:i:s');

            $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), (new \DateTime($end))->modify('+1 day'));
            $createdDates = [];
            $skippedDates = [];

            foreach ($period as $dt) {
                $ymd = $dt->format('Y-m-d');
                $check->execute([':school' => $schoolId, ':ay' => $ay, ':date' => $ymd]);
                $row = $check->fetch();
                if ($row && isset($row['cnt']) && intval($row['cnt']) > 0) {
                    $skippedDates[] = $ymd;
                    continue;
                }
                $ok = $ins->execute([':ay' => $ay, ':school' => $schoolId, ':date' => $ymd, ':title' => $title, ':type' => $type, ':user' => $createdBy, ':created' => $createdAt]);
                if ($ok === false) {
                    $err = $ins->errorInfo();
                    throw new \Exception('Insert failed: ' . ($err[2] ?? json_encode($err)));
                }
                $createdDates[] = $ymd;
            }

            $this->conn->commit();
            return ['createdDates' => $createdDates, 'skippedDates' => $skippedDates];
        } catch (\Throwable $e) {
            try { $this->conn->rollBack(); } catch (\Throwable $ee) {}
            error_log('[AcademicModel::createHolidayRange] ' . $e->getMessage());
            return false;
        }
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
        // Use PascalCase timestamp keys to match DB columns (CreatedAt, UpdatedAt)
        $data['CreatedAt'] = date('Y-m-d H:i:s');
        $data['UpdatedAt'] = date('Y-m-d H:i:s');

        // allow MaxStrength to be provided as PascalCase or snake_case
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
        // Map snake_case updated_at to PascalCase UpdatedAt if present
        if (isset($data['updated_at']) && !isset($data['UpdatedAt'])) {
            $data['UpdatedAt'] = $data['updated_at'];
            unset($data['updated_at']);
        }
        // Ensure UpdatedAt is always set
        $data['UpdatedAt'] = $data['UpdatedAt'] ?? date('Y-m-d H:i:s');

        // Normalize incoming snake_case keys to PascalCase column names so the
        // dynamic SET clause uses real DB column names like SectionName, ClassID, etc.
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
    public function getAcademicYearRange(?int $academicYearId, int $schoolId): array {
        $start = null; $end = null; $ayId = $academicYearId;
        if ($academicYearId) {
            $q = $this->conn->prepare("SELECT AcademicYearID, StartDate, EndDate FROM Tm_AcademicYears WHERE AcademicYearID = :ay LIMIT 1");
            $q->bindValue(param: ':ay',$academicYearId,type: PDO::PARAM_INT);
            $q->execute();
            if ($r = $q->fetch(PDO::FETCH_ASSOC)) { $start = $r['StartDate']; $end = $r['EndDate']; $ayId = (int)$r['AcademicYearID']; }
        }

        if (!$start || !$end) {
            $q = $this->conn->prepare("SELECT AcademicYearID, StartDate, EndDate FROM Tm_AcademicYears WHERE SchoolID = :school AND Status = 'Active' LIMIT 1");
            $q->bindValue(':school',$schoolId,PDO::PARAM_INT);
            $q->execute();
            if ($r = $q->fetch(PDO::FETCH_ASSOC)) { $start = $start ?? $r['StartDate']; $end = $end ?? $r['EndDate']; $ayId = $ayId ?? (int)$r['AcademicYearID']; }
        }

        return ['start' => $start, 'end' => $end, 'academicYearId' => $ayId];
    }
    public function getWeeklyOffs(int $academicYearId, int $schoolId): array {
        $out = [];
        try {
            $q = $this->conn->prepare("SELECT DayOfWeek FROM Tx_WeeklyOffs WHERE AcademicYearID = :ay AND SchoolID = :school AND IsActive=1");
            $q->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
            $q->bindValue(':school',$schoolId,PDO::PARAM_INT);
            $q->execute();
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) $out[] = (int)$r['DayOfWeek'];
        } catch (\Throwable $_) { }
        return $out;
    }

    public function getHolidays(int $academicYearId, int $schoolId): array {
        $map = [];
        try {
            $q = $this->conn->prepare("SELECT Date, Title, Type FROM Tx_Holidays WHERE AcademicYearID = :ay AND SchoolID = :school AND IFNULL(IsActive,TRUE)=1");
            $q->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
            $q->bindValue(':school',$schoolId,PDO::PARAM_INT);
            $q->execute();
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $map[$r['Date']] = ['type' => $r['Type'] ?? 'Holiday', 'title' => $r['Title'] ?? ''];
            }
        } catch (\Throwable $_) { }
        return $map;
    }

    public function computeWorkingDaysByMonth($schoolId, $academicYearId)
    {
       $range = $this->getAcademicYearRange($academicYearId, $schoolId);
       if (!$range['start'] || !$range['end']) return ['labels'=>[], 'datasets'=>[]];
        $weeklyOffs = $academicYearId ? $this->getWeeklyOffs($academicYearId, $schoolId) : [];
        $holidaysMap = $academicYearId ? $this->getHolidays($academicYearId, $schoolId) : [];
        $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $weeklyOffs, $holidaysMap);
        return [$computed];

    }
    public function getcomputeWorkingDaysByMonth(string $start, string $end, array $weeklyOffs = [], array $holidaysMap = []): array {
        $startDt = new DateTime($start);
        $startDt->modify('first day of this month')->setTime(0,0,0);
        $endDt = new DateTime($end);
        $endDt->modify('first day of this month')->setTime(0,0,0);

        $months = [];
        $labels = [];
        $workingDays = [];

        $c = clone $startDt;
        while ($c <= $endDt) {
            $ym = $c->format('Y-m');
            $months[] = $ym;
            $labels[] = $c->format('M');

            $period = new DatePeriod(new DateTime($c->format('Y-m-01')), new DateInterval('P1D'), (new DateTime($c->format('Y-m-t')))->modify('+1 day'));
            $wd = 0;
            foreach ($period as $d) {
                $ymd = $d->format('Y-m-d');
                $dow = (int)$d->format('N');

                $h = $holidaysMap[$ymd] ?? null;

                $isOff = in_array($dow, $weeklyOffs, true);
                $isHoliday = $h && (($h['type'] ?? 'Holiday') === 'Holiday');
                if (!$isOff && !$isHoliday) $wd++;
            }
            $workingDays[] = $wd;
            $c->modify('+1 month');
        }

        return ['months' => $months, 'labels' => $labels, 'workingDays' => $workingDays];
    }
}
