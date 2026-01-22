<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PosAdapterInterface
{
    /**
     * Start payment (Hosted or Merchant UI)
     */
    public function createPayment(array $params): array;

    /**
     * Cancel/void a sale
     */
    public function cancel(string $orderId, string $merchantOrderId): array;

    /**
     * Full refund
     */
    public function refundFull(string $orderId, string $merchantOrderId): array;

    /**
     * Partial refund
     */
    public function refundPartial(string $orderId, string $merchantOrderId, string|int|float $amount): array;

    /**
     * Verify callback payload
     */
    public function verifyCallback(array $data): bool;

    /**
     * Bank name (for logging/debug)
     */
    public function getName(): string;
}