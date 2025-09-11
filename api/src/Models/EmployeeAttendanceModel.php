<?php
namespace SchoolLive\Models;

use PDO;

class EmployeeAttendanceModel extends Model {
    protected $table = 'Tx_Employee_Attendance';

    public function getEmployeeAttendanceForDate($schoolId, $employeeId, $date, $academicYearId = null) {
        $sql = "SELECT * FROM Tx_Employee_Attendance WHERE SchoolID = :school AND EmployeeID = :eid AND Date = :dt";
        if ($academicYearId) $sql .= " AND AcademicYearID = :ay";
        $sql .= " LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':dt', $date);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upsertSignIn($schoolId, $employeeId, $date, $signinAt, $username, $academicYearId = null) {
        // Try update if exists
        $existsSql = "SELECT EmployeeAttendanceID, SignIn FROM Tx_Employee_Attendance WHERE SchoolID=:school AND EmployeeID=:eid AND Date=:dt" . ($academicYearId?" AND AcademicYearID=:ay":"") . " LIMIT 1";
        $chk = $this->conn->prepare($existsSql);
        $chk->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $chk->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $chk->bindValue(':dt', $date);
        if ($academicYearId) $chk->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $chk->execute();
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // if SignIn already exists, leave it
            if (!empty($row['SignIn'])) return ['created'=>false,'updated'=>false,'row'=>$row];
            $upd = $this->conn->prepare("UPDATE Tx_Employee_Attendance SET SignIn = :si, Status = 'Present', Remarks = :rm, UpdatedBy = :ub, UpdatedAt = NOW() WHERE EmployeeAttendanceID = :id");
            $upd->execute([':si'=>$signinAt, ':rm'=>'signin', ':ub'=>$username, ':id'=>$row['EmployeeAttendanceID']]);
            return ['created'=>false,'updated'=>true,'row'=>['EmployeeAttendanceID'=>$row['EmployeeAttendanceID'],'SignIn'=>$signinAt]];
        }
        // Insert
    $ins = $this->conn->prepare("INSERT INTO Tx_Employee_Attendance (Date, EmployeeID, SchoolID, AcademicYearID, SignIn, Status, Remarks, CreatedBy) VALUES (:dt, :eid, :school, :ay, :si, 'Present', :rm, :cb)");
        $ins->bindValue(':dt', $date);
        $ins->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $ins->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $ins->bindValue(':ay', $academicYearId ?? 0, PDO::PARAM_INT);
    $ins->bindValue(':si', $signinAt);
    $ins->bindValue(':rm', 'signin');
    $ins->bindValue(':cb', $username);
        $ins->execute();
        $id = (int)$this->conn->lastInsertId();
        return ['created'=>true,'updated'=>false,'row'=>['EmployeeAttendanceID'=>$id,'SignIn'=>$signinAt]];
    }

    public function upsertSignOut($schoolId, $employeeId, $date, $signoutAt, $username, $academicYearId = null) {
        // Ensure a row exists
        $existsSql = "SELECT EmployeeAttendanceID, SignIn, SignOut FROM Tx_Employee_Attendance WHERE SchoolID=:school AND EmployeeID=:eid AND Date=:dt" . ($academicYearId?" AND AcademicYearID=:ay":"") . " LIMIT 1";
        $chk = $this->conn->prepare($existsSql);
        $chk->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $chk->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $chk->bindValue(':dt', $date);
        if ($academicYearId) $chk->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $chk->execute();
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // if SignOut already exists, leave it
            if (!empty($row['SignOut'])) return ['created'=>false,'updated'=>false,'row'=>$row];
            // compute TotalHours if SignIn exists
            $totalHours = null;
            if (!empty($row['SignIn'])) {
                // compute difference in hours as decimal
                $diffSql = "SELECT TIMESTAMPDIFF(SECOND, :si, :so) as secs";
                $dstmt = $this->conn->prepare($diffSql);
                $dstmt->execute([':si'=>$row['SignIn'], ':so'=>$signoutAt]);
                $diffRow = $dstmt->fetch(PDO::FETCH_ASSOC);
                if ($diffRow && isset($diffRow['secs'])) {
                    $totalHours = round(((int)$diffRow['secs'])/3600, 2);
                }
            }
            $upd = $this->conn->prepare("UPDATE Tx_Employee_Attendance SET SignOut = :so, TotalHours = :th, Status = 'Present', Remarks = :rm, UpdatedBy = :ub, UpdatedAt = NOW() WHERE EmployeeAttendanceID = :id");
            $upd->execute([':so'=>$signoutAt, ':th'=>$totalHours, ':rm'=>'signin and signout complete', ':ub'=>$username, ':id'=>$row['EmployeeAttendanceID']]);
            $retRow = ['EmployeeAttendanceID'=>$row['EmployeeAttendanceID'],'SignOut'=>$signoutAt,'SignIn'=>$row['SignIn']];
            if ($totalHours !== null) $retRow['TotalHours'] = $totalHours;
            return ['created'=>false,'updated'=>true,'row'=>$retRow];
        }
        // If no existing row, do not insert on sign-out; return not-created
        return ['created'=>false,'updated'=>false,'row'=>null,'message'=>'No attendance row found to sign out. Sign-in required first.'];
    }
}
