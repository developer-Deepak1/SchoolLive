<?php
namespace SchoolLive\Models;

use DateTime; use DateInterval; use DatePeriod; use PDO;

class AcademicCalendarModel extends Model {
    /**
     * Return academic year start and end dates for a given academicYearId and/or school.
     * If $academicYearId is null, falls back to the active academic year for the school.
     * Returns ['start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD', 'academicYearId' => int|null]
     */
    public function getAcademicYearRange(?int $academicYearId, int $schoolId): array {
        $start = null; $end = null; $ayId = $academicYearId;
        if ($academicYearId) {
            $q = $this->conn->prepare("SELECT AcademicYearID, StartDate, EndDate FROM Tm_AcademicYears WHERE AcademicYearID = :ay LIMIT 1");
            $q->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
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

    /**
     * Return array of weekly off day-of-week integers (1=Mon..7=Sun) for the academic year and school
     */
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

    /**
     * Return associative map of holiday dates (YYYY-MM-DD => ['type'=> 'Holiday'|'WorkingDay', 'title'=>'...'])
     */
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

    /**
     * Compute working days per month between $start and $end inclusive.
     * $start and $end are YYYY-MM-DD strings.
     * $weeklyOffs: array of ints 1..7
     * $holidaysMap: associative map YYYY-MM-DD => ['type'=>..]
     * Returns arrays: ['months' => ['YYYY-MM', ...], 'labels' => ['MonShort',...], 'workingDays' => [int,...]]
     */
    public function computeWorkingDaysByMonth(string $start, string $end, array $weeklyOffs = [], array $holidaysMap = []): array {
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
                // If a holiday entry explicitly marks this date as a working day, count it once and skip further checks
                // if ($h && (($h['type'] ?? 'Holiday') === 'WorkingDay')) {
                //     $wd++;
                //     continue;
                // }

                $isOff = in_array($dow, $weeklyOffs, true);
                $isHoliday = $h && (($h['type'] ?? 'Holiday') === 'Holiday');
                if (!$isOff && !$isHoliday) $wd++;
            }
            $workingDays[] = $wd;
            $c->modify('+1 month');
        }

        return ['months' => $months, 'labels' => $labels, 'workingDays' => $workingDays];
    }

    /**
     * Aggregate attendance counts (Present) grouped by month (YYYY-MM) for given employee and date range.
     * Uses either Tx_Employee_Attendance or Tx_Employees_Attendance depending on what's present in DB.
     * Returns associative map 'YYYY-MM' => count
     */
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

    /**
     * High-level helper to produce monthly attendance payload for an employee like the TeacherDashboard needs.
     * Returns: ['labels'=>[], 'datasets'=>[ ['label'=>'Working Days','data'=>[]], ['label'=>'Present','data'=>[]], ['label'=>'Attendance %','data'=>[]] ] ]
     */
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
    $computed = $this->computeWorkingDaysByMonth($range['start'], $range['end'], $weeklyOffs, $holidaysMap);

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

        $percent = [];
        foreach ($present as $i => $p) {
            $wd = $computed['workingDays'][$i] ?? 0;
            $percent[] = $wd > 0 ? round($p / $wd * 100, 2) : 0;
        }

        return [
            'labels' => $computed['labels'],
            'datasets' => [
                [ 'label' => 'Working Days', 'data' => $computed['workingDays'], 'backgroundColor' => '#94a3b8' ],
                [ 'label' => 'Present', 'data' => $present, 'backgroundColor' => '#10b981' ],
                [ 'label' => 'Attendance %', 'data' => $percent, 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,0.15)', 'tension' => 0.35, 'fill' => true, 'pointRadius' => 3 ]
            ]
        ];
    }
}

?>
