This migration adds the Tx_Academic_Calendar table and new API endpoints to manage academic calendar entries.

SQL (already added to init_schema.sql):

CREATE TABLE IF NOT EXISTS Tx_Academic_Calendar (
    CalendarID BIGINT AUTO_INCREMENT PRIMARY KEY,
    AcademicYearID INT NOT NULL,
    `Date` DATE NOT NULL,
    DayType ENUM('working_day','holiday','exam_day','special_event') NOT NULL DEFAULT 'working_day',
    Title VARCHAR(200) NOT NULL,
    Description TEXT NULL,
    IsHalfDay BOOLEAN DEFAULT FALSE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedBy VARCHAR(100),
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    UNIQUE KEY unique_calendar_date (AcademicYearID, `Date`)
);

API endpoints (AcademicController):
- GET /api/academic/getAcademicCalendar?academic_year_id=NN
- POST /api/academic/CreateAcademicCalendarEntry
- PUT /api/academic/UpdateAcademicCalendarEntry/{id}
- DELETE /api/academic/DeleteAcademicCalendarEntry/{id}

Validation note:
- When creating an academic year via /api/academic/CreateAcademicYears, the StartDate must be exactly one day after the current academic year's EndDate (if a current year exists). The controller will return HTTP 400 with an explanatory message if this rule is violated.
