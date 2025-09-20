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
            $upd->execute([':si'=>$signinAt, ':rm'=>'SignIn Complete', ':ub'=>$username, ':id'=>$row['EmployeeAttendanceID']]);
            return ['created'=>false,'updated'=>true,'row'=>['EmployeeAttendanceID'=>$row['EmployeeAttendanceID'],'SignIn'=>$signinAt]];
        }
        // Insert
    $ins = $this->conn->prepare("INSERT INTO Tx_Employee_Attendance (Date, EmployeeID, SchoolID, AcademicYearID, SignIn, Status, Remarks, CreatedBy) VALUES (:dt, :eid, :school, :ay, :si, 'Present', :rm, :cb)");
        $ins->bindValue(':dt', $date);
        $ins->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $ins->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $ins->bindValue(':ay', $academicYearId ?? 0, PDO::PARAM_INT);
    $ins->bindValue(':si', $signinAt);
    $ins->bindValue(':rm', 'SignIn Complete');
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
            $upd->execute([':so'=>$signoutAt, ':th'=>$totalHours, ':rm'=>'SignIn and SignOut Complete', ':ub'=>$username, ':id'=>$row['EmployeeAttendanceID']]);
            $retRow = ['EmployeeAttendanceID'=>$row['EmployeeAttendanceID'],'SignOut'=>$signoutAt,'SignIn'=>$row['SignIn']];
            if ($totalHours !== null) $retRow['TotalHours'] = $totalHours;
            return ['created'=>false,'updated'=>true,'row'=>$retRow];
        }
        // If no existing row, do not insert on sign-out; return not-created
        return ['created'=>false,'updated'=>false,'row'=>null,'message'=>'No attendance row found to sign out. Sign-in required first.'];
    }

    /**
     * Return a normalized status for the employee for the given date.
     * Returns array: ['status' => 'Present'|'Leave'|'NotMarked'|'Unknown', 'attendance' => row|null]
     */
    public function getEmployeeStatusToday($schoolId, $employeeId, $date, $academicYearId = null) {
        $row = $this->getEmployeeAttendanceForDate($schoolId, $employeeId, $date, $academicYearId);
        if (!$row) {
            return ['status' => 'NotMarked', 'attendance' => null];
        }
        $status = $row['Status'] ?? null;
        if ($status) return ['status' => $status, 'attendance' => $row];
        // infer from fields
        if (!empty($row['SignIn']) || !empty($row['SignOut'])) {
            return ['status' => 'Present', 'attendance' => $row];
        }
        if (!empty($row['Remarks']) && stripos($row['Remarks'], 'leave') !== false) {
            return ['status' => 'Leave', 'attendance' => $row];
        }
        return ['status' => 'Unknown', 'attendance' => $row];
    }

    /**
     * Try to obtain a leave reason for the employee on the given date.
     * First checks attendance requests (prefer Approved), then falls back to attendance.Remarks.
     */
    public function getLeaveReason($schoolId, $employeeId, $date, $academicYearId = null) {
        // check requests table for a leave request
        $stmt = $this->conn->prepare("SELECT Reason, Status FROM Tx_Employee_AttendanceRequests WHERE EmployeeID = :eid AND Date = :dt AND IsActive = 1 AND (RequestType = 'Leave' OR RequestType = 'leave') ORDER BY AttendanceRequestID DESC LIMIT 1");
        $stmt->execute([':eid' => $employeeId, ':dt' => $date]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($req && !empty($req['Reason'])) return $req['Reason'];

        // fallback to attendance row remarks
        $row = $this->getEmployeeAttendanceForDate($schoolId, $employeeId, $date, $academicYearId);
        if ($row) {
            if (!empty($row['Remarks'])) return $row['Remarks'];
            if (!empty($row['Note'])) return $row['Note'];
        }
        return null;
    }

    /**
     * Get monthly attendance for all employees with optional role filtering
     * Similar to student attendance but for employees
     */
    public function getMonthlyAttendance($schoolId, $academicYearId, $year, $month, $roleId = null) {
        // compute days in month
        $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        
        // Get all employees with optional filtering
        $employeeSql = "SELECT e.EmployeeID, e.FirstName, e.MiddleName, e.LastName, e.RoleID, 
                               r.RoleName
                        FROM Tx_Employees e
                        LEFT JOIN Tm_Roles r ON e.RoleID = r.RoleID
                        WHERE e.SchoolID = :school";
        
        $params = [':school' => $schoolId];
        
        if ($academicYearId) {
            $employeeSql .= " AND e.AcademicYearID = :ay";
            $params[':ay'] = $academicYearId;
        }
        
        if ($roleId) {
            $employeeSql .= " AND e.RoleID = :role";
            $params[':role'] = $roleId;
        }
        
        $employeeSql .= " AND IFNULL(e.IsActive, 1) = 1 ORDER BY e.FirstName, e.LastName";
        
        $stmt = $this->conn->prepare($employeeSql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize result array
        $result = [];
        
        // Get attendance data for all employees for the entire month
        foreach ($employees as $emp) {
            $employeeId = $emp['EmployeeID'];
            $employeeName = trim(($emp['FirstName'] ?? '') . ' ' . ($emp['MiddleName'] ?? '') . ' ' . ($emp['LastName'] ?? ''));
            
            $result[$employeeId] = [
                'EmployeeID' => $employeeId,
                'EmployeeName' => $employeeName,
                'RoleName' => $emp['RoleName'] ?? '',
                'statuses' => array_fill(0, $daysInMonth, null)
            ];
        }
        
        // Get attendance records for the month
        $attendanceSql = "SELECT ea.EmployeeID, ea.Date, ea.Status, ea.SignIn, ea.SignOut
                          FROM Tx_Employee_Attendance ea
                          WHERE ea.SchoolID = :school 
                          AND YEAR(ea.Date) = :year 
                          AND MONTH(ea.Date) = :month";
        
        $attendanceParams = [':school' => $schoolId, ':year' => $year, ':month' => $month];
        
        if ($academicYearId) {
            $attendanceSql .= " AND ea.AcademicYearID = :ay";
            $attendanceParams[':ay'] = $academicYearId;
        }
        
        $stmt = $this->conn->prepare($attendanceSql);
        $stmt->execute($attendanceParams);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Apply attendance data to result
        foreach ($attendanceRecords as $record) {
            $employeeId = $record['EmployeeID'];
            $date = $record['Date'];
            $day = (int)date('j', strtotime($date)) - 1; // 0-based index
            
            if (isset($result[$employeeId]) && $day >= 0 && $day < $daysInMonth) {
                $status = $record['Status'] ?? null;
                
                // Normalize status similar to student attendance
                if ($status) {
                    $statusLower = strtolower(trim($status));
                    if ($statusLower === 'p' || $statusLower === 'present') {
                        $result[$employeeId]['statuses'][$day] = 'Present';
                    } elseif ($statusLower === 'l' || $statusLower === 'leave') {
                        $result[$employeeId]['statuses'][$day] = 'Leave';
                    } elseif ($statusLower === 'h' || $statusLower === 'halfday' || $statusLower === 'half-day') {
                        $result[$employeeId]['statuses'][$day] = 'HalfDay';
                    } elseif ($statusLower === 'a' || $statusLower === 'absent') {
                        $result[$employeeId]['statuses'][$day] = 'Absent';
                    } else {
                        $result[$employeeId]['statuses'][$day] = ucfirst($statusLower);
                    }
                } elseif (!empty($record['SignIn']) || !empty($record['SignOut'])) {
                    // If no status but has sign in/out, mark as Present
                    $result[$employeeId]['statuses'][$day] = 'Present';
                }
            }
        }
        
        // Apply holiday and weekly-off logic
        $this->applyHolidaysAndWeeklyOffs($result, $schoolId, $year, $month, $daysInMonth, $academicYearId);
        
        return array_values($result);
    }

    /**
     * Apply holiday and weekly-off logic to mark appropriate days
     */
    private function applyHolidaysAndWeeklyOffs(&$result, $schoolId, $year, $month, $daysInMonth, $academicYearId = null) {
        // Get holidays for the month
        $holidaySql = "SELECT Date FROM Tx_Holidays 
                       WHERE SchoolID = :school 
                       AND YEAR(Date) = :year 
                       AND MONTH(Date) = :month 
                       AND Type = 'Holiday'
                       AND IsActive = 1";
        
        $holidayParams = [':school' => $schoolId, ':year' => $year, ':month' => $month];
        
        if ($academicYearId) {
            $holidaySql .= " AND AcademicYearID = :ay";
            $holidayParams[':ay'] = $academicYearId;
        }
        
        $stmt = $this->conn->prepare($holidaySql);
        $stmt->execute($holidayParams);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get weekly offs
        $weeklyOffSql = "SELECT DayOfWeek FROM Tx_WeeklyOffs 
                         WHERE SchoolID = :school 
                         AND IsActive = 1";
        
        $weeklyOffParams = [':school' => $schoolId];
        
        if ($academicYearId) {
            $weeklyOffSql .= " AND AcademicYearID = :ay";
            $weeklyOffParams[':ay'] = $academicYearId;
        }
        
        $stmt = $this->conn->prepare($weeklyOffSql);
        $stmt->execute($weeklyOffParams);
        $weeklyOffs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Apply holidays and weekly offs to all employees
        foreach ($result as &$employee) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dayIndex = $day - 1; // 0-based index
                
                // Check if it's a holiday
                if (in_array($currentDate, $holidays)) {
                    // Only mark as holiday if no attendance record exists
                    if ($employee['statuses'][$dayIndex] === null) {
                        $employee['statuses'][$dayIndex] = 'Holiday';
                    }
                    continue;
                }
                
                // Check if it's a weekly off (1=Monday, 7=Sunday)
                $dayOfWeek = date('N', strtotime($currentDate)); // 1=Monday, 7=Sunday
                if (in_array($dayOfWeek, $weeklyOffs)) {
                    // Only mark as weekly off if no attendance record exists
                    if ($employee['statuses'][$dayIndex] === null) {
                        $employee['statuses'][$dayIndex] = 'Weekly-off';
                    }
                }
            }
        }
    }

    /**
     * Get detailed attendance records for all employees by month (Admin view)
     * Returns Date, EmployeeName, SignIn, SignOut, Status, Remarks
     */
    public function getAttendanceDetailsByMonth($schoolId, $academicYearId, $year, $month) {
        $sql = "SELECT 
                    ea.Date,
                    CONCAT(e.FirstName, 
                           CASE WHEN e.MiddleName IS NOT NULL AND e.MiddleName != '' 
                                THEN CONCAT(' ', e.MiddleName) 
                                ELSE '' END,
                           CASE WHEN e.LastName IS NOT NULL AND e.LastName != '' 
                                THEN CONCAT(' ', e.LastName) 
                                ELSE '' END) AS EmployeeName,
                    TIME(ea.SignIn) AS SignInTime,
                    TIME(ea.SignOut) AS SignOutTime,
                    ea.Status,
                    ea.Remarks,
                    ea.EmployeeID
                FROM Tx_Employee_Attendance ea
                INNER JOIN Tx_Employees e ON ea.EmployeeID = e.EmployeeID
                WHERE ea.SchoolID = :school
                AND YEAR(ea.Date) = :year 
                AND MONTH(ea.Date) = :month";
        
        $params = [
            ':school' => $schoolId,
            ':year' => $year,
            ':month' => $month
        ];
        
        if ($academicYearId) {
            $sql .= " AND ea.AcademicYearID = :ay";
            $params[':ay'] = $academicYearId;
        }
        
        $sql .= " ORDER BY ea.Date DESC, EmployeeName ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed attendance records for current user by month (Teacher/User view)
     * Returns Date, SignIn, SignOut, Status, Remarks
     */
    public function getUserAttendanceDetailsByMonth($schoolId, $academicYearId, $employeeId, $year, $month) {
        $sql = "SELECT 
                    ea.Date,
                    TIME(ea.SignIn) AS SignInTime,
                    TIME(ea.SignOut) AS SignOutTime,
                    ea.Status,
                    ea.Remarks
                FROM Tx_Employee_Attendance ea
                WHERE ea.SchoolID = :school
                AND ea.EmployeeID = :employee_id
                AND YEAR(ea.Date) = :year 
                AND MONTH(ea.Date) = :month";
        
        $params = [
            ':school' => $schoolId,
            ':employee_id' => $employeeId,
            ':year' => $year,
            ':month' => $month
        ];
        
        if ($academicYearId) {
            $sql .= " AND ea.AcademicYearID = :ay";
            $params[':ay'] = $academicYearId;
        }
        
        $sql .= " ORDER BY ea.Date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
