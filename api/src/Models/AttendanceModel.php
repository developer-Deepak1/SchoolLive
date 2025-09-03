<?php
namespace SchoolLive\Models;

use PDO; use DateTime;

class AttendanceModel extends Model {
    /**
     * Fetch attendance for a date (default today) for all students (optionally filter by SectionID).
     * Returns list of students with existing Status or null if not yet marked.
     */
    public function getDaily(int $schoolId, ?int $academicYearId, string $date, ?int $sectionId = null): array {
        $sql = "SELECT s.StudentID, s.StudentName, s.FirstName, s.LastName, s.SectionID, sec.SectionName, c.ClassName,
                       a.StudentAttendanceID, a.Status, a.Remarks
                FROM Tx_Students s
                INNER JOIN Tx_Sections sec ON s.SectionID = sec.SectionID
                INNER JOIN Tx_Classes c ON sec.ClassID = c.ClassID
                LEFT JOIN Tx_Students_Attendance a
                       ON a.StudentID = s.StudentID AND a.Date = :dt" . ($academicYearId?" AND a.AcademicYearID = :ay":"") . "
                WHERE s.SchoolID = :school" . ($academicYearId?" AND s.AcademicYearID = :ay":"") . ($sectionId?" AND s.SectionID = :sid":"") . "
                ORDER BY c.ClassName, sec.SectionName, s.StudentName";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':dt',$date);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
        if ($sectionId) $stmt->bindValue(':sid',$sectionId,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Upsert a single student's attendance for a date. Returns array [created=>bool, updated=>bool, row=>data]. */
    public function upsert(int $schoolId, ?int $academicYearId, int $studentId, string $date, string $status, ?string $remarks, string $username): array {
        $ay = $academicYearId ?? 1;
        // Check existing
        $checkSql = "SELECT StudentAttendanceID, Status FROM Tx_Students_Attendance WHERE SchoolID=:school AND StudentID=:sid AND Date=:dt AND AcademicYearID=:ay LIMIT 1";
        $chk = $this->conn->prepare($checkSql);
        $chk->execute([':school'=>$schoolId, ':sid'=>$studentId, ':dt'=>$date, ':ay'=>$ay]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ($existing['Status'] === $status && $remarks === null) {
                return ['created'=>false,'updated'=>false,'row'=>['StudentAttendanceID'=>$existing['StudentAttendanceID'],'Status'=>$existing['Status']]];
            }
            $upd = $this->conn->prepare("UPDATE Tx_Students_Attendance SET Status=:st, Remarks=:rm, UpdatedBy=:ub, UpdatedAt=NOW() WHERE StudentAttendanceID=:id");
            $upd->execute([':st'=>$status, ':rm'=>$remarks, ':ub'=>$username, ':id'=>$existing['StudentAttendanceID']]);
            return ['created'=>false,'updated'=>true,'row'=>['StudentAttendanceID'=>$existing['StudentAttendanceID'],'Status'=>$status]];
        }
                // Include ClassID in the insert (schema requires ClassID NOT NULL). Use student's ClassID if available.
                $ins = $this->conn->prepare("INSERT INTO Tx_Students_Attendance (Date, Status, StudentID, SectionID, ClassID, SchoolID, AcademicYearID, Remarks, CreatedBy) 
                                                                         SELECT :dt, :st, s.StudentID, s.SectionID, s.ClassID, s.SchoolID, :ay, :rm, :cb
                                                                             FROM Tx_Students s WHERE s.StudentID = :sid AND s.SchoolID = :school LIMIT 1");
        $ins->execute([':dt'=>$date, ':st'=>$status, ':sid'=>$studentId, ':school'=>$schoolId, ':ay'=>$ay, ':rm'=>$remarks, ':cb'=>$username]);
        $id = (int)$this->conn->lastInsertId();
        return ['created'=>true,'updated'=>false,'row'=>['StudentAttendanceID'=>$id,'Status'=>$status]];
    }

    /** Batch upsert; entries = [ ['StudentID'=>, 'Status'=>, 'Remarks'=>?], ... ] */
    public function batchUpsert(int $schoolId, ?int $academicYearId, string $date, array $entries, string $username): array {
        $results = []; $created=0; $updated=0; $unchanged=0; $conflicts=[];
        foreach ($entries as $e) {
            $sid = (int)($e['StudentID'] ?? 0); if ($sid<=0) continue;
            $st = strtoupper($e['Status'] ?? 'P');
            // Normalize short codes
            $map = ['P'=>'Present','L'=>'Leave','H'=>'HalfDay','A'=>'Absent'];
            $status = $map[$st] ?? $e['Status'];
            $res = $this->upsert($schoolId,$academicYearId,$sid,$date,$status,$e['Remarks'] ?? null,$username);
            if ($res['created']) $created++; elseif ($res['updated']) $updated++; else $unchanged++;
            $results[] = ['StudentID'=>$sid,'created'=>$res['created'],'updated'=>$res['updated'],'status'=>$res['row']['Status']];
        }
        return ['summary'=>['created'=>$created,'updated'=>$updated,'unchanged'=>$unchanged],'results'=>$results];
    }
}
