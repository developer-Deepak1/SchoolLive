<?php

namespace SchoolLive\DTO\FeeManagement;

class PaymentRequestDto
{
    public function __construct(
        public readonly int $studentFeeId,
        public readonly float $paidAmount,
        public readonly string $mode,
        public readonly ?string $transactionRef,
        public readonly ?string $paymentDate,
        public readonly ?float $discountDelta
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            (int)($payload['StudentFeeID'] ?? $payload['studentFeeId'] ?? 0),
            (float)($payload['PaidAmount'] ?? $payload['paidAmount'] ?? 0),
            (string)($payload['Mode'] ?? $payload['mode'] ?? ''),
            $payload['TransactionRef'] ?? $payload['transactionRef'] ?? null,
            $payload['PaymentDate'] ?? $payload['paymentDate'] ?? null,
            isset($payload['DiscountDelta']) || isset($payload['discountDelta'])
                ? (float)($payload['DiscountDelta'] ?? $payload['discountDelta'])
                : null
        );
    }
}
