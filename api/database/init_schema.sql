
USE INFORMATION_SCHEMA; -- ensure we can drop/create safely if client requires a schema context

-- Drop and recreate the database to ensure a clean schema
DROP DATABASE IF EXISTS schoollive_db;
CREATE DATABASE IF NOT EXISTS schoollive_db;
USE schoollive_db;

-- Drop existing tables if they exist (in correct order to handle foreign keys)
DROP TABLE IF EXISTS Tx_Students_Attendance;
DROP TABLE IF EXISTS Tx_Employee_Attendance;
DROP TABLE IF EXISTS Tx_ClassTeachers;
DROP TABLE IF EXISTS Tx_Students;
DROP TABLE IF EXISTS Tx_Employees;
DROP TABLE IF EXISTS Tx_Classes;
DROP TABLE IF EXISTS Tx_Sections;
DROP TABLE IF EXISTS Tx_RoleRoleMapping;
DROP TABLE IF EXISTS Tx_Users;
DROP TABLE IF EXISTS Tm_Roles;
DROP TABLE IF EXISTS Tm_AcademicYears;
DROP TABLE IF EXISTS Tm_Schools;

CREATE TABLE Tm_Schools (
    SchoolID INT PRIMARY KEY AUTO_INCREMENT,
    SchoolName VARCHAR(100) NOT NULL,
    SchoolCode VARCHAR(20) UNIQUE NOT NULL,
    Address TEXT,
    ContactNumber VARCHAR(15),
    Email VARCHAR(100),
    PrincipalName VARCHAR(100),
    Status VARCHAR(20) DEFAULT 'Active',
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
    Status VARCHAR(20) DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    UNIQUE KEY unique_academic_year (AcademicYearName, SchoolID),
    INDEX idx_school_academic_year (SchoolID, AcademicYearName),
    INDEX idx_status (Status)
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
    
    INDEX idx_role_name (RoleName),
    INDEX idx_role_code (RoleCode)
);

-- Create Role-Role Mapping Table
CREATE TABLE Tx_RoleRoleMapping (
    MappingID BIGINT AUTO_INCREMENT PRIMARY KEY,
    RoleID INT NOT NULL,
    AllowedRoleID INT NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
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
    ClassDisplayName VARCHAR(100) NOT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Status VARCHAR(20) DEFAULT 'Active',
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
    SectionName VARCHAR(50) NOT NULL, 
    SectionDisplayName VARCHAR(100) NOT NULL,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    ClassID BIGINT NOT NULL,
    Status VARCHAR(20) DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE CASCADE,
    INDEX idx_school_section (SchoolID, SectionName),
    INDEX idx_section_academicyear (AcademicYearID),
    INDEX idx_section_class (ClassID)
);

CREATE TABLE Tx_Employees (
    EmployeeID BIGINT AUTO_INCREMENT PRIMARY KEY,
    EmployeeName VARCHAR(300),
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
    StudentName VARCHAR(300),
    Gender CHAR(1) CHECK (Gender IN ('M','F','O')),
    DOB DATE NOT NULL,
    SchoolID INT NOT NULL,  
    SectionID BIGINT,
    UserID BIGINT NOT NULL,
    AcademicYearID INT NOT NULL,
    FatherName VARCHAR(150),
    FatherContactNumber VARCHAR(15),
    MotherName VARCHAR(150),
    MotherContactNumber VARCHAR(15),
    AdmissionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status VARCHAR(20) DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (UserID) REFERENCES Tx_Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (SectionID) REFERENCES Tx_Sections(SectionID) ON DELETE SET NULL,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    INDEX idx_student_school (SchoolID),
    INDEX idx_student_section (SectionID),
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
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    Remarks TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(100),
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(100),
    
    FOREIGN KEY (StudentID) REFERENCES Tx_Students(StudentID) ON DELETE CASCADE,
    FOREIGN KEY (SectionID) REFERENCES Tx_Sections(SectionID) ON DELETE CASCADE,
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
INSERT INTO Tx_Classes (ClassName, ClassDisplayName, SchoolID, AcademicYearID, CreatedBy) VALUES 
('1st', 'First Grade', 1000, 1, 'Super Admin'),
('2nd', 'Second Grade', 1000, 1, 'Super Admin'),
('3rd', 'Third Grade', 1000, 1, 'Super Admin');

-- Create sample sections
-- Create sample sections (explicit SectionID to keep referential consistency)
INSERT INTO Tx_Sections (SectionID, SectionName, SectionDisplayName, SchoolID, AcademicYearID, ClassID, CreatedBy) VALUES 
(1, 'A', 'Section A', 1000, 1, 1, 'Super Admin'),
(2, 'B', 'Section B', 1000, 1, 1, 'Super Admin');

-- Additional sections for other classes
INSERT INTO Tx_Sections (SectionID, SectionName, SectionDisplayName, SchoolID, AcademicYearID, ClassID, CreatedBy) VALUES
(3, 'A', 'Section A', 1000, 1, 2, 'Super Admin'),
(4, 'B', 'Section B', 1000, 1, 2, 'Super Admin'),
(5, 'A', 'Section A', 1000, 1, 3, 'Super Admin'),
(6, 'B', 'Section B', 1000, 1, 3, 'Super Admin');

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

INSERT INTO Tx_Students_Attendance (StudentAttendanceID, Date, Status, StudentID, SectionID, SchoolID, AcademicYearID, Remarks, CreatedBy) VALUES
(1, '2025-08-30', 'Present', 1, 1, 1000, 1, 'Present in class', 'System'),
(2, '2025-08-30', 'Present', 2, 3, 1000, 1, 'Present in class', 'System'),
(3, '2025-08-30', 'Absent', 3, 5, 1000, 1, 'Sick', 'System');
