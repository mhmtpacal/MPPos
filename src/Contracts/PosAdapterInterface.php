<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PosAdapterInterface
{
    /** @return $this */
    public function account(array $account): static;
    /** @return $this */
    public function payload(array $payload): static;
    /** @return $this */
    public function test(bool $test): static;
    public function payment(): array;
    public function cancel(): void;
    public function refund(): void;
    public function partialRefund(): void;
    public function getResponse(): array;
}
