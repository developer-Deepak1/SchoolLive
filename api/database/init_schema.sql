
USE INFORMATION_SCHEMA; -- ensure we can drop/create safely if client requires a schema context

-- Drop and recreate the database to ensure a clean schema
DROP DATABASE IF EXISTS schoollive_db;
CREATE DATABASE IF NOT EXISTS schoollive_db;
USE schoollive_db;

CREATE TABLE Tm_Schools (
    SchoolID INT PRIMARY KEY AUTO_INCREMENT,
    SchoolName VARCHAR(100) NOT NULL,
    SchoolCode VARCHAR(20) UNIQUE NOT NULL,
    Address TEXT,
    ContactNumber VARCHAR(15),
    Email VARCHAR(100),
    PrincipalName VARCHAR(100),
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100) DEFAULT 'Super Admin',
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    INDEX idx_school_code (SchoolCode),
    INDEX idx_status (Status)
);

-- Create Academic Year Table
CREATE TABLE Tm_AcademicYears (
    AcademicYearID INT PRIMARY KEY AUTO_INCREMENT,
    AcademicYearName VARCHAR(20) NOT NULL, -- e.g. '2025-2026'
    StartDate DATE NOT NULL,
    EndDate DATE NOT NULL,
    SchoolID INT NOT NULL,
    IsCurrent BOOLEAN DEFAULT FALSE,
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    UNIQUE KEY unique_academic_year (AcademicYearName, SchoolID, IsActive),
    INDEX idx_school_academic_year (SchoolID, AcademicYearName, IsActive),
    INDEX idx_status (Status)
);

CREATE TABLE Tx_WeeklyOffs (
    WeeklyOffID INT AUTO_INCREMENT PRIMARY KEY,
    AcademicYearID INT NOT NULL,
    DayOfWeek TINYINT NOT NULL, -- 1=Monday, 2=Tuesday, ... 7=Sunday
    SchoolID INT NOT NULL,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID),
    CONSTRAINT chk_weekday CHECK (DayOfWeek BETWEEN 1 AND 7),
    UNIQUE KEY ux_weeklyoff_ay_day (AcademicYearID, DayOfWeek),
    INDEX idx_weeklyoff_ay_day (AcademicYearID, DayOfWeek),
    -- Audit fields (created/updated) expected by application
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100)
);

CREATE TABLE Tx_Holidays (
    HolidayID INT AUTO_INCREMENT PRIMARY KEY,
    AcademicYearID INT NOT NULL,
    Date DATE NOT NULL,
    Title VARCHAR(100),
    SchoolID INT NOT NULL,
    Type ENUM('Holiday', 'WorkingDay') DEFAULT 'Holiday',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID),
    -- Audit fields (created/updated) expected by application
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    UNIQUE KEY ux_holidays_ay_date (AcademicYearID, Date,IsActive),
    INDEX idx_holidays_ay_date (AcademicYearID, Date,IsActive)
);



-- Set auto-increment start value
ALTER TABLE Tm_Schools AUTO_INCREMENT = 1000;

-- Create Roles Master Table
CREATE TABLE Tm_Roles (
    RoleID INT PRIMARY KEY AUTO_INCREMENT,
    RoleName VARCHAR(50) UNIQUE NOT NULL,
    RoleDisplayName VARCHAR(100) UNIQUE NOT NULL,
    RoleCode CHAR(3),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    
    INDEX idx_role_name (RoleName),
    INDEX idx_role_code (RoleCode)
);

-- Create Role-Role Mapping Table
CREATE TABLE Tx_RoleRoleMapping (
    MappingID BIGINT AUTO_INCREMENT PRIMARY KEY,
    RoleID INT NOT NULL,
    AllowedRoleID INT NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    
    FOREIGN KEY (RoleID) REFERENCES Tm_Roles(RoleID) ON DELETE CASCADE,
    FOREIGN KEY (AllowedRoleID) REFERENCES Tm_Roles(RoleID) ON DELETE CASCADE,
    UNIQUE KEY unique_role_mapping (RoleID, AllowedRoleID)
);

-- Create Users table
CREATE TABLE Tx_Users (
    UserID BIGINT PRIMARY KEY AUTO_INCREMENT,
    Username VARCHAR(50) UNIQUE NOT NULL, 
    PasswordHash VARCHAR(255) NOT NULL,
    FirstName VARCHAR(100) NOT NULL,
    MiddleName VARCHAR(100) NULL,
    LastName VARCHAR(100) NULL,
    ContactNumber VARCHAR(15) NULL,
    EmailID VARCHAR(100),
    RoleID INT,
    SchoolID INT,
    IsActive BOOLEAN DEFAULT TRUE,
    IsFirstLogin BOOLEAN DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (RoleID) REFERENCES Tm_Roles(RoleID) ON DELETE SET NULL,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE SET NULL,
    INDEX idx_username (Username),
    INDEX idx_email (EmailID),
    INDEX idx_role (RoleID),
    INDEX idx_school (SchoolID)
);

-- Create Classes table
CREATE TABLE Tx_Classes (
    ClassID BIGINT AUTO_INCREMENT PRIMARY KEY,
    ClassName VARCHAR(50) NOT NULL,
    ClassCode VARCHAR(20) NULL,
    Stream VARCHAR(50) NULL,
    MaxStrength INT DEFAULT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_school_class (SchoolID, ClassName),
    INDEX idx_class_academicyear (AcademicYearID),
    INDEX idx_status (Status)
);

-- Create Sections table
CREATE TABLE Tx_Sections (
    SectionID BIGINT AUTO_INCREMENT PRIMARY KEY,
    ClassID BIGINT NOT NULL,              -- Linked to class
    SectionName VARCHAR(50) NOT NULL,     -- e.g. "A", "B"
    MaxStrength INT DEFAULT NULL,
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE CASCADE,
    INDEX idx_class_section (ClassID, SectionName),
    INDEX idx_class_academicyear (AcademicYearID),
    INDEX idx_status (Status)
);


CREATE TABLE Tx_Employees (
    EmployeeID BIGINT AUTO_INCREMENT PRIMARY KEY,
    EmployeeName VARCHAR(300), -- legacy full name
    FirstName VARCHAR(100) NOT NULL DEFAULT '',
    MiddleName VARCHAR(100) NULL,
    LastName VARCHAR(100) NULL,
    ContactNumber VARCHAR(15) NULL, -- direct contact (was FatherContactNumber earlier for students, keeping both where relevant)
    EmailID VARCHAR(100) NULL,
    UserID BIGINT NOT NULL,
    SchoolID INT NOT NULL,
    RoleID INT,
    AcademicYearID INT NOT NULL,
    DOB DATE NOT NULL,
    Gender CHAR(1) CHECK (Gender IN ('M','F','O')),
    JoiningDate DATE DEFAULT (CURRENT_DATE),
    FatherName VARCHAR(150),
    FatherContactNumber VARCHAR(15),
    MotherName VARCHAR(150),
    MotherContactNumber VARCHAR(15),
    Salary DECIMAL(10,2) DEFAULT 0.00,
    Subjects VARCHAR(200),
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),

    FOREIGN KEY (UserID) REFERENCES Tx_Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (RoleID) REFERENCES Tm_Roles(RoleID) ON DELETE SET NULL,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_employee_school (SchoolID),
    INDEX idx_employee_role (RoleID),
    INDEX idx_employee_academicyear (AcademicYearID),
    INDEX idx_status (Status)
);

