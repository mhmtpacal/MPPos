<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PayloadMapperInterface
{

    public function payment(array $payload): array;
    public function cancel(array $payload): array;
    public function refund(array $payload): array;
    public function partialRefund(array $payload): array;
}
