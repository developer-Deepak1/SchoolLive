<?php

namespace SchoolLive\DTO\FeeManagement;

class ClassSectionMappingDto
{
    public function __construct(
        public readonly ?int $mappingId,
        public readonly int $feeId,
        public readonly int $classId,
        public readonly int $sectionId,
        public readonly ?float $amount,
        public readonly bool $isActive
    ) {}

    public static function fromArray(array $data): self
    {
        $amountRaw = $data['Amount'] ?? $data['amount'] ?? null;
        return new self(
            isset($data['MappingID']) ? (int)$data['MappingID'] : (isset($data['mappingId']) ? (int)$data['mappingId'] : null),
            (int)($data['FeeID'] ?? $data['feeId'] ?? 0),
            (int)($data['ClassID'] ?? $data['classId'] ?? 0),
            (int)($data['SectionID'] ?? $data['sectionId'] ?? 0),
            $amountRaw === null || $amountRaw === '' ? null : (float)$amountRaw,
            isset($data['IsActive']) ? ((int)$data['IsActive'] === 1) : (isset($data['isActive']) ? (bool)$data['isActive'] : true)
        );
    }

    public function toArray(): array
    {
        return [
            'mappingId' => $this->mappingId,
            'feeId' => $this->feeId,
            'classId' => $this->classId,
            'sectionId' => $this->sectionId,
            'amount' => $this->amount,
            'isActive' => $this->isActive,
        ];
    }
}
