<?php
declare(strict_types=1);

namespace MPPos\Core;

use MPPos\Contracts\PosAdapterInterface;

abstract class AbstractPos implements PosAdapterInterface
{
    protected array $payload = [];
    protected array $account = [];
    protected bool $test = false;

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
}