-- Create Class Teachers mapping table
CREATE TABLE Tx_ClassTeachers (
    ClassTeacherID BIGINT AUTO_INCREMENT PRIMARY KEY,
    ClassID BIGINT NOT NULL,
    EmployeeID BIGINT NOT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    StartDate DATE DEFAULT (CURRENT_DATE),
    EndDate DATE NULL,
    IsActive BOOLEAN DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),

    FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE CASCADE,
    FOREIGN KEY (EmployeeID) REFERENCES Tx_Employees(EmployeeID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    UNIQUE KEY unique_class_teacher (ClassID, EmployeeID, SchoolID, IsActive)
    ,INDEX idx_classteacher_academicyear (AcademicYearID)
);

-- Create Students table
CREATE TABLE Tx_Students (
    StudentID BIGINT PRIMARY KEY AUTO_INCREMENT,
    StudentName VARCHAR(300), -- legacy full name
    FirstName VARCHAR(100) NOT NULL DEFAULT '',
    MiddleName VARCHAR(100) NULL,
    LastName VARCHAR(100) NULL,
    ContactNumber VARCHAR(15) NULL,
    EmailID VARCHAR(100) NULL,
    Gender CHAR(1) CHECK (Gender IN ('M','F','O')),
    DOB DATE NOT NULL,
    SchoolID INT NOT NULL,  
    SectionID BIGINT,
    ClassID BIGINT,
    UserID BIGINT NOT NULL,
    AcademicYearID INT NOT NULL,
    FatherName VARCHAR(150),
    FatherContactNumber VARCHAR(15),
    MotherName VARCHAR(150),
    MotherContactNumber VARCHAR(15),
    AdmissionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (UserID) REFERENCES Tx_Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (SectionID) REFERENCES Tx_Sections(SectionID) ON DELETE SET NULL,
    FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE SET NULL,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_student_school (SchoolID),
    INDEX idx_student_section (SectionID),
    INDEX idx_student_class (ClassID),
    INDEX idx_student_academicyear (AcademicYearID),
    INDEX idx_status (Status)
);

-- Create Employee Attendance table
CREATE TABLE Tx_Employee_Attendance (
    EmployeeAttendanceID BIGINT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID BIGINT NOT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Date DATE NOT NULL,
    Status ENUM('Present','Absent','Leave','HalfDay') NOT NULL,
    Remarks TEXT,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),

    FOREIGN KEY (EmployeeID) REFERENCES Tx_Employees(EmployeeID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_date (EmployeeID, Date),
    INDEX idx_employee_date (EmployeeID, Date),
    INDEX idx_school_date (SchoolID, Date)
    ,INDEX idx_empatt_academicyear (AcademicYearID)
);

-- Create Student Attendance table
CREATE TABLE Tx_Students_Attendance (
    StudentAttendanceID BIGINT AUTO_INCREMENT PRIMARY KEY,
    Date DATE NOT NULL,
    Status ENUM('Present','Absent','Leave','HalfDay') NOT NULL,
    StudentID BIGINT NOT NULL,
    SectionID BIGINT NOT NULL,
    ClassID BIGINT NOT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Remarks TEXT,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (StudentID) REFERENCES Tx_Students(StudentID) ON DELETE CASCADE,
    FOREIGN KEY (SectionID) REFERENCES Tx_Sections(SectionID) ON DELETE CASCADE,
    FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    UNIQUE KEY unique_student_date (StudentID, Date),
    INDEX idx_student_date (StudentID, Date),
    INDEX idx_section_date (SectionID, Date),
    INDEX idx_school_date (SchoolID, Date)
    ,INDEX idx_stuatt_academicyear (AcademicYearID)
);

-- Insert initial data
INSERT INTO Tm_Roles (RoleName, RoleDisplayName, RoleCode) VALUES
('super_admin', 'Super Admin', 'SA'),
('client_admin', 'School Admin', 'CA'),
('teacher', 'Teacher', 'T'),
('student', 'Student', 'S');

INSERT INTO Tx_RoleRoleMapping (RoleID, AllowedRoleID) VALUES 
(1, 1), (1, 2), (2, 2), (2, 3), (2, 4), (3, 4);

INSERT INTO Tm_Schools (SchoolName, SchoolCode, Address, ContactNumber, Email, PrincipalName, Status) VALUES 
('Demo School', 'DS001', '123 Main Street, Demo City', '555-1234', 'info@demo-school.edu', 'Dr. John Smith', 'Active');

-- Insert an initial academic year for the demo school (AcademicYearID will be 1)
INSERT INTO Tm_AcademicYears (AcademicYearName, StartDate, EndDate, SchoolID, Status, CreatedBy) VALUES
('2025-2026', '2025-04-01', '2026-03-31', 1000, 'Active', 'System');

