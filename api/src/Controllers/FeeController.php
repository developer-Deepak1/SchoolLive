<?php

namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\FeeModel;
use SchoolLive\Core\Response;
use SchoolLive\Core\Request;

class FeeController extends BaseController
{
    private FeeModel $feeModel;

    public function __construct()
    {
        parent::__construct();
        $this->feeModel = new FeeModel();
    }

    /**
     * Get all fees for a school and academic year
     * GET /api/fees/list
     */
    public function list()
    {
        try {
            $user = $this->currentUser();
            if (!$user) return; // Error already sent by currentUser()

            $schoolId = $user['school_id'] ?? 1;
            $academicYearId = $user['academic_year_id'] ?? 1;

            $feesData = $this->feeModel->getFees($schoolId, $academicYearId);
            
            // Group by FeeID to structure the response properly
            $groupedFees = [];
            foreach ($feesData as $row) {
                $feeId = $row['FeeID'];
                
                if (!isset($groupedFees[$feeId])) {
                    $groupedFees[$feeId] = [
                        'feeId' => $row['FeeID'],
                        'feeName' => $row['FeeName'],
                        'frequency' => $row['Frequency'],
                        'startDate' => $row['StartDate'],
                        'lastDueDate' => $row['LastDueDate'],
                        'amount' => (float)$row['BaseAmount'],
                        'isActive' => (bool)$row['FeeActive'],
                        'status' => $row['Status'],
                        'schoolId' => $row['SchoolID'],
                        'academicYearId' => $row['AcademicYearID'],
                        'createdAt' => $row['FeeCreatedAt'],
                        'createdBy' => $row['FeeCreatedBy'],
                        'updatedAt' => $row['FeeUpdatedAt'],
                        'updatedBy' => $row['FeeUpdatedBy'],
                        'classSections' => []
                    ];
                }
                
                // Add class-section mapping if it exists
                if ($row['MappingID']) {
                    $groupedFees[$feeId]['classSections'][] = [
                        'mappingId' => $row['MappingID'],
                        'classId' => $row['ClassID'],
                        'className' => $row['ClassName'],
                        'sectionId' => $row['SectionID'],
                        'sectionName' => $row['SectionName'],
                        'effectiveAmount' => (float)$row['EffectiveAmount'],
                        'isActive' => (bool)$row['MappingActive']
                    ];
                }
            }

            return $this->ok('Fees retrieved successfully', array_values($groupedFees));

        } catch (\Exception $e) {
            error_log("FeeController::list() - " . $e->getMessage());
            return $this->fail('Failed to fetch fees', 500);
        }
    }

    /**
     * Get fee by ID with class-section mappings
     * GET /api/fees/{id}
     */
    public function show($id)
    {
        try {
            $feeWithMappings = $this->feeModel->getFeeWithMappings($id);
            
            if (empty($feeWithMappings)) {
                return $this->fail('Fee not found', 404);
            }

            // Structure the response similar to list method
            $fee = $feeWithMappings[0]; // Get first row for main fee data
            $response = [
                'feeId' => $fee['FeeID'],
                'feeName' => $fee['FeeName'],
                'frequency' => $fee['Frequency'],
                'startDate' => $fee['StartDate'],
                'lastDueDate' => $fee['LastDueDate'],
                'amount' => (float)$fee['BaseAmount'],
                'isActive' => (bool)$fee['FeeActive'],
                'status' => $fee['Status'],
                'schoolId' => $fee['SchoolID'],
                'academicYearId' => $fee['AcademicYearID'],
                'createdAt' => $fee['FeeCreatedAt'],
                'createdBy' => $fee['FeeCreatedBy'],
                'updatedAt' => $fee['FeeUpdatedAt'],
                'updatedBy' => $fee['FeeUpdatedBy'],
                'classSections' => []
            ];

            // Add all class-section mappings
            foreach ($feeWithMappings as $row) {
                if ($row['MappingID']) {
                    $response['classSections'][] = [
                        'mappingId' => $row['MappingID'],
                        'classId' => $row['ClassID'],
                        'className' => $row['ClassName'],
                        'sectionId' => $row['SectionID'],
                        'sectionName' => $row['SectionName'],
                        'effectiveAmount' => (float)$row['EffectiveAmount'],
                        'isActive' => (bool)$row['MappingActive']
                    ];
                }
            }

            return $this->ok('Fee retrieved successfully', $response);

        } catch (\Exception $e) {
            error_log("FeeController::show() - " . $e->getMessage());
            return $this->fail('Failed to fetch fee', 500);
        }
    }

