<?php

namespace SchoolLive\DTO\FeeManagement;

class FinePolicyDto
{
    public function __construct(
        public readonly int $finePolicyId,
        public readonly int $schoolId,
        public readonly int $academicYearId,
        public readonly ?int $feeId,
        public readonly string $applyType,
        public readonly float $amount,
        public readonly int $graceDays,
        public readonly ?float $maxAmount,
        public readonly bool $isActive,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $createdBy,
        public readonly ?string $updatedBy
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            (int)$row['FinePolicyID'],
            (int)$row['SchoolID'],
            (int)$row['AcademicYearID'],
            isset($row['FeeID']) ? (($row['FeeID'] === null || $row['FeeID'] === '') ? null : (int)$row['FeeID']) : null,
            (string)$row['ApplyType'],
            (float)$row['Amount'],
            (int)$row['GraceDays'],
            isset($row['MaxAmount']) && $row['MaxAmount'] !== null ? (float)$row['MaxAmount'] : null,
            (bool)$row['IsActive'],
            $row['CreatedAt'] ?? null,
            $row['UpdatedAt'] ?? null,
            $row['CreatedBy'] ?? null,
            $row['UpdatedBy'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'finePolicyId' => $this->finePolicyId,
            'schoolId' => $this->schoolId,
            'academicYearId' => $this->academicYearId,
            'feeId' => $this->feeId,
            'applyType' => $this->applyType,
            'amount' => $this->amount,
            'graceDays' => $this->graceDays,
            'maxAmount' => $this->maxAmount,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
        ];
    }
}
