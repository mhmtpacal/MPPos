<?php
declare(strict_types=1);

namespace MPPos\Banks\VakifKatilim;

use MPPos\Contracts\PayloadMapperInterface;
use RuntimeException;

final class VakifKatilimMapper implements PayloadMapperInterface
{
    public function payment(array $payload): array
    {
        foreach (['ok_url', 'fail_url', 'amount', 'card_holder', 'card_number', 'exp_month', 'exp_year', 'cvv'] as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
                throw new RuntimeException("Missing payment field: {$key}");
            }
        }

        $merchantOrderId = (string)($payload['merchantOrderId'] ?? $payload['order_id'] ?? '');
        if ($merchantOrderId === '') {
            throw new RuntimeException('Missing payment field: merchantOrderId');
        }

        $amount = $this->normalizeAmount($payload['amount']);

        return [
            'OkUrl' => (string)$payload['ok_url'],
            'FailUrl' => (string)$payload['fail_url'],
            'MerchantOrderId' => $merchantOrderId,
            'InstallmentCount' => (string)($payload['installment'] ?? 0),
            'Amount' => $amount,
            'DisplayAmount' => $amount,
            'FECAmount' => '0',
            'FECCurrencyCode' => (string)($payload['fec_currency_code'] ?? '0949'),
            'CurrencyCode' => (string)($payload['currency_code'] ?? '0949'),
            'APIVersion' => (string)($payload['api_version'] ?? '1.0.0'),
            'CardNumber' => preg_replace('/\D+/', '', (string)$payload['card_number']),
            'CardExpireDateYear' => substr(str_pad((string)$payload['exp_year'], 2, '0', STR_PAD_LEFT), -2),
            'CardExpireDateMonth' => str_pad((string)$payload['exp_month'], 2, '0', STR_PAD_LEFT),
            'CardCVV2' => preg_replace('/\D+/', '', (string)$payload['cvv']),
            'CardHolderName' => trim((string)$payload['card_holder']),
            'PaymentType' => (string)($payload['payment_type'] ?? 1),
            'SurchargeAmount' => (string)($payload['surcharge_amount'] ?? 0),
            'SGKDebtAmount' => (string)($payload['sgk_debt_amount'] ?? 0),
            'InstallmentMaturityCommisionFlag' => (string)($payload['installment_maturity_commision_flag'] ?? 0),
            'TransactionSecurity' => '3',
            'CustomerIPAddress' => (string)($payload['client_ip'] ?? ''),
            'Addresses' => $this->mapAddresses($payload),
            'AdditionalData' => $this->mapAdditionalData($payload),
        ];
    }

    public function cancel(array $payload): array
    {
        foreach (['merchantOrderId', 'remote_order_id'] as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
                throw new RuntimeException("Missing cancel field: {$key}");
            }
        }

        $amount = isset($payload['amount']) && $payload['amount'] !== '' && $payload['amount'] !== null
            ? $this->normalizeAmount($payload['amount'])
            : '0';

        return [
            'MerchantOrderId' => (string)$payload['merchantOrderId'],
            'OrderId' => (string)$payload['remote_order_id'],
            'Amount' => $amount,
            'PaymentType' => (string)($payload['payment_type'] ?? 1),
        ];
    }

    public function refund(array $payload): array
    {
        $data = $this->cancel($payload);
        unset($data['PaymentType']);
        return $data;
    }

    public function partialRefund(array $payload): array
    {
        foreach (['amount'] as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
                throw new RuntimeException("Missing partial refund field: {$key}");
            }
        }

        return [
            ...$this->cancel($payload),
            'Amount' => $this->normalizeAmount($payload['amount']),
            'DisplayAmount' => $this->normalizeAmount($payload['amount']),
        ];
    }

    public function provision(array $payload, array $auth): array
    {
        $merchantOrderId = (string)($auth['merchant_order_id'] ?? $payload['merchantOrderId'] ?? $payload['order_id'] ?? '');
        if ($merchantOrderId === '') {
            throw new RuntimeException('Missing provision field: merchantOrderId');
        }

        $amount = (string)($auth['amount'] ?? '');
        if ($amount === '') {
            if (!isset($payload['amount'])) {
                throw new RuntimeException('Missing provision field: amount');
            }
            $amount = $this->normalizeAmount($payload['amount']);
        }

        $md = (string)($auth['md'] ?? $payload['md'] ?? '');
        if ($md === '') {
            throw new RuntimeException('Missing provision field: MD');
        }

        return [
            'OkUrl' => (string)($payload['ok_url'] ?? $auth['ok_url'] ?? ''),
            'FailUrl' => (string)($payload['fail_url'] ?? $auth['fail_url'] ?? ''),
            'MerchantOrderId' => $merchantOrderId,
            'InstallmentCount' => (string)($auth['installment_count'] ?? $payload['installment'] ?? 0),
            'Amount' => $amount,
            'FECAmount' => '0',
            'PaymentType' => (string)($payload['payment_type'] ?? 1),
            'SurchargeAmount' => (string)($payload['surcharge_amount'] ?? 0),
            'SGKDebtAmount' => (string)($payload['sgk_debt_amount'] ?? 0),
            'InstallmentMaturityCommisionFlag' => (string)($payload['installment_maturity_commision_flag'] ?? 0),
            'TransactionSecurity' => (string)($auth['transaction_security'] ?? 3),
            'AdditionalData' => [
                ['Key' => 'MD', 'Data' => $md, 'Description' => ''],
            ],
        ];
    }

    private function normalizeAmount(int|float|string $amount): string
    {
        return (string)(int)round(round((float)$amount, 2) * 100);
    }

    private function mapAddresses(array $payload): array
    {
        if (isset($payload['addresses']) && is_array($payload['addresses'])) {
            return $payload['addresses'];
        }

        if (!isset($payload['address']) || !is_array($payload['address'])) {
            return [];
        }

        return [$payload['address']];
    }

    private function mapAdditionalData(array $payload): array
    {
        if (isset($payload['additional_data']) && is_array($payload['additional_data'])) {
            return $payload['additional_data'];
        }

        return [];
    }
}
