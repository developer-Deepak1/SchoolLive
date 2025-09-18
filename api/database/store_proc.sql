
-- Create a procedure to manually run employee absent marking for a specific date
-- Usage: CALL sp_mark_employees_absent_for_date('2025-09-17');
DELIMITER $$

CREATE PROCEDURE sp_mark_employees_absent_for_date(IN target_date DATE)
BEGIN
    DECLARE day_of_week INT;
    DECLARE rows_affected INT DEFAULT 0;
    
    -- Get day of week (1=Monday, 7=Sunday)
    SET day_of_week = WEEKDAY(target_date) + 1;
    
    -- Insert absent records for employees who don't have attendance
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
        CONCAT('Auto-marked absent for ', target_date),
        'System',
        NOW()
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
    
    -- Get number of rows affected
    SET rows_affected = ROW_COUNT();
    
    -- Output result
    SELECT 
        target_date as processed_date,
        day_of_week as day_of_week_number,
        rows_affected as employees_marked_absent,
        CASE day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday' 
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END as day_name;

END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE sp_mark_employees_absent_for_date_range(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    DECLARE v_date DATE;
    SET v_date = p_start_date;

    WHILE v_date <= p_end_date DO
        CALL sp_mark_employees_absent_for_date(v_date);
        SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
    END WHILE;
END$$

DELIMITER ;


-- Create a procedure to manually run the absent marking for a specific date
-- Usage: CALL sp_mark_absent_for_date('2025-09-17');
DELIMITER $$

CREATE PROCEDURE sp_mark_absent_for_date_student(IN target_date DATE)
BEGIN
    DECLARE day_of_week INT;
    DECLARE rows_affected INT DEFAULT 0;
    
    -- Get day of week (1=Monday, 7=Sunday)
    SET day_of_week = WEEKDAY(target_date) + 1;
    
    -- Insert absent records for students who don't have attendance
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
        CONCAT('Auto-marked absent for ', target_date),
        'System',
        NOW()
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
    
    -- Get number of rows affected
    SET rows_affected = ROW_COUNT();
    
    -- Output result
    SELECT 
        target_date as processed_date,
        day_of_week as day_of_week_number,
        rows_affected as students_marked_absent,
        CASE day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday' 
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END as day_name;

END$$

DELIMITER ;