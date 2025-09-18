<?php
namespace SchoolLive\Models;

use PDO; use DateTime;

class AttendanceModel extends Model {
    /**
     * Fetch attendance for a date (default today) for all students (optionally filter by SectionID).
     * Returns list of students with existing Status or null if not yet marked.
     */
    public function getDaily(int $schoolId, ?int $academicYearId, string $date, ?int $sectionId = null, bool $includeInactive = false): array {
        // By default, exclude students where IsActive = 0. Pass includeInactive=true to include them.
        $sql = "SELECT s.StudentID, s.FirstName,s.MiddleName, s.LastName, s.SectionID, sec.SectionName, c.ClassName,
                       IFNULL(s.IsActive, TRUE) AS IsActive, a.StudentAttendanceID, a.Status, a.Remarks
                FROM Tx_Students s
                INNER JOIN Tx_Sections sec ON s.SectionID = sec.SectionID
                INNER JOIN Tx_Classes c ON sec.ClassID = c.ClassID
                LEFT JOIN Tx_Students_Attendance a
                       ON a.StudentID = s.StudentID AND a.Date = :dt" . ($academicYearId?" AND a.AcademicYearID = :ay":"") . "
                WHERE s.SchoolID = :school" . ($academicYearId?" AND s.AcademicYearID = :ay":"") . ($sectionId?" AND s.SectionID = :sid":"");
        if (!$includeInactive) {
            $sql .= " AND IFNULL(s.IsActive, TRUE) = 1";
        }
        $sql .= " ORDER BY c.ClassName, sec.SectionName, s.StudentName";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':dt',$date);
        $stmt->bindValue(':school',$schoolId,PDO::PARAM_INT);
        if ($academicYearId) $stmt->bindValue(':ay',$academicYearId,PDO::PARAM_INT);
    if ($sectionId) $stmt->bindValue(':sid',$sectionId,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Upsert a single student's attendance for a date. Returns array [created=>bool, updated=>bool, row=>data]. */
    public function upsert(int $schoolId, ?int $academicYearId, int $studentId, string $date, string $status, ?string $remarks, string $username, ?int $classId = null, ?int $sectionId = null): array {
        $ay = $academicYearId ?? 1;
        // Check existing
        $updatedAt=date('Y-m-d H:i:s');
        $createdAt=date('Y-m-d H:i:s');
        $checkSql = "SELECT StudentAttendanceID, Status FROM Tx_Students_Attendance WHERE SchoolID=:school AND StudentID=:sid AND Date=:dt AND AcademicYearID=:ay LIMIT 1";
        $chk = $this->conn->prepare($checkSql);
        $chk->execute([':school'=>$schoolId, ':sid'=>$studentId, ':dt'=>$date, ':ay'=>$ay]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ($existing['Status'] === $status && $remarks === null) {
                return ['created'=>false,'updated'=>false,'row'=>['StudentAttendanceID'=>$existing['StudentAttendanceID'],'Status'=>$existing['Status']]];
            }
            $upd = $this->conn->prepare("UPDATE Tx_Students_Attendance SET Status=:st, Remarks=:rm, UpdatedBy=:ub, UpdatedAt=:ua WHERE StudentAttendanceID=:id");
            $upd->execute([':st'=>$status, ':rm'=>$remarks, ':ub'=>$username, ':ua'=>$updatedAt, ':id'=>$existing['StudentAttendanceID']]);
            return ['created'=>false,'updated'=>true,'row'=>['StudentAttendanceID'=>$existing['StudentAttendanceID'],'Status'=>$status]];
        }
             // Include ClassID/SectionID in the insert. Prefer client-provided values when available,
             // otherwise fall back to student's stored values.
             $insSql = "INSERT INTO Tx_Students_Attendance (Date, Status, StudentID, SectionID, ClassID, SchoolID, AcademicYearID, Remarks, CreatedAt, CreatedBy)
                        SELECT :dt, :st, s.StudentID,
                             COALESCE(:sectionId, s.SectionID) AS SectionID,
                             COALESCE(:classId, s.ClassID) AS ClassID,
                             s.SchoolID, :ay, :rm, :ca, :cb
                         FROM Tx_Students s
                        WHERE s.StudentID = :sid AND s.SchoolID = :school
                        LIMIT 1";
             $ins = $this->conn->prepare($insSql);
             $ins->bindValue(':dt',$date);
             $ins->bindValue(':st',$status);
             $ins->bindValue(':sid',$studentId,PDO::PARAM_INT);
             $ins->bindValue(':school',$schoolId,PDO::PARAM_INT);
             $ins->bindValue(':ay',$ay,PDO::PARAM_INT);
             $ins->bindValue(':rm',$remarks);
             $ins->bindValue(':ca',$createdAt);
             $ins->bindValue(':cb',$username);
             // sectionId/classId may be null; bind as integers when non-null
             if ($sectionId !== null) $ins->bindValue(':sectionId',$sectionId,PDO::PARAM_INT); else $ins->bindValue(':sectionId',null,PDO::PARAM_NULL);
             if ($classId !== null) $ins->bindValue(':classId',$classId,PDO::PARAM_INT); else $ins->bindValue(':classId',null,PDO::PARAM_NULL);
             $ins->execute();
        $id = (int)$this->conn->lastInsertId();
        return ['created'=>true,'updated'=>false,'row'=>['StudentAttendanceID'=>$id,'Status'=>$status]];
    }

    /** Batch upsert; entries = [ ['StudentID'=>, 'Status'=>, 'Remarks'=>?], ... ] */
    public function batchUpsert(int $schoolId, ?int $academicYearId, string $date, array $entries, string $username, ?int $classId = null, ?int $sectionId = null): array {
        $results = []; $created=0; $updated=0; $unchanged=0; $conflicts=[];
        foreach ($entries as $e) {
            $sid = (int)($e['StudentID'] ?? 0); if ($sid<=0) continue;
            $st = strtoupper($e['Status'] ?? 'P');
            // Normalize short codes
            $map = ['P'=>'Present','L'=>'Leave','H'=>'HalfDay','A'=>'Absent'];
            $status = $map[$st] ?? $e['Status'];
            $res = $this->upsert($schoolId,$academicYearId,$sid,$date,$status,$e['Remarks'] ?? null,$username, $classId, $sectionId);
            if ($res['created']) $created++; elseif ($res['updated']) $updated++; else $unchanged++;
            $results[] = ['StudentID'=>$sid,'created'=>$res['created'],'updated'=>$res['updated'],'status'=>$res['row']['Status']];
        }
        return ['summary'=>['created'=>$created,'updated'=>$updated,'unchanged'=>$unchanged],'results'=>$results];
    }

    /** Return meta information for attendance for a given date/section (last recorder). */
    public function getAttendanceMeta(int $schoolId, string $date, ?int $sectionId = null, ?int $academicYearId = null): ?array {
        // Fetch latest attendance row and attempt to resolve the CreatedBy username
        // into a full name by joining Tx_Users on Username = CreatedBy.
        $sql = "SELECT a.CreatedBy, a.CreatedAt,
                       CONCAT_WS(' ', u.FirstName, u.MiddleName, u.LastName) AS CreatedByName
                FROM Tx_Students_Attendance a
                LEFT JOIN Tx_Users u ON u.Username = a.CreatedBy AND u.SchoolID = a.SchoolID
                WHERE a.SchoolID = :school AND a.Date = :dt";
        if ($academicYearId) $sql .= " AND a.AcademicYearID = :ay";
        if ($sectionId) $sql .= " AND a.SectionID = :sid";
        $sql .= " ORDER BY a.CreatedAt DESC LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':dt', $date);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        if ($sectionId) $stmt->bindValue(':sid', $sectionId, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r) return null;

        // If CreatedByName is empty/null, fall back to the raw CreatedBy value (username or string)
        if (empty($r['CreatedByName']) && !empty($r['CreatedBy'])) {
            $r['CreatedByName'] = $r['CreatedBy'];
        }

        return $r;
    }
}
