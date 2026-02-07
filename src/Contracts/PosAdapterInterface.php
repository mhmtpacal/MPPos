<?php
declare(strict_types=1);

namespace MPPos\Contracts;

interface PosAdapterInterface
{
    public function payment(): array;
    public function cancel(): void;
    public function refund(): void;
    public function partialRefund(): void;
    public function getResponse(): array;
}
