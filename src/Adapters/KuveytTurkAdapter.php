<?php
declare(strict_types=1);

namespace MPPos\Adapters;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Banks\KuveytTurk;
use MPPos\Exceptions\PosException;

final class KuveytTurkAdapter implements PosAdapterInterface
{
    private KuveytTurk $bank;

    public function __construct(array $config, string $env)
    {
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
