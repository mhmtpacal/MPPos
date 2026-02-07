<?php
declare(strict_types=1);

namespace MPPos\Banks\KuveytTurk;

use MPPos\Contracts\PayloadMapperInterface;

final class KuveytTurkMapper implements PayloadMapperInterface
{
    public function cancel(array $p): array
    {
        return [
            'remote_order_id' => $p['remote_order_id'] ?? null,
            'merchantOrderId' => $p['merchantOrderId'] ?? null,
            'ref_ret_num' => $p['ref_ret_num'] ?? null,
            'auth_code' => $p['auth_code'] ?? null,
            'transaction_id' => $p['transaction_id'] ?? null,
        ];
    }

    public function refund(array $p): array
    {
        return [
            ...$this->cancel($p),
            'amount' => $p['amount'] ?? null,
        ];
    }

    public function partialRefund(array $p): array
    {
        return $this->refund($p);
    }

    public function payment(array $p): array
    {
        foreach (['ok_url', 'fail_url', 'amount', 'merchantOrderId'] as $k) {
            if (empty($p[$k])) {
                throw new \RuntimeException("Missing payment field: {$k}");
            }
        }

        return [
            'APIVersion'        => 'TDV2.0.0',
            'OkUrl'            => $p['ok_url'],
            'FailUrl'          => $p['fail_url'],
            'Amount'           => (string)$p['amount'],
            'DisplayAmount'    => (string)$p['amount'],
            'MerchantOrderId'  => $p['merchantOrderId'],
            'InstallmentCount' => (string)($p['installment'] ?? 0),
            'CurrencyCode'     => '0949',
            'TransactionType'  => 'Sale',
            'TransactionSecurity' => '3',
        ];
    }

}
