-- ================= DATABASE EVENT FOR AUTO EMPLOYEE ATTENDANCE MARKING =================
-- This event runs daily to mark employees as absent for the previous day
-- if they don't have attendance records and it's not a holiday or weekly off

DELIMITER $$

CREATE EVENT IF NOT EXISTS ev_mark_absent_employees
ON SCHEDULE EVERY 1 DAY
STARTS '2025-01-01 19:30:00'
ON COMPLETION PRESERVE
ENABLE
COMMENT 'Mark employees absent for previous day if no attendance record exists'
DO
BEGIN
    DECLARE target_date DATE;
    DECLARE day_of_week INT;
    
    -- Set target date to previous day
   SET target_date = DATE_SUB(CONVERT_TZ(CURDATE(), '+00:00', '+05:30'), INTERVAL 1 DAY);
    
    -- Get day of week (1=Monday, 7=Sunday)
    SET day_of_week = WEEKDAY(target_date) + 1;
    
    -- Insert absent records for employees who don't have attendance
    -- and the date is not a holiday or weekly off
    INSERT INTO Tx_Employee_Attendance (
        EmployeeID,
        SchoolID,
        AcademicYearID,
        Date, 
        Status,
        Remarks,
        CreatedBy,
        CreatedAt
    )
    SELECT 
        e.EmployeeID,
        e.SchoolID,
        e.AcademicYearID,
        target_date,
        'Leave', -- Default status for absent employees
        'Auto-marked absent by system',
        'System',
        CONVERT_TZ(NOW(), '+00:00', '+05:30')
    FROM Tx_Employees e
    INNER JOIN Tm_AcademicYears ay ON e.AcademicYearID = ay.AcademicYearID 
        AND ay.IsActive = TRUE
        AND target_date BETWEEN ay.StartDate AND ay.EndDate
    WHERE e.IsActive = TRUE
    -- Check if attendance record doesn't exist for this date
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_Employee_Attendance ea 
        WHERE ea.EmployeeID = e.EmployeeID 
        AND ea.Date = target_date
    )
    -- Check if it's not a holiday
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_Holidays h 
        WHERE h.SchoolID = e.SchoolID 
        AND h.AcademicYearID = e.AcademicYearID
        AND h.Date = target_date 
        AND h.Type = 'Holiday'
        AND h.IsActive = TRUE
    )
    -- Check if it's not a weekly off
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_WeeklyOffs wo 
        WHERE wo.SchoolID = e.SchoolID 
        AND wo.AcademicYearID = e.AcademicYearID
        AND wo.DayOfWeek = day_of_week
        AND wo.IsActive = TRUE
    );

END$$

DELIMITER ;



-- ================= DATABASE EVENT FOR AUTO ATTENDANCE MARKING =================
-- This event runs daily to mark students as absent for the previous day
-- if they don't have attendance records and it's not a holiday or weekly off

DELIMITER $$

CREATE EVENT IF NOT EXISTS ev_mark_absent_students
ON SCHEDULE EVERY 1 DAY
STARTS '2025-01-01 19:30:00'
ON COMPLETION PRESERVE
ENABLE
COMMENT 'Mark students absent for previous day if no attendance record exists'
DO
BEGIN
    DECLARE target_date DATE;
    DECLARE day_of_week INT;
    
    -- Set target date to previous day
    SET target_date = DATE_SUB(CONVERT_TZ(CURDATE(), '+00:00', '+05:30'), INTERVAL 1 DAY);
    
    -- Get day of week (1=Monday, 7=Sunday)
    SET day_of_week = WEEKDAY(target_date) + 1;
    
    -- Insert absent records for students who don't have attendance
    -- and the date is not a holiday or weekly off
    INSERT INTO Tx_Students_Attendance (
        Date, 
        Status, 
        StudentID, 
        SectionID, 
        ClassID, 
        SchoolID, 
        AcademicYearID, 
        Remarks, 
        CreatedBy,
        CreatedAt
    )
    SELECT 
        target_date,
        'Absent',
        s.StudentID,
        s.SectionID,
        s.ClassID,
        s.SchoolID,
        s.AcademicYearID,
        'Auto-marked absent by system',
        'System',
        CONVERT_TZ(NOW(), '+00:00', '+05:30')
    FROM Tx_Students s
    INNER JOIN Tm_AcademicYears ay ON s.AcademicYearID = ay.AcademicYearID 
        AND ay.IsActive = TRUE
        AND target_date BETWEEN ay.StartDate AND ay.EndDate
    WHERE s.IsActive = TRUE
    -- Check if attendance record doesn't exist for this date
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_Students_Attendance sa 
        WHERE sa.StudentID = s.StudentID 
        AND sa.Date = target_date
    )
    -- Check if it's not a holiday
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_Holidays h 
        WHERE h.SchoolID = s.SchoolID 
        AND h.AcademicYearID = s.AcademicYearID
        AND h.Date = target_date 
        AND h.Type = 'Holiday'
        AND h.IsActive = TRUE
    )
    -- Check if it's not a weekly off
    AND NOT EXISTS (
        SELECT 1 
        FROM Tx_WeeklyOffs wo 
        WHERE wo.SchoolID = s.SchoolID 
        AND wo.AcademicYearID = s.AcademicYearID
        AND wo.DayOfWeek = day_of_week
        AND wo.IsActive = TRUE
    );

END$$

DELIMITER ;