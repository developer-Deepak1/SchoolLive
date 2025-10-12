<?php

namespace SchoolLive\DTO\FeeManagement;

class FeeRequestDto
{
    /** @param ClassSectionMappingDto[] $mappings */
    public function __construct(
        public readonly string $feeName,
        public readonly bool $isActive,
        public readonly ?FeeScheduleDto $schedule,
        public readonly array $mappings
    ) {}

    /**
     * @param array $payload
     */
    public static function fromArray(array $payload): self
    {
        $schedule = null;
        if (isset($payload['Schedule']) || isset($payload['schedule'])) {
            $schedule = FeeScheduleDto::fromArray($payload['Schedule'] ?? $payload['schedule']);
        }

        $mappings = [];
        $rawMappings = $payload['ClassSectionMapping'] ?? $payload['classSectionMapping'] ?? [];
        if (is_array($rawMappings)) {
            foreach ($rawMappings as $mapping) {
                if (is_array($mapping)) {
                    $mappings[] = ClassSectionMappingDto::fromArray($mapping);
                }
            }
        }

        $isActive = $payload['IsActive'] ?? $payload['isActive'] ?? true;
        if (is_string($isActive)) {
            $isActive = in_array(strtolower($isActive), ['true','1','yes','on'], true);
        }

        return new self(
            (string)($payload['FeeName'] ?? $payload['feeName'] ?? ''),
            (bool)$isActive,
            $schedule,
            $mappings
        );
    }

    public function toPersistenceArray(int $schoolId, int $academicYearId, string $username): array
    {
        $data = [
            'FeeName' => $this->feeName,
            'IsActive' => $this->isActive,
            'SchoolID' => $schoolId,
            'AcademicYearID' => $academicYearId,
            'CreatedBy' => $username,
        ];

        if ($this->schedule) {
            $schedule = $this->schedule->toArray();
            $data['Schedule'] = [
                'ScheduleType' => $schedule['scheduleType'] ?? 'OneTime',
                'IntervalMonths' => $schedule['intervalMonths'],
                'DayOfMonth' => $schedule['dayOfMonth'],
                'StartDate' => $schedule['startDate'],
                'EndDate' => $schedule['endDate'],
                'NextDueDate' => $schedule['nextDueDate'],
                'ReminderDaysBefore' => $schedule['reminderDaysBefore'],
            ];
        }

        if ($this->mappings) {
            $data['ClassSectionMapping'] = array_map(static function (ClassSectionMappingDto $mapping) {
                return [
                    'ClassID' => $mapping->classId,
                    'SectionID' => $mapping->sectionId,
                    'Amount' => $mapping->amount,
                    'MappingID' => $mapping->mappingId,
                    'IsActive' => $mapping->isActive ? 1 : 0,
                ];
            }, $this->mappings);
        }

        return $data;
    }
}
