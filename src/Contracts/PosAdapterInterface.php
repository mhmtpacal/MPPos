<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PosAdapterInterface
{
    public function account(array $account): static;
    public function payload(array $payload): static;
    public function test(bool $test): static;
    public function payment(): void;
    public function cancel(): void;
    public function refund(): void;
    public function partialRefund(): void;
    public function getResponse(): array;
}
