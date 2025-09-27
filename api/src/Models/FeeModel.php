<?php

namespace SchoolLive\Models;

use SchoolLive\Models\Model;
use PDO;

class FeeModel extends Model
{
    protected $table = 'Tx_fees';
    protected $primaryKey = 'FeeID';

    /**
     * Get all fees for a school and academic year with their class-section mappings
     */
    public function getFees($schoolId, $academicYearId)
    {
        // Use the new method that includes mappings
        return $this->getAllFeesWithMappings($schoolId, $academicYearId);
    }

    /**
     * Get fee by ID
     */
    public function getFeeById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new fee with class-section mappings and schedules
     */
    public function createFee($data)
    {
        try {
            $this->conn->beginTransaction();
            
            // Create main fee record using DB-native keys
            $sql = "INSERT INTO {$this->table} 
                (FeeName, IsActive, SchoolID, AcademicYearID, CreatedBy) 
                VALUES (:FeeName, :IsActive, :SchoolID, :AcademicYearID, :CreatedBy)";

            $stmt = $this->conn->prepare($sql);
            $feeName = is_array($data['FeeName']) ? reset($data['FeeName']) : $data['FeeName'];
            $stmt->bindValue(':FeeName', $feeName);
            $stmt->bindValue(':IsActive', !empty($data['IsActive']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':SchoolID', $data['SchoolID'], PDO::PARAM_INT);
            $stmt->bindValue(':AcademicYearID', $data['AcademicYearID'], PDO::PARAM_INT);
            $createdBy = is_array($data['CreatedBy']) ? reset($data['CreatedBy']) : $data['CreatedBy'];
            $stmt->bindValue(':CreatedBy', $createdBy);

            $stmt->execute();
            $feeId = $this->conn->lastInsertId();
            
            // Create class-section mappings if provided
            if (!empty($data['ClassSectionMapping'])) {
                $this->createClassSectionMappings($feeId, $data['ClassSectionMapping'], $createdBy);
            }

            // Create schedule if provided
            if (!empty($data['Schedule'])) {
                $this->createFeeSchedule($feeId, $data['Schedule'], $createdBy);
            }
            $this->conn->commit();
            return $feeId;
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Update existing fee with class-section mappings and schedules
     */
    public function updateFee($id, $data)
    {
        try {
            $this->conn->beginTransaction();
            // Normalize incoming id to integer - some routers may pass ['id' => '6']
            if (is_array($id)) {
                if (isset($id['id'])) {
                    $id = (int)$id['id'];
                } else {
                    $id = (int)reset($id);
                }
            } else {
                $id = (int)$id;
            }
            error_log('FeeModel::updateFee - normalized fee id: ' . var_export($id, true) . ' (type: ' . gettype($id) . ')');
            
            $fields = [];
            $params = [':id' => $id];

            // Dynamic field updates using DB-native keys
            $allowedFields = [
                'FeeName' => 'FeeName',
                'IsActive' => 'IsActive',
                'UpdatedBy' => 'UpdatedBy'
            ];

            foreach ($allowedFields as $key => $column) {
                if (isset($data[$key])) {
                    $fields[] = "{$column} = :{$key}";
                    if ($key === 'IsActive') {
                        $params[":{$key}"] = !empty($data[$key]) ? 1 : 0;
                    } else {
                        $params[":{$key}"] = $data[$key];
                    }
                }
            }

            if (!empty($fields)) {
                // Always update UpdatedAt
                $fields[] = "UpdatedAt = NOW()";

                $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
                
                $stmt = $this->conn->prepare($sql);
                
                foreach ($params as $param => $value) {
                    // Normalize value: unwrap arrays recursively to first scalar, otherwise fallback
                    while (is_array($value) && !empty($value)) {
                        $value = reset($value);
                    }

                    // If still an array or object, convert to JSON string to avoid array-to-string notices
                    if (is_array($value)) {
                        $value = json_encode($value);
                    } elseif (is_object($value)) {
                        if (method_exists($value, '__toString')) {
                            $value = (string)$value;
                        } else {
                            $value = json_encode($value);
                        }
                    }

                    // Bind types explicitly to avoid PDO errors
                    if ($value === null) {
                        $stmt->bindValue($param, null, PDO::PARAM_NULL);
                    } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                        $stmt->bindValue($param, (int)$value, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($param, $value);
                    }
                }
                
                $stmt->execute();
            }
            
            // Update class-section mappings if provided: mark all inactive then upsert incoming mappings (which will set IsActive=1)
            if (isset($data['ClassSectionMapping'])) {
                    $incoming = $data['ClassSectionMapping'] ?? [];

                    // Build a list of incoming ClassID_SectionID pairs for selective deactivation
                    $pairs = [];
                    foreach ($incoming as $m) {
                        $c = isset($m['ClassID']) ? (int)$m['ClassID'] : (isset($m['classId']) ? (int)$m['classId'] : 0);
                        $s = isset($m['SectionID']) ? (int)$m['SectionID'] : (isset($m['sectionId']) ? (int)$m['sectionId'] : 0);
                        $pairs[] = $c . '_' . $s;
                    }

                    if (count($pairs) > 0) {
                        // Deactivate only those mappings not present in the incoming list
                        // Use named placeholders for the pair list to avoid mixing positional and named params
                        $phNames = [];
                        foreach ($pairs as $i => $p) {
                            $phNames[] = ':pair' . $i;
                        }
                        $placeholders = implode(',', $phNames);
                        $sqlOff = "UPDATE Tx_fee_class_section_mapping SET IsActive = 0, UpdatedAt = NOW(), UpdatedBy = :UpdatedBy WHERE FeeID = :FeeID AND CONCAT(ClassID, '_', SectionID) NOT IN ($placeholders)";
                        $stmtOff = $this->conn->prepare($sqlOff);
                        // Bind UpdatedBy and FeeID as named params
                        $stmtOff->bindValue(':UpdatedBy', $data['UpdatedBy'] ?? 'system');
                        $stmtOff->bindValue(':FeeID', $id, PDO::PARAM_INT);
                        // Bind each pair by its named placeholder
                        foreach ($pairs as $i => $p) {
                            $stmtOff->bindValue(':pair' . $i, $p);
                        }
                        $okOff = $stmtOff->execute();
                        $offCount = method_exists($stmtOff, 'rowCount') ? $stmtOff->rowCount() : null;
                        error_log('FeeModel::updateFee - marked mappings inactive (selective): ' . ($okOff ? 'true' : 'false') . ' - rowsUpdated: ' . var_export($offCount, true));
                    } else {
                        // No incoming mappings — deactivate all
                        $sqlAllOff = "UPDATE Tx_fee_class_section_mapping SET IsActive = 0, UpdatedAt = NOW(), UpdatedBy = :UpdatedBy WHERE FeeID = :FeeID";
                        $stmtAllOff = $this->conn->prepare($sqlAllOff);
                        $stmtAllOff->bindValue(':UpdatedBy', $data['UpdatedBy'] ?? 'system');
                        $stmtAllOff->bindValue(':FeeID', $id, PDO::PARAM_INT);
                        $okAllOff = $stmtAllOff->execute();
                        $allOffCount = method_exists($stmtAllOff, 'rowCount') ? $stmtAllOff->rowCount() : null;
                        error_log('FeeModel::updateFee - marked all mappings inactive (pre-upsert): ' . ($okAllOff ? 'true' : 'false') . ' - rowsUpdated: ' . var_export($allOffCount, true));
                    }

                    // Upsert incoming mappings - this will update existing by MappingID or by unique key and insert new rows
                    $this->upsertClassSectionMappings($id, $incoming, $data['UpdatedBy'] ?? 'system');
            }
            
            // Update schedule if provided
            // Update schedule if provided
            if (isset($data['Schedule'])) {
                $schedPayload = $data['Schedule'];

                // If payload is empty => delete existing schedule(s)
                if (empty($schedPayload)) {
                    $this->deleteFeeSchedule($id);
                } else {
                    // Prefer explicit ScheduleID provided by caller
                    $scheduleId = $schedPayload['ScheduleID'] ?? ($schedPayload['scheduleId'] ?? null);

                    // If ScheduleID not provided, try to find latest existing schedule for this fee
                    if (!$scheduleId) {
                        $sqlFind = "SELECT ScheduleID FROM Tx_fees_schedules WHERE FeeID = :FeeID ORDER BY ScheduleID DESC LIMIT 1";
                        $stmtFind = $this->conn->prepare($sqlFind);
                        $stmtFind->bindValue(':FeeID', $id, PDO::PARAM_INT);
                        $stmtFind->execute();
                        $found = $stmtFind->fetch(PDO::FETCH_ASSOC);
                        if ($found && isset($found['ScheduleID'])) {
                            $scheduleId = (int)$found['ScheduleID'];
                        }
                    }

                    if ($scheduleId) {
                        // Update the existing schedule row
                        $this->updateFeeSchedule((int)$scheduleId, $schedPayload, $data['UpdatedBy'] ?? 'system');
                    } else {
                        // No existing schedule, create a new one
                        $this->createFeeSchedule($id, $schedPayload, $data['UpdatedBy'] ?? 'system');
                    }
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Delete fee
     */
    public function deleteFee($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get upcoming due fees based on schedule
     */
    public function getUpcomingDueFees($schoolId, $academicYearId, $days = 7)
    {
        // Use schedule's EndDate for due calculations
        $sql = "SELECT f.*, s.EndDate as DueDate, s.ScheduleType FROM {$this->table} f
            JOIN Tx_fees_schedules s ON s.FeeID = f.FeeID
            WHERE f.SchoolID = :schoolId AND f.AcademicYearID = :academicYearId
            AND f.IsActive = 1 AND s.EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY s.EndDate ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue fees based on schedule
     */
    public function getOverdueFees($schoolId, $academicYearId)
    {
        $sql = "SELECT f.*, s.EndDate as DueDate, s.ScheduleType FROM {$this->table} f
            JOIN Tx_fees_schedules s ON s.FeeID = f.FeeID
            WHERE f.SchoolID = :schoolId AND f.AcademicYearID = :academicYearId
            AND f.IsActive = 1 AND s.EndDate < CURDATE()
            ORDER BY s.EndDate ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if fee name exists for school/academic year
     */
    public function feeNameExists($feeName, $schoolId, $academicYearId, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE FeeName = :feeName AND SchoolID = :schoolId AND AcademicYearID = :academicYearId";
        $params = [
            ':feeName' => $feeName,
            ':schoolId' => $schoolId,
            ':academicYearId' => $academicYearId
        ];

        if ($excludeId) {
            $sql .= " AND {$this->primaryKey} != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Create fee schedule
     */
    public function createFeeSchedule($feeId, $scheduleData, $createdBy)
    {
        $sql = "INSERT INTO Tx_fees_schedules 
                (FeeID, ScheduleType, IntervalMonths, DayOfMonth, StartDate, EndDate, NextDueDate, ReminderDaysBefore, CreatedBy) 
                VALUES (:FeeID, :ScheduleType, :IntervalMonths, :DayOfMonth, :StartDate, :EndDate, :NextDueDate, :ReminderDaysBefore, :CreatedBy)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmt->bindValue(':ScheduleType', $scheduleData['ScheduleType'] ?? 'OneTime');
        // Bind IntervalMonths and DayOfMonth as NULL when not provided
        if (array_key_exists('IntervalMonths', $scheduleData) && $scheduleData['IntervalMonths'] !== null) {
            $stmt->bindValue(':IntervalMonths', (int)$scheduleData['IntervalMonths'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':IntervalMonths', null, PDO::PARAM_NULL);
        }

        if (array_key_exists('DayOfMonth', $scheduleData) && $scheduleData['DayOfMonth'] !== null) {
            $stmt->bindValue(':DayOfMonth', (int)$scheduleData['DayOfMonth'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':DayOfMonth', null, PDO::PARAM_NULL);
        }

        // Dates: bind as NULL or string
        $start = $scheduleData['StartDate'] ?? null;
        if ($start === null) {
            $stmt->bindValue(':StartDate', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':StartDate', $start);
        }

        $end = $scheduleData['EndDate'] ?? null;
        if ($end === null) {
            $stmt->bindValue(':EndDate', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':EndDate', $end);
        }

        $next = $scheduleData['NextDueDate'] ?? null;
        if ($next === null) {
            $stmt->bindValue(':NextDueDate', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':NextDueDate', $next);
        }

        if (array_key_exists('ReminderDaysBefore', $scheduleData) && $scheduleData['ReminderDaysBefore'] !== null) {
            $stmt->bindValue(':ReminderDaysBefore', (int)$scheduleData['ReminderDaysBefore'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':ReminderDaysBefore', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':CreatedBy', $createdBy);
        
        return $stmt->execute();
    }

    /**
     * Delete fee schedule
     */
    public function deleteFeeSchedule($feeId)
    {
        $sql = "DELETE FROM Tx_fees_schedules WHERE FeeID = :feeId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':feeId', $feeId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update an existing fee schedule row by ScheduleID
     */
    public function updateFeeSchedule($scheduleId, $scheduleData, $updatedBy)
    {
        // Build dynamic set clauses only for provided keys to avoid overwriting with NULL unintentionally
        $fields = [];
        $params = [':ScheduleID' => $scheduleId];

        if (isset($scheduleData['ScheduleType'])) {
            $fields[] = 'ScheduleType = :ScheduleType';
            $params[':ScheduleType'] = $scheduleData['ScheduleType'];
        }

        if (array_key_exists('IntervalMonths', $scheduleData)) {
            $fields[] = 'IntervalMonths = :IntervalMonths';
            $params[':IntervalMonths'] = $scheduleData['IntervalMonths'] === null ? null : (int)$scheduleData['IntervalMonths'];
        }

        if (array_key_exists('DayOfMonth', $scheduleData)) {
            $fields[] = 'DayOfMonth = :DayOfMonth';
            $params[':DayOfMonth'] = $scheduleData['DayOfMonth'] === null ? null : (int)$scheduleData['DayOfMonth'];
        }

        if (array_key_exists('StartDate', $scheduleData)) {
            $fields[] = 'StartDate = :StartDate';
            $params[':StartDate'] = $scheduleData['StartDate'] === null ? null : $scheduleData['StartDate'];
        }

        if (array_key_exists('EndDate', $scheduleData)) {
            $fields[] = 'EndDate = :EndDate';
            $params[':EndDate'] = $scheduleData['EndDate'] === null ? null : $scheduleData['EndDate'];
        }

        if (array_key_exists('NextDueDate', $scheduleData)) {
            $fields[] = 'NextDueDate = :NextDueDate';
            $params[':NextDueDate'] = $scheduleData['NextDueDate'] === null ? null : $scheduleData['NextDueDate'];
        }

        if (array_key_exists('ReminderDaysBefore', $scheduleData)) {
            $fields[] = 'ReminderDaysBefore = :ReminderDaysBefore';
            $params[':ReminderDaysBefore'] = $scheduleData['ReminderDaysBefore'] === null ? null : (int)$scheduleData['ReminderDaysBefore'];
        }

        // Always set UpdatedBy/UpdatedAt
        $fields[] = 'UpdatedBy = :UpdatedBy';
        $fields[] = 'UpdatedAt = NOW()';
        $params[':UpdatedBy'] = $updatedBy;

        if (empty($fields)) return false;

        $sql = 'UPDATE Tx_fees_schedules SET ' . implode(', ', $fields) . ' WHERE ScheduleID = :ScheduleID';
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $p => $v) {
            if ($v === null) {
                $stmt->bindValue($p, null, PDO::PARAM_NULL);
            } elseif (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $stmt->bindValue($p, (int)$v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($p, $v);
            }
        }

        $ok = $stmt->execute();
        if (!$ok) {
            error_log('FeeModel::updateFeeSchedule - update failed: ' . json_encode(['scheduleId' => $scheduleId, 'error' => $stmt->errorInfo()]));
        }
        return $ok;
    }
    /**
     * Create class-section mappings for a fee
     */
    public function createClassSectionMappings($feeId, $mappings, $createdBy)
    {
        $sql = "INSERT INTO Tx_fee_class_section_mapping 
                (FeeID, ClassID, SectionID, Amount, IsActive, CreatedBy) 
                VALUES (:FeeID, :ClassID, :SectionID, :Amount, :IsActive, :CreatedBy)";

        $stmt = $this->conn->prepare($sql);

        foreach ($mappings as $mapping) {
            // Accept both 'Amount' and 'amount' keys from different clients
            $rawAmount = array_key_exists('Amount', $mapping) ? $mapping['Amount'] : (array_key_exists('amount', $mapping) ? $mapping['amount'] : null);
            $amount = $this->normalizeAmount($rawAmount);

            $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
            $stmt->bindValue(':ClassID', $mapping['ClassID'], PDO::PARAM_INT);
            $stmt->bindValue(':SectionID', $mapping['SectionID'], PDO::PARAM_INT);

            if ($amount === null) {
                $stmt->bindValue(':Amount', null, PDO::PARAM_NULL);
            } else {
                // Bind as string to avoid PDO driver-specific float handling; DB will cast
                $stmt->bindValue(':Amount', (string)$amount);
            }

            // IsActive may be provided by the caller; default to 1 (selected)
            $isActive = array_key_exists('IsActive', $mapping) ? (!empty($mapping['IsActive']) ? 1 : 0) : 1;
            $stmt->bindValue(':IsActive', $isActive, PDO::PARAM_INT);

            $stmt->bindValue(':CreatedBy', $createdBy);
            $ok = $stmt->execute();
            if (!$ok) {
                $err = $stmt->errorInfo();
                error_log("FeeModel::createClassSectionMappings - insert failed: " . json_encode(['feeId' => $feeId, 'mapping' => $mapping, 'amount' => $amount, 'error' => $err]));
            } else {
                error_log("FeeModel::createClassSectionMappings - inserted mapping: " . json_encode(['feeId' => $feeId, 'mapping' => $mapping, 'amount' => $amount, 'isActive' => $isActive]));
            }
        }
    }

    /**
     * Upsert (update existing by MappingID or Fee/Class/Section, insert new otherwise)
     */
    private function upsertClassSectionMappings($feeId, $mappings, $updatedBy)
    {
        foreach ($mappings as $mapping) {
            $mappingId = $mapping['MappingID'] ?? ($mapping['mappingId'] ?? null);
            $classId = isset($mapping['ClassID']) ? (int)$mapping['ClassID'] : (isset($mapping['classId']) ? (int)$mapping['classId'] : 0);
            $sectionId = isset($mapping['SectionID']) ? (int)$mapping['SectionID'] : (isset($mapping['sectionId']) ? (int)$mapping['sectionId'] : 0);
            $rawAmount = array_key_exists('Amount', $mapping) ? $mapping['Amount'] : (array_key_exists('amount', $mapping) ? $mapping['amount'] : null);
            $amount = $this->normalizeAmount($rawAmount);

            if ($mappingId) {
                // Update by MappingID
                $sql = "UPDATE Tx_fee_class_section_mapping
                        SET Amount = :Amount, IsActive = :IsActive, UpdatedBy = :UpdatedBy, UpdatedAt = NOW()
                        WHERE MappingID = :MappingID AND FeeID = :FeeID";
                $stmt = $this->conn->prepare($sql);
                if ($amount === null) {
                    $stmt->bindValue(':Amount', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':Amount', (string)$amount);
                }
                $stmt->bindValue(':IsActive', 1, PDO::PARAM_INT);
                $stmt->bindValue(':UpdatedBy', $updatedBy);
                $stmt->bindValue(':MappingID', (int)$mappingId, PDO::PARAM_INT);
                $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
                $ok = $stmt->execute();
                if (!$ok) {
                    error_log('FeeModel::upsertClassSectionMappings - update failed: ' . json_encode(['mappingId' => $mappingId, 'feeId' => $feeId, 'error' => $stmt->errorInfo()]));
                }
            } else {
                // No MappingID — check if a row exists for (FeeID, ClassID, SectionID)
                $sqlSel = "SELECT MappingID FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND ClassID = :ClassID AND SectionID = :SectionID LIMIT 1";
                $sel = $this->conn->prepare($sqlSel);
                $sel->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
                $sel->bindValue(':ClassID', $classId, PDO::PARAM_INT);
                $sel->bindValue(':SectionID', $sectionId, PDO::PARAM_INT);
                $sel->execute();
                $existing = $sel->fetch(PDO::FETCH_ASSOC);
                if ($existing && isset($existing['MappingID'])) {
                    // Update existing
                    $exId = (int)$existing['MappingID'];
                    $sqlUp = "UPDATE Tx_fee_class_section_mapping
                              SET Amount = :Amount, IsActive = :IsActive, UpdatedBy = :UpdatedBy, UpdatedAt = NOW()
                              WHERE MappingID = :MappingID";
                    $up = $this->conn->prepare($sqlUp);
                    if ($amount === null) {
                        $up->bindValue(':Amount', null, PDO::PARAM_NULL);
                    } else {
                        $up->bindValue(':Amount', (string)$amount);
                    }
                    $up->bindValue(':IsActive', 1, PDO::PARAM_INT);
                    $up->bindValue(':UpdatedBy', $updatedBy);
                    $up->bindValue(':MappingID', $exId, PDO::PARAM_INT);
                    $ok = $up->execute();
                    $affectedUp = method_exists($up, 'rowCount') ? $up->rowCount() : null;
                    if (!$ok) {
                        error_log('FeeModel::upsertClassSectionMappings - update by unique key failed: ' . json_encode(['mappingId' => $exId, 'feeId' => $feeId, 'error' => $up->errorInfo()]));
                    } else {
                        error_log('FeeModel::upsertClassSectionMappings - update by unique key executed: ' . json_encode(['mappingId' => $exId, 'feeId' => $feeId, 'affectedRows' => $affectedUp]));
                    }
                } else {
                    // Insert new
                    $this->createClassSectionMappings($feeId, [[
                        'ClassID' => $classId,
                        'SectionID' => $sectionId,
                        'Amount' => $amount,
                    ]], $updatedBy);
                }
            }
        }
    }

    /** Normalize incoming amount values into float|null */
    private function normalizeAmount($raw)
    {
        if ($raw === null || $raw === '') return null;
        if (is_numeric($raw)) return (float)$raw;
        if (is_string($raw)) {
            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);
            if ($normalized === '' || $normalized === null) return null;
            return (float)$normalized;
        }
        return null;
    }

    /**
     * Delete class-section mappings for a fee
     */
    public function deleteClassSectionMappings($feeId)
    {
        $sql = "DELETE FROM Tx_fee_class_section_mapping WHERE FeeID = :feeId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':feeId', $feeId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get fee with class-section mappings using the view
     */
    public function getFeeWithMappings($feeId)
    {
        // Return structured data without aliasing column names
        // 1. Fee
        $sqlFee = "SELECT * FROM Tx_fees WHERE FeeID = :FeeID";
        $stmtFee = $this->conn->prepare($sqlFee);
        $stmtFee->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmtFee->execute();
        $fee = $stmtFee->fetch(PDO::FETCH_ASSOC);
        if (!$fee) return [];

        // 2. Schedule (assuming at most one per Fee)
        $sqlSched = "SELECT * FROM Tx_fees_schedules WHERE FeeID = :FeeID ORDER BY ScheduleID DESC LIMIT 1";
        $stmtSched = $this->conn->prepare($sqlSched);
        $stmtSched->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmtSched->execute();
        $schedule = $stmtSched->fetch(PDO::FETCH_ASSOC) ?: null;

        // 3. Mappings
        $sqlMap = "SELECT * FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID ORDER BY ClassID, SectionID";
        $stmtMap = $this->conn->prepare($sqlMap);
        $stmtMap->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmtMap->execute();
        $mappings = $stmtMap->fetchAll(PDO::FETCH_ASSOC);

        // Structured return
        return [[
            'Fee' => $fee,
            'Schedule' => $schedule,
            'ClassSectionMapping' => $mappings,
        ]];
    }

    /**
     * Get all fees with class-section mappings using the view
     */
    public function getAllFeesWithMappings($schoolId, $academicYearId)
    {
        // Get all fees
        $sqlFees = "SELECT * FROM Tx_fees WHERE SchoolID = :SchoolID AND AcademicYearID = :AcademicYearID ORDER BY FeeID";
        $stmtFees = $this->conn->prepare($sqlFees);
        $stmtFees->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $stmtFees->bindValue(':AcademicYearID', $academicYearId, PDO::PARAM_INT);
        $stmtFees->execute();
        $fees = $stmtFees->fetchAll(PDO::FETCH_ASSOC);
        if (!$fees) return [];

        $feeIds = array_column($fees, 'FeeID');
        $inClause = implode(',', array_fill(0, count($feeIds), '?'));

        // Get schedules (one per fee assumed; use latest by ScheduleID if multiple)
        $schedulesByFee = [];
        $sqlSched = "SELECT * FROM Tx_fees_schedules WHERE FeeID IN ($inClause) ORDER BY FeeID, ScheduleID DESC";
        $stmtSched = $this->conn->prepare($sqlSched);
        foreach ($feeIds as $i => $fid) {
            $stmtSched->bindValue($i + 1, $fid, PDO::PARAM_INT);
        }
        $stmtSched->execute();
        while ($row = $stmtSched->fetch(PDO::FETCH_ASSOC)) {
            $fid = $row['FeeID'];
            if (!isset($schedulesByFee[$fid])) {
                $schedulesByFee[$fid] = $row;
            }
        }

        // Get mappings
        $mappingsByFee = [];
        $sqlMap = "SELECT * FROM Tx_fee_class_section_mapping WHERE FeeID IN ($inClause) ORDER BY FeeID, ClassID, SectionID";
        $stmtMap = $this->conn->prepare($sqlMap);
        foreach ($feeIds as $i => $fid) {
            $stmtMap->bindValue($i + 1, $fid, PDO::PARAM_INT);
        }
        $stmtMap->execute();
        while ($row = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
            $fid = $row['FeeID'];
            if (!isset($mappingsByFee[$fid])) $mappingsByFee[$fid] = [];
            $mappingsByFee[$fid][] = $row;
        }

        // Assemble
        $result = [];
        foreach ($fees as $fee) {
            $fid = $fee['FeeID'];
            $result[] = [
                'Fee' => $fee,
                'Schedule' => $schedulesByFee[$fid] ?? null,
                'ClassSectionMapping' => $mappingsByFee[$fid] ?? []
            ];
        }

        return $result;
    }
}