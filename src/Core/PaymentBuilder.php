<?php
declare(strict_types=1);

namespace MPPos\Core;

use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Result\PaymentResult;
use MPPos\Exceptions\PosException;

use MPPos\Logging\FileLogger;
use MPPos\Logging\NullLogger;
use MPPos\Logging\PosLoggerInterface;


final class PaymentBuilder
{
    private ?string $bank = null;
    private ?string $env = null;
    private array $payload = [];
    private array $bankConfig = [];

    public function setBank(string $bank): self
    {
        $this->bank = $bank;
        return $this;
    }

    public function setEnv(string $env): self
    {
        $this->env = $env;
        return $this;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function setBankConfig(array $config): self
    {
        $this->bankConfig = $config;
        return $this;
    }

    private bool $logEnabled = false;

    public function setLog(bool $enabled): self
    {
        $this->logEnabled = $enabled;
        return $this;
    }

    public function execute(): PaymentResult
    {
        if (!$this->bank || !$this->env) {
            throw new PosException('Bank or env not set');
        }

        $logger = $this->logEnabled
            ? new FileLogger(__DIR__ . '/../../logs')
            : new NullLogger();

        $manager = new PosManager($logger);

        return $manager->pay(
            $this->bank,
            $this->env,
            new PaymentPayload($this->payload),
            $this->bankConfig
        );
    }

}
