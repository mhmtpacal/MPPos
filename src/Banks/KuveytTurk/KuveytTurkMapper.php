<?php
declare(strict_types=1);

namespace MPPos\Banks\KuveytTurk;

use MPPos\Contracts\PayloadMapperInterface;
use RuntimeException;

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
        foreach (['ok_url', 'fail_url', 'amount'] as $key) {
            if (empty($p[$key])) {
                throw new RuntimeException("Missing payment field: {$key}");
            }
        }

        foreach (['card_holder', 'card_number', 'exp_month', 'exp_year', 'cvv', 'client_ip'] as $key) {
            if (empty($p[$key])) {
                throw new RuntimeException("Missing payment field: {$key}");
            }
        }

        $merchantOrderId = (string)($p['merchantOrderId'] ?? $p['order_id'] ?? '');
        if ($merchantOrderId === '') {
            throw new RuntimeException('Missing payment field: merchantOrderId');
        }

        $amount = $this->normalizeAmount($p['amount']);
        $phone = preg_replace('/\D+/', '', (string)($p['phone'] ?? ''));
        $phoneCc = (string)($p['phone_cc'] ?? '');
        $phoneSubscriber = (string)($p['phone_subscriber'] ?? '');

        if ($phone !== '') {
            if ($phoneCc === '' && strlen($phone) === 10) {
                $phoneCc = '90';
                $phoneSubscriber = $phone;
            } elseif ($phoneCc === '' && strlen($phone) === 11 && str_starts_with($phone, '0')) {
                $phoneCc = '90';
                $phoneSubscriber = substr($phone, 1);
            } elseif ($phoneCc === '' && str_starts_with($phone, '90') && strlen($phone) > 10) {
                $phoneCc = '90';
                $phoneSubscriber = substr($phone, 2);
            }
        }

        return [
            'APIVersion' => 'TDV2.0.0',
            'OkUrl' => (string)$p['ok_url'],
            'FailUrl' => (string)$p['fail_url'],
            'CardNumber' => preg_replace('/\D+/', '', (string)$p['card_number']),
            'CardExpireDateYear' => substr(str_pad((string)$p['exp_year'], 2, '0', STR_PAD_LEFT), -2),
            'CardExpireDateMonth' => str_pad((string)$p['exp_month'], 2, '0', STR_PAD_LEFT),
            'CardCVV2' => preg_replace('/\D+/', '', (string)$p['cvv']),
            'CardHolderName' => trim((string)$p['card_holder']),
            'CardType' => $this->resolveCardType($p['card_type'] ?? null, (string)$p['card_number']),
            'TransactionType' => 'Sale',
            'InstallmentCount' => (string)($p['installment'] ?? 0),
            'Amount' => $amount,
            'DisplayAmount' => $amount,
            'CurrencyCode' => (string)($p['currency_code'] ?? '0949'),
            'MerchantOrderId' => $merchantOrderId,
            'TransactionSecurity' => '3',
            'DeviceChannel' => (string)($p['device_channel'] ?? '02'),
            'ClientIP' => (string)$p['client_ip'],
            'BillAddrCity' => (string)($p['bill_addr_city'] ?? ''),
            'BillAddrCountry' => (string)($p['bill_addr_country'] ?? '792'),
            'BillAddrLine1' => (string)($p['bill_addr_line1'] ?? ''),
            'BillAddrPostCode' => (string)($p['bill_addr_post_code'] ?? ''),
            'BillAddrState' => (string)($p['bill_addr_state'] ?? ''),
            'Email' => (string)($p['email'] ?? ''),
            'Cc' => $phoneCc,
            'Subscriber' => $phoneSubscriber,
        ];
    }

    public function provision(array $p, array $auth): array
    {
        $merchantOrderId = (string)($auth['merchant_order_id'] ?? $p['merchantOrderId'] ?? $p['order_id'] ?? '');
        $md = (string)($auth['md'] ?? $p['md'] ?? '');

        if ($merchantOrderId === '') {
            throw new RuntimeException('Missing provision field: merchantOrderId');
        }

        if ($md === '') {
            throw new RuntimeException('Missing provision field: MD');
        }

        $amount = (string)($auth['amount'] ?? '');
        if ($amount === '') {
            if (!isset($p['amount'])) {
                throw new RuntimeException('Missing provision field: amount');
            }
            $amount = $this->normalizeAmount($p['amount']);
        }

        return [
            'APIVersion' => 'TDV2.0.0',
            'TransactionType' => 'Sale',
            'InstallmentCount' => (string)($auth['installment_count'] ?? $p['installment'] ?? 0),
            'Amount' => $amount,
            'MerchantOrderId' => $merchantOrderId,
            'TransactionSecurity' => (string)($auth['transaction_security'] ?? '3'),
            'MD' => $md,
        ];
    }

    private function normalizeAmount(int|float|string $amount): string
    {
        return (string)(int)round(round((float)$amount, 2) * 100);
    }

    private function resolveCardType(?string $cardType, string $cardNumber): string
    {
        $normalized = strtoupper(trim((string)$cardType));
        if ($normalized !== '') {
            return match ($normalized) {
                'MASTER', 'MASTERCARD' => 'MasterCard',
                'TROY' => 'Troy',
                default => 'VISA',
            };
        }

        $digits = preg_replace('/\D+/', '', $cardNumber);

        if (preg_match('/^(5[1-5]|2[2-7])/', $digits) === 1) {
            return 'MasterCard';
        }

        if (str_starts_with($digits, '9792') || str_starts_with($digits, '65')) {
            return 'Troy';
        }

        return 'VISA';
    }
}
