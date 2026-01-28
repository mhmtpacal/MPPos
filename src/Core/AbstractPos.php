<?php
declare(strict_types=1);

namespace MPPos\Core;

abstract class AbstractPos
{
    protected array $payload = [];
    protected array $account = [];
    protected bool $test = false;

    protected ?array $lastResponse = null;

    // 🔒 State setter'lar sadece child tarafından çağrılır
    protected function setAccount(array $account): void
    {
        $this->account = $account;
    }

    protected function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    protected function setTest(bool $test): void
    {
        $this->test = $test;
    }
}
