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

            $fees = $this->feeModel->getFees($schoolId, $academicYearId);
            // Flatten to top-level DB columns with nested Schedule and ClassSectionMapping
            $response = [];
            foreach ($fees as $item) {
                $fee = $item['Fee'];
                // Only expose active class-section mappings to list responses so the UI marks selected correctly
                $mappings = array_filter($item['ClassSectionMapping'] ?? [], function($m) {
                    return isset($m['IsActive']) ? (int)$m['IsActive'] === 1 : false;
                });
                $response[] = array_merge($fee, [
                    'Schedule' => $item['Schedule'],
                    'ClassSectionMapping' => array_values($mappings)
                ]);
            }

            return $this->ok('Fees retrieved successfully', $response);

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
            // First element holds the structured Fee, Schedule, and Mappings
            $data = $feeWithMappings[0];
            $mappings = array_filter($data['ClassSectionMapping'] ?? [], function($m) {
                return isset($m['IsActive']) ? (int)$m['IsActive'] === 1 : false;
            });
            $response = array_merge($data['Fee'], [
                'Schedule' => $data['Schedule'],
                'ClassSectionMapping' => array_values($mappings)
            ]);

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
            $input = $this->input();
            
            // Get user from session
            $user = $this->currentUser();
            if (!$user) return; // Error already sent by currentUser()

            // Build DB-native payload
            $data = [];
            $data['FeeName'] = $input['FeeName'] ?? $input['feeName'] ?? null;
            $data['IsActive'] = isset($input['IsActive']) ? (bool)$input['IsActive'] : (isset($input['isActive']) ? (bool)$input['isActive'] : true);
            $data['SchoolID'] = $user['school_id'] ?? 1;
            $data['AcademicYearID'] = $user['academic_year_id'] ?? 1;
            $data['CreatedBy'] = $user['username'] ?? 'System';

            // Validate required fields
            $required = ['FeeName'];
            if (!$this->ensure($data, $required)) {
                return;
            }

            // Schedule (DB-native)
            if (!empty($input['Schedule']) || !empty($input['schedule'])) {
                $sch = $input['Schedule'] ?? $input['schedule'];
                // Accept either DB-native or legacy camelCase keys
                $data['Schedule'] = [
                    'ScheduleType' => $sch['ScheduleType'] ?? $sch['scheduleType'] ?? 'OneTime',
                    'IntervalMonths' => $sch['IntervalMonths'] ?? $sch['intervalMonths'] ?? null,
                    'DayOfMonth' => $sch['DayOfMonth'] ?? $sch['dayOfMonth'] ?? null,
                    'StartDate' => $sch['StartDate'] ?? $sch['startDate'] ?? null,
                    'EndDate' => $sch['EndDate'] ?? $sch['endDate'] ?? null,
                    'NextDueDate' => $sch['NextDueDate'] ?? $sch['nextDueDate'] ?? null,
                    'ReminderDaysBefore' => $sch['ReminderDaysBefore'] ?? $sch['reminderDaysBefore'] ?? null,
                ];

                // Normalize schedule fields depending on ScheduleType
                $stype = $data['Schedule']['ScheduleType'] ?? 'OneTime';
                // For non-recurring schedules, clear fields not applicable
                if ($stype !== 'Recurring') {
                    $data['Schedule']['IntervalMonths'] = null;
                    $data['Schedule']['DayOfMonth'] = null;
                    $data['Schedule']['ReminderDaysBefore'] = null;
                }

                // For OneTime schedules, StartDate and EndDate should be null per requirement
                if ($stype === 'OneTime') {
                    $data['Schedule']['StartDate'] = null;
                    $data['Schedule']['EndDate'] = null;
                }

                // For OnDemand ensure StartDate <= EndDate if both provided
                if ($stype === 'OnDemand') {
                    $start = $data['Schedule']['StartDate'] ?? null;
                    $end = $data['Schedule']['EndDate'] ?? null;
                    $parseDate = function ($d) {
                        if ($d === null) return null;
                        // Try strtotime first (ISO or common formats)
                        $ts = strtotime($d);
                        if ($ts !== false) return (new \DateTime())->setTimestamp($ts);
                        // Try dd/mm/YYYY
                        $dt = \DateTime::createFromFormat('d/m/Y', $d);
                        if ($dt !== false) return $dt;
                        // Try Y-m-d
                        $dt = \DateTime::createFromFormat('Y-m-d', $d);
                        if ($dt !== false) return $dt;
                        return null;
                    };
                    $sd = $parseDate($start);
                    $ed = $parseDate($end);
                    if ($sd && $ed && $sd > $ed) {
                        return $this->fail('StartDate must be before or equal to EndDate for OnDemand schedule', 400);
                    }
                }
            }

            // ClassSectionMapping
            if (!empty($input['ClassSectionMapping']) || !empty($input['classSectionMapping'])) {
                $mappings = $input['ClassSectionMapping'] ?? $input['classSectionMapping'];
                $data['ClassSectionMapping'] = array_map(function($m) {
                    return [
                        'ClassID' => $m['ClassID'] ?? $m['classId'],
                        'SectionID' => $m['SectionID'] ?? $m['sectionId'],
                        'Amount' => $m['Amount'] ?? $m['amount'] ?? null,
                    ];
                }, $mappings);
            }
            
            // Create the fee record with mappings and schedules
            $feeId = $this->feeModel->createFee($data);
            
            if ($feeId) {
                // Get the created fee with its mappings for response
                $feeWithMappings = $this->feeModel->getFeeWithMappings($feeId);

                if (empty($feeWithMappings)) {
                    return $this->fail('Failed to fetch created fee', 500);
                }

                // Structure response
                $dataOut = $feeWithMappings[0];
                $response = array_merge($dataOut['Fee'], [
                    'Schedule' => $dataOut['Schedule'],
                    'ClassSectionMapping' => $dataOut['ClassSectionMapping']
                ]);

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
            $input = $this->input();
            
            // Check if fee exists
            $existingFee = $this->feeModel->getFeeById($id);
            if (!$existingFee) {
                return $this->fail('Fee not found', 404);
            }

            // Build DB-native payload for update
            $data = [];
            if (isset($input['FeeName']) || isset($input['feeName'])) $data['FeeName'] = $input['FeeName'] ?? $input['feeName'];
            if (isset($input['IsActive']) || isset($input['isActive'])) $data['IsActive'] = isset($input['IsActive']) ? (bool)$input['IsActive'] : (bool)$input['isActive'];
            $data['UpdatedBy'] = $this->currentUser(false)['username'] ?? 'System';

            if (isset($input['Schedule']) || isset($input['schedule'])) {
                $sch = $input['Schedule'] ?? $input['schedule'];
                $data['Schedule'] = [
                    'ScheduleType' => $sch['ScheduleType'] ?? $sch['scheduleType'] ?? null,
                    'IntervalMonths' => $sch['IntervalMonths'] ?? $sch['intervalMonths'] ?? null,
                    'DayOfMonth' => $sch['DayOfMonth'] ?? $sch['dayOfMonth'] ?? null,
                    'StartDate' => $sch['StartDate'] ?? $sch['startDate'] ?? null,
                    'EndDate' => $sch['EndDate'] ?? $sch['endDate'] ?? null,
                    'NextDueDate' => $sch['NextDueDate'] ?? $sch['nextDueDate'] ?? null,
                    'ReminderDaysBefore' => $sch['ReminderDaysBefore'] ?? $sch['reminderDaysBefore'] ?? null,
                ];

                // Normalize depending on ScheduleType
                $stype = $data['Schedule']['ScheduleType'] ?? null;
                if ($stype !== 'Recurring') {
                    $data['Schedule']['IntervalMonths'] = null;
                    $data['Schedule']['DayOfMonth'] = null;
                    $data['Schedule']['ReminderDaysBefore'] = null;
                }

                // For OneTime schedules, StartDate and EndDate should be null
                if ($stype === 'OneTime') {
                    $data['Schedule']['StartDate'] = null;
                    $data['Schedule']['EndDate'] = null;
                }

                if ($stype === 'OnDemand') {
                    $parseDate = function ($d) {
                        if ($d === null) return null;
                        $ts = strtotime($d);
                        if ($ts !== false) return (new \DateTime())->setTimestamp($ts);
                        $dt = \DateTime::createFromFormat('d/m/Y', $d);
                        if ($dt !== false) return $dt;
                        $dt = \DateTime::createFromFormat('Y-m-d', $d);
                        if ($dt !== false) return $dt;
                        return null;
                    };
                    $sd = $parseDate($data['Schedule']['StartDate'] ?? null);
                    $ed = $parseDate($data['Schedule']['EndDate'] ?? null);
                    if ($sd && $ed && $sd > $ed) {
                        return $this->fail('StartDate must be before or equal to EndDate for OnDemand schedule', 400);
                    }
                }
            }

            if (isset($input['ClassSectionMapping']) || isset($input['classSectionMapping'])) {
                $mappings = $input['ClassSectionMapping'] ?? $input['classSectionMapping'];
                $data['ClassSectionMapping'] = array_map(function($m) {
                    $out = [
                        'ClassID' => $m['ClassID'] ?? $m['classId'],
                        'SectionID' => $m['SectionID'] ?? $m['sectionId'],
                        'Amount' => $m['Amount'] ?? $m['amount'] ?? null,
                    ];
                    // Preserve MappingID when present for precise updates
                    if (isset($m['MappingID'])) {
                        $out['MappingID'] = (int)$m['MappingID'];
                    } elseif (isset($m['mappingId'])) {
                        $out['MappingID'] = (int)$m['mappingId'];
                    }
                    return $out;
                }, $mappings);
            }

            // Sanitize scalar fields to avoid accidental arrays (which cause "Array to string conversion")
            if (isset($data['FeeName']) && is_array($data['FeeName'])) {
                // Coerce to string using first element if array provided
                $data['FeeName'] = reset($data['FeeName']);
            }
            if (isset($data['IsActive']) && is_array($data['IsActive'])) {
                $data['IsActive'] = (bool)reset($data['IsActive']);
            }
            if (isset($data['UpdatedBy']) && is_array($data['UpdatedBy'])) {
                $data['UpdatedBy'] = reset($data['UpdatedBy']);
            }

            // Validate Schedule structure if present
            if (isset($data['Schedule']) && !is_array($data['Schedule'])) {
                // If it's a scalar, wrap into array? better to reject — coerce to null
                $data['Schedule'] = null;
            } elseif (is_array($data['Schedule'])) {
                // Ensure scalar values inside Schedule are scalars or null
                foreach (['ScheduleType','IntervalMonths','DayOfMonth','StartDate','EndDate','NextDueDate','ReminderDaysBefore'] as $k) {
                    if (array_key_exists($k, $data['Schedule']) && is_array($data['Schedule'][$k])) {
                        // Coerce to first element
                        $data['Schedule'][$k] = reset($data['Schedule'][$k]);
                    }
                }
            }

            // Validate ClassSectionMapping if present
            if (isset($data['ClassSectionMapping']) && is_array($data['ClassSectionMapping'])) {
                $cleanMappings = [];
                foreach ($data['ClassSectionMapping'] as $m) {
                    if (!is_array($m)) continue;
                    $row = [
                        'ClassID' => isset($m['ClassID']) ? (int)$m['ClassID'] : (isset($m['classId']) ? (int)$m['classId'] : 0),
                        'SectionID' => isset($m['SectionID']) ? (int)$m['SectionID'] : (isset($m['sectionId']) ? (int)$m['sectionId'] : 0),
                        'Amount' => array_key_exists('Amount', $m) ? $m['Amount'] : (array_key_exists('amount', $m) ? $m['amount'] : null)
                    ];
                    if (isset($m['MappingID'])) {
                        $row['MappingID'] = (int)$m['MappingID'];
                    } elseif (isset($m['mappingId'])) {
                        $row['MappingID'] = (int)$m['mappingId'];
                    }
                    $cleanMappings[] = $row;
                }
                $data['ClassSectionMapping'] = $cleanMappings;
            }

            // Log class-section mapping payload for debugging amount update issues
            if (isset($data['ClassSectionMapping'])) {
                error_log('FeeController::update - ClassSectionMapping payload: ' . json_encode($data['ClassSectionMapping']));
            }

            $success = $this->feeModel->updateFee($id, $data);
            
            if ($success) {
                $feeWithMappings = $this->feeModel->getFeeWithMappings($id);
                if (empty($feeWithMappings)) return $this->ok('Fee updated successfully', []);
                $dataOut = $feeWithMappings[0];
                $response = array_merge($dataOut['Fee'], [
                    'Schedule' => $dataOut['Schedule'],
                    'ClassSectionMapping' => $dataOut['ClassSectionMapping']
                ]);
                return $this->ok('Fee updated successfully', $response);
            } else {
                return $this->fail('Failed to update fee', 500);
            }

        } catch (\Exception $e) {
            // Better error logging with file/line/trace for debugging
            $msg = sprintf("%s in %s:%d\n%s", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            error_log("FeeController::update() - " . $msg);
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
            // Log incoming payload for troubleshooting
            error_log('FeeController::toggleStatus payload: ' . json_encode($data));

            if (!isset($data['IsActive']) && !isset($data['isActive'])) {
                return $this->fail('isActive field is required', 400);
            }

            $existingFee = $this->feeModel->getFeeById($id);
            if (!$existingFee) {
                return $this->fail('Fee not found', 404);
            }

            // Determine boolean value, accept arrays/strings/ints
            $raw = isset($data['IsActive']) ? $data['IsActive'] : $data['isActive'];
            if (is_array($raw)) $raw = reset($raw);
            if (is_string($raw)) {
                $rawLower = strtolower($raw);
                if (in_array($rawLower, ['true','1','yes','on'])) $isActiveVal = true;
                elseif (in_array($rawLower, ['false','0','no','off'])) $isActiveVal = false;
                else $isActiveVal = (bool)$raw;
            } else {
                $isActiveVal = (bool)$raw;
            }

            $currentUser = $this->currentUser(false);
            $updatedBy = is_array($currentUser) ? ($currentUser['username'] ?? 'System') : 'System';

            $updateData = [
                'IsActive' => $isActiveVal,
                'UpdatedBy' => $updatedBy
            ];

            $success = $this->feeModel->updateFee($id, $updateData);
            
            if ($success) {
                $feeWithMappings = $this->feeModel->getFeeWithMappings($id);
                if (empty($feeWithMappings)) return $this->ok('Fee status updated successfully', []);
                $dataOut = $feeWithMappings[0];
                $response = array_merge($dataOut['Fee'], [
                    'Schedule' => $dataOut['Schedule'],
                    'ClassSectionMapping' => $dataOut['ClassSectionMapping']
                ]);
                return $this->ok('Fee status updated successfully', $response);
            } else {
                return $this->fail('Failed to update fee status', 500);
            }

        } catch (\Exception $e) {
            error_log("FeeController::toggleStatus() - " . $e->getMessage());
            return $this->fail('Failed to update fee status', 500);
        }
    }
}