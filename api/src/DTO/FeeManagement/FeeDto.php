<?php

namespace SchoolLive\DTO\FeeManagement;

class FeeDto
{
    /** @param ClassSectionMappingDto[] $mappings */
    public function __construct(
        public readonly int $feeId,
        public readonly string $feeName,
        public readonly bool $isActive,
        public readonly int $schoolId,
        public readonly int $academicYearId,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $createdBy,
        public readonly ?string $updatedBy,
        public readonly ?FeeScheduleDto $schedule,
        public readonly array $mappings
    ) {}

    /**
     * @param array{Fee: array, Schedule?: ?array, ClassSectionMapping?: array} $row
     */
    public static function fromStructuredArray(array $row): self
    {
        $fee = $row['Fee'];
        $schedule = FeeScheduleDto::fromArray($row['Schedule'] ?? null);
        $mappingsRaw = $row['ClassSectionMapping'] ?? [];
        $mappings = [];
        foreach ($mappingsRaw as $mapping) {
            $mappings[] = ClassSectionMappingDto::fromArray($mapping);
        }

        return new self(
            (int)$fee['FeeID'],
            (string)$fee['FeeName'],
            (bool)$fee['IsActive'],
            (int)$fee['SchoolID'],
            (int)$fee['AcademicYearID'],
            $fee['CreatedAt'] ?? null,
            $fee['UpdatedAt'] ?? null,
            $fee['CreatedBy'] ?? null,
            $fee['UpdatedBy'] ?? null,
            $schedule,
            $mappings
        );
    }

    public function toArray(): array
    {
        return [
            'feeId' => $this->feeId,
            'feeName' => $this->feeName,
            'isActive' => $this->isActive,
            'schoolId' => $this->schoolId,
            'academicYearId' => $this->academicYearId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'schedule' => $this->schedule?->toArray(),
            'classSectionMappings' => array_map(static fn(ClassSectionMappingDto $m) => $m->toArray(), $this->mappings),
        ];
    }
}
