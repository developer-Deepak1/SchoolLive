<?php

namespace SchoolLive\Models;

use SchoolLive\Models\Model;
use PDO;

class FeeModel extends Model
{
    protected $table = 'Tx_fees';
    protected $primaryKey = 'FeeID';

    /**
     * Get all fees for a school and academic year with their class-section mappings
     */
    public function getFees($schoolId, $academicYearId)
    {
        // Use the new method that includes mappings
        return $this->getAllFeesWithMappings($schoolId, $academicYearId);
    }

    /**
     * Get fee by ID
     */
    public function getFeeById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new fee with class-section mappings
     */
    public function createFee($data)
    {
        try {
            $this->conn->beginTransaction();
            
            // Create main fee record (without ClassID and SectionID)
            $sql = "INSERT INTO {$this->table} 
                    (FeeName, Frequency, StartDate, LastDueDate, Amount, IsActive, Status, 
                     SchoolID, AcademicYearID, CreatedBy) 
                    VALUES (:feeName, :frequency, :startDate, :lastDueDate, :amount, :isActive, :status, :schoolId, :academicYearId, :createdBy)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':feeName', $data['feeName']);
            $stmt->bindValue(':frequency', $data['frequency']);
            $stmt->bindValue(':startDate', $data['startDate']);
            $stmt->bindValue(':lastDueDate', $data['lastDueDate']);
            $stmt->bindValue(':amount', $data['amount']);
            $stmt->bindValue(':isActive', $data['isActive'] ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':status', $data['status']);
            $stmt->bindValue(':schoolId', $data['schoolId'], PDO::PARAM_INT);
            $stmt->bindValue(':academicYearId', $data['academicYearId'], PDO::PARAM_INT);
            $stmt->bindValue(':createdBy', $data['createdBy']);

            $stmt->execute();
            $feeId = $this->conn->lastInsertId();
            
            // Create class-section mappings if provided
            if (!empty($data['classSectionMapping'])) {
                $this->createClassSectionMappings($feeId, $data['classSectionMapping'], $data['createdBy']);
            }
            
            $this->conn->commit();
            return $feeId;
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Update existing fee with class-section mappings
     */
    public function updateFee($id, $data)
    {
        try {
            $this->conn->beginTransaction();
            
            $fields = [];
            $params = [':id' => $id];

            // Dynamic field updates (remove classId and sectionId)
            $allowedFields = [
                'feeName' => 'FeeName',
                'frequency' => 'Frequency', 
                'startDate' => 'StartDate',
                'lastDueDate' => 'LastDueDate',
                'amount' => 'Amount',
                'isActive' => 'IsActive',
                'status' => 'Status',
                'updatedBy' => 'UpdatedBy'
            ];

            foreach ($allowedFields as $key => $column) {
                if (isset($data[$key])) {
                    $fields[] = "{$column} = :{$key}";
                    
                    if ($key === 'isActive') {
                        $params[":{$key}"] = $data[$key] ? 1 : 0;
                    } else {
                        $params[":{$key}"] = $data[$key];
                    }
                }
            }

            if (!empty($fields)) {
                // Always update UpdatedAt
                $fields[] = "UpdatedAt = NOW()";

                $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
                
                $stmt = $this->conn->prepare($sql);
                
                foreach ($params as $param => $value) {
                    $stmt->bindValue($param, $value);
                }
                
                $stmt->execute();
            }
            
            // Update class-section mappings if provided
            if (isset($data['classSectionMapping'])) {
                // Delete existing mappings
                $this->deleteClassSectionMappings($id);
                
                // Create new mappings
                if (!empty($data['classSectionMapping'])) {
                    $this->createClassSectionMappings($id, $data['classSectionMapping'], $data['updatedBy'] ?? 'system');
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Delete fee
     */
    public function deleteFee($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get fees by frequency
     */
    public function getFeesByFrequency($frequency, $schoolId, $academicYearId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE Frequency = :frequency AND SchoolID = :schoolId AND AcademicYearID = :academicYearId 
                ORDER BY CreatedAt DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':frequency', $frequency);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get fees by status (active/inactive)
     */
    public function getFeesByStatus($isActive, $schoolId, $academicYearId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE IsActive = :isActive AND SchoolID = :schoolId AND AcademicYearID = :academicYearId 
                ORDER BY CreatedAt DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':isActive', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if fee name exists for school/academic year
     */
    public function feeNameExists($feeName, $schoolId, $academicYearId, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE FeeName = :feeName AND SchoolID = :schoolId AND AcademicYearID = :academicYearId";
        $params = [
            ':feeName' => $feeName,
            ':schoolId' => $schoolId,
            ':academicYearId' => $academicYearId
        ];

        if ($excludeId) {
            $sql .= " AND {$this->primaryKey} != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Get upcoming due fees
     */
    public function getUpcomingDueFees($schoolId, $academicYearId, $days = 7)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE SchoolID = :schoolId AND AcademicYearID = :academicYearId 
                AND IsActive = 1 
                AND LastDueDate BETWEEN GETDATE() AND DATEADD(day, :days, GETDATE())
                ORDER BY LastDueDate ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue fees
     */
    public function getOverdueFees($schoolId, $academicYearId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE SchoolID = :schoolId AND AcademicYearID = :academicYearId 
                AND IsActive = 1 
                AND LastDueDate < GETDATE()
                ORDER BY LastDueDate ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create class-section mappings for a fee
     */
    public function createClassSectionMappings($feeId, $mappings, $createdBy)
    {
        $sql = "INSERT INTO Tx_fee_class_section_mapping 
                (FeeID, ClassID, SectionID, Amount, CreatedBy) 
                VALUES (:feeId, :classId, :sectionId, :amount, :createdBy)";

        $stmt = $this->conn->prepare($sql);

        foreach ($mappings as $mapping) {
            $amount = null;
            if (isset($mapping['Amount'])) {
                $amount = $mapping['Amount'];
            } elseif (isset($mapping['amount'])) {
                $amount = $mapping['amount'];
            }

            $stmt->bindValue(':feeId', $feeId, PDO::PARAM_INT);
            $stmt->bindValue(':classId', $mapping['classId'], PDO::PARAM_INT);
            $stmt->bindValue(':sectionId', $mapping['sectionId'], PDO::PARAM_INT);

            if ($amount === null) {
                $stmt->bindValue(':amount', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':amount', (float)$amount);
            }

            $stmt->bindValue(':createdBy', $createdBy);
            $stmt->execute();
        }
    }

    /**
     * Delete class-section mappings for a fee
     */
    public function deleteClassSectionMappings($feeId)
    {
        $sql = "DELETE FROM Tx_fee_class_section_mapping WHERE FeeID = :feeId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':feeId', $feeId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get fee with class-section mappings using the view
     */
    public function getFeeWithMappings($feeId)
    {
        $sql = "SELECT * FROM VW_FeeDetails WHERE FeeID = :feeId ORDER BY ClassID, SectionID";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':feeId', $feeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all fees with class-section mappings using the view
     */
    public function getAllFeesWithMappings($schoolId, $academicYearId)
    {
        $sql = "SELECT * FROM VW_FeeDetails 
                WHERE SchoolID = :schoolId AND AcademicYearID = :academicYearId
                ORDER BY FeeID, ClassID, SectionID";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':academicYearId', $academicYearId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}