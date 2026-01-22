<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface BankClientInterface
{
    public function securePaymentRegister(array $bankPayload): array;
    public function saleReversal(array $bankPayload): array;
}
