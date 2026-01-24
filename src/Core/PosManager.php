<?php
declare(strict_types=1);

namespace MPPos\Core;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Result\PaymentResult;
use MPPos\Exceptions\PosException;
use MPPos\Logging\NullLogger;
use MPPos\Logging\PosLoggerInterface;

final class PosManager
{
    private PosLoggerInterface $logger;

    public function __construct(?PosLoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function pay(
        string $bank,
        string $env,
        PaymentPayload $payload,
        array $bankConfig = []
    ): PaymentResult {
        $start = microtime(true);

        try {
            $adapter = $this->resolveAdapter($bank, $env, $bankConfig);

            $result = $adapter->payment($payload);

            $this->logger->info('payment.success', [
                'bank'   => $bank,
                'env'    => $env,
                'order'  => $payload->orderId,
                'timeMs' => (int)((microtime(true) - $start) * 1000),
            ]);

            return $result;

        } catch (\Throwable $e) {

            $this->logger->error('payment.failed', [
                'bank'   => $bank,
                'env'    => $env,
                'order'  => $payload->orderId ?? null,
                'error'  => $e->getMessage(),
                'timeMs'=> (int)((microtime(true) - $start) * 1000),
            ]);

            throw $e;
        }
    }

    private function resolveAdapter(
        string $bank,
        string $env,
        array $bankConfig
    ): PosAdapterInterface {
        $class = match ($bank) {
            'parampos' => \MPPos\Banks\ParamPos\ParamPosAdapter::class,
            default    => throw new PosException("Unsupported bank: {$bank}")
        };

        return new $class($env, $bankConfig);
    }
}
