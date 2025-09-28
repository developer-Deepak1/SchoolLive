<?php

namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Core\Request;
use SchoolLive\Models\FinePolicyModel;

class FinePolicyController extends BaseController
{
    private FinePolicyModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new FinePolicyModel();
    }

    /** GET /api/fines */
    public function list()
    {
        try {
            $user = $this->currentUser();
            if (!$user) return; // 401 sent
            $schoolId = (int)($user['school_id'] ?? 1);
            $ayId = (int)($user['academic_year_id'] ?? 1);
            $rows = $this->model->getPolicies($schoolId, $ayId);
            return $this->ok('Fine policies fetched', $rows);
        } catch (\Exception $e) {
            error_log('FinePolicyController::list - ' . $e->getMessage());
            return $this->fail('Failed to fetch fine policies', 500);
        }
    }

    /** POST /api/fines */
    public function create()
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $input = $this->input();

            // Normalize payload to DB-native keys
            $data = [];
            $data['SchoolID'] = (int)($user['school_id'] ?? 1);
            $data['AcademicYearID'] = (int)($user['academic_year_id'] ?? 1);
            $data['FeeID'] = $input['FeeID'] ?? ($input['feeId'] ?? null);
            $data['ApplyType'] = $input['ApplyType'] ?? ($input['applyType'] ?? null);
            $data['Amount'] = $this->normalizeNumber($input['Amount'] ?? ($input['amount'] ?? 0));
            $data['GraceDays'] = isset($input['GraceDays']) ? (int)$input['GraceDays'] : (isset($input['graceDays']) ? (int)$input['graceDays'] : 0);
            $data['MaxAmount'] = $this->normalizeNullableNumber($input['MaxAmount'] ?? ($input['maxAmount'] ?? null));
            $data['IsActive'] = isset($input['IsActive']) ? (bool)$input['IsActive'] : (isset($input['isActive']) ? (bool)$input['isActive'] : true);
            $data['CreatedBy'] = $user['username'] ?? 'System';

            // Validate required
            if (!$this->ensure($data, ['ApplyType', 'Amount'])) { return; }
            // Validate ApplyType
            $allowedTypes = ['Fixed','PerDay','Percentage'];
            if (!in_array($data['ApplyType'], $allowedTypes, true)) {
                return $this->fail('Invalid ApplyType', 400);
            }

            // Business rules
            if ($data['ApplyType'] === 'Percentage' && (float)$data['Amount'] > 100) {
                return $this->fail('Percentage amount cannot exceed 100', 400);
            }
            if ($data['GraceDays'] < 0) { return $this->fail('GraceDays cannot be negative', 400); }

            $id = $this->model->createPolicy($data);
            if ($id) {
                $row = $this->model->getPolicyById((int)$id);
                return $this->ok('Fine policy created', $row, 201);
            }
            return $this->fail('Failed to create policy', 500);

        } catch (\Exception $e) {
            error_log('FinePolicyController::create - ' . $e->getMessage());
            return $this->fail('Failed to create policy', 500);
        }
    }

    /** PUT /api/fines/{id} */
    public function update($params)
    {
        try {
            $id = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($id <= 0) return $this->fail('Invalid id', 400);

            $existing = $this->model->getPolicyById($id);
            if (!$existing) return $this->fail('Policy not found', 404);

            $user = $this->currentUser(false);
            $input = $this->input();
            $data = [];
            if (array_key_exists('FeeID', $input) || array_key_exists('feeId', $input)) {
                $val = $input['FeeID'] ?? ($input['feeId'] ?? null);
                $data['FeeID'] = ($val === null || $val === '') ? null : (int)$val;
            }
            if (array_key_exists('ApplyType', $input) || array_key_exists('applyType', $input)) {
                $data['ApplyType'] = $input['ApplyType'] ?? ($input['applyType'] ?? null);
            }
            if (array_key_exists('Amount', $input) || array_key_exists('amount', $input)) {
                $data['Amount'] = $this->normalizeNumber($input['Amount'] ?? ($input['amount'] ?? 0));
            }
            if (array_key_exists('GraceDays', $input) || array_key_exists('graceDays', $input)) {
                $data['GraceDays'] = (int)($input['GraceDays'] ?? ($input['graceDays'] ?? 0));
            }
            if (array_key_exists('MaxAmount', $input) || array_key_exists('maxAmount', $input)) {
                $data['MaxAmount'] = $this->normalizeNullableNumber($input['MaxAmount'] ?? ($input['maxAmount'] ?? null));
            }
            if (array_key_exists('IsActive', $input) || array_key_exists('isActive', $input)) {
                $raw = $input['IsActive'] ?? ($input['isActive'] ?? null);
                $data['IsActive'] = (bool)$raw;
            }
            $data['UpdatedBy'] = is_array($user) ? ($user['username'] ?? 'System') : 'System';

            // Business validations
            if (isset($data['ApplyType']) && !in_array($data['ApplyType'], ['Fixed','PerDay','Percentage'], true)) {
                return $this->fail('Invalid ApplyType', 400);
            }
            if ((isset($data['ApplyType']) ? $data['ApplyType'] : ($existing['ApplyType'] ?? null)) === 'Percentage') {
                $pct = isset($data['Amount']) ? (float)$data['Amount'] : (float)($existing['Amount'] ?? 0);
                if ($pct > 100) { return $this->fail('Percentage amount cannot exceed 100', 400); }
            }
            if (isset($data['GraceDays']) && $data['GraceDays'] < 0) {
                return $this->fail('GraceDays cannot be negative', 400);
            }

            $ok = $this->model->updatePolicy($id, $data);
            if ($ok) {
                $row = $this->model->getPolicyById($id);
                return $this->ok('Fine policy updated', $row);
            }
            return $this->fail('Failed to update policy', 500);

        } catch (\Exception $e) {
            error_log('FinePolicyController::update - ' . $e->getMessage());
            return $this->fail('Failed to update policy', 500);
        }
    }

    /** PATCH /api/fines/{id}/status */
    public function toggleStatus($params)
    {
        try {
            $id = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($id <= 0) return $this->fail('Invalid id', 400);
            $existing = $this->model->getPolicyById($id);
            if (!$existing) return $this->fail('Policy not found', 404);
            $input = $this->input();
            if (!isset($input['IsActive']) && !isset($input['isActive'])) {
                return $this->fail('isActive field is required', 400);
            }
            $raw = $input['IsActive'] ?? $input['isActive'];
            $val = is_string($raw) ? in_array(strtolower($raw), ['1','true','yes','on']) : (bool)$raw;

            $user = $this->currentUser(false);
            $ok = $this->model->updatePolicy($id, [
                'IsActive' => $val ? 1 : 0,
                'UpdatedBy' => is_array($user) ? ($user['username'] ?? 'System') : 'System'
            ]);
            if ($ok) {
                $row = $this->model->getPolicyById($id);
                return $this->ok('Fine policy status updated', $row);
            }
            return $this->fail('Failed to update policy status', 500);
        } catch (\Exception $e) {
            error_log('FinePolicyController::toggleStatus - ' . $e->getMessage());
            return $this->fail('Failed to update policy status', 500);
        }
    }

    /** DELETE /api/fines/{id} */
    public function delete($params)
    {
        try {
            $id = is_array($params) ? (int)($params['id'] ?? 0) : (int)$params;
            if ($id <= 0) return $this->fail('Invalid id', 400);
            $existing = $this->model->getPolicyById($id);
            if (!$existing) return $this->fail('Policy not found', 404);
            $ok = $this->model->deletePolicy($id);
            if ($ok) return $this->ok('Fine policy deleted', true);
            return $this->fail('Failed to delete policy', 500);
        } catch (\Exception $e) {
            error_log('FinePolicyController::delete - ' . $e->getMessage());
            return $this->fail('Failed to delete policy', 500);
        }
    }

    private function normalizeNumber($raw)
    {
        if ($raw === null || $raw === '') return 0;
        if (is_numeric($raw)) return (float)$raw;
        if (is_string($raw)) {
            $n = preg_replace('/[^0-9.\-]/', '', $raw);
            return $n === '' ? 0 : (float)$n;
        }
        return 0;
    }

    private function normalizeNullableNumber($raw)
    {
        if ($raw === null || $raw === '') return null;
        if (is_numeric($raw)) return (float)$raw;
        if (is_string($raw)) {
            $n = preg_replace('/[^0-9.\-]/', '', $raw);
            return $n === '' ? null : (float)$n;
        }
        return null;
    }
}