-- Create super admin user (password: password123)
INSERT INTO Tx_Users (Username, PasswordHash, FirstName, MiddleName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES 
('superSA002', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Super', '', 'Admin', '123456790', 'admin@schoollive.com', 1, NULL, 1, 1, 'System');

-- Create sample classes
INSERT INTO Tx_Classes (ClassName, ClassCode, Stream, MaxStrength, SchoolID, AcademicYearID, CreatedBy) VALUES 
('1st', 'C1', 'General', 40, 1000, 1, 'Super Admin'),
('2nd', 'C2', 'General', 40, 1000, 1, 'Super Admin'),
('3rd', 'C3', 'General', 40, 1000, 1, 'Super Admin');

INSERT INTO Tx_Sections (SectionID, ClassID, SectionName, SchoolID, AcademicYearID, CreatedBy) VALUES
(1, 1, 'A', 1000, 1, 'Super Admin'),
(2, 1, 'B', 1000, 1, 'Super Admin'),
(3, 2, 'A', 1000, 1, 'Super Admin'),
(4, 2, 'B', 1000, 1, 'Super Admin'),
(5, 3, 'A', 1000, 1, 'Super Admin'),
(6, 3, 'B', 1000, 1, 'Super Admin');

-- Demo users: teacher and student (password: password123)

-- Demo users: teacher and student (password: password123)
INSERT INTO Tx_Users (Username, PasswordHash, FirstName, MiddleName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES 
('teacher1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Alice', '', 'Teacher', '555-1111', 'alice@demo-school.edu', 3, 1000, 1, 1, 'System'),
('student1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Bob', '', 'Student', '555-2222', 'bob@student.demo', 4, 1000, 1, 1, 'System');

-- More demo users (explicit UserIDs to make later inserts predictable)
INSERT INTO Tx_Users (UserID, Username, PasswordHash, FirstName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES
(4, 'admin2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Admin', 'Two', '555-3333', 'admin2@demo-school.edu', 2, 1000, 1, 1, 'System'),
(5, 'teacher2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Carol', 'Teacher', '555-4444', 'carol@demo-school.edu', 3, 1000, 1, 1, 'System'),
(6, 'teacher3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Dave', 'Teacher', '555-5555', 'dave@demo-school.edu', 3, 1000, 1, 1, 'System'),
(7, 'student2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Eve', 'Student', '555-6666', 'eve@student.demo', 4, 1000, 1, 1, 'System'),
(8, 'student3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Frank', 'Student', '555-7777', 'frank@student.demo', 4, 1000, 1, 1, 'System');

-- Demo employee for teacher1 (UserID will be 2)
-- Demo employees
INSERT INTO Tx_Employees (EmployeeID, EmployeeName, UserID, SchoolID, RoleID, AcademicYearID, DOB, Gender, JoiningDate, Salary, Subjects, Status, CreatedBy) VALUES
(1, 'Alice Teacher', 2, 1000, 3, 1, '1985-06-15', 'F', '2020-06-01', 30000.00, 'Math,Science', 'Active', 'System'),
(2, 'Carol Teacher', 5, 1000, 3, 1, '1990-02-20', 'F', '2021-01-15', 28000.00, 'English', 'Active', 'System'),
(3, 'Dave Teacher', 6, 1000, 3, 1, '1988-11-10', 'M', '2019-08-01', 32000.00, 'Physics', 'Active', 'System');

-- Demo student record for student1 (UserID will be 3). SectionID references SectionID=1
-- Demo students (explicit StudentIDs)
INSERT INTO Tx_Students (StudentID, StudentName, Gender, DOB, SchoolID, SectionID, UserID, AcademicYearID, FatherName, MotherName, AdmissionDate, Status, CreatedBy) VALUES
(1, 'Bob Student', 'M', '2015-09-01', 1000, 1, 3, 1, 'Mr Parent', 'Mrs Parent', '2025-04-01', 'Active', 'System'),
(2, 'Eve Student', 'F', '2016-03-12', 1000, 3, 7, 1, 'Mr Parent2', 'Mrs Parent2', '2025-04-02', 'Active', 'System'),
(3, 'Frank Student', 'M', '2014-07-22', 1000, 5, 8, 1, 'Mr Parent3', 'Mrs Parent3', '2025-04-03', 'Active', 'System');

-- Map class teacher (ClassID=1, EmployeeID=1)
-- Map class teachers
INSERT INTO Tx_ClassTeachers (ClassID, EmployeeID, SchoolID, AcademicYearID, StartDate, IsActive, CreatedBy) VALUES
(1, 1, 1000, 1, '2020-06-01', 1, 'System'),
(2, 2, 1000, 1, '2021-01-15', 1, 'System'),
(3, 3, 1000, 1, '2019-08-01', 1, 'System');

-- Demo attendance entries
-- Demo attendance entries (multiple employees and students)
INSERT INTO Tx_Employee_Attendance (EmployeeAttendanceID, EmployeeID, SchoolID, AcademicYearID, Date, Status, Remarks, CreatedBy) VALUES
(1, 1, 1000, 1, '2025-08-30', 'Present', 'On time', 'System'),
(2, 2, 1000, 1, '2025-08-30', 'Present', 'On time', 'System'),
(3, 3, 1000, 1, '2025-08-30', 'Absent', 'Sick', 'System');

-- Attendance must include ClassID (not null). Use class ids that correspond to the section ids above (1->class 1, 3->class 2, 5->class 3)
INSERT INTO Tx_Students_Attendance (StudentAttendanceID, Date, Status, StudentID, SectionID, ClassID, SchoolID, AcademicYearID, Remarks, CreatedBy) VALUES
(1, '2025-08-30', 'Present', 1, 1, 1, 1000, 1, 'Present in class', 'System'),
(2, '2025-08-30', 'Present', 2, 3, 2, 1000, 1, 'Present in class', 'System'),
(3, '2025-08-30', 'Absent', 3, 5, 3, 1000, 1, 'Sick', 'System');

-- ================= Additional Tables for Dashboard Enhancements =================
-- Events table
CREATE TABLE IF NOT EXISTS Tx_Events (
    EventID BIGINT AUTO_INCREMENT PRIMARY KEY,
    SchoolID INT NOT NULL,
    AcademicYearID INT NULL,
    Title VARCHAR(200) NOT NULL,
    EventDate DATE NOT NULL,
    StartTime TIME NULL,
    EndTime TIME NULL,
    Location VARCHAR(150) NULL,
    Type VARCHAR(50) NULL,
    Priority VARCHAR(10) DEFAULT 'medium',
    Status VARCHAR(20) DEFAULT 'Active',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE SET NULL,
    INDEX idx_event_school_date (SchoolID, EventDate)
);

-- Activity Log table
CREATE TABLE IF NOT EXISTS Tx_ActivityLog (
    ActivityID BIGINT AUTO_INCREMENT PRIMARY KEY,
    SchoolID INT NOT NULL,
    AcademicYearID INT NULL,
    ActivityType VARCHAR(50) NOT NULL,
    Message VARCHAR(400) NOT NULL,
    Severity VARCHAR(20) DEFAULT 'info',
    Icon VARCHAR(50) DEFAULT 'pi pi-info-circle',
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE SET NULL,
    INDEX idx_activity_school_created (SchoolID, CreatedAt)
);

-- Student Grades table
CREATE TABLE IF NOT EXISTS Tx_StudentGrades (
    GradeID BIGINT AUTO_INCREMENT PRIMARY KEY,
    StudentID BIGINT NOT NULL,
    AcademicYearID INT NOT NULL,
    Subject VARCHAR(100) NOT NULL,
    GradeLetter VARCHAR(3) NOT NULL,
    Marks DECIMAL(5,2) NULL,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    FOREIGN KEY (StudentID) REFERENCES Tx_Students(StudentID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_grade_student (StudentID),
    INDEX idx_grade_ac_year (AcademicYearID)
);

-- Fees (payments) table
CREATE TABLE IF NOT EXISTS Tx_Fees (
    FeeID BIGINT AUTO_INCREMENT PRIMARY KEY,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Category VARCHAR(50) NOT NULL, -- Tuition, Extra, Transport
    Amount DECIMAL(12,2) NOT NULL,
    PaymentDate DATE NOT NULL,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_fees_school_date (SchoolID, PaymentDate)
);

-- Fee Invoices for pending fee calculation
CREATE TABLE IF NOT EXISTS Tx_FeeInvoices (
    InvoiceID BIGINT AUTO_INCREMENT PRIMARY KEY,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    StudentID BIGINT NULL,
    AmountDue DECIMAL(12,2) NOT NULL,
    AmountPaid DECIMAL(12,2) NOT NULL DEFAULT 0,
    Status VARCHAR(20) NOT NULL DEFAULT 'Pending', -- Pending, Partial, Paid
    DueDate DATE NULL,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    FOREIGN KEY (StudentID) REFERENCES Tx_Students(StudentID) ON DELETE SET NULL,
    INDEX idx_invoice_school_status (SchoolID, Status)
);

-- Dummy Events (future dates)
INSERT INTO Tx_Events (SchoolID, AcademicYearID, Title, EventDate, StartTime, EndTime, Location, Type, Priority, CreatedBy) VALUES
(1000, 1, 'Parent-Teacher Conference', '2025-09-05', '09:00:00', '17:00:00', 'Main Auditorium', 'Academic', 'high', 'System'),
(1000, 1, 'Annual Sports Day', '2025-09-08', '08:00:00', '16:00:00', 'School Grounds', 'Sports', 'medium', 'System'),
(1000, 1, 'Science Fair', '2025-09-12', '10:00:00', '15:00:00', 'Science Block', 'Academic', 'medium', 'System'),
(1000, 1, 'Mid-term Examinations Begin', '2025-09-15', NULL, NULL, 'Examination Halls', 'Examination', 'high', 'System');

-- Dummy Activity Log
INSERT INTO Tx_ActivityLog (SchoolID, AcademicYearID, ActivityType, Message, Severity, Icon, CreatedBy) VALUES
(1000, 1, 'enrollment', 'New student John Doe enrolled in Grade 10-A', 'success', 'pi pi-user-plus', 'System'),
(1000, 1, 'fee', 'Fee payment received from Sarah Johnson - $2,500', 'info', 'pi pi-credit-card', 'System'),
(1000, 1, 'attendance', 'Low attendance alert for Grade 8-B (78%)', 'warning', 'pi pi-exclamation-triangle', 'System'),
(1000, 1, 'exam', 'Mid-term exam results published for Grade 12', 'success', 'pi pi-file-edit', 'System'),
(1000, 1, 'event', 'Annual Sports Day scheduled for next Friday', 'info', 'pi pi-calendar', 'System');

-- Dummy Student Grades (grades distribution)
INSERT INTO Tx_StudentGrades (StudentID, AcademicYearID, Subject, GradeLetter, Marks, CreatedBy) VALUES
(1,1,'Mathematics','A+',95,'System'),
(1,1,'Science','A',90,'System'),
(2,1,'Mathematics','B+',82,'System'),
(2,1,'Science','B',78,'System'),
(3,1,'Mathematics','A',91,'System'),
(3,1,'Science','C+',68,'System'),
(1,1,'English','A+',96,'System'),
(2,1,'English','B',75,'System'),
(3,1,'English','C',62,'System');

-- Dummy Fees (last 6 months)
INSERT INTO Tx_Fees (SchoolID, AcademicYearID, Category, Amount, PaymentDate, CreatedBy) VALUES
(1000,1,'Tuition',450000,'2025-03-15','System'),
(1000,1,'Extra',45000,'2025-03-20','System'),
(1000,1,'Transport',35000,'2025-03-25','System'),
(1000,1,'Tuition',465000,'2025-04-15','System'),
(1000,1,'Extra',48000,'2025-04-20','System'),
(1000,1,'Transport',36000,'2025-04-25','System'),
(1000,1,'Tuition',470000,'2025-05-15','System'),
(1000,1,'Extra',52000,'2025-05-20','System'),
(1000,1,'Transport',37000,'2025-05-25','System'),
(1000,1,'Tuition',480000,'2025-06-15','System'),
(1000,1,'Extra',55000,'2025-06-20','System'),
(1000,1,'Transport',38000,'2025-06-25','System'),
(1000,1,'Tuition',485000,'2025-07-15','System'),
(1000,1,'Extra',58000,'2025-07-20','System'),
(1000,1,'Transport',39000,'2025-07-25','System'),
(1000,1,'Tuition',490000,'2025-08-15','System'),
(1000,1,'Extra',60000,'2025-08-20','System'),
(1000,1,'Transport',40000,'2025-08-25','System');

-- Dummy Fee Invoices (some pending/partial)
INSERT INTO Tx_FeeInvoices (SchoolID, AcademicYearID, StudentID, AmountDue, AmountPaid, Status, DueDate, CreatedBy) VALUES
(1000,1,1,2500,2500,'Paid','2025-04-10','System'),
(1000,1,2,2500,1500,'Partial','2025-04-10','System'),
(1000,1,3,2500,0,'Pending','2025-04-10','System');

-- ================= GYAN GANGA public school demo data (6 months) =================
-- Insert new school
INSERT INTO Tm_Schools (SchoolName, SchoolCode, Address, ContactNumber, Email, PrincipalName, Status) VALUES
('GYAN GANGA public school', 'GGPS001', '45 Knowledge Lane, Study City', '999-000-1111', 'info@gyanganga.edu', 'Mrs. Nirmala Sharma', 'Active');

-- capture the new school and academic year id in variables
SET @school = (SELECT SchoolID FROM Tm_Schools WHERE SchoolCode = 'GGPS001' LIMIT 1);
INSERT INTO Tm_AcademicYears (AcademicYearName, StartDate, EndDate, SchoolID, Status, CreatedBy) VALUES
('2025-2026', '2025-04-01', '2026-03-31', @school, 'Active', 'System');
SET @ay = (SELECT AcademicYearID FROM Tm_AcademicYears WHERE SchoolID = @school LIMIT 1);

-- Create admin, teachers and students users for Gyan Ganga
INSERT INTO Tx_Users (Username, PasswordHash, FirstName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES
('gg_admin', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'GG', 'Admin', '999000111', 'admin@gyanganga.edu', 2, @school, 1, 1, 'System'),
('gg_teacher1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Aarti', 'Sharma', '999000222', 'aarti@gyanganga.edu', 3, @school, 1, 1, 'System'),
('gg_teacher2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Rohit', 'Verma', '999000333', 'rohit@gyanganga.edu', 3, @school, 1, 1, 'System'),
('gg_student1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Anil', 'Kumar', '999001001', 'anil@student.ggy', 4, @school, 1, 1, 'System'),
('gg_student2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Meera', 'Singh', '999001002', 'meera@student.ggy', 4, @school, 1, 1, 'System'),
('gg_student3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Rajat', 'Patel', '999001003', 'rajat@student.ggy', 4, @school, 1, 1, 'System'),
('gg_student4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Sana', 'Khan', '999001004', 'sana@student.ggy', 4, @school, 1, 1, 'System'),
('gg_student5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Vikram', 'Rao', '999001005', 'vikram@student.ggy', 4, @school, 1, 1, 'System'),
('gg_student6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Neha', 'Das', '999001006', 'neha@student.ggy', 4, @school, 1, 1, 'System');

-- Create employees (teachers) mapped to user accounts
INSERT INTO Tx_Employees (EmployeeName, UserID, SchoolID, RoleID, AcademicYearID, DOB, Gender, JoiningDate, Salary, Subjects, Status, CreatedBy)
VALUES
('Aarti Sharma', (SELECT UserID FROM Tx_Users WHERE Username='gg_teacher1' AND SchoolID=@school LIMIT 1), @school, 3, @ay, '1990-05-01', 'F', '2021-06-01', 28000.00, 'Mathematics', 'Active', 'System'),
('Rohit Verma', (SELECT UserID FROM Tx_Users WHERE Username='gg_teacher2' AND SchoolID=@school LIMIT 1), @school, 3, @ay, '1988-09-10', 'M', '2020-07-15', 30000.00, 'Science', 'Active', 'System');

-- Create classes (6th - 11th)
INSERT INTO Tx_Classes (ClassName, ClassCode, Stream, MaxStrength, SchoolID, AcademicYearID, CreatedBy) VALUES
('6th', 'GG6', 'General', 40, @school, @ay, 'System'),
('7th', 'GG7', 'General', 40, @school, @ay, 'System'),
('8th', 'GG8', 'General', 40, @school, @ay, 'System'),
('9th', 'GG9', 'General', 40, @school, @ay, 'System'),
('10th', 'GG10', 'General', 40, @school, @ay, 'System'),
('11th', 'GG11', 'General', 40, @school, @ay, 'System');

-- Create one section per class (A)
-- Create one section per class (A)
-- Note: schema expects ClassID then SectionName in Tx_Sections
INSERT INTO Tx_Sections (ClassID, SectionName, SchoolID, AcademicYearID, CreatedBy) VALUES
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1), 'A', @school, @ay, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1), 'A', @school, @ay, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1), 'A', @school, @ay, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1), 'A', @school, @ay, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1), 'A', @school, @ay, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1), 'A', @school, @ay, 'System');

