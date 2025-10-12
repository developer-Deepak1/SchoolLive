<?php

namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\DTO\FeeManagement\FeeRequestDto;
use SchoolLive\DTO\FeeManagement\FinePolicyRequestDto;
use SchoolLive\DTO\FeeManagement\PaymentRequestDto;
use SchoolLive\Models\FeeManagementModel;

class FeeManagementController extends BaseController
{
    private FeeManagementModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new FeeManagementModel();
    }

    public function listFees(): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $schoolId = (int)($user['school_id'] ?? 1);
            $ayId = (int)($user['academic_year_id'] ?? 1);
            $fees = $this->model->listFees($schoolId, $ayId);
            $payload = array_map(static fn($fee) => $fee->toArray(), $fees);
            $this->ok('Fees retrieved successfully', $payload);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::listFees - ' . $e->getMessage());
            $this->fail('Failed to fetch fees', 500);
        }
    }

    public function getFee($params): void
    {
        try {
            $feeId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($feeId <= 0) {
                $this->fail('Invalid fee id', 400);
                return;
            }
            $this->currentUser(); // ensure auth; method sends error if unauthorized
            $fee = $this->model->getFee($feeId);
            if (!$fee) {
                $this->fail('Fee not found', 404);
                return;
            }
            $this->ok('Fee retrieved successfully', $fee->toArray());
        } catch (\Throwable $e) {
            error_log('FeeManagementController::getFee - ' . $e->getMessage());
            $this->fail('Failed to fetch fee', 500);
        }
    }

    public function createFee(): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $payload = $this->input();
            $request = FeeRequestDto::fromArray($payload ?? []);
            if ($request->feeName === '') {
                $this->fail('FeeName is required', 400);
                return;
            }
            $schoolId = (int)($user['school_id'] ?? 1);
            $ayId = (int)($user['academic_year_id'] ?? 1);
            $fee = $this->model->createFee($request, $schoolId, $ayId, $user['username'] ?? 'System');
            $this->ok('Fee created successfully', $fee->toArray(), 201);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::createFee - ' . $e->getMessage());
            $this->fail('Failed to create fee', 500);
        }
    }

    public function updateFee($params): void
    {
        try {
            $feeId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($feeId <= 0) {
                $this->fail('Invalid fee id', 400);
                return;
            }
            $user = $this->currentUser();
            if (!$user) { return; }
            $request = FeeRequestDto::fromArray($this->input() ?? []);
            $fee = $this->model->updateFee($feeId, $request, $user['username'] ?? 'System');
            $this->ok('Fee updated successfully', $fee->toArray());
        } catch (\Throwable $e) {
            error_log('FeeManagementController::updateFee - ' . $e->getMessage());
            $this->fail('Failed to update fee', 500);
        }
    }

    public function toggleFeeStatus($params): void
    {
        try {
            $feeId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($feeId <= 0) {
                $this->fail('Invalid fee id', 400);
                return;
            }
            $payload = $this->input();
            if (!isset($payload['IsActive']) && !isset($payload['isActive'])) {
                $this->fail('isActive field is required', 400);
                return;
            }
            $raw = $payload['IsActive'] ?? $payload['isActive'];
            if (is_string($raw)) {
                $isActive = in_array(strtolower($raw), ['true','1','yes','on'], true);
            } else {
                $isActive = (bool)$raw;
            }
            $user = $this->currentUser();
            if (!$user) { return; }
            $fee = $this->model->toggleFeeStatus($feeId, $isActive, $user['username'] ?? 'System');
            $this->ok('Fee status updated successfully', $fee->toArray());
        } catch (\Throwable $e) {
            error_log('FeeManagementController::toggleFeeStatus - ' . $e->getMessage());
            $this->fail('Failed to update fee status', 500);
        }
    }

    public function listFinePolicies(): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $schoolId = (int)($user['school_id'] ?? 1);
            $ayId = (int)($user['academic_year_id'] ?? 1);
            $policies = $this->model->listFinePolicies($schoolId, $ayId);
            $payload = array_map(static fn($policy) => $policy->toArray(), $policies);
            $this->ok('Fine policies fetched', $payload);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::listFinePolicies - ' . $e->getMessage());
            $this->fail('Failed to fetch fine policies', 500);
        }
    }

    public function createFinePolicy(): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $request = FinePolicyRequestDto::fromArray($this->input() ?? []);
            $schoolId = (int)($user['school_id'] ?? 1);
            $ayId = (int)($user['academic_year_id'] ?? 1);
            $policy = $this->model->createFinePolicy($request, $schoolId, $ayId, $user['username'] ?? 'System');
            $this->ok('Fine policy created', $policy->toArray(), 201);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::createFinePolicy - ' . $e->getMessage());
            $this->fail('Failed to create fine policy', 500);
        }
    }

    public function updateFinePolicy($params): void
    {
        try {
            $policyId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($policyId <= 0) {
                $this->fail('Invalid fine policy id', 400);
                return;
            }
            $user = $this->currentUser();
            if (!$user) { return; }
            $request = FinePolicyRequestDto::fromArray($this->input() ?? []);
            $policy = $this->model->updateFinePolicy($policyId, $request, $user['username'] ?? 'System');
            $this->ok('Fine policy updated', $policy->toArray());
        } catch (\Throwable $e) {
            error_log('FeeManagementController::updateFinePolicy - ' . $e->getMessage());
            $this->fail('Failed to update fine policy', 500);
        }
    }

    public function deleteFinePolicy($params): void
    {
        try {
            $policyId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($policyId <= 0) {
                $this->fail('Invalid fine policy id', 400);
                return;
            }
            $this->model->deleteFinePolicy($policyId);
            $this->ok('Fine policy deleted', true);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::deleteFinePolicy - ' . $e->getMessage());
            $this->fail('Failed to delete fine policy', 500);
        }
    }

    public function recordPayments(): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $payload = $this->input();
            if (!$payload) {
                $this->fail('Empty payload', 400);
                return;
            }
            $items = [];
            if (isset($payload[0]) && is_array($payload[0])) {
                foreach ($payload as $item) {
                    $items[] = PaymentRequestDto::fromArray($item);
                }
            } else {
                $items[] = PaymentRequestDto::fromArray($payload);
            }
            $responses = $this->model->recordPayments($items, $user['username'] ?? 'System');
            $this->ok('Payments processed', [
                'items' => array_map(static fn($resp) => $resp->toArray(), $responses)
            ]);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::recordPayments - ' . $e->getMessage());
            $this->fail('Failed to record payments', 500);
        }
    }

    public function getStudentLedger($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }
            $filters = [
                'only_due' => isset($_GET['only_due']) ? (bool)$_GET['only_due'] : false,
                'include_paid' => isset($_GET['include_paid']) ? (bool)$_GET['include_paid'] : true,
            ];
            $ledger = $this->model->getStudentLedger((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $filters);
            $payload = array_map(static fn($row) => $row->toArray(), $ledger);
            $this->ok('Ledger fetched', $payload);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::getStudentLedger - ' . $e->getMessage());
            $this->fail('Failed to fetch ledger', 500);
        }
    }

    public function getStudentDues($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }
            $dues = $this->model->getStudentDues((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId);
            $payload = array_map(static fn($row) => $row->toArray(), $dues);
            $this->ok('Dues fetched', $payload);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::getStudentDues - ' . $e->getMessage());
            $this->fail('Failed to fetch dues', 500);
        }
    }

    public function getStudentMonthlyPlan($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
            $plan = $this->model->getMonthlyPlan((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $year, $month);
            $payload = array_map(static fn($row) => $row->toArray(), $plan);
            $this->ok('Monthly plan fetched', $payload);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::getStudentMonthlyPlan - ' . $e->getMessage());
            $this->fail('Failed to fetch monthly plan', 500);
        }
    }

    public function getStudentFeePlan($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }

            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
            if ($month < 1 || $month > 12) {
                $month = (int)date('n');
            }
            $reference = \DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%04d-%d-01', $year, $month)) ?: new \DateTimeImmutable('first day of this month');

            $plan = $this->model->getStudentFeePlan(
                (int)($user['school_id'] ?? 1),
                (int)($user['academic_year_id'] ?? 1),
                $studentId,
                $reference
            );

            $this->ok('Student fee plan fetched', $plan);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::getStudentFeePlan - ' . $e->getMessage());
            $this->fail('Failed to fetch student fee plan', 500);
        }
    }

    public function ensureStudentMonthly($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }
            $payload = $this->input();
            if (!$payload) {
                $this->fail('Empty payload', 400);
                return;
            }
            $items = [];
            if (isset($payload[0]) && is_array($payload[0])) {
                $items = $payload;
            } else {
                $items[] = $payload;
            }
            $results = $this->model->ensureMonthlyRows((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $items, $user['username'] ?? 'System');
            $this->ok('Monthly rows ensured', ['items' => $results]);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::ensureStudentMonthly - ' . $e->getMessage());
            $this->fail('Failed to ensure monthly rows', 500);
        }
    }

    public function assignStudentFee($params): void
    {
        try {
            $user = $this->currentUser();
            if (!$user) { return; }
            $studentId = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($studentId <= 0) {
                $this->fail('Invalid student id', 400);
                return;
            }
            $payload = $this->input();
            if (!$payload) {
                $this->fail('Empty payload', 400);
                return;
            }
            $studentFeeId = $this->model->assignStudentFee((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $payload, $user['username'] ?? 'System');
            $this->ok('Fee assigned', ['studentFeeId' => $studentFeeId], 201);
        } catch (\Throwable $e) {
            error_log('FeeManagementController::assignStudentFee - ' . $e->getMessage());
            $this->fail('Failed to assign fee', 500);
        }
    }
}
