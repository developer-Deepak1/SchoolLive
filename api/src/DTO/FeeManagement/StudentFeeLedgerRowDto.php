<?php

namespace SchoolLive\DTO\FeeManagement;

class StudentFeeLedgerRowDto
{
    public function __construct(
        public readonly int $studentFeeId,
        public readonly int $studentId,
        public readonly int $feeId,
        public readonly string $feeName,
        public readonly ?int $mappingId,
        public readonly float $amount,
        public readonly float $fineAmount,
        public readonly float $discountAmount,
        public readonly float $amountPaid,
        public readonly ?string $dueDate,
        public readonly string $status,
        public readonly ?string $invoiceRef,
        public readonly ?string $remarks,
        public readonly ?float $computedFine,
        public readonly ?float $outstanding,
        public readonly ?string $collectedDate
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            (int)$row['StudentFeeID'],
            (int)$row['StudentID'],
            (int)$row['FeeID'],
            (string)($row['FeeName'] ?? ''),
            isset($row['MappingID']) ? (($row['MappingID'] === null || $row['MappingID'] === '') ? null : (int)$row['MappingID']) : null,
            (float)($row['Amount'] ?? 0),
            (float)($row['FineAmount'] ?? 0),
            (float)($row['DiscountAmount'] ?? 0),
            (float)($row['AmountPaid'] ?? 0),
            $row['DueDate'] ?? null,
            (string)($row['Status'] ?? ''),
            $row['InvoiceRef'] ?? null,
            $row['Remarks'] ?? null,
            isset($row['ComputedFine']) ? ($row['ComputedFine'] === null ? null : (float)$row['ComputedFine']) : null,
            isset($row['Outstanding']) ? ($row['Outstanding'] === null ? null : (float)$row['Outstanding']) : null,
            $row['CollectedDate'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'studentFeeId' => $this->studentFeeId,
            'studentId' => $this->studentId,
            'feeId' => $this->feeId,
            'feeName' => $this->feeName,
            'mappingId' => $this->mappingId,
            'amount' => $this->amount,
            'fineAmount' => $this->fineAmount,
            'discountAmount' => $this->discountAmount,
            'amountPaid' => $this->amountPaid,
            'dueDate' => $this->dueDate,
            'status' => $this->status,
            'invoiceRef' => $this->invoiceRef,
            'remarks' => $this->remarks,
            'computedFine' => $this->computedFine,
            'outstanding' => $this->outstanding,
            'collectedDate' => $this->collectedDate,
        ];
    }
}
