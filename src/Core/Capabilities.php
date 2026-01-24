<?php
declare(strict_types=1);

namespace MPPos\Core;

final class Capabilities
{
    public function __construct(
        public readonly bool $merchantForm,
        public readonly bool $hostedForm,
        public readonly bool $partialRefund,
        public readonly bool $cancel
    ) {}
}
