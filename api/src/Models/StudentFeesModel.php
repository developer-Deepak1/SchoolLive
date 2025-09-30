<?php

namespace SchoolLive\Models;

use PDO;

class StudentFeesModel extends Model
{
    protected $table = 'Tx_student_fees';
    protected $pk = 'StudentFeeID';

    /**
     * Get ledger items for a student with computed fine and outstanding.
     *
     * Returns array of rows with keys (DB-native) plus:
     *  - FeeName
     *  - ComputedFine (dynamic fine based on fine policies and today/payment date)
     *  - Outstanding (Amount + ComputedFine - DiscountAmount - AmountPaid)
     */
    public function getStudentLedger(int $schoolId, int $academicYearId, int $studentId, array $filters = []): array
    {
        $onlyDue = isset($filters['only_due']) ? (bool)$filters['only_due'] : false;
        $includePaid = isset($filters['include_paid']) ? (bool)$filters['include_paid'] : true;

        $sql = "SELECT sf.*, f.FeeName
                FROM Tx_student_fees sf
                JOIN Tx_fees f ON f.FeeID = sf.FeeID
                WHERE sf.SchoolID = :SchoolID AND sf.StudentID = :StudentID";
        $params = [
            ':SchoolID' => $schoolId,
            ':StudentID' => $studentId,
        ];

        if (!$includePaid) {
            $sql .= " AND sf.Status <> 'Paid'";
        }
        if ($onlyDue) {
            $sql .= " AND (sf.Status IN ('Pending','Partial','Overdue'))";
        }

        $sql .= " ORDER BY COALESCE(sf.DueDate, sf.CreatedAt) ASC, sf." . $this->pk . " ASC";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Attach computed fine and outstanding
        foreach ($rows as &$r) {
            $fine = $this->computeFine($schoolId, $academicYearId, (int)$r['FeeID'], $r['DueDate'], (float)$r['Amount']);
            $r['ComputedFine'] = $fine;
            $discount = isset($r['DiscountAmount']) ? (float)$r['DiscountAmount'] : 0.0;
            $paid = isset($r['AmountPaid']) ? (float)$r['AmountPaid'] : 0.0;
            $r['Outstanding'] = max(0.0, round(((float)$r['Amount'] + $fine - $discount - $paid), 2));

            // Re-evaluate Status lightly (do not persist here)
            $r['Status'] = $this->deriveStatus($r['DueDate'], (float)$r['Amount'] + $fine - $discount, $paid);
        }
        unset($r);

        return $rows;
    }

