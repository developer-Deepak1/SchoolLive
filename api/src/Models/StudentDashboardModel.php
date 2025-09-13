<?php
namespace SchoolLive\Models;

use PDO; use DateTime; use DateInterval; use DatePeriod;

class StudentDashboardModel extends Model {
    private function tableExists(string $table): bool {
        try {
            $stmt = $this->conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
            $stmt->bindValue(':t',$table);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) { return false; }
    }

    public function resolveStudentIdForUser(int $schoolId, int $studentId): ?int {
    // Only map to active student record by default
    $sql = "SELECT StudentID FROM Tx_Students WHERE SchoolID=:school AND StudentID=:sid AND IFNULL(IsActive, TRUE) = 1 LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function validateStudentBelongsToSchool(int $schoolId, int $studentId): bool {
        try {
            $stmt = $this->conn->prepare("SELECT 1 FROM Tx_Students WHERE SchoolID=:school AND StudentID=:sid LIMIT 1");
            $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
            $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) { return false; }
    }

    public function getAverageAttendance(int $schoolId, int $studentId, ?int $academicYearId): float {
        if (!$this->tableExists('Tx_Students_Attendance')) return 0.0;
    $sql = "SELECT AVG(CASE WHEN Status='Present' THEN 1 ELSE 0 END)*100 FROM Tx_Students_Attendance WHERE SchoolID=:school AND StudentID=:sid" . ($academicYearId?" AND AcademicYearID=:ay":"");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? round((float)$val,2) : 0.0;
    }

    public function getMonthlyAttendance(int $schoolId, int $studentId, ?int $academicYearId, int $months = 12): array {
        $cal = new AcademicModel();
        return $cal->getMonthlyAttendance($schoolId, $studentId, $academicYearId, $months);
    }


    /**
     * Get today's attendance status for a student (Status, Remarks).
     * Returns array like: ['status'=>'Present'|'Absent'|'Leave'|'HalfDay'|'NotMarked', 'remarks'=>string|null]
     */
    public function getTodayAttendance(int $schoolId, int $studentId, ?int $academicYearId): array {
        if (!$this->tableExists('Tx_Students_Attendance')) return ['status' => 'NotMarked', 'remarks' => null];
        $sql = "SELECT Status, Remarks FROM Tx_Students_Attendance WHERE SchoolID = :school AND StudentID = :sid AND Date = CURDATE()" . ($academicYearId?" AND AcademicYearID = :ay":"") . " LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        try { $stmt->execute(); } catch (\Throwable $e) { return ['status' => 'NotMarked', 'remarks' => null]; }
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['status' => 'NotMarked', 'remarks' => null];
        $st = $r['Status'] ?? '';
        $map = ['P'=>'Present','Present'=>'Present','A'=>'Absent','Absent'=>'Absent','L'=>'Leave','Leave'=>'Leave','H'=>'HalfDay','HalfDay'=>'HalfDay','Half Day'=>'HalfDay'];
        $norm = $map[$st] ?? $st;
        return ['status' => $norm ?: 'NotMarked', 'remarks' => $r['Remarks'] ?? null];
    }

    public function getRecentActivities(int $schoolId, int $studentId, ?int $academicYearId, int $limit=10): array {
        // If a per-student log table exists use it; else fallback to global activity log filtered by maybe StudentID in message
        if ($this->tableExists('Tx_StudentActivity')) {
            $sql="SELECT ActivityType, Message, CreatedAt, Severity, Icon FROM Tx_StudentActivity WHERE SchoolID=:school AND StudentID=:sid" . ($academicYearId?" AND AcademicYearID=:ay":"") . " ORDER BY CreatedAt DESC LIMIT :lim";
            $stmt=$this->conn->prepare($sql); $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT); $stmt->bindValue(':sid',$studentId,PDO::PARAM_INT); if($academicYearId)$stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT); $stmt->bindValue(':lim',$limit,PDO::PARAM_INT); $stmt->execute();
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($this->tableExists('Tx_ActivityLog')) {
            $sql="SELECT ActivityType, Message, CreatedAt, Severity, Icon FROM Tx_ActivityLog WHERE SchoolID=:school" . ($academicYearId?" AND AcademicYearID=:ay":"") . " ORDER BY CreatedAt DESC LIMIT :lim";
            $stmt=$this->conn->prepare($sql); $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT); if($academicYearId)$stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT); $stmt->bindValue(':lim',$limit,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        } else { $rows=[]; }
        return array_map(fn($r)=>[
            'type'=>$r['ActivityType']??'info',
            'message'=>$r['Message']??'',
            'timestamp'=>$r['CreatedAt']??'',
            'icon'=>$r['Icon']?:'pi pi-info-circle',
            'severity'=>$r['Severity']?:'info'
        ], $rows?:[]);
    }

    public function getMonthlyAttendancePercentage(int $schoolId, int $studentId, ?int $academicYearId, int $months = 12): array {
        $cal = new AcademicModel();
        $range = $cal->getAcademicYearRange($academicYearId, $schoolId);
        if (!$range['start'] || !$range['end']) return ['labels'=>[],'datasets'=>[]];

        $ayId = $range['academicYearId'];
        $weeklyOffs = $ayId ? $cal->getWeeklyOffs($ayId, $schoolId) : [];
        $holidaysMap = $ayId ? $cal->getHolidays($ayId, $schoolId) : [];

        // Compute the months and base working days for each month
        $computed = $cal->getMonthlyWorkingDays($ayId, $schoolId);

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

            // Return only the percentage dataset (labels + percent values)
            return [
                'labels' => $computed['labels'],
                'datasets' => [
                    [ 'label' => 'Percent', 'data' => $percent, 'backgroundColor' => '#92dbc2ff' ]
                ]
            ];
    }
}
?>