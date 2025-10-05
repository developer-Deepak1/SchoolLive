<?php

namespace SchoolLive\Models;

use PDO;

class StudentFeesModel extends Model
{
    protected $table = 'Tx_student_fees';
    protected $pk = 'StudentFeeID';

    // Return student ledger rows augmented with computed fine, outstanding, and derived status
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

        if (!$rows) return $rows;
        $studentFeeIds = array_map(fn($r) => (int)$r['StudentFeeID'], $rows);
        $collectedById = $this->fetchLatestPaymentDates($studentFeeIds);
        $feeIds = array_values(array_unique(array_map(fn($r) => (int)$r['FeeID'], $rows)));
        $pol = $this->fetchPoliciesForFees($schoolId, $academicYearId, $feeIds);
        foreach ($rows as &$r) {
            $sid = (int)($r['StudentFeeID'] ?? 0);
            $r['CollectedDate'] = $collectedById[$sid] ?? null;
            $fine = $this->computeFineFromPolicies($pol['byFee'][(int)$r['FeeID']] ?? [], $pol['global'], $r['DueDate'], (float)$r['Amount']);
            $r['ComputedFine'] = $fine;
            $discount = isset($r['DiscountAmount']) ? (float)$r['DiscountAmount'] : 0.0;
            $paid = isset($r['AmountPaid']) ? (float)$r['AmountPaid'] : 0.0;
            $r['Outstanding'] = max(0.0, round(((float)$r['Amount'] + $fine - $discount - $paid), 2));
            $r['Status'] = $this->deriveStatus($r['DueDate'], (float)$r['Amount'] + $fine - $discount, $paid);
        }
        unset($r);