    /**
     * Build a month-based plan of dues for a student without precomputing rows.
     * Returns one row per applicable fee for that month with either an existing StudentFeeID
     * (if a ledger row exists with DueDate in that month) or null when not yet created.
     */
    public function getMonthlyPlan(int $schoolId, int $academicYearId, int $studentId, int $year, int $month): array
    {
        // Get student's class & section for amount mapping
        $stu = $this->getStudent($studentId);
        if (!$stu) return [];
        $classId = isset($stu['ClassID']) ? (int)$stu['ClassID'] : null;
        $sectionId = isset($stu['SectionID']) ? (int)$stu['SectionID'] : null;

        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        // Fetch all active fees with schedules for this school & academic year
        $sql = "SELECT f.FeeID, f.FeeName, s.ScheduleType, s.IntervalMonths, s.DayOfMonth, s.StartDate, s.EndDate
                FROM Tx_fees f
                JOIN Tx_fees_schedules s ON s.FeeID = f.FeeID
                WHERE f.SchoolID = :SchoolID AND f.AcademicYearID = :AcademicYearID AND f.IsActive = 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $st->bindValue(':AcademicYearID', $academicYearId, PDO::PARAM_INT);
        $st->execute();
        $fees = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($fees as $f) {
            $type = $f['ScheduleType'];
            $dueDate = $this->deriveDueDateForMonth($f, $year, $month);
            // We'll pick one ledger row to represent this fee in the output, when applicable.
            $sfUsed = null;
            // If schedule doesn't produce a due date for this month, still include if an existing ledger row
            // for this student/fee falls within the month (covers OneTime and OnDemand previously billed items).
            if (!$dueDate) {
                // First, check for existing ledger rows in this month
                $sfInMonth = $this->findExistingRowForMonth($studentId, (int)$f['FeeID'], $monthStart, $monthEnd);
                if ($sfInMonth) {
                    // If existing row is Paid and fee is OneTime, skip
                    if (strtolower((string)$type) === 'onetime' && isset($sfInMonth['Status']) && strtolower($sfInMonth['Status']) === 'paid') {
                        continue;
                    }
                    $sfUsed = $sfInMonth;
                    $dueDate = new \DateTime($sfInMonth['DueDate']);
                } else {
                    // Nothing in this month; carry-forward logic for OneTime and OnDemand
                    $lt = strtolower((string)$type);
                    if ($lt === 'onetime' || $lt === 'ondemand') {
                        // If any PAID ledger exists for this fee, skip showing in plan
                        if ($this->hasPaidLedger($studentId, (int)$f['FeeID'])) {
                            continue;
                        }
                        // Prefer any existing UNPAID ledger (from other months) to avoid duplicating amounts
                        $sfAnyUnpaid = $this->findAnyUnpaidLedger($studentId, (int)$f['FeeID']);
                        if ($sfAnyUnpaid) {
                            $sfUsed = $sfAnyUnpaid;
                            $dueDate = new \DateTime($sfAnyUnpaid['DueDate']);
                        } else {
                            if ($lt === 'onetime') {
                                // OneTime without any ledger: no specific due date; let UI show '-'
                                $dueDate = null;
                            } else {
                                // OnDemand without any ledger: synthesize a due date for current month to allow billing
                                $seed = !empty($f['StartDate']) ? new \DateTime($f['StartDate']) : clone $monthStart;
                                $dueDate = $seed;
                            }
                        }
                    } else {
                        // For other schedule types, if not scheduled and no ledger in this month, skip
                        continue;
                    }
                }
            } else {
                // A schedule produced a due date; if a ledger already exists within this month, prefer that row
                $sfInMonth = $this->findExistingRowForMonth($studentId, (int)$f['FeeID'], $monthStart, $monthEnd);
                if ($sfInMonth) {
                    if (strtolower((string)$type) === 'onetime' && isset($sfInMonth['Status']) && strtolower($sfInMonth['Status']) === 'paid') {
                        continue;
                    }
                    $sfUsed = $sfInMonth;
                    $dueDate = new \DateTime($sfInMonth['DueDate']);
                } else if (strtolower((string)$type) === 'ondemand') {
                    // Month-independent carry-forward for OnDemand: prefer any existing UNPAID ledger
                    if ($this->hasPaidLedger($studentId, (int)$f['FeeID'])) {
                        continue;
                    }
                    $sfAnyUnpaid = $this->findAnyUnpaidLedger($studentId, (int)$f['FeeID']);
                    if ($sfAnyUnpaid) {
                        $sfUsed = $sfAnyUnpaid;
                        $dueDate = new \DateTime($sfAnyUnpaid['DueDate']);
                    }
                } else if (strtolower((string)$type) === 'onetime') {
                    // For OneTime, even if schedule produced a date, show '-' unless there is a ledger this month.
                    // If already paid anywhere, skip.
                    if ($this->hasPaidLedger($studentId, (int)$f['FeeID'])) {
                        continue;
                    }
                    // No ledger in this month: clear due date so UI shows '-'
                    $dueDate = null;
                }
            }

            // Clamp due date inside academic year bounds (optional: skip if outside AY)
            $amount = $this->resolveMappingAmount((int)$f['FeeID'], $classId, $sectionId);
            if ($amount <= 0) continue;

            // Build row using either an existing ledger row (sfUsed) or a synthesized entry
            // Resolve due date string; for OneTime fees, force null so UI shows '-'
            $dueStr = $sfUsed ? ($sfUsed['DueDate'] ?? null) : ($dueDate instanceof \DateTime ? $dueDate->format('Y-m-d') : null);
            if (strtolower((string)$type) === 'onetime') {
                $dueStr = null;
            }
            $row = [
                'StudentFeeID' => $sfUsed ? (int)$sfUsed['StudentFeeID'] : null,
                'StudentID' => $studentId,
                'FeeID' => (int)$f['FeeID'],
                'FeeName' => $f['FeeName'],
                'Amount' => $sfUsed ? (float)$sfUsed['Amount'] : (float)$amount,
                'FineAmount' => $sfUsed ? (float)$sfUsed['FineAmount'] : 0.0,
                'DiscountAmount' => $sfUsed ? (float)$sfUsed['DiscountAmount'] : 0.0,
                'AmountPaid' => $sfUsed ? (float)$sfUsed['AmountPaid'] : 0.0,
                'DueDate' => $dueStr,
                'Status' => $sfUsed ? $sfUsed['Status'] : 'Pending',
            ];
            // Compute fine and outstanding dynamically
            $fine = $this->computeFine($schoolId, $academicYearId, (int)$f['FeeID'], $row['DueDate'] ?? null, (float)$row['Amount']);
            $row['ComputedFine'] = $fine;
            $row['Outstanding'] = max(0.0, round(((float)$row['Amount'] + $fine - (float)$row['DiscountAmount'] - (float)$row['AmountPaid']), 2));
            $row['Status'] = $this->deriveStatus($row['DueDate'] ?? null, (float)$row['Amount'] + $fine - (float)$row['DiscountAmount'], (float)$row['AmountPaid']);
            $out[] = $row;
        }
        // Sort by due date then FeeName
        usort($out, function($a, $b){
            $ad = strtotime($a['DueDate'] ?? '');
            $bd = strtotime($b['DueDate'] ?? '');
            if ($ad === $bd) return strcmp($a['FeeName'] ?? '', $b['FeeName'] ?? '');
            return $ad <=> $bd;
        });
        return $out;
    }

