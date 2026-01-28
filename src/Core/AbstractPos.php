<?php
declare(strict_types=1);

namespace MPPos\Core;

use MPPos\Contracts\PosAdapterInterface;

abstract class AbstractPos implements PosAdapterInterface
{
    protected array $account = [];
    protected array $payload = [];
    protected bool $test = false;

    protected ?array $lastResponse = null;

    public function account(array $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function payload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function test(bool $test): static
    {
        $this->test = $test;
        return $this;
    }

    public function getResponse(): array
    {
        return $this->lastResponse ?? [
            'ok'        => false,
            'code'      => 'NO_REQUEST',
            'message'   => 'No transaction executed',
            'http_code' => 0,
            'type'      => null,
            'provider'  => null,
        ];
    }
}