        return $rows;
    }

    private function fetchLatestPaymentDates(array $studentFeeIds): array
    {
        $studentFeeIds = array_values(array_unique(array_filter($studentFeeIds, fn($v) => (int)$v > 0)));
        if (!$studentFeeIds) return [];

        $placeholders = implode(',', array_fill(0, count($studentFeeIds), '?'));
        $sql = "SELECT StudentFeeID, MAX(PaymentDate) AS LatestPaymentDate FROM Tx_student_fee_payments WHERE StudentFeeID IN ($placeholders) GROUP BY StudentFeeID";
        $stmt = $this->conn->prepare($sql);
        $i = 1;
        foreach ($studentFeeIds as $id) {
            $stmt->bindValue($i++, (int)$id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['StudentFeeID'])) continue;
            $sid = (int)$row['StudentFeeID'];
            $date = $row['LatestPaymentDate'] ?? null;
            if ($sid > 0 && $date) {
                $out[$sid] = $date;
            }
        }
        return $out;
    }

    // Build a month-based plan of dues for a student (one row per fee for that month)
    public function getMonthlyPlan(int $schoolId, int $academicYearId, int $studentId, int $year, int $month): array
    {
        $stu = $this->getStudent($studentId);
        if (!$stu) return [];
        $classId = isset($stu['ClassID']) ? (int)$stu['ClassID'] : null;
        $sectionId = isset($stu['SectionID']) ? (int)$stu['SectionID'] : null;

        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');

        $sql = "SELECT f.FeeID, f.FeeName, s.ScheduleType, s.IntervalMonths, s.DayOfMonth, s.StartDate, s.EndDate
                FROM Tx_fees f
                JOIN Tx_fees_schedules s ON s.FeeID = f.FeeID
                WHERE f.SchoolID = :SchoolID AND f.AcademicYearID = :AcademicYearID AND f.IsActive = 1";
        $st = $this->conn->prepare($sql);
        $st->bindValue(':SchoolID', $schoolId, PDO::PARAM_INT);
        $st->bindValue(':AcademicYearID', $academicYearId, PDO::PARAM_INT);
        $st->execute();
        $fees = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$fees) return [];

        $feeIds = array_values(array_unique(array_map(fn($f) => (int)$f['FeeID'], $fees)));
        $placeholders = implode(',', array_fill(0, count($feeIds), '?'));

        $qMonth = $this->conn->prepare("SELECT * FROM Tx_student_fees WHERE StudentID = ? AND FeeID IN ($placeholders) AND DueDate BETWEEN ? AND ?");
        $i = 1; $qMonth->bindValue($i++, $studentId, PDO::PARAM_INT);
        foreach ($feeIds as $fid) { $qMonth->bindValue($i++, $fid, PDO::PARAM_INT); }
        $qMonth->bindValue($i++, $monthStart->format('Y-m-d'));
        $qMonth->bindValue($i++, $monthEnd->format('Y-m-d'));
        $qMonth->execute();
        $inMonthByFee = [];
        while ($r = $qMonth->fetch(PDO::FETCH_ASSOC)) { $inMonthByFee[(int)$r['FeeID']] = $r; }

        $qPaid = $this->conn->prepare("SELECT DISTINCT FeeID FROM Tx_student_fees WHERE StudentID = ? AND FeeID IN ($placeholders) AND Status = 'Paid'");
        $i = 1; $qPaid->bindValue($i++, $studentId, PDO::PARAM_INT);
        foreach ($feeIds as $fid) { $qPaid->bindValue($i++, $fid, PDO::PARAM_INT); }
        $qPaid->execute();
        $paidSet = [];
        while ($r = $qPaid->fetch(PDO::FETCH_ASSOC)) { $paidSet[(int)$r['FeeID']] = true; }

        $qUnpaid = $this->conn->prepare("SELECT * FROM Tx_student_fees WHERE StudentID = ? AND FeeID IN ($placeholders) AND Status <> 'Paid' ORDER BY COALESCE(DueDate, CreatedAt) DESC");
        $i = 1; $qUnpaid->bindValue($i++, $studentId, PDO::PARAM_INT);
        foreach ($feeIds as $fid) { $qUnpaid->bindValue($i++, $fid, PDO::PARAM_INT); }
        $qUnpaid->execute();
        $latestUnpaidByFee = [];
        while ($r = $qUnpaid->fetch(PDO::FETCH_ASSOC)) { $fid = (int)$r['FeeID']; if (!isset($latestUnpaidByFee[$fid])) { $latestUnpaidByFee[$fid] = $r; } }

        $pol = $this->fetchPoliciesForFees($schoolId, $academicYearId, $feeIds);
        $out = [];
        foreach ($fees as $f) {
            $type = $f['ScheduleType'];
            $dueDate = $this->deriveDueDateForMonth($f, $year, $month);
            $sfUsed = null;

            if (!$dueDate) {
                $sfInMonth = $inMonthByFee[(int)$f['FeeID']] ?? null;
                if ($sfInMonth) {
                    // If the existing ledger in this month is already paid, skip including it in the plan
                    if (isset($sfInMonth['Status']) && strtolower((string)$sfInMonth['Status']) === 'paid') {
                        continue;
                    }
                    if (strtolower((string)$type) === 'onetime' && isset($sfInMonth['Status']) && strtolower((string)$sfInMonth['Status']) === 'paid') {
                        // redundant guard preserved for clarity, though above check will handle it
                        continue;
                    }
                    $sfUsed = $sfInMonth;
                    $dueDate = new \DateTime($sfInMonth['DueDate']);
                } else {
                    $lt = strtolower((string)$type);
                    if ($lt === 'onetime' || $lt === 'ondemand') {
                        if (!empty($paidSet[(int)$f['FeeID']])) {
                            continue;
                        }
                        $sfAnyUnpaid = $latestUnpaidByFee[(int)$f['FeeID']] ?? null;
                        if ($sfAnyUnpaid) {
                            $sfUsed = $sfAnyUnpaid;
                            if (!empty($sfAnyUnpaid['DueDate'])) {
                                $dueDate = new \DateTime($sfAnyUnpaid['DueDate']);
                            }
                        } else {
                            // No existing ledger. For OnDemand after its EndDate month, carry once into the immediate next month using EndDate.
                            if ($lt === 'ondemand' && !empty($f['EndDate'])) {
                                $ed = new \DateTime($f['EndDate']);
                                // If current month is the immediate next month after EndDate's month, carry forward
                                $edMonthStart = new \DateTime($ed->format('Y-m-01'));
                                $diffMonths = $this->monthsDiff($edMonthStart, $monthStart);
                                if ($ed < $monthStart && $diffMonths === 1) {
                                    $dueDate = $ed; // keep original EndDate so status becomes Overdue
                                } else {
                                    // Before EndDate month or too far in future: no fixed due date
                                    $dueDate = null;
                                }
                            } else {
                                // OneTime or OnDemand without EndDate: no fixed due date
                                $dueDate = null;
                            }
                        }
                    } else {
                        continue;
                    }
                }
            } else {
                $sfInMonth = $inMonthByFee[(int)$f['FeeID']] ?? null;
                if ($sfInMonth) {
                    // skip if already paid
                    if (isset($sfInMonth['Status']) && strtolower((string)$sfInMonth['Status']) === 'paid') {
                        continue;
                    }
                    if (strtolower((string)$type) === 'onetime' && isset($sfInMonth['Status']) && strtolower((string)$sfInMonth['Status']) === 'paid') {
                        continue;
                    }
                    $sfUsed = $sfInMonth;
                    $dueDate = new \DateTime($sfInMonth['DueDate']);
                } else if (strtolower((string)$type) === 'ondemand') {
                    if (!empty($paidSet[(int)$f['FeeID']])) {
                        continue;
                    }
                    $sfAnyUnpaid = $latestUnpaidByFee[(int)$f['FeeID']] ?? null;
                    if ($sfAnyUnpaid) {
                        $sfUsed = $sfAnyUnpaid;
                        if (!empty($sfAnyUnpaid['DueDate'])) {
                            $dueDate = new \DateTime($sfAnyUnpaid['DueDate']);
                        }
                    } // else keep schedule-derived due date (likely EndDate within this month)
                } else if (strtolower((string)$type) === 'onetime') {
                    if (!empty($paidSet[(int)$f['FeeID']])) {
                        continue;
                    }
                    $dueDate = null;
                }
            }

            $amount = $this->resolveMappingAmount((int)$f['FeeID'], $classId, $sectionId);
            if ($amount <= 0) continue;

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
            $fine = $this->computeFineFromPolicies($pol['byFee'][(int)$f['FeeID']] ?? [], $pol['global'], $row['DueDate'] ?? null, (float)$row['Amount']);
            $row['ComputedFine'] = $fine;
            $row['Outstanding'] = max(0.0, round(((float)$row['Amount'] + $fine - (float)$row['DiscountAmount'] - (float)$row['AmountPaid']), 2));
            $row['Status'] = $this->deriveStatus($row['DueDate'] ?? null, (float)$row['Amount'] + $fine - (float)$row['DiscountAmount'], (float)$row['AmountPaid']);
            $out[] = $row;
        }
        usort($out, function($a, $b){
            $ad = strtotime($a['DueDate'] ?? '');
            $bd = strtotime($b['DueDate'] ?? '');
            if ($ad === $bd) return strcmp($a['FeeName'] ?? '', $b['FeeName'] ?? '');
            return $ad <=> $bd;
        });
        return $out;
    }

    // Locate existing fee row within a month window
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

    // Check if any PAID ledger exists for this student/fee
    private function hasPaidLedger(int $studentId, int $feeId): bool
    {
        $q = "SELECT 1 FROM Tx_student_fees WHERE StudentID = :StudentID AND FeeID = :FeeID AND Status = 'Paid' LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':StudentID', $studentId, PDO::PARAM_INT);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    // Find any existing UNPAID ledger row for this student/fee
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

    // Ensure a monthly ledger row exists (create if missing) and return its ID
    public function ensureMonthlyRow(int $schoolId, int $academicYearId, int $studentId, int $feeId, int $year, int $month, ?string $createdBy): int
    {
        $monthStart = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = (clone $monthStart)->modify('last day of this month');
        $existing = $this->findExistingRowForMonth($studentId, $feeId, $monthStart, $monthEnd);
        if ($existing) return (int)$existing['StudentFeeID'];
        $stu = $this->getStudent($studentId);
        $classId = isset($stu['ClassID']) ? (int)$stu['ClassID'] : null;
        $sectionId = isset($stu['SectionID']) ? (int)$stu['SectionID'] : null;
        $sched = $this->getFeeSchedule($feeId);
        $due = $this->deriveDueDateForMonth($sched, $year, $month);
        if (!$due) {
            $due = $monthStart;
        }
        $amount = $this->resolveMappingAmount($feeId, $classId, $sectionId);
        if ($amount < 0) $amount = 0.0;

        return $this->assignFee($schoolId, $academicYearId, $studentId, $feeId, $classId, $sectionId, $due->format('Y-m-d'), $amount, null, $createdBy ?: 'System');
    }

    // Compute due date for a month from a fee schedule; null when not applicable
    private function deriveDueDateForMonth(array $feeScheduleRow, int $year, int $month): ?\DateTime
    {
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
            if ($end && $monthStart > $end) return null;
            $refStart = $start ?: new \DateTime('1970-01-01');
            if ($start && $monthEnd < $start) return null;

            $monthsDiff = $this->monthsDiff($refStart, $monthStart);
            $interval = $interval ?: 1;
            if ($monthsDiff % $interval !== 0) return null;

            $maxDay = (int)$monthEnd->format('j');
            $day = (int)($dom ?: 1);
            if ($day < 1) $day = 1;
            if ($day > $maxDay) $day = $maxDay;
            return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        }
        if ($type === 'OneTime') {
            $d = $start ?: null;
            if (!$d) return null;
            if ($d >= $monthStart && $d <= $monthEnd) return $d;
            return null;
        }

        if ($type === 'OnDemand') {
            // For OnDemand, treat the EndDate as the due date when available
            if ($start && $end) {
                if ($start <= $monthEnd && $end >= $monthStart) {
                    return ($end >= $monthStart && $end <= $monthEnd) ? $end : null;
                }
                return null;
            }
            if ($end) {
                if ($end >= $monthStart && $end <= $monthEnd) return $end;
                return null;
            }
            // No EndDate: no fixed due date from schedule
            return null;
        }
        return null;
    }

    // Month difference between two dates
    private function monthsDiff(\DateTime $start, \DateTime $end): int
    {
        $y = ((int)$end->format('Y')) - ((int)$start->format('Y'));
        $m = ((int)$end->format('n')) - ((int)$start->format('n'));
        return $y * 12 + $m;
    }

    // Fetch schedule for a fee
    private function getFeeSchedule(int $feeId): ?array
    {
        $q = "SELECT FeeID, ScheduleType, IntervalMonths, DayOfMonth, StartDate, EndDate FROM Tx_fees_schedules WHERE FeeID = :FeeID LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    // Fetch student row
    private function getStudent(int $studentId): ?array
    {
        $q = "SELECT * FROM Tx_Students WHERE StudentID = :id LIMIT 1";
        $st = $this->conn->prepare($q);
        $st->bindValue(':id', $studentId, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    // Compute fine using all applicable policies (fee-specific + global)
    public function computeFine(int $schoolId, int $academicYearId, int $feeId, ?string $dueDate, float $baseAmount, ?\DateTime $asOf = null): float
    {
        $asOf = $asOf ?: new \DateTime('today');
        if (!$dueDate) return 0.0;

        $due = new \DateTime($dueDate);
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
                $fineP = $amount * max(1, $daysLate);
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

    // Compute fine from preloaded policies
    private function computeFineFromPolicies(array $feePolicies, array $globalPolicies, ?string $dueDate, float $baseAmount, ?\DateTime $asOf = null): float
    {
        $asOf = $asOf ?: new \DateTime('today');
        if (!$dueDate) return 0.0;
        $due = new \DateTime($dueDate);
        $policies = array_merge($feePolicies, $globalPolicies);
        if (!$policies) return 0.0;
        $totalFine = 0.0;
        foreach ($policies as $p) {
            $graceDays = isset($p['GraceDays']) ? (int)$p['GraceDays'] : 0;
            $graceLimit = (clone $due)->modify("+{$graceDays} day");
            if ($asOf <= $graceLimit) continue;
            $apply = $p['ApplyType'] ?? 'Fixed';
            $amount = isset($p['Amount']) ? (float)$p['Amount'] : 0.0;
            $daysLate = (int)$graceLimit->diff($asOf)->format('%a');
            $fineP = 0.0;
            if ($apply === 'Fixed') { $fineP = $amount; }
            elseif ($apply === 'PerDay') { $fineP = $amount * max(1, $daysLate); }
            elseif ($apply === 'Percentage') { $fineP = ($amount / 100.0) * $baseAmount; }
            $maxCap = (array_key_exists('MaxAmount', $p) && $p['MaxAmount'] !== null) ? (float)$p['MaxAmount'] : null;
            if ($maxCap !== null) { $fineP = min($fineP, $maxCap); }
            $totalFine += max(0.0, round($fineP, 2));
        }
        return round(max(0.0, $totalFine), 2);
    }

    // Fetch fine policies once for a set of fees
    private function fetchPoliciesForFees(int $schoolId, int $academicYearId, array $feeIds): array
    {
        if (!$feeIds) return ['global' => [], 'byFee' => []];
        $placeholders = implode(',', array_fill(0, count($feeIds), '?'));
        $sql = "SELECT * FROM Tx_fine_policies WHERE SchoolID = ? AND AcademicYearID = ? AND IsActive = 1 AND (FeeID IN ($placeholders) OR FeeID IS NULL) ORDER BY (FeeID IS NULL) ASC, FinePolicyID DESC";
        $st = $this->conn->prepare($sql);
        $i = 1;
        $st->bindValue($i++, $schoolId, PDO::PARAM_INT);
        $st->bindValue($i++, $academicYearId, PDO::PARAM_INT);
        foreach ($feeIds as $fid) { $st->bindValue($i++, $fid, PDO::PARAM_INT); }
        $st->execute();
        $global = [];
        $byFee = [];
        while ($p = $st->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($p['FeeID']) || $p['FeeID'] === null) { $global[] = $p; }
            else { $byFee[(int)$p['FeeID']][] = $p; }
        }
        return ['global' => $global, 'byFee' => $byFee];
    }

    // Insert a student fee ledger row and return its ID
    public function assignFee(int $schoolId, int $academicYearId, int $studentId, int $feeId, ?int $classId, ?int $sectionId, ?string $dueDate, ?float $amount, ?int $mappingId, string $createdBy): int
    {
        if ($amount === null) {
            $amount = $this->resolveMappingAmount($feeId, $classId, $sectionId);
        }
        if ($dueDate === null) {
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

    // Record a payment against a student fee row
    public function recordPayment(int $studentFeeId, float $paidAmount, string $mode, ?string $transactionRef, ?string $paymentDate, ?float $discountDelta, ?string $createdBy): bool
    {
        $paymentDate = $paymentDate ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $inTx = false;
        try {
            if ($this->conn->inTransaction() === false) {
                $this->conn->beginTransaction();
                $inTx = true;
            }
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

    // Refresh status of a ledger row based on fields and fine policy
    public function refreshStatus(int $studentFeeId): void
    {
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

    // Derive status string
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

    // Resolve Amount from class-section mapping for fee
    private function resolveMappingAmount(int $feeId, ?int $classId, ?int $sectionId): float
    {
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
        if ($classId) {
            $sql = "SELECT Amount FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND ClassID = :ClassID AND (SectionID IS NULL OR SectionID = 0) AND IsActive = 1 LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
            $stmt->bindValue(':ClassID', $classId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['Amount'] !== null) return (float)$row['Amount'];
        }
        $sql = "SELECT MIN(Amount) AS Amount FROM Tx_fee_class_section_mapping WHERE FeeID = :FeeID AND IsActive = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':FeeID', $feeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['Amount'] !== null) return (float)$row['Amount'];
        return 0.0;
    }
}
