<?php
declare(strict_types=1);

namespace MPPos\Contracts;

use MPPos\DTO\PosPayload;
use MPPos\DTO\FormPayload;

interface PosAdapterInterface
{
    public function createMerchantForm(PosPayload $payload): FormPayload;

    /**
     * Hosted/Token model: register -> token + redirectUrl
     * @return array{token:string, redirectUrl:string, raw:array}
     */
    public function registerToken(PosPayload $payload): array;

    public function cancel(string $orderId, string $merchantOrderId): array;
    public function refundFull(string $orderId, string $merchantOrderId): array;
    public function refundPartial(string $orderId, string $merchantOrderId, int $amountCents): array;

    public function verifyCallback(array $data): bool;
    public function getName(): string;
}
