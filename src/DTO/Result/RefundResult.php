<?php
declare(strict_types=1);

namespace MPPos\DTO\Result;

final class RefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $raw = []
    ) {}
}
