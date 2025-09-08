<?php
namespace SchoolLive\Models;

use PDO;
use DateTime;

class DashboardModel extends Model {
    /**
     * Aggregated top-level statistics.
     */
    public function getStats(int $schoolId, ?int $academicYearId): array {
        $stats = [
            'totalStudents' => 0,
            'totalTeachers' => 0,
            'totalStaff' => 0,
            'totalClasses' => 0,
            'averageAttendance' => 0.0,
            'pendingFees' => 0,
            'upcomingEvents' => 0,
            'totalRevenue' => 0
        ];

        // Students
    // Count only active students by default (use IFNULL to tolerate older schemas)
    $sql = "SELECT COUNT(*) c FROM Tx_Students WHERE SchoolID = :school AND IFNULL(IsActive, TRUE) = 1" . ($academicYearId ? " AND AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['totalStudents'] = (int)$stmt->fetchColumn();

        // Employees (teachers + other staff) from Tx_Employees
    $sql = "SELECT COUNT(*) c FROM Tx_Employees WHERE SchoolID = :school AND IFNULL(IsActive, TRUE) = 1" . ($academicYearId ? " AND AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $totalEmployees = (int)$stmt->fetchColumn();

        // Teachers have RoleID referencing teacher role (look up RoleID where RoleName='teacher')
        $teacherRoleId = $this->getTeacherRoleId();
        if ($teacherRoleId) {
            $sql = "SELECT COUNT(*) c FROM Tx_Employees WHERE SchoolID = :school AND RoleID = :role AND IFNULL(IsActive, TRUE) = 1" . ($academicYearId ? " AND AcademicYearID = :ay" : "");
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
            $stmt->bindValue(':role', $teacherRoleId, PDO::PARAM_INT);
            if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['totalTeachers'] = (int)$stmt->fetchColumn();
        }
        $stats['totalStaff'] = max(0, $totalEmployees - $stats['totalTeachers']);

        // Classes
    $sql = "SELECT COUNT(*) c FROM Tx_Classes WHERE SchoolID = :school AND IFNULL(IsActive, TRUE) = 1" . ($academicYearId ? " AND AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['totalClasses'] = (int)$stmt->fetchColumn();

        // Average attendance today (student)
        $today = (new DateTime())->format('Y-m-d');
        $avg = $this->computeAverageAttendanceForDate($schoolId, $academicYearId, $today);
        if ($avg === null) { // fallback latest date
            $latest = $this->getLatestAttendanceDate($schoolId, $academicYearId);
            if ($latest) {
                $avg = $this->computeAverageAttendanceForDate($schoolId, $academicYearId, $latest);
            }
        }
        $stats['averageAttendance'] = $avg !== null ? round($avg, 2) : 0.0;

        // Pending fees (sum due - paid for invoices not fully paid)
        if ($this->tableExists('Tx_FeeInvoices')) {
            $sql = "SELECT SUM(AmountDue-AmountPaid) FROM Tx_FeeInvoices WHERE SchoolID=:school AND Status IN ('Pending','Partial')" . ($academicYearId?" AND AcademicYearID=:ay":"");
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
            if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
            $stmt->execute();
            $val = $stmt->fetchColumn();
            $stats['pendingFees'] = (float)($val ?: 0);
        }

        // Upcoming events count
        if ($this->tableExists('Tx_Events')) {
            // Consider only active events if IsActive exists; otherwise include by default
            $sql = "SELECT COUNT(*) FROM Tx_Events WHERE SchoolID=:school AND EventDate >= CURDATE() AND IFNULL(IsActive, TRUE) = 1" . ($academicYearId?" AND AcademicYearID=:ay":"");
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
            if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
            $stmt->execute();
            $stats['upcomingEvents'] = (int)$stmt->fetchColumn();
        }

        // Total revenue (sum paid fees over current AY / timeframe)
        if ($this->tableExists('Tx_Fees')) {
            $sql = "SELECT SUM(Amount) FROM Tx_Fees WHERE SchoolID=:school" . ($academicYearId?" AND AcademicYearID=:ay":"");
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
            if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
            $stmt->execute();
            $stats['totalRevenue'] = (float)($stmt->fetchColumn() ?: 0);
        }

        return $stats;
    }

    private function getTeacherRoleId(): ?int {
        $stmt = $this->conn->query("SELECT RoleID FROM Tm_Roles WHERE RoleName='teacher' LIMIT 1");
        $row = $stmt->fetch();
        return $row ? (int)$row['RoleID'] : null;
    }

    private function computeAverageAttendanceForDate(int $schoolId, ?int $academicYearId, string $date): ?float {
        $sql = "SELECT AVG(CASE WHEN a.Status='Present' THEN 1 ELSE 0 END)*100 AS pct
                FROM Tx_Students_Attendance a
                WHERE a.SchoolID = :school AND a.Date = :dt" . ($academicYearId ? " AND a.AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':dt', $date);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : null;
    }

    private function getLatestAttendanceDate(int $schoolId, ?int $academicYearId): ?string {
        $sql = "SELECT MAX(Date) FROM Tx_Students_Attendance WHERE SchoolID = :school" . ($academicYearId ? " AND AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ?: null;
    }

    /**
     * Attendance overview for today (present/absent/late placeholder; late not tracked so returns 0).
     */
    public function getAttendanceOverviewToday(int $schoolId, ?int $academicYearId): array {
        $today = (new DateTime())->format('Y-m-d');
        $sql = "SELECT 
                    SUM(CASE WHEN a.Status='Present' THEN 1 ELSE 0 END) present,
                    SUM(CASE WHEN a.Status='Absent' THEN 1 ELSE 0 END) absent,
                    SUM(CASE WHEN a.Status='HalfDay' THEN 1 ELSE 0 END) halfday
                FROM Tx_Students_Attendance a
                WHERE a.SchoolID = :school AND a.Date = :dt" . ($academicYearId ? " AND a.AcademicYearID = :ay" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':dt', $today);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['present'=>0,'absent'=>0,'halfday'=>0];
        return [
            'labels' => ['Present','Absent','HalfDay'],
            'datasets' => [
                [
                    'data' => [ (int)$row['present'], (int)$row['absent'], (int)$row['halfday'] ],
                    'backgroundColor' => ['#10b981','#ef4444','#f59e0b']
                ]
            ]
        ];
    }

    /**
     * Class-wise present vs absent counts for today (or latest date fallback).
     */
    public function getClassAttendanceToday(int $schoolId, ?int $academicYearId): array {
        $today = (new DateTime())->format('Y-m-d');
        $dateToUse = $today;
        // ensure date has records
        $checkSql = "SELECT COUNT(1) FROM Tx_Students_Attendance WHERE SchoolID=:school AND Date=:dt" . ($academicYearId?" AND AcademicYearID=:ay":"");
        $check = $this->conn->prepare($checkSql);
        $check->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $check->bindValue(':dt',$today);
        if ($academicYearId) $check->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        $check->execute();
        if ((int)$check->fetchColumn() === 0) {
            $latest = $this->getLatestAttendanceDate($schoolId, $academicYearId);
            if ($latest) { $dateToUse = $latest; }
        }

        $sql = "SELECT CONCAT(c.ClassName,'-',sec.SectionName) as ClassName,
                       SUM(CASE WHEN a.Status='Present' THEN 1 ELSE 0 END) AS present,
                       SUM(CASE WHEN a.Status='Absent' THEN 1 ELSE 0 END) AS absent,
                       SUM(CASE WHEN a.Status='Leave' THEN 1 ELSE 0 END) AS onleave,
                       SUM(CASE WHEN a.Status='HalfDay' THEN 1 ELSE 0 END) AS halfday
                FROM Tx_Students s
                INNER JOIN Tx_Sections sec ON s.SectionID = sec.SectionID
                INNER JOIN Tx_Classes c ON sec.ClassID = c.ClassID
                LEFT JOIN Tx_Students_Attendance a 
                       ON a.StudentID = s.StudentID 
                      AND a.Date = :dt
                      " . ($academicYearId ? " AND a.AcademicYearID = :ay" : "") . "
                WHERE s.SchoolID = :school" . ($academicYearId ? " AND s.AcademicYearID = :ay" : "") . "
                GROUP BY c.ClassID, c.ClassName,sec.SectionID,sec.SectionName
                ORDER BY c.ClassName";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':dt', $dateToUse);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        $stmt->execute();
    $labels = [];
    $present = [];
    $absent = [];
    $onleave = [];
    $halfday = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['ClassName'];
            $present[] = (int)$row['present'];
            $absent[] = (int)$row['absent'];
            $onleave[] = (int)$row['onleave'];
            $halfday[] = (int)$row['halfday'];
        }
        return [
            'labels' => $labels,
            'datasets' => [
                [ 'label' => 'Present', 'data' => $present, 'backgroundColor' => '#ef4444', 'stack' => 'attendance' ],
                [ 'label' => 'Absent', 'data' => $absent, 'backgroundColor' => '#10b981', 'stack' => 'attendance' ],
                [ 'label' => 'Leave', 'data' => $onleave, 'backgroundColor' => '#f59e0b', 'stack' => 'attendance' ],
                [ 'label' => 'HalfDay', 'data' => $halfday, 'backgroundColor' => '#0964f6ff', 'stack' => 'attendance' ]
            ],
            'options' => [ 'indexAxis' => 'y', 'stacked' => true ]
        ];
    }

    /**
     * Monthly attendance percentages for last $months months (including current month).
     * Percentage = (Present records / Total attendance records) * 100 per month.
     */
    public function getMonthlyAttendance(int $schoolId, ?int $academicYearId, int $months = 12): array {
        if ($months < 1) { $months = 1; }
        if ($months > 24) { $months = 24; }
        $end = new DateTime('first day of this month');
        $start = (clone $end)->modify('-' . ($months - 1) . ' months');

        // Aggregate present and total per month in range
        $sql = "SELECT DATE_FORMAT(Date,'%Y-%m') ym,
                       SUM(CASE WHEN Status='Present' THEN 1 ELSE 0 END) AS present,
                       COUNT(*) AS total
                FROM Tx_Students_Attendance
                WHERE SchoolID = :school AND Date >= :startDate" . ($academicYearId ? " AND AcademicYearID = :ay" : "") . "
                GROUP BY ym";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':startDate', $start->format('Y-m-d'));
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        try { $stmt->execute(); } catch (\Throwable $e) { return ['labels'=>[],'datasets'=>[]]; }
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$r['ym']] = [ 'present' => (int)$r['present'], 'total' => (int)$r['total'] ];
        }
        $labels = [];
        $data = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $ym = $cursor->format('Y-m');
            $labels[] = $cursor->format('M');
            if (isset($map[$ym]) && $map[$ym]['total'] > 0) {
                $pct = ($map[$ym]['present'] / $map[$ym]['total']) * 100;
                $data[] = round($pct, 2);
            } else {
                $data[] = 0;
            }
            $cursor->modify('+1 month');
        }
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Attendance %',
                'data' => $data,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16,185,129,0.15)',
                'tension' => 0.35,
                'fill' => true,
                'pointRadius' => 3
            ]]
        ];
    }

    /**
     * Monthly enrollment trend (student cumulative counts by month based on AdmissionDate)
     */
    public function getEnrollmentTrend(int $schoolId, ?int $academicYearId, int $months = 12): array {
        // Determine date range: last $months months including current month
        $labels = [];
        $counts = [];
        $end = new DateTime('first day of this month');
        $start = (clone $end)->modify('-' . ($months - 1) . ' months');

        // Pre-fetch cumulative counts grouped by month
        $sql = "SELECT DATE_FORMAT(AdmissionDate, '%Y-%m') ym, COUNT(*) cnt
                FROM Tx_Students
                WHERE SchoolID = :school" . ($academicYearId ? " AND AcademicYearID = :ay" : "") . "
                  AND AdmissionDate >= :startDate
                GROUP BY ym";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':startDate', $start->format('Y-m-d'));
        $stmt->execute();
        $byMonth = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byMonth[$row['ym']] = (int)$row['cnt'];
        }

        $cumulative = 0;
        $cursor = clone $start;
        while ($cursor <= $end) {
            $ym = $cursor->format('Y-m');
            $label = $cursor->format('M');
            $labels[] = $label;
            if (isset($byMonth[$ym])) {
                $cumulative += $byMonth[$ym];
            }
            $counts[] = $cumulative;
            $cursor->modify('+1 month');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Enrollment',
                    'data' => $counts,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.15)',
                    'tension' => 0.35,
                    'fill' => true
                ]
            ]
        ];
    }

    /** Grade distribution chart (A+,A,B+,B,C+,C,D,F) derived from Tx_StudentGrades */
    public function getGradeDistribution(int $schoolId, ?int $academicYearId): array {
        $gradeBuckets = ['A+','A','B+','B','C+','C','D','F'];
        // If the grades table doesn't exist, return labeled buckets with zero counts
        if (!$this->tableExists('Tx_StudentGrades')) {
            return [
                'labels' => $gradeBuckets,
                'datasets' => [[
                    'label' => 'Students',
                    'data' => array_fill(0, count($gradeBuckets), 0),
                    'backgroundColor' => [
                        '#10b981','#34d399','#60a5fa','#3b82f6','#a78bfa','#8b5cf6','#f59e0b','#ef4444'
                    ],
                    'borderRadius' => 4
                ]]
            ];
        }
        $placeholders = implode(',', array_fill(0, count($gradeBuckets), '?'));
        $sql = "SELECT g.GradeLetter, COUNT(*) cnt
                FROM Tx_StudentGrades g
                INNER JOIN Tx_Students s ON g.StudentID = s.StudentID
                WHERE s.SchoolID = ?" . ($academicYearId?" AND g.AcademicYearID = ?":"") . "
                GROUP BY g.GradeLetter";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1,$schoolId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(2,$academicYearId,PDO::PARAM_INT);
        $stmt->execute();
        $countsMap = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $countsMap[$r['GradeLetter']] = (int)$r['cnt'];
        }
        $dataCounts = array_map(fn($g)=> $countsMap[$g] ?? 0, $gradeBuckets);
        return [
            'labels' => $gradeBuckets,
            'datasets' => [[
                'label' => 'Students',
                'data' => $dataCounts,
                'backgroundColor' => [
                    '#10b981','#34d399','#60a5fa','#3b82f6','#a78bfa','#8b5cf6','#f59e0b','#ef4444'
                ],
                'borderRadius' => 4
            ]]
        ];
    }

    /** Revenue breakdown last 6 months by category (Tuition, Extra, Transport) */
    public function getRevenueBreakdown(int $schoolId, ?int $academicYearId, int $months = 6): array {
        if (!$this->tableExists('Tx_Fees')) {
            return ['labels'=>[], 'datasets'=>[]];
        }
        $categories = ['Tuition','Extra','Transport'];
        $end = new DateTime('first day of this month');
        $start = (clone $end)->modify('-' . ($months - 1) . ' months');
        $sql = "SELECT DATE_FORMAT(PaymentDate,'%Y-%m') ym, Category, SUM(Amount) amt
                FROM Tx_Fees
                WHERE SchoolID = :school AND PaymentDate >= :startDate" . ($academicYearId?" AND AcademicYearID=:ay":"") . "
                GROUP BY ym, Category";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        $stmt->bindValue(':startDate',$start->format('Y-m-d'));
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        $stmt->execute();
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$r['ym']][$r['Category']] = (float)$r['amt'];
        }
        $labels = [];
        $cursor = clone $start;
        while ($cursor <= $end) { $labels[] = $cursor->format('M'); $cursor->modify('+1 month'); }
        $datasets = [];
        $colors = ['Tuition'=>'#3b82f6','Extra'=>'#10b981','Transport'=>'#f59e0b'];
        foreach ($categories as $cat) {
            $data = [];
            $cursor = clone $start;
            while ($cursor <= $end) {
                $ym = $cursor->format('Y-m');
                $data[] = $map[$ym][$cat] ?? 0;
                $cursor->modify('+1 month');
            }
            $datasets[] = [ 'label'=>$cat . ' Fees', 'data'=>$data, 'backgroundColor'=>$colors[$cat] ?? '#999' ];
        }
        return [ 'labels'=>$labels, 'datasets'=>$datasets ];
    }

    /**
     * Top performing classes based on average marks and attendance.
     * Returns top N classes with teacher, student count, averageGrade, attendance
     */
    public function getTopClasses(int $schoolId, ?int $academicYearId, int $limit = 5): array {
        // Aggregate average marks and attendance per class
        $sql = "SELECT c.ClassID, c.ClassName,
                       COUNT(DISTINCT s.StudentID) AS students,
                       ROUND(AVG(g.Marks),2) AS averageGrade,
                       ROUND(AVG(CASE WHEN a.Status='Present' THEN 1 ELSE 0 END)*100,2) AS attendance,
                       CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName) AS teacher
                FROM Tx_Classes c
                LEFT JOIN Tx_Sections sec ON sec.ClassID = c.ClassID
                LEFT JOIN Tx_Students s ON s.SectionID = sec.SectionID AND s.SchoolID = c.SchoolID
                LEFT JOIN Tx_StudentGrades g ON g.StudentID = s.StudentID" . ($academicYearId ? " AND g.AcademicYearID = :ay" : "") . "
                LEFT JOIN Tx_Students_Attendance a ON a.StudentID = s.StudentID" . ($academicYearId ? " AND a.AcademicYearID = :ay" : "") . "
                LEFT JOIN Tx_ClassTeachers ct ON ct.ClassID = c.ClassID AND ct.IsActive = 1
                LEFT JOIN Tx_Employees e ON e.EmployeeID = ct.EmployeeID
                WHERE c.SchoolID = :school" . ($academicYearId ? " AND c.AcademicYearID = :ay" : "") . "
                GROUP BY c.ClassID, c.ClassName, CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName)
                ORDER BY averageGrade DESC
                LIMIT :lim";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'className' => $r['ClassName'],
                'teacher' => $r['teacher'] ?? 'TBD',
                'students' => (int)$r['students'],
                'averageGrade' => $r['averageGrade'] !== null ? (float)$r['averageGrade'] : null,
                'attendance' => $r['attendance'] !== null ? (float)$r['attendance'] : null,
            ];
        }
        return $rows;
    }

    /**
     * Class-wise student count split by gender.
     * Returns labels and datasets for Male/Female/Other
     */
    public function getClassGenderCounts(int $schoolId, ?int $academicYearId): array {
    $sql = "SELECT c.ClassID, c.ClassName,
               SUM(CASE WHEN s.Gender='M' THEN 1 ELSE 0 END) AS male,
               SUM(CASE WHEN s.Gender='F' THEN 1 ELSE 0 END) AS female,
               SUM(CASE WHEN s.Gender='O' THEN 1 ELSE 0 END) AS other
        FROM Tx_Classes c
                LEFT JOIN Tx_Sections sec ON sec.ClassID = c.ClassID
                LEFT JOIN Tx_Students s ON s.SectionID = sec.SectionID AND s.SchoolID = c.SchoolID
                WHERE c.SchoolID = :school" . ($academicYearId ? " AND c.AcademicYearID = :ay" : "") . "
                GROUP BY c.ClassID, c.ClassName
                ORDER BY c.ClassName";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $male = [];
        $female = [];
        $other = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = trim($r['ClassName'] ?? '');
            if ($label === '') {
                $label = 'Class ' . ($r['ClassID'] ?? '');
            }
            $labels[] = $label;
            $male[] = (int)$r['male'];
            $female[] = (int)$r['female'];
            $other[] = (int)$r['other'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Male', 'data' => $male, 'backgroundColor' => '#3b82f6', 'stack' => 'gender'],
                ['label' => 'Female', 'data' => $female, 'backgroundColor' => '#ec4899', 'stack' => 'gender'],
                ['label' => 'Other', 'data' => $other, 'backgroundColor' => '#9ca3af', 'stack' => 'gender']
            ]
        ];
    }

    /**
     * Attempt to get upcoming events if an events table exists.
     * Returns an array of associative rows or empty array.
     */
    public function getUpcomingEvents(int $schoolId, ?int $academicYearId, int $limit = 6): array {
        if (!$this->tableExists('Tx_Events')) return [];
        $sql = "SELECT EventID, Title, EventDate, StartTime, EndTime, Location, Type, Priority
                FROM Tx_Events
                WHERE SchoolID = :school
                  AND EventDate >= CURDATE()" . ($academicYearId ? " AND AcademicYearID = :ay" : "") . "
                ORDER BY EventDate ASC
                LIMIT :lim";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Normalize fields to front-end expected structure
        return array_map(function($r){
            return [
                'id' => $r['EventID'],
                'title' => $r['Title'],
                'date' => $r['EventDate'],
                'time' => trim(($r['StartTime'] ?? '') . ($r['EndTime'] ? (' - ' . $r['EndTime']) : '')),
                'location' => $r['Location'],
                'type' => $r['Type'],
                'priority' => strtolower($r['Priority'] ?? 'medium')
            ];
        }, $rows ?: []);
    }

    /**
     * Attempt to get recent activities if an activity log table exists.
     */
    public function getRecentActivities(int $schoolId, ?int $academicYearId, int $limit = 10): array {
        if (!$this->tableExists('Tx_ActivityLog')) return [];
        $sql = "SELECT ActivityID, ActivityType, Message, CreatedAt, Severity, Icon
                FROM Tx_ActivityLog
                WHERE SchoolID = :school" . ($academicYearId ? " AND AcademicYearID = :ay" : "") . "
                ORDER BY CreatedAt DESC
                LIMIT :lim";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($r){
            return [
                'id' => $r['ActivityID'],
                'type' => $r['ActivityType'],
                'message' => $r['Message'],
                'timestamp' => $r['CreatedAt'],
                'icon' => $r['Icon'] ?: 'pi pi-info-circle',
                'severity' => $r['Severity'] ?: 'info'
            ];
        }, $rows ?: []);
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
            $stmt->bindValue(':t', $table);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
