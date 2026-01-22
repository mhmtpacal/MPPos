<?php
declare(strict_types=1);

namespace MPPos;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Adapters\KuveytTurkAdapter;
use MPPos\Adapters\VakifKatilimAdapter;
use MPPos\Exceptions\PosException;
use BadMethodCallException;

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

    public function createPaymentWithAdapter(array $params): array
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

    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'createPayment') {
            return $this->createPaymentWithAdapter(...$arguments);
        }

        throw new BadMethodCallException("Undefined method: {$name}");
    }

    public static function createPayment(array $payload, string $env): array
    {
        $bank = $payload['bankAdapter'] ?? null;
        if ($bank === null || $bank === '') {
            throw new PosException('Missing bankAdapter');
        }

        unset($payload['bankAdapter']);
        $bank = self::normalizeBankAdapter((string)$bank);

        [$config, $params] = self::splitConfigAndParams($bank, $payload);

        $pos = new self($bank, $env, $config);
        return $pos->createPaymentWithAdapter($params);
    }

    private static function normalizeBankAdapter(string $bank): string
    {
        return match ($bank) {
            self::KUVEYT_TURK,
            self::VAKIF_KATILIM => $bank,
            'KuveytTurkAdapter',
            'MPPos\\Adapters\\KuveytTurkAdapter' => self::KUVEYT_TURK,
            'VakifKatilimAdapter',
            'MPPos\\Adapters\\VakifKatilimAdapter' => self::VAKIF_KATILIM,
            default => throw new PosException("Unsupported bankAdapter: {$bank}")
        };
    }

    private static function splitConfigAndParams(string $bank, array $payload): array
    {
        $configKeys = match ($bank) {
            self::KUVEYT_TURK => ['merchantId', 'username', 'password', 'customerId'],
            self::VAKIF_KATILIM => ['merchantId', 'customerId', 'userName', 'password', 'okUrl', 'failUrl'],
            default => throw new PosException("Unsupported bank: {$bank}")
        };

        $config = [];
        $params = [];
        foreach ($payload as $k => $v) {
            if (in_array($k, $configKeys, true)) {
                $config[$k] = $v;
            } else {
                $params[$k] = $v;
            }
        }

        return [$config, $params];
    }
}
