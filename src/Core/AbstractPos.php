<?php
declare(strict_types=1);

namespace MPPos\Core;

abstract class AbstractPos
{
    protected array $payload = [];
    protected array $account = [];
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
}
