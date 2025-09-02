<?php
namespace SchoolLive\Models;

use PDO;

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

    public function getAcademicYearById($id) {
        $query = "SELECT AcademicYearID, AcademicYearName, StartDate, EndDate
                  FROM " . $this->table . " 
                  WHERE AcademicYearID = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAcademicYearsBySchoolId($schoolId) {
        $query = "SELECT AcademicYearID, AcademicYearName, StartDate, EndDate,Status FROM " . $this->table . " 
                  WHERE SchoolID = :school_id
                  ORDER BY StartDate";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCurrentAcademicYear($schoolId) {
        // Prefer explicitly marked current academic year if present
        $row = [];
        try {
            $query = "SELECT AcademicYearID,AcademicYearName,StartDate,EndDate,Status FROM " . $this->table . " WHERE SchoolID = :schoolId and Status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':schoolId', $schoolId);   
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
        $query2 = "SELECT * FROM " . $this->table . " WHERE StartDate <= CURDATE() AND EndDate >= CURDATE() LIMIT 1";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->execute();
        $row2 = $stmt2->fetch();
        if ($row2) {
            return $row2;
        }

        // Final fallback: most recent academic year
        $query3 = "SELECT * FROM " . $this->table . " ORDER BY StartDate DESC LIMIT 1";
        $stmt3 = $this->conn->prepare($query3);
        $stmt3->execute();
        return $stmt3->fetch();
    }

    public function deleteAcademicYear($id, $schoolId) {
        // First check if the academic year exists and get its status
        $query = "SELECT AcademicYearID, Status FROM " . $this->table . " WHERE AcademicYearID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $academicYear = $stmt->fetch();

        if (!$academicYear) {
            return ['success' => false, 'message' => 'Academic year not found'];
        }

        $currentacademicYear = $this->getCurrentAcademicYear($schoolId);
        
        // Check if the academic year is active - prevent deletion
        if (strtolower($academicYear['Status']) === 'active' && $academicYear['AcademicYearID'] == $currentacademicYear['AcademicYearID']) {
            return ['success' => false, 'message' => 'Cannot delete active academic year. Please change status first.'];
        }

        // Proceed with deletion if not active
        $deleteQuery = "DELETE FROM " . $this->table . " WHERE AcademicYearID = :id";
        $deleteStmt = $this->conn->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            return ['success' => true, 'message' => 'Academic year deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete academic year'];
        }
    }

    // Classes Methods
    public function createClass($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // New schema: Tx_Classes table stores class metadata. Teacher assignment lives in Tx_ClassTeachers table.
        // Accept incoming keys in either PascalCase (ClassName) or snake_case (class_name) for compatibility.
        $className = $data['ClassName'];
        $classCode = $data['ClassCode'];
        $stream = $data['Stream'] ;
        $academicYearId = $data['AcademicYearID'];
        $maxStrength = $data['MaxStrength'] ?? 50;
        $schoolId = $data['SchoolID'] ?? null;
        $createdBy = $data['Username'] ?? null;

        $query = "INSERT INTO Tx_Classes (ClassName, ClassCode, Stream, AcademicYearID, MaxStrength, SchoolID, CreatedAt, CreatedBy)
            VALUES (:class_name, :class_code, :stream, :academic_year_id, :max_strength, :school_id, :created_at, :created_by)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_name', $className);
        $stmt->bindParam(':class_code', $classCode);
        $stmt->bindParam(':stream', $stream);
        $stmt->bindParam(':academic_year_id', $academicYearId);
        $stmt->bindParam(':max_strength', $maxStrength);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':created_at', $data['created_at']);
        $stmt->bindParam(':created_by', $createdBy);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateClass($id, $data) {
        $data['UpdatedAt'] = date('Y-m-d H:i:s');

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

    public function getAllClasses($academic_year_id = null, $school_id = null) {
    $query = "SELECT c.ClassID,c.ClassName,c.ClassCode,c.Stream,c.MaxStrength,c.SchoolID,c.Status, ay.AcademicYearName
          FROM Tx_Classes c
          INNER JOIN Tm_AcademicYears ay ON c.AcademicYearID = ay.AcademicYearID";

        if ($academic_year_id && $school_id) {
            $query .= " WHERE c.AcademicYearID = :academic_year_id and c.SchoolID = :school_id";
        }
        
    $query .= " ORDER BY c.ClassName, c.ClassCode";
        
        $stmt = $this->conn->prepare($query);
        
        if ($academic_year_id) {
            $stmt->bindParam(':academic_year_id', $academic_year_id);
        }
        if ($school_id) {
            $stmt->bindParam(':school_id', $school_id);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getClassById($id) {
    $query = "SELECT c.ClassID,c.ClassName,c.ClassCode,c.Stream,c.MaxStrength,c.SchoolID,c.Status, ay.AcademicYearName
          FROM Tx_Classes c
          INNER JOIN Tm_AcademicYears ay ON c.AcademicYearID = ay.AcademicYearID
          WHERE c.ClassID = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function deleteClass($id) {
    $query = "DELETE FROM Tx_Classes WHERE ClassID = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
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
        $query = "SELECT s.*, ay.AcademicYearName FROM Tx_Sections s
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

        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Weekly Offs and Holidays
    public function getWeeklyOffsByAcademicYear($schoolId, $academicYearId = null) {
        $query = "SELECT WeeklyOffID, AcademicYearID, DayOfWeek FROM Tx_WeeklyOffs WHERE SchoolID = :school_id";
        if ($academicYearId) $query .= " AND AcademicYearID = :academic_year_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        if ($academicYearId) $stmt->bindParam(':academic_year_id', $academicYearId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setWeeklyOffs($schoolId, $academicYearId, $daysArray, $username = null) {
        // daysArray expected as array of integers (1-7)
        try {
            $this->conn->beginTransaction();
            // delete existing for this academic year
            $del = $this->conn->prepare("DELETE FROM Tx_WeeklyOffs WHERE SchoolID = :school_id AND AcademicYearID = :academic_year_id");
            $del->execute([':school_id' => $schoolId, ':academic_year_id' => $academicYearId]);

            $ins = $this->conn->prepare("INSERT INTO Tx_WeeklyOffs (AcademicYearID, SchoolID, DayOfWeek, CreatedBy, CreatedAt) VALUES (:ay, :school, :dow, :user, :created)");
            $created = date('Y-m-d H:i:s');
            foreach ($daysArray as $d) {
                $dow = (int)$d;
                $ok = $ins->execute([':ay' => $academicYearId, ':school' => $schoolId, ':dow' => $dow, ':user' => $username, ':created' => $created]);
                if ($ok === false) {
                    $err = $ins->errorInfo();
                    throw new \Exception('Insert weekly off failed: ' . ($err[2] ?? json_encode($err)));
                }
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

    public function getHolidaysByAcademicYear($schoolId, $academicYearId = null) {
        $query = "SELECT HolidayID, AcademicYearID, Date, Title, Type FROM Tx_Holidays WHERE SchoolID = :school_id";
        if ($academicYearId) $query .= " AND AcademicYearID = :academic_year_id";
        $query .= " ORDER BY Date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $schoolId);
        if ($academicYearId) $stmt->bindParam(':academic_year_id', $academicYearId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createHoliday($data) {
        $query = "INSERT INTO Tx_Holidays (AcademicYearID, SchoolID, Date, Title, Type, CreatedBy, CreatedAt) VALUES (:ay, :school, :date, :title, :type, :user, :created)";
        $stmt = $this->conn->prepare($query);
        $created = date('Y-m-d H:i:s');
        $stmt->bindParam(':ay', $data['AcademicYearID']);
        $stmt->bindParam(':school', $data['SchoolID']);
        $stmt->bindParam(':date', $data['Date']);
        $stmt->bindParam(':title', $data['Title']);
        $stmt->bindParam(':type', $data['Type']);
        $stmt->bindParam(':user', $data['CreatedBy']);
        $stmt->bindParam(':created', $created);
        if ($stmt->execute()) return $this->conn->lastInsertId();
        return false;
    }

    public function getHolidayById($id) {
        $query = "SELECT HolidayID, AcademicYearID, SchoolID, Date, Title, Type FROM Tx_Holidays WHERE HolidayID = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function deleteHoliday($id, $schoolId) {
        $query = "DELETE FROM Tx_Holidays WHERE HolidayID = :id AND SchoolID = :school_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':school_id', $schoolId);
        return $stmt->execute();
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
        $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), (new \DateTime($end))->modify('+1 day'));

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
        $query = "SELECT s.*, ay.AcademicYearName FROM Tx_Sections s
            LEFT JOIN Tm_AcademicYears ay ON s.AcademicYearID = ay.AcademicYearID
            WHERE s.SectionID = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function createSection($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $query = "INSERT INTO Tx_Sections (SectionName, SectionDisplayName, SchoolID, AcademicYearID, ClassID, Status, CreatedAt, CreatedBy, UpdatedAt)
            VALUES (:section_name, :section_display_name, :school_id, :academic_year_id, :class_id, :status, :created_at, :created_by, :updated_at)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_name', $data['section_name']);
        $stmt->bindParam(':section_display_name', $data['section_display_name']);
        $stmt->bindParam(':school_id', $data['school_id']);
        $stmt->bindParam(':academic_year_id', $data['academic_year_id']);
        $stmt->bindParam(':class_id', $data['class_id']);
        $status = $data['status'] ?? 'Active';
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_at', $data['created_at']);
        $created_by = $data['created_by'] ?? 'System';
        $stmt->bindParam(':created_by', $created_by);
        $stmt->bindParam(':updated_at', $data['updated_at']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateSection($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $fields = array_keys($data);
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
        $query = "DELETE FROM Tx_Sections WHERE SectionID = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
