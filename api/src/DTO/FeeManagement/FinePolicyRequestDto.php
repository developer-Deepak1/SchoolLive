<?php

namespace SchoolLive\DTO\FeeManagement;

class FinePolicyRequestDto
{
    public function __construct(
        public readonly ?int $feeId,
        public readonly string $applyType,
        public readonly float $amount,
        public readonly int $graceDays,
        public readonly ?float $maxAmount,
        public readonly bool $isActive
    ) {}

    public static function fromArray(array $payload): self
    {
        $feeIdVal = $payload['FeeID'] ?? $payload['feeId'] ?? null;
        $applyType = $payload['ApplyType'] ?? $payload['applyType'] ?? 'Fixed';
        $amount = $payload['Amount'] ?? $payload['amount'] ?? 0;
        $graceDays = $payload['GraceDays'] ?? $payload['graceDays'] ?? 0;
        $maxAmount = $payload['MaxAmount'] ?? $payload['maxAmount'] ?? null;
        $isActive = $payload['IsActive'] ?? $payload['isActive'] ?? true;

        if (is_string($isActive)) {
            $isActive = in_array(strtolower($isActive), ['true','1','yes','on'], true);
        }

        return new self(
            $feeIdVal === null || $feeIdVal === '' ? null : (int)$feeIdVal,
            (string)$applyType,
            (float)$amount,
            (int)$graceDays,
            ($maxAmount === null || $maxAmount === '') ? null : (float)$maxAmount,
            (bool)$isActive
        );
    }

    public function toPersistenceArray(int $schoolId, int $academicYearId, string $username, bool $isCreate = true): array
    {
        $data = [
            'SchoolID' => $schoolId,
            'AcademicYearID' => $academicYearId,
            'FeeID' => $this->feeId,
            'ApplyType' => $this->applyType,
            'Amount' => $this->amount,
            'GraceDays' => $this->graceDays,
            'MaxAmount' => $this->maxAmount,
            'IsActive' => $this->isActive,
        ];

        if ($isCreate) {
            $data['CreatedBy'] = $username;
        } else {
            $data['UpdatedBy'] = $username;
        }

        return $data;
    }
}
