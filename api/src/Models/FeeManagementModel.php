<?php

namespace SchoolLive\Models;

use SchoolLive\DTO\FeeManagement\ClassSectionMappingDto;
use SchoolLive\DTO\FeeManagement\FeeDto;
use SchoolLive\DTO\FeeManagement\FeeRequestDto;
use SchoolLive\DTO\FeeManagement\FeeScheduleDto;
use SchoolLive\DTO\FeeManagement\FinePolicyDto;
use SchoolLive\DTO\FeeManagement\FinePolicyRequestDto;
use SchoolLive\DTO\FeeManagement\PaymentRequestDto;
use SchoolLive\DTO\FeeManagement\PaymentResponseDto;
use SchoolLive\DTO\FeeManagement\StudentFeeLedgerRowDto;

class FeeManagementModel extends Model
{
    private FeeModel $fees;
    private FinePolicyModel $finePolicies;
    private StudentFeesModel $studentFees;

    public function __construct()
    {
        parent::__construct();
        $this->fees = new FeeModel();
        $this->finePolicies = new FinePolicyModel();
        $this->studentFees = new StudentFeesModel();
    }

    /** @return FeeDto[] */
    public function listFees(int $schoolId, int $academicYearId): array
    {
        $rows = $this->fees->getAllFeesWithMappings($schoolId, $academicYearId);
        return array_map(static fn(array $row) => FeeDto::fromStructuredArray($row), $rows);
    }

    public function getFee(int $feeId): ?FeeDto
    {
        $rows = $this->fees->getFeeWithMappings($feeId);
        if (empty($rows)) {
            return null;
        }
        return FeeDto::fromStructuredArray($rows[0]);
    }

    public function createFee(FeeRequestDto $request, int $schoolId, int $academicYearId, string $username): FeeDto
    {
        $payload = $request->toPersistenceArray($schoolId, $academicYearId, $username);
        $feeId = $this->fees->createFee($payload);
        $fee = $this->getFee((int)$feeId);
        if (!$fee) {
            throw new \RuntimeException('Failed to create fee');
        }
        return $fee;
    }

    public function updateFee(int $feeId, FeeRequestDto $request, string $username): FeeDto
    {
        $existing = $this->fees->getFeeById($feeId);
        if (!$existing) {
            throw new \InvalidArgumentException('Fee not found');
        }

        $payload = $request->toPersistenceArray((int)$existing['SchoolID'], (int)$existing['AcademicYearID'], $existing['CreatedBy'] ?? $username);
        $payload['UpdatedBy'] = $username;
        $this->fees->updateFee($feeId, $payload);
        $fee = $this->getFee($feeId);
        if (!$fee) {
            throw new \RuntimeException('Failed to reload fee');
        }
        return $fee;
    }

    public function toggleFeeStatus(int $feeId, bool $isActive, string $username): FeeDto
    {
        $existing = $this->fees->getFeeById($feeId);
        if (!$existing) {
            throw new \InvalidArgumentException('Fee not found');
        }

        $this->fees->updateFee($feeId, [
            'IsActive' => $isActive,
            'UpdatedBy' => $username,
        ]);

        $fee = $this->getFee($feeId);
        if (!$fee) {
            throw new \RuntimeException('Failed to reload fee');
        }
        return $fee;
    }

    /** @return FinePolicyDto[] */
    public function listFinePolicies(int $schoolId, int $academicYearId): array
    {
        $rows = $this->finePolicies->getPolicies($schoolId, $academicYearId);
        return array_map(static fn(array $row) => FinePolicyDto::fromArray($row), $rows);
    }

    public function createFinePolicy(FinePolicyRequestDto $request, int $schoolId, int $academicYearId, string $username): FinePolicyDto
    {
        $payload = $request->toPersistenceArray($schoolId, $academicYearId, $username, true);
        $id = $this->finePolicies->createPolicy($payload);
        $row = $this->finePolicies->getPolicyById((int)$id);
        if (!$row) {
            throw new \RuntimeException('Failed to create fine policy');
        }
        return FinePolicyDto::fromArray($row);
    }

    public function updateFinePolicy(int $finePolicyId, FinePolicyRequestDto $request, string $username): FinePolicyDto
    {
        $existing = $this->finePolicies->getPolicyById($finePolicyId);
        if (!$existing) {
            throw new \InvalidArgumentException('Fine policy not found');
        }

        $payload = $request->toPersistenceArray((int)$existing['SchoolID'], (int)$existing['AcademicYearID'], $username, false);
        $this->finePolicies->updatePolicy($finePolicyId, $payload);
        $row = $this->finePolicies->getPolicyById($finePolicyId);
        if (!$row) {
            throw new \RuntimeException('Failed to reload fine policy');
        }
        return FinePolicyDto::fromArray($row);
    }

    public function deleteFinePolicy(int $finePolicyId): bool
    {
        return $this->finePolicies->deletePolicy($finePolicyId);
    }

    /** @return PaymentResponseDto[] */
    public function recordPayments(array $payments, string $username): array
    {
        $responses = [];
        foreach ($payments as $payment) {
            if (!$payment instanceof PaymentRequestDto) {
                continue;
            }
            $ok = $this->studentFees->recordPayment(
                $payment->studentFeeId,
                $payment->paidAmount,
                $payment->mode,
                $payment->transactionRef,
                $payment->paymentDate,
                $payment->discountDelta,
                $username
            );
            $responses[] = new PaymentResponseDto($payment->studentFeeId, (bool)$ok);
        }
        return $responses;
    }

