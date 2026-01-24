<?php
declare(strict_types=1);

namespace MPPos\DTO\Result;

use MPPos\DTO\Form\FormPayload;

final class PaymentResult
{
    public function __construct(
        public readonly bool $redirectRequired,
        public readonly ?string $redirectUrl = null,
        public readonly ?FormPayload $form = null,
        public readonly ?string $html = null,
        public readonly array $raw = []
    ) {}
}
