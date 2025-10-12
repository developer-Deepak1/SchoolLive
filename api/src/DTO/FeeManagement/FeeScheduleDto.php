<?php

namespace SchoolLive\DTO\FeeManagement;

class FeeScheduleDto
{
    public function __construct(
        public readonly ?int $scheduleId,
        public readonly ?int $feeId,
        public readonly string $scheduleType,
        public readonly ?int $intervalMonths,
        public readonly ?int $dayOfMonth,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?string $nextDueDate,
        public readonly ?int $reminderDaysBefore,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if (!$data) {
            return null;
        }
        $scheduleType = $data['ScheduleType'] ?? $data['scheduleType'] ?? 'OneTime';
        return new self(
            isset($data['ScheduleID']) ? (int)$data['ScheduleID'] : (isset($data['scheduleId']) ? (int)$data['scheduleId'] : null),
            isset($data['FeeID']) ? (int)$data['FeeID'] : (isset($data['feeId']) ? (int)$data['feeId'] : null),
            $scheduleType,
            isset($data['IntervalMonths']) ? self::toInt($data['IntervalMonths']) : (isset($data['intervalMonths']) ? self::toInt($data['intervalMonths']) : null),
            isset($data['DayOfMonth']) ? self::toInt($data['DayOfMonth']) : (isset($data['dayOfMonth']) ? self::toInt($data['dayOfMonth']) : null),
            self::toDateString($data['StartDate'] ?? $data['startDate'] ?? null),
            self::toDateString($data['EndDate'] ?? $data['endDate'] ?? null),
            self::toDateString($data['NextDueDate'] ?? $data['nextDueDate'] ?? null),
            isset($data['ReminderDaysBefore']) ? self::toInt($data['ReminderDaysBefore']) : (isset($data['reminderDaysBefore']) ? self::toInt($data['reminderDaysBefore']) : null),
            $data['CreatedAt'] ?? $data['createdAt'] ?? null,
            $data['UpdatedAt'] ?? $data['updatedAt'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'scheduleId' => $this->scheduleId,
            'feeId' => $this->feeId,
            'scheduleType' => $this->scheduleType,
            'intervalMonths' => $this->intervalMonths,
            'dayOfMonth' => $this->dayOfMonth,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'nextDueDate' => $this->nextDueDate,
            'reminderDaysBefore' => $this->reminderDaysBefore,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    private static function toInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private static function toDateString($value): ?string
    {
        if (!$value) {
            return null;
        }
        return (string)$value;
    }
}
