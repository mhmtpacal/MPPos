<?php
declare(strict_types=1);

namespace MPPos\Adapters;

use MPPos\Banks\VakifKatilim;
use MPPos\MPPos;
use MPPos\Contracts\PosAdapterInterface;
use MPPos\Exceptions\PosException;

final class VakifKatilimAdapter implements PosAdapterInterface
{
    private VakifKatilim $bank;

    public function __construct(array $config, string|bool $env)
    {
        $env = is_bool($env) ? ($env ? MPPos::ENV_TEST : MPPos::ENV_PROD) : $env;

        foreach ([
                     'merchantId',
                     'customerId',
                     'userName',
                     'password'
                 ] as $k) {
            if (empty($config[$k])) {
                throw new PosException("VakifKatilim config missing: {$k}");
            }
        }

        $this->bank = new VakifKatilim(
            env: $env,
            merchantId: (string)$config['merchantId'],
            customerId: (string)$config['customerId'],
            userName: (string)$config['userName'],
            apiPassword: (string)$config['password']
        );
    }

    /**
     * Bankaya direkt POST edilecek
     * HTML form datasını döner
     */
    public function createPayment(array $params): array
    {
        try {
            return $this->bank->createForm($params);
        } catch (\Throwable $e) {
            throw new PosException(
                'VakifKatilim createPayment failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * 3D Hosted modelde callback
     * genelde hash ile doğrulanır.
     * Şimdilik true döndürüyoruz.
     */
    public function verifyCallback(array $data): bool
    {
        // İleride HashData kontrolü eklenecek
        return true;
    }

    public function getName(): string
    {
        return 'Vakıf Katılım';
    }
}
