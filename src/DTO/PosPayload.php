<?php
declare(strict_types=1);

namespace MPPos\DTO;

use MPPos\Exceptions\ValidationException;

final class PosPayload
{
    public string $orderId;
    public int $amount;
    public string $successUrl;
    public string $failUrl;
    public string $email;
    public string $ip;
    public ?string $currencyCode = null;
    public ?string $language = null;
    public ?int $installmentCount = null;
    public ?int $deferringCount = null;

    public function validate(): void
    {
        foreach (['orderId', 'successUrl', 'failUrl', 'email', 'ip'] as $k) {
            if (empty($this->{$k})) {
                throw new ValidationException("Missing parameter: {$k}");
            }
        }

        if ($this->amount <= 0) {
            throw new ValidationException('Amount must be > 0');
        }

        if ($this->installmentCount !== null && $this->deferringCount !== null) {
            throw new ValidationException('installmentCount and deferringCount cannot be sent together');
        }
    }
}
