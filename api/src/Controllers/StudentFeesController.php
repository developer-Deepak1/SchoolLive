<?php

namespace SchoolLive\Controllers;

use SchoolLive\Core\BaseController;
use SchoolLive\Models\StudentFeesModel;

class StudentFeesController extends BaseController
{
    private StudentFeesModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new StudentFeesModel();
    }

    /** GET /api/fees/student/{id}/monthly?year=YYYY&month=MM */
    public function monthly($params)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $studentId = (int)($params['id'] ?? 0);
            if ($studentId <= 0) return $this->fail('Invalid student id', 400);
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
            if ($month < 1 || $month > 12) return $this->fail('Invalid month', 400);
            $rows = $this->model->getMonthlyPlan((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $year, $month);
            return $this->ok('Monthly plan fetched', $rows);
        } catch (\Exception $e) {
            error_log('StudentFeesController::monthly - ' . $e->getMessage());
            return $this->fail('Failed to fetch monthly plan', 500);
        }
    }

    /** POST /api/fees/student/{id}/monthly/ensure JSON: { FeeID, Year, Month } or [ {...}, {...} ] */
    public function ensureMonthly($params)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $studentId = (int)($params['id'] ?? 0);
            if ($studentId <= 0) return $this->fail('Invalid student id', 400);
            $in = $this->input();
            // Batch: if input is a list of items
            if (is_array($in) && isset($in[0]) && is_array($in[0])) {
                $out = [];
                foreach ($in as $item) {
                    $feeId = (int)($item['FeeID'] ?? 0);
                    $year = (int)($item['Year'] ?? date('Y'));
                    $month = (int)($item['Month'] ?? date('n'));
                    if ($feeId > 0 && $month >= 1 && $month <= 12) {
                        $sid = $this->model->ensureMonthlyRow((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $feeId, $year, $month, $user['username'] ?? 'System');
                        $out[] = ['FeeID' => $feeId, 'StudentFeeID' => $sid, 'Year' => $year, 'Month' => $month];
                    }
                }
                return $this->ok('Monthly rows ensured', ['items' => $out]);
            }
            // Single payload
            $feeId = (int)($in['FeeID'] ?? 0);
            $year = (int)($in['Year'] ?? date('Y'));
            $month = (int)($in['Month'] ?? date('n'));
            if ($feeId <= 0 || $month < 1 || $month > 12) return $this->fail('Invalid payload', 400);
            $sid = $this->model->ensureMonthlyRow((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $feeId, $year, $month, $user['username'] ?? 'System');
            return $this->ok('Monthly row ensured', ['StudentFeeID' => $sid]);
        } catch (\Exception $e) {
            error_log('StudentFeesController::ensureMonthly - ' . $e->getMessage());
            return $this->fail('Failed to ensure monthly row', 500);
        }
    }
    /** GET /api/fees/student/{id}/ledger?only_due=0&include_paid=1 */
    public function ledger($params)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $studentId = (int)($params['id'] ?? 0);
            if ($studentId <= 0) return $this->fail('Invalid student id', 400);
            $filters = [
                'only_due' => isset($_GET['only_due']) ? (bool)$_GET['only_due'] : false,
                'include_paid' => isset($_GET['include_paid']) ? (bool)$_GET['include_paid'] : true,
            ];
            $rows = $this->model->getStudentLedger((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $filters);
            return $this->ok('Ledger fetched', $rows);
        } catch (\Exception $e) {
            error_log('StudentFeesController::ledger - ' . $e->getMessage());
            return $this->fail('Failed to fetch ledger', 500);
        }
    }

    /** GET /api/fees/student/{id}/dues */
    public function dues($params)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $studentId = (int)($params['id'] ?? 0);
            if ($studentId <= 0) return $this->fail('Invalid student id', 400);
            $rows = $this->model->getStudentLedger((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, ['only_due' => true, 'include_paid' => false]);
            return $this->ok('Dues fetched', $rows);
        } catch (\Exception $e) {
            error_log('StudentFeesController::dues - ' . $e->getMessage());
            return $this->fail('Failed to fetch dues', 500);
        }
    }

    /** POST /api/fees/payments  JSON: { StudentFeeID, PaidAmount, Mode, TransactionRef?, PaymentDate?, DiscountDelta? } or [ {...}, {...} ] */
    public function createPayment()
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $in = $this->input();
            // Batch: if input is a list of payments
            if (is_array($in) && isset($in[0]) && is_array($in[0])) {
                $results = [];
                foreach ($in as $p) {
                    $id = (int)($p['StudentFeeID'] ?? 0);
                    $paid = isset($p['PaidAmount']) ? (float)$p['PaidAmount'] : 0.0;
                    $mode = $p['Mode'] ?? null;
                    $ref = $p['TransactionRef'] ?? null;
                    $pdate = $p['PaymentDate'] ?? null;
                    $discountDelta = array_key_exists('DiscountDelta', $p) ? (float)$p['DiscountDelta'] : null;
                    if ($id > 0 && $paid > 0 && $mode) {
                        $ok = $this->model->recordPayment($id, $paid, $mode, $ref, $pdate, $discountDelta, $user['username'] ?? 'System');
                        $results[] = ['StudentFeeID' => $id, 'ok' => (bool)$ok];
                    } else {
                        $results[] = ['StudentFeeID' => $id, 'ok' => false];
                    }
                }
                return $this->ok('Payments processed', ['items' => $results]);
            }
            $id = (int)($in['StudentFeeID'] ?? 0);
            $paid = isset($in['PaidAmount']) ? (float)$in['PaidAmount'] : 0.0;
            $mode = $in['Mode'] ?? null;
            $ref = $in['TransactionRef'] ?? null;
            $pdate = $in['PaymentDate'] ?? null;
            $discountDelta = array_key_exists('DiscountDelta', $in) ? (float)$in['DiscountDelta'] : null;
            if ($id <= 0 || $paid <= 0 || !$mode) return $this->fail('Invalid payload', 400);

            $ok = $this->model->recordPayment($id, $paid, $mode, $ref, $pdate, $discountDelta, $user['username'] ?? 'System');
            if ($ok) return $this->ok('Payment recorded', true);
            return $this->fail('Failed to record payment', 500);
        } catch (\Exception $e) {
            error_log('StudentFeesController::createPayment - ' . $e->getMessage());
            return $this->fail('Failed to record payment', 500);
        }
    }

    /** POST /api/fees/student/{id}/assign  JSON: { FeeID, ClassID?, SectionID?, Amount?, MappingID?, DueDate? } */
    public function assign($params)
    {
        try {
            $user = $this->currentUser();
            if (!$user) return;
            $studentId = (int)($params['id'] ?? 0);
            if ($studentId <= 0) return $this->fail('Invalid student id', 400);
            $in = $this->input();
            $feeId = (int)($in['FeeID'] ?? 0);
            if ($feeId <= 0) return $this->fail('FeeID is required', 400);
            $classId = isset($in['ClassID']) ? (int)$in['ClassID'] : null;
            $sectionId = isset($in['SectionID']) ? (int)$in['SectionID'] : null;
            $amount = isset($in['Amount']) && $in['Amount'] !== '' ? (float)$in['Amount'] : null;
            $mappingId = isset($in['MappingID']) ? (int)$in['MappingID'] : null;
            $dueDate = $in['DueDate'] ?? null;

            $newId = $this->model->assignFee((int)($user['school_id'] ?? 1), (int)($user['academic_year_id'] ?? 1), $studentId, $feeId, $classId, $sectionId, $dueDate, $amount, $mappingId, $user['username'] ?? 'System');
            return $this->ok('Fee assigned', ['StudentFeeID' => $newId], 201);
        } catch (\Exception $e) {
            error_log('StudentFeesController::assign - ' . $e->getMessage());
            return $this->fail('Failed to assign fee', 500);
        }
    }
}