INSERT INTO Tx_ClassTeachers (ClassID, EmployeeID, SchoolID, AcademicYearID, StartDate, IsActive, CreatedBy) VALUES
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Aarti Sharma' AND SchoolID=@school LIMIT 1), @school, @ay, '2021-06-01', 1, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Aarti Sharma' AND SchoolID=@school LIMIT 1), @school, @ay, '2021-06-01', 1, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Rohit Verma' AND SchoolID=@school LIMIT 1), @school, @ay, '2020-07-15', 1, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Rohit Verma' AND SchoolID=@school LIMIT 1), @school, @ay, '2020-07-15', 1, 'System');

-- Add additional teachers and map them to remaining classes (10th, 11th)
-- Create teacher user accounts for 10th and 11th
INSERT INTO Tx_Users (Username, PasswordHash, FirstName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES
('gg_teacher3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Suresh', 'Kumar', '999000444', 'suresh@gyanganga.edu', 3, @school, 1, 1, 'System'),
('gg_teacher4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'Priya', 'Gupta', '999000555', 'priya@gyanganga.edu', 3, @school, 1, 1, 'System');

-- Create employee records for these teacher users
INSERT INTO Tx_Employees (EmployeeName, UserID, SchoolID, RoleID, AcademicYearID, DOB, Gender, JoiningDate, Salary, Subjects, Status, CreatedBy)
VALUES
('Suresh Kumar', (SELECT UserID FROM Tx_Users WHERE Username='gg_teacher3' AND SchoolID=@school LIMIT 1), @school, 3, @ay, '1985-04-10', 'M', '2018-08-01', 30000.00, 'Mathematics', 'Active', 'System'),
('Priya Gupta', (SELECT UserID FROM Tx_Users WHERE Username='gg_teacher4' AND SchoolID=@school LIMIT 1), @school, 3, @ay, '1987-09-22', 'F', '2019-09-01', 29000.00, 'Physics', 'Active', 'System');

-- Map the new teachers to classes 10th and 11th respectively
INSERT INTO Tx_ClassTeachers (ClassID, EmployeeID, SchoolID, AcademicYearID, StartDate, IsActive, CreatedBy)
VALUES
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Suresh Kumar' AND SchoolID=@school LIMIT 1), @school, @ay, '2018-08-01', 1, 'System'),
((SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1), (SELECT EmployeeID FROM Tx_Employees WHERE EmployeeName='Priya Gupta' AND SchoolID=@school LIMIT 1), @school, @ay, '2019-09-01', 1, 'System');

-- Create student records assigned to sections
INSERT INTO Tx_Students (StudentName, Gender, DOB, SchoolID, SectionID, UserID, AcademicYearID, FatherName, MotherName, AdmissionDate, Status, CreatedBy) VALUES
('Anil Kumar', 'M', '2013-02-10', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student1' AND SchoolID=@school LIMIT 1), @ay, 'Mr Kumar', 'Mrs Kumar', '2025-03-15', 'Active', 'System'),
('Meera Singh', 'F', '2012-11-24', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student2' AND SchoolID=@school LIMIT 1), @ay, 'Mr Singh', 'Mrs Singh', '2025-04-20', 'Active', 'System'),
('Rajat Patel', 'M', '2011-06-18', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student3' AND SchoolID=@school LIMIT 1), @ay, 'Mr Patel', 'Mrs Patel', '2025-05-10', 'Active', 'System'),
('Sana Khan', 'F', '2010-09-04', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student4' AND SchoolID=@school LIMIT 1), @ay, 'Mr Khan', 'Mrs Khan', '2025-06-01', 'Active', 'System'),
('Vikram Rao', 'M', '2009-01-30', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student5' AND SchoolID=@school LIMIT 1), @ay, 'Mr Rao', 'Mrs Rao', '2025-07-08', 'Active', 'System'),
('Neha Das', 'F', '2008-05-21', @school, (SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1), (SELECT UserID FROM Tx_Users WHERE Username='gg_student6' AND SchoolID=@school LIMIT 1), @ay, 'Mr Das', 'Mrs Das', '2025-08-05', 'Active', 'System');

-- Insert student attendance for a recent date (so charts using latest date pick it up)
-- Ensure ClassID is supplied (computed from the student's SectionID)
INSERT INTO Tx_Students_Attendance (Date, Status, StudentID, SectionID, ClassID, SchoolID, AcademicYearID, Remarks, CreatedBy) VALUES
('2025-08-30','Present',(SELECT StudentID FROM Tx_Students WHERE StudentName='Anil Kumar' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Anil Kumar' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Anil Kumar' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'Present in class','System'),
('2025-08-30','Present',(SELECT StudentID FROM Tx_Students WHERE StudentName='Meera Singh' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Meera Singh' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Meera Singh' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'Present in class','System'),
('2025-08-30','Absent',(SELECT StudentID FROM Tx_Students WHERE StudentName='Rajat Patel' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Rajat Patel' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Rajat Patel' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'Sick','System'),
('2025-08-30','Present',(SELECT StudentID FROM Tx_Students WHERE StudentName='Sana Khan' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Sana Khan' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Sana Khan' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'Present in class','System'),
('2025-08-30','Present',(SELECT StudentID FROM Tx_Students WHERE StudentName='Vikram Rao' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Vikram Rao' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Vikram Rao' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'Present in class','System'),
('2025-08-30','Absent',(SELECT StudentID FROM Tx_Students WHERE StudentName='Neha Das' AND SchoolID=@school LIMIT 1),(SELECT SectionID FROM Tx_Students WHERE StudentName='Neha Das' AND SchoolID=@school LIMIT 1),(SELECT ClassID FROM Tx_Sections WHERE SectionID = (SELECT SectionID FROM Tx_Students WHERE StudentName='Neha Das' AND SchoolID=@school LIMIT 1) LIMIT 1),@school,@ay,'On leave','System');

-- Insert student grades (sample)
INSERT INTO Tx_StudentGrades (StudentID, AcademicYearID, Subject, GradeLetter, Marks, CreatedBy) VALUES
((SELECT StudentID FROM Tx_Students WHERE StudentName='Anil Kumar' AND SchoolID=@school LIMIT 1), @ay, 'Mathematics', 'A', 90, 'System'),
((SELECT StudentID FROM Tx_Students WHERE StudentName='Meera Singh' AND SchoolID=@school LIMIT 1), @ay, 'Science', 'B+', 82, 'System'),
((SELECT StudentID FROM Tx_Students WHERE StudentName='Rajat Patel' AND SchoolID=@school LIMIT 1), @ay, 'English', 'A+', 95, 'System');

-- Insert fees payments for last 6 months (Mar-Aug 2025) for Gyan Ganga
INSERT INTO Tx_Fees (SchoolID, AcademicYearID, Category, Amount, PaymentDate, CreatedBy) VALUES
(@school,@ay,'Tuition',120000,'2025-03-15','System'),
(@school,@ay,'Extra',12000,'2025-03-20','System'),
(@school,@ay,'Transport',8000,'2025-03-25','System'),
(@school,@ay,'Tuition',125000,'2025-04-15','System'),
(@school,@ay,'Extra',13000,'2025-04-20','System'),
(@school,@ay,'Transport',8200,'2025-04-25','System'),
(@school,@ay,'Tuition',127000,'2025-05-15','System'),
(@school,@ay,'Extra',13500,'2025-05-20','System'),
(@school,@ay,'Transport',8400,'2025-05-25','System'),
(@school,@ay,'Tuition',128500,'2025-06-15','System'),
(@school,@ay,'Extra',13800,'2025-06-20','System'),
(@school,@ay,'Transport',8600,'2025-06-25','System'),
(@school,@ay,'Tuition',130000,'2025-07-15','System'),
(@school,@ay,'Extra',14000,'2025-07-20','System'),
(@school,@ay,'Transport',8800,'2025-07-25','System'),
(@school,@ay,'Tuition',132000,'2025-08-15','System'),
(@school,@ay,'Extra',14500,'2025-08-20','System'),
(@school,@ay,'Transport',9000,'2025-08-25','System');

-- Insert fee invoices for some students (pending/partial)
INSERT INTO Tx_FeeInvoices (SchoolID, AcademicYearID, StudentID, AmountDue, AmountPaid, Status, DueDate, CreatedBy) VALUES
(@school,@ay,(SELECT StudentID FROM Tx_Students WHERE StudentName='Anil Kumar' AND SchoolID=@school LIMIT 1),2500,2500,'Paid','2025-04-10','System'),
(@school,@ay,(SELECT StudentID FROM Tx_Students WHERE StudentName='Meera Singh' AND SchoolID=@school LIMIT 1),2500,1200,'Partial','2025-04-10','System'),
(@school,@ay,(SELECT StudentID FROM Tx_Students WHERE StudentName='Neha Das' AND SchoolID=@school LIMIT 1),2500,0,'Pending','2025-04-10','System');

-- Events for Gyan Ganga
INSERT INTO Tx_Events (SchoolID, AcademicYearID, Title, EventDate, StartTime, EndTime, Location, Type, Priority, CreatedBy) VALUES
(@school,@ay,'Inter-School Math Quiz','2025-09-10','10:00:00','13:00:00','Hall A','Academic','medium','System'),
(@school,@ay,'Cultural Evening','2025-09-20','17:00:00','20:00:00','Auditorium','Cultural','high','System');

-- Activity log entries for Gyan Ganga
INSERT INTO Tx_ActivityLog (SchoolID, AcademicYearID, ActivityType, Message, Severity, Icon, CreatedBy) VALUES
(@school,@ay,'enrollment','New student Anil Kumar enrolled in 6th A','success','pi pi-user-plus','System'),
(@school,@ay,'fee','Fee payment received from Meera Singh - 1200','info','pi pi-credit-card','System');

-- -----------------------------------------------------------------------------
-- Insert last 7 days of attendance for all students in GYAN GANGA (2025-08-25 .. 2025-08-31)
-- This block avoids duplicate inserts by LEFT JOINing existing attendance rows.
-- -----------------------------------------------------------------------------
INSERT INTO Tx_Students_Attendance (Date, Status, StudentID, SectionID, ClassID, SchoolID, AcademicYearID, Remarks, CreatedBy)
SELECT d.Date,
             CASE WHEN (s.StudentID % 5) = d.offset THEN 'Absent' ELSE 'Present' END AS Status,
             s.StudentID,
             s.SectionID,
             (SELECT ClassID FROM Tx_Sections WHERE SectionID = s.SectionID LIMIT 1) AS ClassID,
             @school,
             @ay,
             CASE WHEN (s.StudentID % 5) = d.offset THEN 'Sick' ELSE 'Present in class' END AS Remarks,
             'System'
FROM Tx_Students s
CROSS JOIN (
        SELECT 0 AS offset, '2025-08-25' AS Date UNION ALL
        SELECT 1, '2025-08-26' UNION ALL
        SELECT 2, '2025-08-27' UNION ALL
        SELECT 3, '2025-08-28' UNION ALL
        SELECT 4, '2025-08-29' UNION ALL
        SELECT 5, '2025-08-30' UNION ALL
        SELECT 6, '2025-08-31'
) d
LEFT JOIN Tx_Students_Attendance a ON a.StudentID = s.StudentID AND a.Date = d.Date
WHERE s.SchoolID = @school
    AND a.StudentAttendanceID IS NULL;


-- -----------------------------------------------------------------------------
-- Add additional students so each section has at least 10 students (Gyan Ganga)
-- -----------------------------------------------------------------------------
-- Create student user accounts (9 additional per class, usernames gg_s{class}_{n})
INSERT INTO Tx_Users (Username, PasswordHash, FirstName, LastName, ContactNumber, EmailID, RoleID, SchoolID, IsActive, IsFirstLogin, CreatedBy) VALUES
('gg_s6_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_1', 'Student', '999002001', 's6_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_2', 'Student', '999002002', 's6_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_3', 'Student', '999002003', 's6_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_4', 'Student', '999002004', 's6_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_5', 'Student', '999002005', 's6_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_6', 'Student', '999002006', 's6_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_7', 'Student', '999002007', 's6_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_8', 'Student', '999002008', 's6_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s6_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S6_9', 'Student', '999002009', 's6_9@gyanganga.edu', 4, @school, 1, 1, 'System'),

('gg_s7_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_1', 'Student', '999003001', 's7_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_2', 'Student', '999003002', 's7_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_3', 'Student', '999003003', 's7_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_4', 'Student', '999003004', 's7_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_5', 'Student', '999003005', 's7_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_6', 'Student', '999003006', 's7_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_7', 'Student', '999003007', 's7_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_8', 'Student', '999003008', 's7_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s7_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S7_9', 'Student', '999003009', 's7_9@gyanganga.edu', 4, @school, 1, 1, 'System'),

('gg_s8_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_1', 'Student', '999004001', 's8_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_2', 'Student', '999004002', 's8_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_3', 'Student', '999004003', 's8_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_4', 'Student', '999004004', 's8_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_5', 'Student', '999004005', 's8_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_6', 'Student', '999004006', 's8_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_7', 'Student', '999004007', 's8_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_8', 'Student', '999004008', 's8_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s8_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S8_9', 'Student', '999004009', 's8_9@gyanganga.edu', 4, @school, 1, 1, 'System'),

('gg_s9_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_1', 'Student', '999005001', 's9_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_2', 'Student', '999005002', 's9_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_3', 'Student', '999005003', 's9_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_4', 'Student', '999005004', 's9_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_5', 'Student', '999005005', 's9_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_6', 'Student', '999005006', 's9_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_7', 'Student', '999005007', 's9_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_8', 'Student', '999005008', 's9_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s9_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S9_9', 'Student', '999005009', 's9_9@gyanganga.edu', 4, @school, 1, 1, 'System'),

('gg_s10_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_1', 'Student', '999006001', 's10_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_2', 'Student', '999006002', 's10_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_3', 'Student', '999006003', 's10_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_4', 'Student', '999006004', 's10_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_5', 'Student', '999006005', 's10_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_6', 'Student', '999006006', 's10_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_7', 'Student', '999006007', 's10_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_8', 'Student', '999006008', 's10_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s10_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S10_9', 'Student', '999006009', 's10_9@gyanganga.edu', 4, @school, 1, 1, 'System'),

('gg_s11_1', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_1', 'Student', '999007001', 's11_1@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_2', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_2', 'Student', '999007002', 's11_2@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_3', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_3', 'Student', '999007003', 's11_3@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_4', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_4', 'Student', '999007004', 's11_4@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_5', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_5', 'Student', '999007005', 's11_5@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_6', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_6', 'Student', '999007006', 's11_6@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_7', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_7', 'Student', '999007007', 's11_7@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_8', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_8', 'Student', '999007008', 's11_8@gyanganga.edu', 4, @school, 1, 1, 'System'),
('gg_s11_9', '$2y$12$U3X7satfZs7fDwHMV3ShHenIhduvqBWBR01XdrQf89OWPkBUm8.DG', 'S11_9', 'Student', '999007009', 's11_9@gyanganga.edu', 4, @school, 1, 1, 'System');

-- Insert student records for each new user, assigning to the correct section; alternate genders for variety
INSERT INTO Tx_Students (StudentName, Gender, DOB, SchoolID, SectionID, UserID, AcademicYearID, FatherName, MotherName, AdmissionDate, Status, CreatedBy) VALUES
('Stud 6-1','M','2013-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_1' AND SchoolID=@school LIMIT 1),@ay,'Father A','Mother A','2025-03-01','Active','System'),
('Stud 6-2','F','2013-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_2' AND SchoolID=@school LIMIT 1),@ay,'Father B','Mother B','2025-03-02','Active','System'),
('Stud 6-3','M','2013-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_3' AND SchoolID=@school LIMIT 1),@ay,'Father C','Mother C','2025-03-03','Active','System'),
('Stud 6-4','F','2013-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_4' AND SchoolID=@school LIMIT 1),@ay,'Father D','Mother D','2025-03-04','Active','System'),
('Stud 6-5','M','2013-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_5' AND SchoolID=@school LIMIT 1),@ay,'Father E','Mother E','2025-03-05','Active','System'),
('Stud 6-6','F','2013-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_6' AND SchoolID=@school LIMIT 1),@ay,'Father F','Mother F','2025-03-06','Active','System'),
('Stud 6-7','M','2013-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_7' AND SchoolID=@school LIMIT 1),@ay,'Father G','Mother G','2025-03-07','Active','System'),
('Stud 6-8','F','2013-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_8' AND SchoolID=@school LIMIT 1),@ay,'Father H','Mother H','2025-03-08','Active','System'),
('Stud 6-9','M','2013-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='6th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s6_9' AND SchoolID=@school LIMIT 1),@ay,'Father I','Mother I','2025-03-09','Active','System'),

('Stud 7-1','M','2012-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_1' AND SchoolID=@school LIMIT 1),@ay,'Father J','Mother J','2025-03-10','Active','System'),
('Stud 7-2','F','2012-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_2' AND SchoolID=@school LIMIT 1),@ay,'Father K','Mother K','2025-03-11','Active','System'),
('Stud 7-3','M','2012-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_3' AND SchoolID=@school LIMIT 1),@ay,'Father L','Mother L','2025-03-12','Active','System'),
('Stud 7-4','F','2012-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_4' AND SchoolID=@school LIMIT 1),@ay,'Father M','Mother M','2025-03-13','Active','System'),
('Stud 7-5','M','2012-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_5' AND SchoolID=@school LIMIT 1),@ay,'Father N','Mother N','2025-03-14','Active','System'),
('Stud 7-6','F','2012-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_6' AND SchoolID=@school LIMIT 1),@ay,'Father O','Mother O','2025-03-15','Active','System'),
('Stud 7-7','M','2012-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_7' AND SchoolID=@school LIMIT 1),@ay,'Father P','Mother P','2025-03-16','Active','System'),
('Stud 7-8','F','2012-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_8' AND SchoolID=@school LIMIT 1),@ay,'Father Q','Mother Q','2025-03-17','Active','System'),
('Stud 7-9','M','2012-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='7th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s7_9' AND SchoolID=@school LIMIT 1),@ay,'Father R','Mother R','2025-03-18','Active','System'),

('Stud 8-1','M','2011-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_1' AND SchoolID=@school LIMIT 1),@ay,'Father S','Mother S','2025-03-19','Active','System'),
('Stud 8-2','F','2011-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_2' AND SchoolID=@school LIMIT 1),@ay,'Father T','Mother T','2025-03-20','Active','System'),
('Stud 8-3','M','2011-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SchoolID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_3' AND SchoolID=@school LIMIT 1),@ay,'Father U','Mother U','2025-03-21','Active','System'),
('Stud 8-4','F','2011-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_4' AND SchoolID=@school LIMIT 1),@ay,'Father V','Mother V','2025-03-22','Active','System'),
('Stud 8-5','M','2011-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_5' AND SchoolID=@school LIMIT 1),@ay,'Father W','Mother W','2025-03-23','Active','System'),
('Stud 8-6','F','2011-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_6' AND SchoolID=@school LIMIT 1),@ay,'Father X','Mother X','2025-03-24','Active','System'),
('Stud 8-7','M','2011-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_7' AND SchoolID=@school LIMIT 1),@ay,'Father Y','Mother Y','2025-03-25','Active','System'),
('Stud 8-8','F','2011-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_8' AND SchoolID=@school LIMIT 1),@ay,'Father Z','Mother Z','2025-03-26','Active','System'),
('Stud 8-9','M','2011-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='8th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s8_9' AND SchoolID=@school LIMIT 1),@ay,'Father AA','Mother AA','2025-03-27','Active','System'),

('Stud 9-1','M','2010-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_1' AND SchoolID=@school LIMIT 1),@ay,'Father AB','Mother AB','2025-03-28','Active','System'),
('Stud 9-2','F','2010-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_2' AND SchoolID=@school LIMIT 1),@ay,'Father AC','Mother AC','2025-03-29','Active','System'),
('Stud 9-3','M','2010-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_3' AND SchoolID=@school LIMIT 1),@ay,'Father AD','Mother AD','2025-03-30','Active','System'),
('Stud 9-4','F','2010-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_4' AND SchoolID=@school LIMIT 1),@ay,'Father AE','Mother AE','2025-03-31','Active','System'),
('Stud 9-5','M','2010-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_5' AND SchoolID=@school LIMIT 1),@ay,'Father AF','Mother AF','2025-04-01','Active','System'),
('Stud 9-6','F','2010-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_6' AND SchoolID=@school LIMIT 1),@ay,'Father AG','Mother AG','2025-04-02','Active','System'),
('Stud 9-7','M','2010-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_7' AND SchoolID=@school LIMIT 1),@ay,'Father AH','Mother AH','2025-04-03','Active','System'),
('Stud 9-8','F','2010-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_8' AND SchoolID=@school LIMIT 1),@ay,'Father AI','Mother AI','2025-04-04','Active','System'),
('Stud 9-9','M','2010-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='9th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s9_9' AND SchoolID=@school LIMIT 1),@ay,'Father AJ','Mother AJ','2025-04-05','Active','System'),

('Stud 10-1','M','2009-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_1' AND SchoolID=@school LIMIT 1),@ay,'Father AK','Mother AK','2025-04-06','Active','System'),
('Stud 10-2','F','2009-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_2' AND SCHOOLID=@school LIMIT 1),@ay,'Father AL','Mother AL','2025-04-07','Active','System'),
('Stud 10-3','M','2009-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_3' AND SchoolID=@school LIMIT 1),@ay,'Father AM','Mother AM','2025-04-08','Active','System'),
('Stud 10-4','F','2009-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_4' AND SchoolID=@school LIMIT 1),@ay,'Father AN','Mother AN','2025-04-09','Active','System'),
('Stud 10-5','M','2009-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_5' AND SchoolID=@school LIMIT 1),@ay,'Father AO','Mother AO','2025-04-10','Active','System'),
('Stud 10-6','F','2009-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_6' AND SchoolID=@school LIMIT 1),@ay,'Father AP','Mother AP','2025-04-11','Active','System'),
('Stud 10-7','M','2009-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_7' AND SchoolID=@school LIMIT 1),@ay,'Father AQ','Mother AQ','2025-04-12','Active','System'),
('Stud 10-8','F','2009-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_8' AND SchoolID=@school LIMIT 1),@ay,'Father AR','Mother AR','2025-04-13','Active','System'),
('Stud 10-9','M','2009-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='10th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s10_9' AND SchoolID=@school LIMIT 1),@ay,'Father AS','Mother AS','2025-04-14','Active','System'),

('Stud 11-1','M','2008-01-10',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_1' AND SchoolID=@school LIMIT 1),@ay,'Father AT','Mother AT','2025-04-15','Active','System'),
('Stud 11-2','F','2008-02-11',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_2' AND SchoolID=@school LIMIT 1),@ay,'Father AU','Mother AU','2025-04-16','Active','System'),
('Stud 11-3','M','2008-03-12',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_3' AND SchoolID=@school LIMIT 1),@ay,'Father AV','Mother AV','2025-04-17','Active','System'),
('Stud 11-4','F','2008-04-13',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_4' AND SchoolID=@school LIMIT 1),@ay,'Father AW','Mother AW','2025-04-18','Active','System'),
('Stud 11-5','M','2008-05-14',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_5' AND SchoolID=@school LIMIT 1),@ay,'Father AX','Mother AX','2025-04-19','Active','System'),
('Stud 11-6','F','2008-06-15',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_6' AND SchoolID=@school LIMIT 1),@ay,'Father AY','Mother AY','2025-04-20','Active','System'),
('Stud 11-7','M','2008-07-16',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_7' AND SchoolID=@school LIMIT 1),@ay,'Father AZ','Mother AZ','2025-04-21','Active','System'),
('Stud 11-8','F','2008-08-17',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_8' AND SchoolID=@school LIMIT 1),@ay,'Father BA','Mother BA','2025-04-22','Active','System'),
('Stud 11-9','M','2008-09-18',@school,(SELECT SectionID FROM Tx_Sections WHERE SCHOOLID=@school AND ClassID=(SELECT ClassID FROM Tx_Classes WHERE SchoolID=@school AND ClassName='11th' LIMIT 1) LIMIT 1),(SELECT UserID FROM Tx_Users WHERE Username='gg_s11_9' AND SchoolID=@school LIMIT 1),@ay,'Father BB','Mother BB','2025-04-23','Active','System');

