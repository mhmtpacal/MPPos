<?php
declare(strict_types=1);

namespace MPPos\DTO;

final class FormPayload
{
    public function __construct(
        public string $action,
        public string $method,
        /** @var array<string,string> */
        public array $fields
    ) {}
}