    /** @return StudentFeeLedgerRowDto[] */
    public function getStudentLedger(int $schoolId, int $academicYearId, int $studentId, array $filters): array
    {
        $rows = $this->studentFees->getStudentLedger($schoolId, $academicYearId, $studentId, $filters);
        return array_map(static fn(array $row) => StudentFeeLedgerRowDto::fromArray($row), $rows);
    }

    /** @return StudentFeeLedgerRowDto[] */
    public function getStudentDues(int $schoolId, int $academicYearId, int $studentId): array
    {
        return $this->getStudentLedger($schoolId, $academicYearId, $studentId, ['only_due' => true, 'include_paid' => false]);
    }

    /** @return StudentFeeLedgerRowDto[] */
    public function getMonthlyPlan(int $schoolId, int $academicYearId, int $studentId, int $year, int $month): array
    {
        $rows = $this->studentFees->getMonthlyPlan($schoolId, $academicYearId, $studentId, $year, $month);
        return array_map(static fn(array $row) => StudentFeeLedgerRowDto::fromArray($row), $rows);
    }

    public function ensureMonthlyRows(int $schoolId, int $academicYearId, int $studentId, array $items, string $username): array
    {
        $results = [];
        foreach ($items as $item) {
            $feeId = (int)($item['FeeID'] ?? $item['feeId'] ?? 0);
            $year = (int)($item['Year'] ?? $item['year'] ?? date('Y'));
            $month = (int)($item['Month'] ?? $item['month'] ?? date('n'));
            if ($feeId <= 0 || $month < 1 || $month > 12) {
                continue;
            }
            $id = $this->studentFees->ensureMonthlyRow($schoolId, $academicYearId, $studentId, $feeId, $year, $month, $username);
            $results[] = [
                'feeId' => $feeId,
                'studentFeeId' => $id,
                'year' => $year,
                'month' => $month,
            ];
        }
        return $results;
    }

    public function getStudentFeePlan(int $schoolId, int $academicYearId, int $studentId, ?\DateTimeInterface $referenceDate = null): array
    {
        $reference = $referenceDate
            ? \DateTimeImmutable::createFromInterface($referenceDate)
            : new \DateTimeImmutable('first day of this month');

        $monthStart = $reference->modify('first day of this month');
        $monthEnd = $reference->modify('last day of this month');

        $ledger = $this->getStudentLedger($schoolId, $academicYearId, $studentId, ['only_due' => true, 'include_paid' => false]);

        $current = [];
        $upcoming = [];

        foreach ($ledger as $row) {
            if (!$row instanceof StudentFeeLedgerRowDto) {
                continue;
            }

            $dueDate = $row->dueDate;
            if ($dueDate) {
                try {
                    $due = new \DateTimeImmutable($dueDate);
                    if ($due > $monthEnd) {
                        $upcoming[] = $row;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // fall through to treat as current
                }
            }
            $current[] = $row;
        }

        $sumOutstanding = static function (array $items): float {
            $total = 0.0;
            foreach ($items as $item) {
                if ($item instanceof StudentFeeLedgerRowDto) {
                    $total += max(0.0, (float)($item->outstanding ?? 0.0));
                }
            }
            return round($total, 2);
        };

        $calendar = new AcademicCalendarModel();
        $range = $calendar->getAcademicYearRange($academicYearId, $schoolId) ?? [];

        return [
            'meta' => [
                'referenceYear' => (int)$monthStart->format('Y'),
                'referenceMonth' => (int)$monthStart->format('n'),
                'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'academicYearStart' => $range['start'] ?? null,
                'academicYearEnd' => $range['end'] ?? null,
            ],
            'summary' => [
                'currentCount' => count($current),
                'upcomingCount' => count($upcoming),
                'currentOutstanding' => $sumOutstanding($current),
                'upcomingOutstanding' => $sumOutstanding($upcoming),
            ],
            'current' => array_map(static fn(StudentFeeLedgerRowDto $row) => $row->toArray(), $current),
            'upcoming' => array_map(static fn(StudentFeeLedgerRowDto $row) => $row->toArray(), $upcoming),
        ];
    }

    public function assignStudentFee(int $schoolId, int $academicYearId, int $studentId, array $payload, string $username): int
    {
        $feeId = (int)($payload['FeeID'] ?? $payload['feeId'] ?? 0);
        if ($feeId <= 0) {
            throw new \InvalidArgumentException('FeeID is required');
        }
        $classId = isset($payload['ClassID']) ? (int)$payload['ClassID'] : (isset($payload['classId']) ? (int)$payload['classId'] : null);
        $sectionId = isset($payload['SectionID']) ? (int)$payload['SectionID'] : (isset($payload['sectionId']) ? (int)$payload['sectionId'] : null);
        $amount = isset($payload['Amount']) ? ($payload['Amount'] === '' ? null : (float)$payload['Amount']) : (isset($payload['amount']) ? ($payload['amount'] === '' ? null : (float)$payload['amount']) : null);
        $mappingId = isset($payload['MappingID']) ? (int)$payload['MappingID'] : (isset($payload['mappingId']) ? (int)$payload['mappingId'] : null);
        $dueDate = $payload['DueDate'] ?? $payload['dueDate'] ?? null;

        return (int)$this->studentFees->assignFee($schoolId, $academicYearId, $studentId, $feeId, $classId, $sectionId, $dueDate, $amount, $mappingId, $username);
    }
}
