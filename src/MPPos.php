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

    public const KUVEYT_TURK = 'kuveytturk';
    public const VAKIF_KATILIM = 'vakifkatilim';

    private PosAdapterInterface $adapter;

    public static function request(): MPPosRequest
    {
        return new MPPosRequest();
    }

    public function __construct(
        string      $bank,
        string|bool $env,
        array       $config
    )
    {
        $env = self::normalizeEnv($env);

        $this->adapter = match ($bank) {
            self::KUVEYT_TURK => new KuveytTurkAdapter($config, $env),
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

    public static function createPayment(string $bank, array $payload, string|bool $env): array
    {
        $env = self::normalizeEnv($env);

        [$config, $params] = self::splitConfigAndParams($bank, $payload);

        try {
            $pos = new self($bank, $env, $config);
            return $pos->createPaymentWithAdapter($params);
        } catch (\Throwable $e) {
            // Ensure callers get a typed exception and we keep the original stacktrace.
            throw new PosException('MPPos createPayment failed: ' . $e->getMessage(), previous: $e);
        }
    }

    private static function normalizeEnv(string|bool $env): string
    {
        if (is_bool($env)) {
            return $env ? self::ENV_TEST : self::ENV_PROD;
        }

        $env = strtolower(trim($env));
        if (!in_array($env, [self::ENV_TEST, self::ENV_PROD], true)) {
            throw new PosException('Invalid env');
        }

        return $env;
    }

    private static function splitConfigAndParams(string $bank, array $payload): array
    {
        $configKeys = match ($bank) {
            self::KUVEYT_TURK => ['merchantId', 'customerId', 'username', 'password'],
            self::VAKIF_KATILIM => ['merchantId', 'customerId', 'userName', 'password'],
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
