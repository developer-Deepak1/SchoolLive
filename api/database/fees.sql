-- Main fees table (normalized without ClassID and SectionID)
CREATE TABLE Tx_fees (
    FeeID INT PRIMARY KEY AUTO_INCREMENT,
    FeeName VARCHAR(100) NOT NULL,
    Frequency ENUM('OneTime', 'OnDemand', 'Daily', 'Weekly', 'Monthly', 'Yearly') NOT NULL,
    StartDate DATE NOT NULL,
    LastDueDate DATE NOT NULL,
    Amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    Status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    SchoolID INT NOT NULL,
    AcademicYearID INT NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL,
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    
    -- Foreign key constraints
    CONSTRAINT FK_Tx_fees_School FOREIGN KEY (SchoolID) REFERENCES Tm_Schools(SchoolID),
    CONSTRAINT FK_Tx_fees_AcademicYear FOREIGN KEY (AcademicYearID) REFERENCES Tm_AcademicYears(AcademicYearID),
    
    -- Indexes for performance
    INDEX IX_Tx_fees_School_Academic (SchoolID, AcademicYearID),
    INDEX IX_Tx_fees_Status (Status, IsActive),
    INDEX IX_Tx_fees_Frequency (Frequency),
    INDEX IX_Tx_fees_Dates (StartDate, LastDueDate)
);

-- Separate mapping table for fee class-section assignments
CREATE TABLE Tx_fee_class_section_mapping (
    MappingID INT PRIMARY KEY AUTO_INCREMENT,
    FeeID INT NOT NULL,
    ClassID BIGINT NOT NULL,
    SectionID BIGINT NOT NULL,
    Amount DECIMAL(10,2) NULL,
    IsActive TINYINT(1) NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreatedBy VARCHAR(50) NOT NULL,
    UpdatedAt DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UpdatedBy VARCHAR(50) NULL,
    
    -- Foreign key constraints
    CONSTRAINT FK_FeeMapping_Fee FOREIGN KEY (FeeID) REFERENCES Tx_fees(FeeID) ON DELETE CASCADE,
    CONSTRAINT FK_FeeMapping_Class FOREIGN KEY (ClassID) REFERENCES Tx_Classes(ClassID) ON DELETE CASCADE,
    CONSTRAINT FK_FeeMapping_Section FOREIGN KEY (SectionID) REFERENCES Tx_Sections(SectionID) ON DELETE CASCADE,
    
    -- Unique constraint to prevent duplicate mappings
    CONSTRAINT UK_Fee_Class_Section UNIQUE (FeeID, ClassID, SectionID),
    
    -- Indexes for performance
    INDEX IX_FeeMapping_Fee (FeeID),
    INDEX IX_FeeMapping_Class (ClassID),
    INDEX IX_FeeMapping_Section (SectionID),
    INDEX IX_FeeMapping_Class_Section (ClassID, SectionID)
);

-- View for easy fee details with class-section info
CREATE VIEW VW_FeeDetails AS
SELECT 
    f.FeeID,
    f.FeeName,
    f.Frequency,
    f.StartDate,
    f.LastDueDate,
    f.Amount as BaseAmount,
    f.IsActive as FeeActive,
    f.Status,
    f.SchoolID,
    f.AcademicYearID,
    f.CreatedAt as FeeCreatedAt,
    f.CreatedBy as FeeCreatedBy,
    f.UpdatedAt as FeeUpdatedAt,
    f.UpdatedBy as FeeUpdatedBy,
    
    -- Mapping details
    m.MappingID,
    m.ClassID,
    m.SectionID,
    m.Amount as ClassSectionAmount,
    m.IsActive as MappingActive,
    m.CreatedAt as MappingCreatedAt,
    m.CreatedBy as MappingCreatedBy,
    
    -- Class and Section names
    c.ClassName,
    s.SectionName,
    
    -- Calculated amount (use mapping amount if exists, otherwise base amount)
    COALESCE(m.Amount, f.Amount) as EffectiveAmount
    
FROM Tx_fees f
LEFT JOIN Tx_fee_class_section_mapping m ON f.FeeID = m.FeeID AND m.IsActive = 1
LEFT JOIN Tx_Classes c ON m.ClassID = c.ClassID
LEFT JOIN Tx_Sections s ON m.SectionID = s.SectionID
WHERE f.IsActive = 1;


