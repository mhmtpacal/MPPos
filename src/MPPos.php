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
    public const KUVEYT_TURK = 'kuveytturk';
    public const VAKIF_KATILIM = 'vakifkatilim';

    private PosAdapterInterface $adapter;

    public function __construct(
        string $bank,
        bool   $test,
        array  $config
    )
    {
        $this->adapter = match ($bank) {
            self::KUVEYT_TURK => new KuveytTurkAdapter($config, $test),
            self::VAKIF_KATILIM => new VakifKatilimAdapter($config, $test),
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

    public static function createPayment(string $bank, array $payload, bool $test): array
    {
        [$config, $params] = self::splitConfigAndParams($bank, $payload);

        $pos = new self($bank, $test, $config);
        return $pos->createPaymentWithAdapter($params);
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