    /**
     * Create new fee
     * POST /api/fees/create
     */
    public function create()
    {
        try {
            $data = $this->input();
            
            // Get user from session
            $user = $this->currentUser();
            if (!$user) return; // Error already sent by currentUser()

            // Add school and academic year from user session
            $data['schoolId'] = $user['school_id'] ?? 1;
            $data['academicYearId'] = $user['academic_year_id'] ?? 1;
            
            // Validate required fields (top-level amount is optional when per-class amounts are provided)
            $required = ['feeName', 'frequency', 'startDate', 'lastDueDate'];
            if (!$this->ensure($data, $required)) {
                return;
            }

            // Normalize and validate frequency (accept frontend lowercase values)
            $frequencyMap = [
                'onetime' => 'OneTime',
                'ondemand' => 'OnDemand',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'yearly' => 'Yearly'
            ];

            $freqKey = strtolower((string)($data['frequency'] ?? ''));
            if (!isset($frequencyMap[$freqKey])) {
                return $this->fail('Invalid frequency value', 400);
            }

            // Store normalized value that matches DB enum
            $data['frequency'] = $frequencyMap[$freqKey];

            // Validate top-level amount if provided. If not provided, default to 0.00
            if (isset($data['amount'])) {
                if (!is_numeric($data['amount']) || $data['amount'] < 0) {
                    return $this->fail('Amount must be a valid positive number', 400);
                }
                // Normalize to numeric
                $data['amount'] = (float)$data['amount'];
            } else {
                $data['amount'] = 0.00;
            }

            // Validate dates
            if (!$this->isValidDate($data['startDate']) || !$this->isValidDate($data['lastDueDate'])) {
                return $this->fail('Invalid date format. Use YYYY-MM-DD', 400);
            }

            // Set default values
            $data['isActive'] = $data['isActive'] ?? true;
            $data['status'] = $data['status'] ?? 'Active';
            $data['createdBy'] = $user['username'] ?? 'System';
            
            // Create single fee record with class-section mappings
            $feeId = $this->feeModel->createFee($data);
            
            if ($feeId) {
                // Get the created fee with its mappings for response
                $feeWithMappings = $this->feeModel->getFeeWithMappings($feeId);

                if (empty($feeWithMappings)) {
                    return $this->fail('Failed to fetch created fee', 500);
                }

                // Structure response similar to show()
                $feeRow = $feeWithMappings[0];
                $response = [
                    'feeId' => $feeRow['FeeID'],
                    'feeName' => $feeRow['FeeName'],
                    'frequency' => $feeRow['Frequency'],
                    'startDate' => $feeRow['StartDate'],
                    'lastDueDate' => $feeRow['LastDueDate'],
                    'amount' => (float)$feeRow['BaseAmount'],
                    'isActive' => (bool)$feeRow['FeeActive'],
                    'status' => $feeRow['Status'],
                    'schoolId' => $feeRow['SchoolID'],
                    'academicYearId' => $feeRow['AcademicYearID'],
                    'createdAt' => $feeRow['FeeCreatedAt'],
                    'createdBy' => $feeRow['FeeCreatedBy'],
                    'updatedAt' => $feeRow['FeeUpdatedAt'],
                    'updatedBy' => $feeRow['FeeUpdatedBy'],
                    'classSections' => []
                ];

                foreach ($feeWithMappings as $row) {
                    if ($row['MappingID']) {
                        $response['classSections'][] = [
                            'mappingId' => $row['MappingID'],
                            'classId' => $row['ClassID'],
                            'className' => $row['ClassName'],
                            'sectionId' => $row['SectionID'],
                            'sectionName' => $row['SectionName'],
                            'effectiveAmount' => (float)$row['EffectiveAmount'],
                            'isActive' => (bool)$row['MappingActive']
                        ];
                    }
                }

                return $this->ok('Fee created successfully with class-section mappings', $response, 201);
            } else {
                return $this->fail('Failed to create fee', 500);
            }

        } catch (\Exception $e) {
            error_log("FeeController::create() - " . $e->getMessage());
            return $this->fail('Failed to create fee', 500);
        }
    }

