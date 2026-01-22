<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Exceptions\PosException;

/**
 * Fluent builder for one-off calls like:
 *   $req = new MPPosRequest();
 *   $req->setBank = MPPos::VAKIF_KATILIM;
 *   $req->setPayload = [...];
 *   $req->setTest = true;
 *   $form = $req->createPayment();
 */
final class MPPosRequest
{
    private ?string $bank = null;
    private array $payload = [];
    private string|bool|null $env = null;

    public function setBank(string $bank): self
    {
        $this->bank = $bank;
        return $this;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Back-compat helper: true => test, false => prod.
     */
    public function setTest(bool $test): self
    {
        $this->env = $test;
        return $this;
    }

    public function setEnv(string $env): self
    {
        $this->env = $env;
        return $this;
    }

    public function createPayment(): array
    {
        if ($this->bank === null || $this->bank === '') {
            throw new PosException('Missing bank');
        }
        if ($this->env === null) {
            throw new PosException('Missing env/test');
        }

        return MPPos::createPayment($this->bank, $this->payload, $this->env);
    }

    public function cancel(): array
    {
        if ($this->bank === null || $this->bank === '') {
            throw new PosException('Missing bank');
        }
        if ($this->env === null) {
            throw new PosException('Missing env/test');
        }

        return MPPos::cancel($this->bank, $this->payload, $this->env);
    }

    public function refundFull(): array
    {
        if ($this->bank === null || $this->bank === '') {
            throw new PosException('Missing bank');
        }
        if ($this->env === null) {
            throw new PosException('Missing env/test');
        }

        return MPPos::refundFull($this->bank, $this->payload, $this->env);
    }

    public function refundPartial(): array
    {
        if ($this->bank === null || $this->bank === '') {
            throw new PosException('Missing bank');
        }
        if ($this->env === null) {
            throw new PosException('Missing env/test');
        }

        return MPPos::refundPartial($this->bank, $this->payload, $this->env);
    }

    /**
     * Allow property-style usage requested by user:
     *   $req->setBank = MPPos::VAKIF_KATILIM;
     *   $req->setPayload = [...];
     *   $req->setTest = true;
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'setBank', 'bank' => $this->setBank((string)$value),
            'setPayload', 'payload' => $this->setPayload((array)$value),
            'setTest', 'test' => $this->setTest((bool)$value),
            'setEnv', 'env' => $this->setEnv((string)$value),
            default => throw new PosException("Unknown property: {$name}"),
        };
    }
}
