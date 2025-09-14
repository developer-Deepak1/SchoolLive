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

    // Weekly Offs and Holidays Methods
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

            // Recompute and persist working days for this academic year & school
            try {
                $computed = $this->getcomputeWorkingDaysByMonth($this->getAcademicYearRange($academicYearId, $schoolId)['start'], $this->getAcademicYearRange($academicYearId, $schoolId)['end'], $desired, $this->getHolidays($academicYearId, $schoolId));
                if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                    $this->upsertWorkingDaysForAcademicYear($academicYearId, $schoolId, $computed['months'], $computed['workingDays'], $username);
                }
            } catch (\Throwable $_) { /* non-fatal */ }

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

        // Look for existing holiday (include Type to avoid overwriting explicit WorkingDay entries)
        $checkSql = "SELECT HolidayID, Type, IFNULL(IsActive, TRUE) AS IsActive FROM Tx_Holidays WHERE SchoolID = :school AND AcademicYearID = :ay AND Date = :date LIMIT 1";
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
                // If update succeeds, we did mutate DB and should recompute; otherwise return false
                if ($upd->execute($params)) {
                    // Recompute and persist working days because we changed an existing holiday
                    try {
                        // If this change marks the date as a WorkingDay, recompute only that month for efficiency
                        if (($type ?? null) === 'WorkingDay' && $date) {
                            $mStart = (new DateTime($date))->format('Y-m-01');
                            $mEnd = (new DateTime($date))->format('Y-m-t');
                            $computed = $this->getcomputeWorkingDaysByMonth($mStart, $mEnd, $this->getWeeklyOffs($ay, $school), $this->getHolidays($ay, $school));
                            if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                                $this->upsertWorkingDaysForAcademicYear($ay, $school, $computed['months'], $computed['workingDays'], $user);
                            }
                        } else {
                            $range = $this->getAcademicYearRange($ay, $school);
                            $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($ay, $school), $this->getHolidays($ay, $school));
                            if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                                $this->upsertWorkingDaysForAcademicYear($ay, $school, $computed['months'], $computed['workingDays'], $user);
                            }
                        }
                    } catch (\Throwable $_) {}
                    return $existing['HolidayID'];
                }
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
        if ($ins->execute()) {
            $lastId = $this->conn->lastInsertId();
            // Recompute working days for this academic year & school
            try {
                // If inserted holiday was marked as WorkingDay, recompute only that month for efficiency
                if (($type ?? null) === 'WorkingDay' && $date) {
                    $mStart = (new DateTime($date))->format('Y-m-01');
                    $mEnd = (new DateTime($date))->format('Y-m-t');
                    $computed = $this->getcomputeWorkingDaysByMonth($mStart, $mEnd, $this->getWeeklyOffs($ay, $school), $this->getHolidays($ay, $school));
                    if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                        $this->upsertWorkingDaysForAcademicYear($ay, $school, $computed['months'], $computed['workingDays'], $user);
                    }
                } else {
                    $range = $this->getAcademicYearRange($ay, $school);
                    $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($ay, $school), $this->getHolidays($ay, $school));
                    if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                        $this->upsertWorkingDaysForAcademicYear($ay, $school, $computed['months'], $computed['workingDays'], $user);
                    }
                }
            } catch (\Throwable $_) {}
            return $lastId;
        }
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
        $ok = $stmt->execute($params);
        if ($ok) {
            // Recompute working days for academic year(s) affected. Try to fetch academic year of this holiday
            try {
                $h = $this->getHolidayById($id);
                $ay = $h['AcademicYearID'] ?? null;
                if ($ay) {
                    $range = $this->getAcademicYearRange($ay, $schoolId);
                    $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($ay, $schoolId), $this->getHolidays($ay, $schoolId));
                    if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                        $this->upsertWorkingDaysForAcademicYear($ay, $schoolId, $computed['months'], $computed['workingDays'], $deletedBy);
                    }
                }
            } catch (\Throwable $_) {}
        }
        return $ok;
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
        $ok = $stmt->execute($params);
        if ($ok) {
            // Recompute working days for the affected academic year
            try {
                $h = $this->getHolidayById($id);
                $ay = $h['AcademicYearID'] ?? null;
                if ($ay) {
                    $range = $this->getAcademicYearRange($ay, $schoolId);
                    $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($ay, $schoolId), $this->getHolidays($ay, $schoolId));
                    if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                        $this->upsertWorkingDaysForAcademicYear($ay, $schoolId, $computed['months'], $computed['workingDays'], $data['UpdatedBy'] ?? null);
                    }
                }
            } catch (\Throwable $_) {}
        }
        return $ok;
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

            $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
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

            // Recompute and persist working days only if we actually created any new holiday rows
            if (!empty($createdDates)) {
                try {
                    $range = $this->getAcademicYearRange($ay, $schoolId);
                    $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($ay, $schoolId), $this->getHolidays($ay, $schoolId));
                    if ($computed && isset($computed['months']) && isset($computed['workingDays'])) {
                        $this->upsertWorkingDaysForAcademicYear($ay, $schoolId, $computed['months'], $computed['workingDays'], $createdBy);
                    }
                } catch (\Throwable $_) {}
            }

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

    public function getAcademicYearRange(?int $academicYearId, int $schoolId): array {
        $start = null; $end = null; $ayId = $academicYearId;
        if ($academicYearId) {
            $q = $this->conn->prepare("SELECT AcademicYearID, StartDate, EndDate FROM Tm_AcademicYears WHERE AcademicYearID = :ay LIMIT 1");
            $q->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
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

                // If a date is explicitly marked as a 'WorkingDay', it overrides weekly offs
                $isWorkingDay = $h && (($h['type'] ?? 'Holiday') === 'WorkingDay');
                $isOff = in_array($dow, $weeklyOffs, true);
                $isHoliday = $h && (($h['type'] ?? 'Holiday') === 'Holiday');

                if ($isWorkingDay) {
                    $wd++;
                } else if (!$isOff && !$isHoliday) {
                    $wd++;
                }
            }
            $workingDays[] = $wd;
            $c->modify('+1 month');
        }

        return ['months' => $months, 'labels' => $labels, 'workingDays' => $workingDays];
    }

    /**
     * Upsert computed working days into Tx_WorkingDays for the given academic year and school.
     * $months: array of YYYY-MM
     * $workingDays: parallel array of ints
     */
    private function upsertWorkingDaysForAcademicYear(int $academicYearId, int $schoolId, array $months, array $workingDays, $username = null) {
        if (count($months) !== count($workingDays)) return false;
        try {
            $this->conn->beginTransaction();
            $sel = $this->conn->prepare("SELECT WorkingDaysID FROM Tx_WorkingDays WHERE AcademicYearID = :ay AND SchoolID = :school AND Month = :month LIMIT 1");
            $ins = $this->conn->prepare("INSERT INTO Tx_WorkingDays (AcademicYearID, SchoolID, Month, WorkingDays, CreatedBy, CreatedAt) VALUES (:ay, :school, :month, :wd, :user, :created)");
            $upd = $this->conn->prepare("UPDATE Tx_WorkingDays SET WorkingDays = :wd, UpdatedBy = :user, UpdatedAt = :updated WHERE WorkingDaysID = :id");
            $now = date('Y-m-d H:i:s');
            foreach ($months as $i => $m) {
                $wd = (int)($workingDays[$i] ?? 0);
                $sel->execute([':ay' => $academicYearId, ':school' => $schoolId, ':month' => $m]);
                $row = $sel->fetch();
                if ($row && isset($row['WorkingDaysID'])) {
                    $upd->execute([':wd' => $wd, ':user' => $username, ':updated' => $now, ':id' => $row['WorkingDaysID']]);
                } else {
                    $ins->execute([':ay' => $academicYearId, ':school' => $schoolId, ':month' => $m, ':wd' => $wd, ':user' => $username, ':created' => $now]);
                }
            }
            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            try { $this->conn->rollBack(); } catch (\Throwable $_) {}
            error_log('[AcademicModel::upsertWorkingDaysForAcademicYear] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get monthly working days for an academic year & school.
     * Returns array with keys: 'months' => ["YYYY-MM"], 'workingDays' => [ints], 'labels' => [short month names]
     * Will try to read from Tx_WorkingDays; if rows missing, will compute fallback via getcomputeWorkingDaysByMonth
     */
    public function getMonthlyWorkingDays(int $academicYearId, int $schoolId): array {
        // Try to read persisted rows
        $months = [];
        $workingDays = [];
        try {
            $q = $this->conn->prepare("SELECT Month, WorkingDays FROM Tx_WorkingDays WHERE AcademicYearID = :ay AND SchoolID = :school AND IFNULL(IsActive,TRUE)=1 ORDER BY Month");
            $q->execute([':ay' => $academicYearId, ':school' => $schoolId]);
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $months[] = $r['Month'];
                $workingDays[] = (int)$r['WorkingDays'];
            }
        } catch (\Throwable $_) { }

        // If persisted data not available, compute full range
        if (empty($months)) {
            $range = $this->getAcademicYearRange($academicYearId, $schoolId);
            if (!$range['start'] || !$range['end']) return ['months' => [], 'workingDays' => [], 'labels' => []];
            $computed = $this->getcomputeWorkingDaysByMonth($range['start'], $range['end'], $this->getWeeklyOffs($academicYearId, $schoolId), $this->getHolidays($academicYearId, $schoolId));
            return $computed;
        }

        // Build labels (short month names)
        $labels = array_map(function($m){ return (new DateTime($m.'-01'))->format('M'); }, $months);
        return ['months' => $months, 'workingDays' => $workingDays, 'labels' => $labels];
    }
    public function getAttendanceCountsByMonth(int $schoolId, int $employeeId, string $start, string $end, ?int $academicYearId = null): array {
        // detect attendance table
        $attendanceTable = null;
        try {
            $stmt = $this->conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
            $stmt->bindValue(':t','Tx_Employee_Attendance'); $stmt->execute();
            if ($stmt->fetchColumn()) $attendanceTable = 'Tx_Employee_Attendance';
            else {
                $stmt->bindValue(':t','Tx_Employees_Attendance');
                $stmt->execute();
                if ($stmt->fetchColumn()) $attendanceTable = 'Tx_Employees_Attendance';
            }
        } catch (\Throwable $_) { }
        if (!$attendanceTable) return [];

        $sql = "SELECT DATE_FORMAT(Date,'%Y-%m') ym, SUM(CASE WHEN Status='Present' THEN 1 ELSE 0 END) p
                FROM " . $attendanceTable . "
                WHERE SchoolID = :school AND EmployeeID = :eid AND Date >= :start AND Date <= :end" . ($academicYearId?" AND AcademicYearID = :ay":"") . " GROUP BY ym";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $stmt->bindValue(':eid',$employeeId,PDO::PARAM_INT);
        $stmt->bindValue(':start',$start);
        $stmt->bindValue(':end',$end);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);

        try { $stmt->execute(); } catch (\Throwable $_) { return []; }
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[$r['ym']] = (int)$r['p']; }
        return $map;
    }

    public function getMonthlyAttendanceForEmployee(int $schoolId, int $employeeId, ?int $academicYearId = null, ?string $employeeJoinDate = null): array {
        $range = $this->getAcademicYearRange($academicYearId, $schoolId);
        if (!$range['start'] || !$range['end']) return ['labels'=>[], 'datasets'=>[]];
       
        // Fetch joining date if caller didn't provide
        if (!$employeeJoinDate) {
            try {
                $q = $this->conn->prepare("SELECT JoiningDate FROM Tx_Employees WHERE EmployeeID = :eid LIMIT 1");
                $q->bindValue(':eid',$employeeId,PDO::PARAM_INT);
                $q->execute();
                $r = $q->fetch(PDO::FETCH_ASSOC);
                $employeeJoinDate = $r['JoiningDate'] ?? null;
            } catch (\Throwable $_) { $employeeJoinDate = null; }
        }

        $ayId = $range['academicYearId'];
        $weeklyOffs = $ayId ? $this->getWeeklyOffs($ayId, $schoolId) : [];
        $holidaysMap = $ayId ? $this->getHolidays($ayId, $schoolId) : [];

        // Compute the months and base working days for each month
        $computed = $this->getMonthlyWorkingDays($ayId, $schoolId);

        // If employee joined after academic year start, adjust working days per month: months entirely before joining -> 0; first month partial.
        if ($employeeJoinDate) {
            $joinDt = new DateTime($employeeJoinDate);
            $ayStartDt = new DateTime($range['start']);
            // iterate months and recompute per-month working days using per-day scan but limited by join date
            $adjustedWorking = [];
            foreach ($computed['months'] as $i => $ym) {
                $monthStart = new DateTime($ym . '-01');
                $monthEnd = new DateTime($monthStart->format('Y-m-t'));
                // determine effective day range for this month
                $effStart = $monthStart < $joinDt ? ($joinDt > $monthEnd ? null : $joinDt) : $monthStart;
                if ($effStart === null) { $adjustedWorking[] = 0; continue; }
                $period = new DatePeriod(new DateTime($effStart->format('Y-m-d')), new DateInterval('P1D'), $monthEnd->modify('+1 day'));
                $wd = 0;
                foreach ($period as $d) {
                    $ymd = $d->format('Y-m-d');
                    $dow = (int)$d->format('N');
                    // Respect explicit 'WorkingDay' entries: they override weekly offs
                    $h = $holidaysMap[$ymd] ?? null;
                    if ($h && (($h['type'] ?? 'Holiday') === 'WorkingDay')) { $wd++; continue; }
                    $isOff = in_array($dow, $weeklyOffs, true);
                    $isHoliday = $h && ($h['type'] ?? 'Holiday') === 'Holiday';
                    if (!$isOff && !$isHoliday) $wd++;
                }
                $adjustedWorking[] = $wd;
            }
            $computed['workingDays'] = $adjustedWorking;
        }

        $attendanceMap = $this->getAttendanceCountsByMonth($schoolId, $employeeId, $range['start'], $range['end'], $ayId);

        $present = array_map(function($ym) use ($attendanceMap) { return $attendanceMap[$ym] ?? 0; }, $computed['months']);

        return [
            'labels' => $computed['labels'],
            'datasets' => [
                [ 'label' => 'Working Days', 'data' => $computed['workingDays'], 'backgroundColor' => '#10b981' ],
                [ 'label' => 'Present', 'data' => $present, 'backgroundColor' => '#92dbc2ff' ],
            ]
        ];
    }
    public function getMonthlyAttendance(int $schoolId, int $studentId, ?int $academicYearId, int $months = 12): array {

        $range = $this->getAcademicYearRange( $academicYearId, $schoolId);
        if (!$range['start'] || !$range['end']) return ['labels'=>[],'datasets'=>[]];

        $ayId = $range['academicYearId'];
        $weeklyOffs = $ayId ? $this->getWeeklyOffs($ayId, $schoolId) : [];
        $holidaysMap = $ayId ? $this->getHolidays($ayId, $schoolId) : [];

        // Compute the months and base working days for each month
        $computed = $this->getMonthlyWorkingDays($ayId, $schoolId);

            // Adjust working days for admission date if present. Clamp admission to academic start when earlier.
            try {
                $admQ = $this->conn->prepare("SELECT AdmissionDate FROM Tx_Students WHERE SchoolID=:school AND StudentID=:sid LIMIT 1");
                $admQ->bindValue(':school',$schoolId,PDO::PARAM_INT);
                $admQ->bindValue(':sid',$studentId,PDO::PARAM_INT);
                $admQ->execute();
                $adDate = $admQ->fetchColumn();
                if ($adDate) {
                    $joinDt = new DateTime($adDate);
                    $rangeStartDt = new DateTime($range['start']);
                    if ($joinDt < $rangeStartDt) $joinDt = $rangeStartDt;

                    $adjustedWorking = [];
                    foreach ($computed['months'] as $i => $ym) {
                        $monthStart = new DateTime($ym . '-01');
                        $monthEnd = new DateTime($monthStart->format('Y-m-t'));
                        $effStart = $monthStart < $joinDt ? ($joinDt > $monthEnd ? null : $joinDt) : $monthStart;
                        if ($effStart === null) { $adjustedWorking[] = 0; continue; }
                        $period = new DatePeriod(new DateTime($effStart->format('Y-m-d')), new DateInterval('P1D'), $monthEnd->modify('+1 day'));
                        $wd = 0;
                        foreach ($period as $d) {
                            $ymd = $d->format('Y-m-d');
                            $dow = (int)$d->format('N');
                            $h = $holidaysMap[$ymd] ?? null;
                            if ($h && (($h['type'] ?? 'Holiday') === 'WorkingDay')) { $wd++; continue; }
                            $isOff = in_array($dow, $weeklyOffs, true);
                            $isHoliday = $h && ($h['type'] ?? 'Holiday') === 'Holiday';
                            if (!$isOff && !$isHoliday) $wd++;
                        }
                        $adjustedWorking[] = $wd;
                    }
                    $computed['workingDays'] = $adjustedWorking;
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Fetch counts of Present per month for students
            $sql = "SELECT DATE_FORMAT(Date,'%Y-%m') ym, SUM(CASE WHEN Status='Present' THEN 1 ELSE 0 END) p
                    FROM Tx_Students_Attendance
                    WHERE SchoolID=:school AND StudentID=:sid AND Date >= :start AND Date <= :end" . ($ayId?" AND AcademicYearID=:ay":"") . " GROUP BY ym";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
            $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT);
            $stmt->bindValue(':start',$range['start']);
            $stmt->bindValue(':end',$range['end']);
            if ($ayId) $stmt->bindValue(':ay',$ayId,PDO::PARAM_INT);
            try { $stmt->execute(); } catch (\Throwable $e) { return ['labels'=>[],'datasets'=>[]]; }
            $map = []; while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[$r['ym']] = (int)$r['p']; }

            $present = array_map(function($ym) use ($map) { return $map[$ym] ?? 0; }, $computed['months']);

            $percent = [];
            foreach ($present as $i => $p) {
                $wd = $computed['workingDays'][$i] ?? 0;
                $percent[] = $wd > 0 ? round($p / $wd * 100, 2) : 0;
            }

            return [
                'labels' => $computed['labels'],
                'datasets' => [
                    [ 'label' => 'Working Days', 'data' => $computed['workingDays'], 'backgroundColor' => '#10b981' ],
                    [ 'label' => 'Present', 'data' => $present, 'backgroundColor' => '#92dbc2ff' ]
                ]
            ];
    }
}
