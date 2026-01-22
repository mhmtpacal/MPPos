<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Adapters\KuveytTurkAdapter;
use MPPos\Contracts\PosAdapterInterface;
use MPPos\DTO\PosPayload;
use MPPos\Exceptions\PosException;
use MPPos\Support\Amount;

final class RequestBuilder
{
    private string $bankKey = '';
    private string $env = MPPos::ENV_PROD;

    /** @var array<string,mixed> */
    private array $payload = [];

    public function setBank(string $bankKey): self
    {
        $this->bankKey = $bankKey;
        return $this;
    }

    public function setEnv(string $env): self
    {
        $this->env = $env;
        return $this;
    }

    /** @param array<string,mixed> $payload */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Merchant UI: bankaya POST edilecek form payload (action+hidden fields)
     * @return array{action:string, method:string, fields:array<string,string>}
     */
    public function createPayment(): array
    {
        $adapter = $this->makeAdapter();

        [$config, $domain] = $this->splitConfigAndDomain($this->payload);

        $dto = $this->toDomainPayload($domain);
        $form = $adapter->createMerchantForm($dto);

        return [
            'action' => $form->action,
            'method' => $form->method,
            'fields' => $form->fields,
        ];
    }

    /**
     * Hosted/Token flow
     * @return array{token:string, redirectUrl:string, raw:array}
     */
    public function registerToken(): array
    {
        $adapter = $this->makeAdapter();
        [, $domain] = $this->splitConfigAndDomain($this->payload);

        $dto = $this->toDomainPayload($domain);
        return $adapter->registerToken($dto);
    }

    public function cancel(string $orderId, string $merchantOrderId): array
    {
        return $this->makeAdapter()->cancel($orderId, $merchantOrderId);
    }

    public function refundFull(string $orderId, string $merchantOrderId): array
    {
        return $this->makeAdapter()->refundFull($orderId, $merchantOrderId);
    }

    public function refundPartial(string $orderId, string $merchantOrderId, int|string|float $amount): array
    {
        return $this->makeAdapter()->refundPartial($orderId, $merchantOrderId, Amount::toCents($amount));
    }

    public function verifyCallback(array $data): bool
    {
        return $this->makeAdapter()->verifyCallback($data);
    }

    private function makeAdapter(): PosAdapterInterface
    {
        if ($this->bankKey === '') {
            throw new PosException('Bank is not set');
        }

        [$config] = $this->splitConfigAndDomain($this->payload);

        return match ($this->bankKey) {
            MPPos::KUVEYT_TURK   => new KuveytTurkAdapter($config, $this->env),
            MPPos::VAKIF_KATILIM => new VakifKatilimAdapter($config, $this->env),
            default => throw new PosException("Unknown bank: {$this->bankKey}")
        };
    }

    /**
     * Payload içinde config + domain karışmasın diye ayırıyoruz.
     * config: merchantId, customerId, storeKey, userName, password...
     * domain: orderId, amount, successUrl, failUrl, email, ip...
     *
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function splitConfigAndDomain(array $p): array
    {
        $configKeys = [
            'merchantId','customerId','storeKey','userName','username','password',
        ];

        $config = [];
        $domain = [];

        foreach ($p as $k => $v) {
            if (in_array($k, $configKeys, true)) $config[$k] = $v;
            else $domain[$k] = $v;
        }

        return [$config, $domain];
    }

    /** @param array<string,mixed> $d */
    private function toDomainPayload(array $d): PosPayload
    {
        $dto = new PosPayload();

        $dto->orderId     = (string)($d['orderId'] ?? $d['merchantOrderId'] ?? '');
        $dto->amount      = Amount::toCents($d['amount'] ?? 0);
        $dto->successUrl  = (string)($d['successUrl'] ?? '');
        $dto->failUrl     = (string)($d['failUrl'] ?? '');
        $dto->email       = (string)($d['email'] ?? '');
        $dto->ip          = (string)($d['ip'] ?? $d['cardHolderIp'] ?? '');

        $dto->currencyCode = isset($d['currencyCode']) ? (string)$d['currencyCode'] : null;
        $dto->language     = isset($d['language']) ? (string)$d['language'] : null;

        $dto->installmentCount = isset($d['installmentCount']) ? (int)$d['installmentCount'] : null;
        $dto->deferringCount   = isset($d['deferringCount']) ? (int)$d['deferringCount'] : null;

        $dto->validate();
        return $dto;
    }
}
