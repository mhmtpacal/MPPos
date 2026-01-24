<?php
declare(strict_types=1);

namespace MPPos\DTO\Form;

final class FormPayload
{
    public function __construct(
        public readonly string $action,
        public readonly string $method,
        public readonly array $hidden,
        public readonly array $cardSchema
    ) {}
}