    /**
     * Update existing fee
     * PUT /api/fees/update/{id}
     */
    public function update($id)
    {
        try {
            $data = $this->input();
            
            // Check if fee exists
            $existingFee = $this->feeModel->getFeeById($id);
            if (!$existingFee) {
                return $this->fail('Fee not found', 404);
            }

            // Normalize and validate frequency if provided
            if (isset($data['frequency'])) {
                $frequencyMap = [
                    'onetime' => 'OneTime',
                    'ondemand' => 'OnDemand',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'yearly' => 'Yearly'
                ];

                $freqKey = strtolower((string)$data['frequency']);
                if (!isset($frequencyMap[$freqKey])) {
                    return $this->fail('Invalid frequency value', 400);
                }

                $data['frequency'] = $frequencyMap[$freqKey];
            }

            // Accept optional classId and sectionId for updates
            if (isset($data['classId'])) {
                // Optional: validate classId exists in database
            }
            if (isset($data['sectionId'])) {
                // Optional: validate sectionId exists in database
            }

            // Validate amount if provided
            if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] < 0)) {
                return $this->fail('Amount must be a valid positive number', 400);
            }

            // Validate dates if provided
            if (isset($data['startDate']) && !$this->isValidDate($data['startDate'])) {
                return $this->fail('Invalid start date format. Use YYYY-MM-DD', 400);
            }
            if (isset($data['lastDueDate']) && !$this->isValidDate($data['lastDueDate'])) {
                return $this->fail('Invalid last due date format. Use YYYY-MM-DD', 400);
            }

            $data['updatedBy'] = $this->currentUser(false)['username'] ?? 'System';

            $success = $this->feeModel->updateFee($id, $data);
            
            if ($success) {
                $fee = $this->feeModel->getFeeById($id);
                return $this->ok('Fee updated successfully', $fee);
            } else {
                return $this->fail('Failed to update fee', 500);
            }

        } catch (\Exception $e) {
            error_log("FeeController::update() - " . $e->getMessage());
            return $this->fail('Failed to update fee', 500);
        }
    }

    /**
     * Toggle fee active/inactive status
     * PATCH /api/fees/{id}/status
     */
    public function toggleStatus($id)
    {
        try {
            $data = $this->input();
            
            if (!isset($data['isActive'])) {
                return $this->fail('isActive field is required', 400);
            }

            $existingFee = $this->feeModel->getFeeById($id);
            if (!$existingFee) {
                return $this->fail('Fee not found', 404);
            }

            $updateData = [
                'isActive' => (bool)$data['isActive'],
                'status' => $data['isActive'] ? 'Active' : 'Inactive',
                'updatedBy' => $this->currentUser(false)['username'] ?? 'System'
            ];

            $success = $this->feeModel->updateFee($id, $updateData);
            
            if ($success) {
                $fee = $this->feeModel->getFeeById($id);
                return $this->ok('Fee status updated successfully', $fee);
            } else {
                return $this->fail('Failed to update fee status', 500);
            }

        } catch (\Exception $e) {
            error_log("FeeController::toggleStatus() - " . $e->getMessage());
            return $this->fail('Failed to update fee status', 500);
        }
    }

    /**
     * Delete fee
     * DELETE /api/fees/{id}
     */
    public function delete($id)
    {
        try {
            $existingFee = $this->feeModel->getFeeById($id);
            if (!$existingFee) {
                return $this->fail('Fee not found', 404);
            }

            $success = $this->feeModel->deleteFee($id);
            
            if ($success) {
                return $this->ok('Fee deleted successfully');
            } else {
                return $this->fail('Failed to delete fee', 500);
            }

        } catch (\Exception $e) {
            error_log("FeeController::delete() - " . $e->getMessage());
            return $this->fail('Failed to delete fee', 500);
        }
    }

    /**
     * Get fees by frequency
     * GET /api/fees/frequency/{frequency}
     */
    public function getByFrequency($frequency)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return; // Error already sent by currentUser()

            $schoolId = $user['school_id'] ?? 1;
            $academicYearId = $user['academic_year_id'] ?? 1;

            $frequencyMap = [
                'onetime' => 'OneTime',
                'ondemand' => 'OnDemand',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'yearly' => 'Yearly'
            ];

            $freqKey = strtolower((string)$frequency);
            if (!isset($frequencyMap[$freqKey])) {
                return $this->fail('Invalid frequency value', 400);
            }

            $normalizedFrequency = $frequencyMap[$freqKey];
            $fees = $this->feeModel->getFeesByFrequency($normalizedFrequency, $schoolId, $academicYearId);
            return $this->ok('Fees retrieved successfully', $fees);

        } catch (\Exception $e) {
            error_log("FeeController::getByFrequency() - " . $e->getMessage());
            return $this->fail('Failed to fetch fees by frequency', 500);
        }
    }

    /**
     * Get active/inactive fees
     * GET /api/fees/status/{status}
     */
    public function getByStatus($status)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return; // Error already sent by currentUser()

            $schoolId = $user['school_id'] ?? 1;
            $academicYearId = $user['academic_year_id'] ?? 1;

            $isActive = strtolower($status) === 'active';
            $fees = $this->feeModel->getFeesByStatus($isActive, $schoolId, $academicYearId);
            return $this->ok('Fees retrieved successfully', $fees);

        } catch (\Exception $e) {
            error_log("FeeController::getByStatus() - " . $e->getMessage());
            return $this->fail('Failed to fetch fees by status', 500);
        }
    }

    /**
     * Validate date format
     */
    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}