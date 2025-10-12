<?php

namespace SchoolLive\DTO\FeeManagement;

class PaymentResponseDto
{
    public function __construct(
        public readonly int $studentFeeId,
        public readonly bool $success
    ) {}

    public function toArray(): array
    {
        return [
            'studentFeeId' => $this->studentFeeId,
            // Frontend expects 'ok' in batch responses; keep 'studentFeeId' for compatibility
            'ok' => $this->success,
        ];
    }
}
