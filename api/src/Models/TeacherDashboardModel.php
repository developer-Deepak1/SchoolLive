<?php
namespace SchoolLive\Models;

use PDO; use DateTime;

class TeacherDashboardModel extends Model {
    /**
     * Resolve and validate an EmployeeID for the given school.
     * If $employeeId is provided, validate it belongs to the school and is active and return it.
     * Otherwise, resolve via UserID -> Employee.UserID mapping.
     */
    public function resolveEmployeeIdForUser(int $schoolId, int $employeeId): ?int {
        $q = $this->conn->prepare("SELECT EmployeeID FROM Tx_Employees WHERE EmployeeID = :eid AND SchoolID = :school AND IsActive = 1 LIMIT 1");
        $q->bindValue(':eid', $employeeId, PDO::PARAM_INT);
        $q->bindValue(':school', $schoolId, PDO::PARAM_INT);
        $q->execute();
        $r = $q->fetchColumn();
        return $r ? (int)$r : null;
    }

    public function getMonthlyAttendance(int $schoolId, int $employeeId, ?int $academicYearId): array {
        // Delegate calendar and attendance computations to AcademicCalendarModel for reuse
        $cal = new AcademicCalendarModel();
        return $cal->getMonthlyAttendanceForEmployee($schoolId, $employeeId, $academicYearId);
    }
}

?>
