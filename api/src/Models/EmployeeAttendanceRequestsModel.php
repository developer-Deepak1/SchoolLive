<?php
namespace SchoolLive\Models;

use PDO;

class EmployeeAttendanceRequestsModel extends Model {
    protected $table = 'Tx_Employee_AttendanceRequests';

    public function createRequest($schoolId, $employeeId, $date, $requestType, $reason, $createdBy, $academicYearId = 0) {
        try {
            // check for existing request for the employee+date
            $chk = $this->conn->prepare("SELECT AttendanceRequestID, IsActive FROM Tx_Employee_AttendanceRequests WHERE EmployeeID = :eid AND Date = :dt LIMIT 1");
            $chk->execute([':eid' => $employeeId, ':dt' => $date]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                // if an active request already exists, signal duplicate
                if ((int)$existing['IsActive'] === 1) {
                    return 'exists_active';
                }
                // if an inactive (soft-deleted) request exists, reactivate and update fields
                $up = $this->conn->prepare("UPDATE Tx_Employee_AttendanceRequests SET IsActive = 1, RequestType = :rt, Reason = :rs, Status = 'Pending', UpdatedBy = :ub, UpdatedAt = NOW() WHERE AttendanceRequestID = :id");
                $up->execute([':rt' => $requestType, ':rs' => $reason ?? null, ':ub' => $createdBy ?? 'system', ':id' => $existing['AttendanceRequestID']]);
                return (int)$existing['AttendanceRequestID'];
            }

            $sql = "INSERT INTO Tx_Employee_AttendanceRequests (EmployeeID, Date, RequestType, Reason, Status, SchoolID, AcademicYearID, CreatedBy) VALUES (:eid, :dt, :rt, :rs, 'Pending', :school, :ay, :cb)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':eid', $employeeId, PDO::PARAM_INT);
            $stmt->bindValue(':dt', $date);
            $stmt->bindValue(':rt', $requestType);
            $stmt->bindValue(':rs', $reason ?? null);
            $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
            $stmt->bindValue(':ay', $academicYearId ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':cb', $createdBy ?? 'system');
            if ($stmt->execute()) {
                return (int)$this->conn->lastInsertId();
            }
            return false;
        } catch (\PDOException $e) {
            // Handle unique constraint race: if a concurrent insert caused duplicate, try to find existing row
            if ($e->getCode() === '23000') {
                $chk = $this->conn->prepare("SELECT AttendanceRequestID, IsActive FROM Tx_Employee_AttendanceRequests WHERE EmployeeID = :eid AND Date = :dt LIMIT 1");
                $chk->execute([':eid' => $employeeId, ':dt' => $date]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    if ((int)$existing['IsActive'] === 1) return 'exists_active';
                    // reactivate
                    $up = $this->conn->prepare("UPDATE Tx_Employee_AttendanceRequests SET IsActive = 1, RequestType = :rt, Reason = :rs, Status = 'Pending', UpdatedBy = :ub, UpdatedAt = NOW() WHERE AttendanceRequestID = :id");
                    $up->execute([':rt' => $requestType, ':rs' => $reason ?? null, ':ub' => $createdBy ?? 'system', ':id' => $existing['AttendanceRequestID']]);
                    return (int)$existing['AttendanceRequestID'];
                }
            }
            return false;
        }
    }

