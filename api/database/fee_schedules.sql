-- -------------------------------------------------------------
-- 1. Fee Master Table
-- -------------------------------------------------------------
CREATE TABLE Tx_fees (
    FeeID BIGINT PRIMARY KEY AUTO_INCREMENT,
    FeeName VARCHAR(100) NOT NULL,
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    SchoolID INT NOT NULL DEFAULT 1,
    AcademicYearID INT NOT NULL DEFAULT 0,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE
);
CREATE INDEX IX_Fees_School ON Tx_fees(SchoolID);
CREATE INDEX IX_Fees_AcademicYear ON Tx_fees(AcademicYearID);

-- -------------------------------------------------------------
-- 2. Class-Section Fee Mapping (for custom amounts per class/section)
-- -------------------------------------------------------------
CREATE TABLE Tx_fee_class_section_mapping (
    MappingID BIGINT PRIMARY KEY AUTO_INCREMENT,
    FeeID BIGINT NOT NULL,
    ClassID BIGINT NOT NULL,
    SectionID BIGINT NOT NULL,
    Amount DECIMAL(10,2) NULL,
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    CONSTRAINT UK_Fee_Class_Section UNIQUE (FeeID, ClassID, SectionID),
    CONSTRAINT FK_FeeMapping_Fee FOREIGN KEY (FeeID) REFERENCES Tx_fees(FeeID) ON DELETE CASCADE
);
CREATE INDEX IX_FeeMapping_Fee ON Tx_fee_class_section_mapping(FeeID);

-- -------------------------------------------------------------
-- 3. Fee Schedule Table (Recurring / OneTime / OnDemand)
-- -------------------------------------------------------------
CREATE TABLE Tx_fees_schedules (
    ScheduleID BIGINT PRIMARY KEY AUTO_INCREMENT,
    FeeID BIGINT NOT NULL,
    ScheduleType ENUM('OneTime','OnDemand','Recurring') NOT NULL,
    IntervalMonths INT NULL,   -- 1=Monthly,3=Quarterly,6=HalfYearly,12=Yearly
    DayOfMonth INT NULL,       -- day of month for recurring
    StartDate DATE NULL,
    EndDate DATE NULL,
    NextDueDate DATE NULL,
    ReminderDaysBefore INT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    CONSTRAINT FK_FeeSchedule_Fee FOREIGN KEY (FeeID) REFERENCES Tx_fees(FeeID) ON DELETE CASCADE
);
CREATE INDEX IX_FeeSchedule_FeeID ON Tx_fees_schedules(FeeID);
CREATE INDEX IX_FeeSchedule_ScheduleType ON Tx_fees_schedules(ScheduleType);

-- -------------------------------------------------------------
-- 5. Student Fee Ledger (per student, per fee/month)
-- -------------------------------------------------------------
CREATE TABLE Tx_student_fees (
    StudentFeeID BIGINT PRIMARY KEY AUTO_INCREMENT,
    SchoolID INT NOT NULL,
    StudentID BIGINT NOT NULL,
    FeeID BIGINT NOT NULL,
    MappingID BIGINT NULL,
    Amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FineAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    DiscountAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    AmountPaid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    DueDate DATE NULL,
    Status ENUM('Pending','Partial','Paid','Overdue') NOT NULL DEFAULT 'Pending',
    InvoiceRef VARCHAR(100) NULL,
    Remarks VARCHAR(255) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (StudentID) REFERENCES Tx_Students(StudentID) ON DELETE CASCADE,
    CONSTRAINT FK_StudentFees_Fee FOREIGN KEY (FeeID) REFERENCES Tx_fees(FeeID) ON DELETE CASCADE,
    CONSTRAINT FK_StudentFees_Mapping FOREIGN KEY (MappingID) REFERENCES Tx_fee_class_section_mapping(MappingID) ON DELETE SET NULL
);
CREATE INDEX IX_StudentFees_Student ON Tx_student_fees(StudentID);
CREATE INDEX IX_StudentFees_Fee ON Tx_student_fees(FeeID);
CREATE INDEX IX_StudentFees_Status ON Tx_student_fees(Status);
CREATE INDEX IX_StudentFees_School ON Tx_student_fees(SchoolID);

-- -------------------------------------------------------------
-- 6. Student Fee Payments (actual payments)
-- -------------------------------------------------------------
CREATE TABLE Tx_student_fee_payments (
    PaymentID BIGINT PRIMARY KEY AUTO_INCREMENT,
    StudentFeeID BIGINT NOT NULL,
    PaymentDate DATE NOT NULL,
    PaidAmount DECIMAL(10,2) NOT NULL,
    Mode ENUM('Cash','Online','Cheque','UPI') NOT NULL,
    TransactionRef VARCHAR(100) NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    CONSTRAINT FK_StudentFeePayments_Fee FOREIGN KEY (StudentFeeID) REFERENCES Tx_student_fees(StudentFeeID) ON DELETE CASCADE
);
CREATE INDEX IX_StudentFeePayments_StudentFee ON Tx_student_fee_payments(StudentFeeID);


-- -------------------------------------------------------------
-- Fine Policies (rules for late fees)
-- -------------------------------------------------------------
CREATE TABLE Tx_fine_policies (
    FinePolicyID BIGINT PRIMARY KEY AUTO_INCREMENT,
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL DEFAULT 0,
    FeeID BIGINT NULL,    -- if NULL = apply to all fees, else specific fee
    ApplyType ENUM('Fixed','PerDay','Percentage') NOT NULL,
    Amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- fixed amount OR per-day amount OR percentage
    GraceDays INT NOT NULL DEFAULT 0,           -- no fine if within grace days after DueDate
    MaxAmount DECIMAL(10,2) NULL,               -- optional cap (e.g., max ₹500 fine)
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL DEFAULT 'System',
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,

    FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID) ON DELETE CASCADE,
    FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID) ON DELETE CASCADE,
    FOREIGN KEY (FeeID) REFERENCES Tx_fees(FeeID) ON DELETE SET NULL
);

CREATE INDEX IX_FinePolicy_School ON Tx_fine_policies(SchoolID);
CREATE INDEX IX_FinePolicy_Fee ON Tx_fine_policies(FeeID);
CREATE INDEX IX_FinePolicy_AcademicYear ON Tx_fine_policies(AcademicYearID);
