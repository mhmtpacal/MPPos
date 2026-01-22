<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Adapters\KuveytTurkAdapter;
use MPPos\Adapters\VakifKatilimAdapter;
use MPPos\Exceptions\PosException;

final class MPPos
{
    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    public const KUVEYT_TURK   = 'kuveytturk';
    public const VAKIF_KATILIM = 'vakifkatilim';

    private PosAdapterInterface $adapter;

    public function __construct(
        string $bank,
        string $env,
        array $config
    ) {
        if (!in_array($env, [self::ENV_TEST, self::ENV_PROD], true)) {
            throw new PosException('Invalid env');
        }

        $this->adapter = match ($bank) {
            self::KUVEYT_TURK   => new KuveytTurkAdapter($config, $env),
            self::VAKIF_KATILIM => new VakifKatilimAdapter($config, $env),
            default => throw new PosException("Unsupported bank: {$bank}")
        };
    }

    public function createPayment(array $params): array
    {
        return $this->adapter->createPayment($params);
    }

    public function verifyCallback(array $data): bool
    {
        return $this->adapter->verifyCallback($data);
    }

    public function bankName(): string
    {
        return $this->adapter->getName();
    }
}