    public function listRequests($schoolId, $employeeId = null, $status = null, $academicYearId = null) {
        // Include employee display name by joining Tx_Employees; keep legacy shape plus EmployeeName
        $sql = "SELECT r.*, CONCAT_WS(' ', e.FirstName, e.MiddleName, e.LastName) AS EmployeeName FROM Tx_Employee_AttendanceRequests r LEFT JOIN Tx_Employees e ON e.EmployeeID = r.EmployeeID WHERE r.SchoolID = :school AND r.IsActive = 1";
        if ($employeeId) $sql .= " AND r.EmployeeID = :eid";
        if ($status) $sql .= " AND r.Status = :status";
        if ($academicYearId) $sql .= " AND r.AcademicYearID = :ay";
        $sql .= " ORDER BY r.AttendanceRequestID DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school', $schoolId, PDO::PARAM_INT);
        if ($employeeId) $stmt->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        if ($status) $stmt->bindValue(':status', $status);
        if ($academicYearId) $stmt->bindValue(':ay', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelRequest($id, $updatedBy = null) {
        $sql = "UPDATE Tx_Employee_AttendanceRequests SET IsActive = 0, Status = 'Rejected', UpdatedBy = :ub, UpdatedAt = NOW() WHERE AttendanceRequestID = :id AND IsActive = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':ub', $updatedBy ?? 'system');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function approveRequest($id, $approvedBy = null) {
        try {
            $this->conn->beginTransaction();
            // fetch request
            $stmt = $this->conn->prepare("SELECT * FROM Tx_Employee_AttendanceRequests WHERE AttendanceRequestID = :id AND IsActive = 1 LIMIT 1");
            $stmt->execute([':id' => $id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) { $this->conn->rollBack(); return false; }

            $employeeId = $req['EmployeeID'];
            $date = $req['Date'];
            $schoolId = $req['SchoolID'];
            $academicYearId = $req['AcademicYearID'];
            $requestType = $req['RequestType'];

            // check if attendance exists
            $chk = $this->conn->prepare("SELECT EmployeeAttendanceID FROM Tx_Employee_Attendance WHERE EmployeeID = :eid AND Date = :dt LIMIT 1");
            $chk->execute([':eid' => $employeeId, ':dt' => $date]);
            $att = $chk->fetch(PDO::FETCH_ASSOC);
            if ($att) {
                // reactivate if previously soft-deleted
                $upd = $this->conn->prepare("UPDATE Tx_Employee_Attendance SET IsActive = 1, UpdatedBy = :ub, UpdatedAt = NOW() WHERE EmployeeAttendanceID = :id");
                $upd->execute([':ub' => $approvedBy ?? 'system', ':id' => $att['EmployeeAttendanceID']]);
            } else {
                // insert attendance row; map requestType to Status
                $status = ($requestType === 'Attendance') ? 'Present' : 'Leave';
                $ins = $this->conn->prepare("INSERT INTO Tx_Employee_Attendance (Date, EmployeeID, SchoolID, AcademicYearID, Status, CreatedBy) VALUES (:dt, :eid, :school, :ay, :st, :cb)");
                $ins->execute([':dt' => $date, ':eid' => $employeeId, ':school' => $schoolId, ':ay' => $academicYearId ?? 0, ':st' => $status, ':cb' => $approvedBy ?? 'system']);
            }

            // mark request approved
            $up = $this->conn->prepare("UPDATE Tx_Employee_AttendanceRequests SET Status = 'Approved', UpdatedBy = :ub, UpdatedAt = NOW() WHERE AttendanceRequestID = :id");
            $up->execute([':ub' => $approvedBy ?? 'system', ':id' => $id]);

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return false;
        }
    }

    public function rejectRequest($id, $rejectedBy = null) {
        try {
            $this->conn->beginTransaction();
            // mark request rejected and inactive
            $stmt = $this->conn->prepare("UPDATE Tx_Employee_AttendanceRequests SET Status = 'Rejected', UpdatedBy = :ub, UpdatedAt = NOW() WHERE AttendanceRequestID = :id AND IsActive = 1");
            $stmt->execute([':ub' => $rejectedBy ?? 'system', ':id' => $id]);
            // if attendance row exists, soft-delete it (IsActive = 0)
            $chk = $this->conn->prepare("SELECT EmployeeID, Date FROM Tx_Employee_AttendanceRequests WHERE AttendanceRequestID = :id LIMIT 1");
            $chk->execute([':id' => $id]);
            $req = $chk->fetch(PDO::FETCH_ASSOC);
            if ($req) {
                $dstmt = $this->conn->prepare("UPDATE Tx_Employee_Attendance SET IsActive = 0, UpdatedBy = :ub, UpdatedAt = NOW() WHERE EmployeeID = :eid AND Date = :dt");
                $dstmt->execute([':ub' => $rejectedBy ?? 'system', ':eid' => $req['EmployeeID'], ':dt' => $req['Date']]);
            }
            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return false;
        }
    }
}
