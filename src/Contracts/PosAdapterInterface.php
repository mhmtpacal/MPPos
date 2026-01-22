<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PosAdapterInterface
{
    /**
     * Ödeme başlat (Hosted veya Merchant UI)
     */
    public function createPayment(array $params): array;

    /**
     * Banka callback doğrulama
     */
    public function verifyCallback(array $data): bool;

    /**
     * Banka adı (log/debug için)
     */
    public function getName(): string;
}
