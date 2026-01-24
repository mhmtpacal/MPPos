<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface BankClientInterface
{
    public function request(string $operation, array $payload): array;
}
