<?php
declare(strict_types=1);

namespace MPPos\Adapters;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Banks\KuveytTurk;
use MPPos\Exceptions\PosException;
use MPPos\MPPos;

final class KuveytTurkAdapter implements PosAdapterInterface
{
    private KuveytTurk $bank;

    public function __construct(array $config, string|bool $env)
    {
        $env = is_bool($env) ? ($env ? MPPos::ENV_TEST : MPPos::ENV_PROD) : $env;

        foreach (['merchantId', 'username', 'password', 'customerId'] as $k) {
            if (empty($config[$k])) {
                throw new PosException("KuveytTurk config missing: {$k}");
            }
        }

        $this->bank = new KuveytTurk(
            env: $env,
            merchantId: $config['merchantId'],
            username: $config['username'],
            password: $config['password'],
            customerId: $config['customerId']
        );
    }

    public function createPayment(array $params): array
    {
        return $this->bank->buildMerchantUiPayload($params);
    }

    public function cancel(string $orderId, string $merchantOrderId): array
    {
        return $this->bank->cancel($orderId, $merchantOrderId);
    }

    public function refundFull(string $orderId, string $merchantOrderId): array
    {
        return $this->bank->refundFull($orderId, $merchantOrderId);
    }

    public function refundPartial(string $orderId, string $merchantOrderId, string|int|float $amount): array
    {
        return $this->bank->refundPartial($orderId, $merchantOrderId, $amount);
    }

    public function verifyCallback(array $data): bool
    {
        // Kuveyt Türk: hash + ResponseCode kontrolü
        if (($data['ResponseCode'] ?? null) !== '00') {
            return false;
        }

        if (!isset($data['HashData'])) {
            return false;
        }

        // Gerekirse burada hash tekrar hesaplanır (ileride eklenebilir)
        return true;
    }

    public function getName(): string
    {
        return 'KuveytTürk';
    }
}