    /** Locate existing fee row within a month window. */
    private function findExistingRowForMonth(int $studentId, int $feeId, \DateTime $monthStart, \DateTime $monthEnd): ?array
    {
        $q = "SELECT * FROM Tx_student_fees WHERE StudentID = :StudentID AND FeeID = :FeeID AND DueDate BETWEEN :Start AND :End LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':StudentID', $studentId, PDO::PARAM_INT);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->bindValue(':Start', $monthStart->format('Y-m-d'));
        $st->bindValue(':End', $monthEnd->format('Y-m-d'));
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Check if any PAID ledger exists for this student/fee. */
    private function hasPaidLedger(int $studentId, int $feeId): bool
    {
        $q = "SELECT 1 FROM Tx_student_fees WHERE StudentID = :StudentID AND FeeID = :FeeID AND Status = 'Paid' LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':StudentID', $studentId, PDO::PARAM_INT);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    /** Find any existing UNPAID ledger row for this student/fee (outside current month allowed). */
    private function findAnyUnpaidLedger(int $studentId, int $feeId): ?array
    {
        $q = "SELECT * FROM Tx_student_fees WHERE StudentID = :StudentID AND FeeID = :FeeID AND Status <> 'Paid' ORDER BY COALESCE(DueDate, CreatedAt) DESC LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':StudentID', $studentId, PDO::PARAM_INT);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Create or return existing ledger row for the requested fee/month for a student. */
    public function ensureMonthlyRow(int $schoolId, int $academicYearId, int $studentId, int $feeId, int $year, int $month, ?string $createdBy): int
    {
        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');
        $existing = $this->findExistingRowForMonth($studentId, $feeId, $monthStart, $monthEnd);
        if ($existing) return (int)$existing['StudentFeeID'];

        // Fetch student's class & section for amount and due date
        $stu = $this->getStudent($studentId);
        $classId = isset($stu['ClassID']) ? (int)$stu['ClassID'] : null;
        $sectionId = isset($stu['SectionID']) ? (int)$stu['SectionID'] : null;

        // Derive due date from schedule
        $sched = $this->getFeeSchedule($feeId);
        $due = $this->deriveDueDateForMonth($sched, $year, $month);
        if (!$due) {
            // If not scheduled for this month, default to first day of month
            $due = $monthStart;
        }
        $amount = $this->resolveMappingAmount($feeId, $classId, $sectionId);
        if ($amount < 0) $amount = 0.0;

        return $this->assignFee($schoolId, $academicYearId, $studentId, $feeId, $classId, $sectionId, $due->format('Y-m-d'), $amount, null, $createdBy ?: 'System');
    }

    /**
     * Given a fee schedule row (or fee+schedule combined row), compute the due date for a given month.
     * Returns null when the schedule does not apply to that month.
     */
    private function deriveDueDateForMonth(array $feeScheduleRow, int $year, int $month): ?\DateTime
    {
        // Normalize row (if only FeeID provided, fetch schedule)
        if (!isset($feeScheduleRow['ScheduleType'])) {
            $feeScheduleRow = $this->getFeeSchedule((int)$feeScheduleRow['FeeID']);
            if (!$feeScheduleRow) return null;
        }
        $type = $feeScheduleRow['ScheduleType'] ?? 'OnDemand';
        $interval = isset($feeScheduleRow['IntervalMonths']) ? (int)$feeScheduleRow['IntervalMonths'] : null;
        $dom = isset($feeScheduleRow['DayOfMonth']) ? (int)$feeScheduleRow['DayOfMonth'] : null;
        $start = !empty($feeScheduleRow['StartDate']) ? new \DateTime($feeScheduleRow['StartDate']) : null;
        $end = !empty($feeScheduleRow['EndDate']) ? new \DateTime($feeScheduleRow['EndDate']) : null;

        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        if ($type === 'Recurring') {
            // If no explicit start date is provided, treat the schedule as always-active (apply every IntervalMonths)
            // but still respect an EndDate if present.
            if ($end && $monthStart > $end) return null;

            // Determine reference start for interval calculations. Prefer provided start; otherwise use a far-past epoch
            // so the recurrence rules align with every month (or IntervalMonths). Using 1st Jan 1970 ensures monthsDiff
            // yields a consistent index for modulo arithmetic.
            $refStart = $start ?: new \DateTime('1970-01-01');
            // If start exists and the requested month is before it, skip
            if ($start && $monthEnd < $start) return null;

            $monthsDiff = $this->monthsDiff($refStart, $monthStart);
            $interval = $interval ?: 1;
            if ($monthsDiff % $interval !== 0) return null;

            // Determine day in month, clamp to valid days
            $maxDay = (int)$monthEnd->format('j');
            $day = (int)($dom ?: 1);
            if ($day < 1) $day = 1;
            if ($day > $maxDay) $day = $maxDay;
            return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        }
        if ($type === 'OneTime') {
            // One-time fee: requires a StartDate (the event/date). Only show if that date falls within the month.
            $d = $start ?: null;
            if (!$d) return null;
            if ($d >= $monthStart && $d <= $monthEnd) return $d;
            return null;
        }

        if ($type === 'OnDemand') {
            // OnDemand fees are applicable when their StartDate/EndDate window overlaps the requested month.
            // If both start and end present, check for any overlap.
            if ($start && $end) {
                if ($start <= $monthEnd && $end >= $monthStart) {
                    // Prefer the StartDate if it lies within the month, otherwise use the month start
                    return ($start >= $monthStart && $start <= $monthEnd) ? $start : $monthStart;
                }
                return null;
            }
            // If only start provided, treat like OneTime (show if start in month)
            if ($start) {
                if ($start >= $monthStart && $start <= $monthEnd) return $start;
                return null;
            }
            // If only end provided, show if end in month
            if ($end) {
                if ($end >= $monthStart && $end <= $monthEnd) return $end;
                return null;
            }
            return null;
        }

        // Default: not auto-generated
        return null;
    }

    private function monthsDiff(\DateTime $start, \DateTime $end): int
    {
        $y = ((int)$end->format('Y')) - ((int)$start->format('Y'));
        $m = ((int)$end->format('n')) - ((int)$start->format('n'));
        return $y * 12 + $m;
    }

    private function getFeeSchedule(int $feeId): ?array
    {
        $q = "SELECT FeeID, ScheduleType, IntervalMonths, DayOfMonth, StartDate, EndDate FROM Tx_fees_schedules WHERE FeeID = :FeeID LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function getStudent(int $studentId): ?array
    {
        $q = "SELECT * FROM Tx_Students WHERE StudentID = :id LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':id', $studentId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    /** Compute fine using all active fine policies applicable to the fee.
     * Each policy is evaluated independently with its own grace period and capped by its own MaxAmount, then summed.
     */
    public function computeFine(int $schoolId, int $academicYearId, int $feeId, ?string $dueDate, float $baseAmount, ?\DateTime $asOf = null): float
    {
        $asOf = $asOf ?: new \DateTime('today');
        if (!$dueDate) return 0.0;

        $due = new \DateTime($dueDate);
        // Fetch all active policies for this school/year that target this fee or are global (FeeID IS NULL)
        $sql = "SELECT * FROM Tx_fine_policies
                WHERE SchoolID = :SchoolID AND AcademicYearID = :AcademicYearID AND IsActive = 1
                  AND (FeeID = :FeeID OR FeeID IS NULL)
                ORDER BY (FeeID IS NULL) ASC, FinePolicyID DESC"; // fee-specific first, then global
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':AcademicYearID', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmt->execute();
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$policies) return 0.0;

        $totalFine = 0.0;
        foreach ($policies as $p) {
            $graceDays = isset($p['GraceDays']) ? (int)$p['GraceDays'] : 0;
            $graceLimit = (clone $due)->modify("+{$graceDays} day");
            // If still within grace for this policy, it contributes nothing
            if ($asOf <= $graceLimit) {
                continue;
            }

            $apply = $p['ApplyType'] ?? 'Fixed';
            $amount = isset($p['Amount']) ? (float)$p['Amount'] : 0.0;
            $daysLate = (int)$graceLimit->diff($asOf)->format('%a');
            $fineP = 0.0;
            if ($apply === 'Fixed') {
                $fineP = $amount;
            } elseif ($apply === 'PerDay') {
                $fineP = $amount * max(1, $daysLate); // at least 1 day after grace
            } elseif ($apply === 'Percentage') {
                $fineP = ($amount / 100.0) * $baseAmount;
            } else {
                $fineP = 0.0;
            }
            $maxCap = (array_key_exists('MaxAmount', $p) && $p['MaxAmount'] !== null) ? (float)$p['MaxAmount'] : null;
            if ($maxCap !== null) {
                $fineP = min($fineP, $maxCap);
            }
            $totalFine += max(0.0, round($fineP, 2));
        }

        return round(max(0.0, $totalFine), 2);
    }

    /** Assign a fee ledger row for a student (one line). If amount null, resolve from mapping. */
    public function assignFee(int $schoolId, int $academicYearId, int $studentId, int $feeId, ?int $classId, ?int $sectionId, ?string $dueDate, ?float $amount, ?int $mappingId, string $createdBy): int
    {
        if ($amount === null) {
            $amount = $this->resolveMappingAmount($feeId, $classId, $sectionId);
        }
        if ($dueDate === null) {
            // Best-effort default due date: today
            $dueDate = date('Y-m-d');
        }

        $sql = "INSERT INTO Tx_student_fees (SchoolID, StudentID, FeeID, MappingID, Amount, FineAmount, DiscountAmount, AmountPaid, DueDate, Status, InvoiceRef, Remarks, CreatedBy, CreatedAt)
                VALUES (:SchoolID, :StudentID, :FeeID, :MappingID, :Amount, 0.00, 0.00, 0.00, :DueDate, :Status, NULL, NULL, :CreatedBy, :CreatedAt)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':StudentID', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        if ($mappingId) { $stmt->bindValue(':MappingID', $mappingId, PDO::PARAM_INT); } else { $stmt->bindValue(':MappingID', null, PDO::PARAM_NULL); }
        $stmt->bindValue(':Amount', (string)round((float)$amount, 2));
        $stmt->bindValue(':DueDate', $dueDate);
        $status = $this->deriveStatus($dueDate, (float)$amount, 0.0);
        $stmt->bindValue(':Status', $status);
        $stmt->bindValue(':CreatedBy', $createdBy);
        $stmt->bindValue(':CreatedAt', date('Y-m-d H:i:s'));
        $ok = $stmt->execute();
        if (!$ok) {
            $err = $stmt->errorInfo();
            throw new \RuntimeException('Failed to assign student fee: ' . json_encode($err));
        }
        return (int)$this->conn->lastInsertId();
    }

    /** Record payment against a student fee row. Optionally update discount and remarks. */
    public function recordPayment(int $studentFeeId, float $paidAmount, string $mode, ?string $transactionRef, ?string $paymentDate, ?float $discountDelta, ?string $createdBy): bool
    {
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Ensure atomicity across payment insert and ledger update
        $inTx = false;
        try {
            if ($this->conn->inTransaction() === false) {
                $this->conn->beginTransaction();
                $inTx = true;
            }

        // Insert payment row
        $sqlPay = "INSERT INTO Tx_student_fee_payments (StudentFeeID, PaymentDate, PaidAmount, Mode, TransactionRef, CreatedAt, CreatedBy)
                   VALUES (:StudentFeeID, :PaymentDate, :PaidAmount, :Mode, :TransactionRef, :CreatedAt, :CreatedBy)";
        $stmt = $this->conn->prepare($sqlPay);
        $stmt->bindValue(':StudentFeeID', $studentFeeId, PDO::PARAM_INT);
        $stmt->bindValue(':PaymentDate', $paymentDate);
        $stmt->bindValue(':PaidAmount', (string)round($paidAmount, 2));
        $stmt->bindValue(':Mode', $mode);
        if ($transactionRef) { $stmt->bindValue(':TransactionRef', $transactionRef); } else { $stmt->bindValue(':TransactionRef', null, PDO::PARAM_NULL); }
        $stmt->bindValue(':CreatedAt', $now);
        $stmt->bindValue(':CreatedBy', $createdBy ?: 'System');
            $ok = $stmt->execute();
            if (!$ok) { if ($inTx) $this->conn->rollBack(); return false; }

        // Update ledger row amounts and status
        $sqlUpd = "UPDATE Tx_student_fees SET AmountPaid = ROUND(AmountPaid + :PaidAmount, 2), UpdatedAt = :UpdatedAt, UpdatedBy = :UpdatedBy";
        $params = [
            ':PaidAmount' => (string)round($paidAmount, 2),
            ':UpdatedAt' => $now,
            ':UpdatedBy' => $createdBy ?: 'System',
            ':StudentFeeID' => $studentFeeId
        ];
        if ($discountDelta !== null && $discountDelta != 0) {
            $sqlUpd .= ", DiscountAmount = ROUND(COALESCE(DiscountAmount,0) + :DiscountDelta, 2)";
            $params[':DiscountDelta'] = (string)round($discountDelta, 2);
        }
        $sqlUpd .= " WHERE StudentFeeID = :StudentFeeID";
        $up = $this->conn->prepare($sqlUpd);
        foreach ($params as $k => $v) {
            if ($k === ':StudentFeeID') { $up->bindValue($k, (int)$v, PDO::PARAM_INT); continue; }
            $up->bindValue($k, $v);
        }
            $ok2 = $up->execute();
            if (!$ok2) { if ($inTx) $this->conn->rollBack(); return false; }

        // Recalculate status based on updated values
        $this->refreshStatus($studentFeeId);
            if ($inTx) $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            if ($inTx && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('StudentFeesModel::recordPayment exception: ' . $e->getMessage());
            return false;
        }
    }

    /** Refresh status of a ledger row based on fields and fine policy as of today. */
    public function refreshStatus(int $studentFeeId): void
    {
        // Load row with school/ay for fine computation
        $sql = "SELECT sf.*, s.SchoolID, s.AcademicYearID
                FROM Tx_student_fees sf
                JOIN Tx_Students s ON s.StudentID = sf.StudentID
                WHERE sf.StudentFeeID = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $studentFeeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;

        $fine = $this->computeFine((int)$row['SchoolID'], (int)$row['AcademicYearID'], (int)$row['FeeID'], $row['DueDate'], (float)$row['Amount']);
        $discount = isset($row['DiscountAmount']) ? (float)$row['DiscountAmount'] : 0.0;
        $paid = isset($row['AmountPaid']) ? (float)$row['AmountPaid'] : 0.0;
        $status = $this->deriveStatus($row['DueDate'], (float)$row['Amount'] + $fine - $discount, $paid);

        $sqlU = "UPDATE Tx_student_fees SET FineAmount = :FineAmount, Status = :Status, UpdatedAt = :UpdatedAt WHERE StudentFeeID = :id";
        $u = $this->conn->prepare($sqlU);
        $u->bindValue(':FineAmount', (string)$fine);
        $u->bindValue(':Status', $status);
        $u->bindValue(':UpdatedAt', date('Y-m-d H:i:s'));
        $u->bindValue(':id', $studentFeeId, PDO::PARAM_INT);
        $u->execute();
    }

    /** Helper to derive status string. */
    private function deriveStatus(?string $dueDate, float $totalDue, float $paid): string
    {
        $outstanding = round(max(0.0, $totalDue - $paid), 2);
        if ($outstanding <= 0.0) return 'Paid';
        if ($dueDate) {
            $today = new \DateTime('today');
            $due = new \DateTime($dueDate);
            if ($today > $due) return 'Overdue';
        }
        return $paid > 0 ? 'Partial' : 'Pending';
    }

    /** Resolve Amount from class-section mapping for fee. */
    private function resolveMappingAmount(int $feeId, ?int $classId, ?int $sectionId): float
    {
        // 1) Exact class+section mapping
        if ($classId && $sectionId) {
            $sql = "SELECT Amount FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND ClassID = :ClassID AND SectionID = :SectionID AND IsActive = 1 LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
            $stmt->bindValue(':ClassID', $classId, PDO::PARAM_INT);
            $stmt->bindValue(':SectionID', $sectionId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['Amount'] !== null) return (float)$row['Amount'];
        }

        // 2) Fallback: class-only mapping (if schema allows it via NULL SectionID)
        if ($classId) {
            $sql = "SELECT Amount FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND ClassID = :ClassID AND (SectionID IS NULL OR SectionID = 0) AND IsActive = 1 LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
            $stmt->bindValue(':ClassID', $classId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['Amount'] !== null) return (float)$row['Amount'];
        }

        // 3) Fallback: any active mapping amount for this fee (pick the lowest to be safe)
        $sql = "SELECT MIN(Amount) AS Amount FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND IsActive = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['Amount'] !== null) return (float)$row['Amount'];
        return 0.0;
    }
}
