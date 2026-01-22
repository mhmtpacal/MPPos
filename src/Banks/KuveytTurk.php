<?php
declare(strict_types=1);

namespace MPPos\Banks;

use RuntimeException;
use InvalidArgumentException;
use MPPos\MPPos;

final class KuveytTurk
{
    private string $env;
    private string $merchantId;
    private string $username;
    private string $password;
    private string $customerId;
    private int $timeoutSeconds = 20;

    public function __construct(
        string $env,
        string $merchantId,
        string $username,
        string $password,
        string $customerId
    ) {
        $env = strtolower(trim($env));
        if (!in_array($env, [MPPos::ENV_TEST, MPPos::ENV_PROD], true)) {
            throw new InvalidArgumentException('Invalid env');
        }

        $this->env        = $env;
        $this->merchantId = $merchantId;
        $this->username   = $username;
        $this->password   = $password;
        $this->customerId = $customerId;
    }

    public function setTimeoutSeconds(int $seconds): self
    {
        $this->timeoutSeconds = max(1, $seconds);
        return $this;
    }

    /* =========================================================
     * A) HOSTED / TOKEN
     * ========================================================= */

    public function register(array $p): array
    {
        $this->assertRequired($p, [
            'merchantOrderId', 'amount', 'successUrl', 'failUrl', 'cardHolderIp', 'email'
        ]);

        if (!empty($p['installmentCount']) && !empty($p['deferringCount'])) {
            throw new InvalidArgumentException('installmentCount and deferringCount cannot be sent together.');
        }

        $amount       = $this->normalizeAmount($p['amount']);
        $currencyCode = $p['currencyCode'] ?? '0949';
        $language     = $p['language'] ?? 'TR';

        $hashData = $this->hashDataForRegister(
            (string)$p['merchantOrderId'],
            $amount,
            (string)$p['successUrl'],
            (string)$p['failUrl']
        );

        $payload = [
            'HashData'        => $hashData,
            'MerchantId'      => $this->merchantId,
            'UserName'        => $this->username,
            'TransactionType' => 'Sale',
            'TokenType'       => 'SecureCommonPayment',
            'SuccessUrl'      => (string)$p['successUrl'],
            'FailUrl'         => (string)$p['failUrl'],
            'Amount'          => $amount,
            'CurrencyCode'    => $currencyCode,
            'CardHolderIP'    => (string)$p['cardHolderIp'],
            'MerchantOrderId' => (string)$p['merchantOrderId'],
            'Email'           => (string)$p['email'],
            'Language'        => $language,
        ];

        if (!empty($p['installmentCount'])) {
            $payload['InstallmentCount'] = (string)(int)$p['installmentCount'];
        }

        if (!empty($p['deferringCount'])) {
            $payload['DeferringCount'] = (string)(int)$p['deferringCount'];
        }

        $raw = $this->postJson($this->endpointSecurePaymentRegister(), $payload);

        $token = $this->pickFirstString($raw, ['Token', 'Data.Token', 'Result.Token']);
        if ($token === '') {
            throw new RuntimeException('Token could not be parsed');
        }

        return [
            'token'       => $token,
            'redirectUrl' => $this->securePaymentUiUrl($token),
            'raw'         => $raw,
        ];
    }

    public function securePaymentUiUrl(string $token): string
    {
        return $this->endpointSecurePaymentUiBase() . '?Token=' . rawurlencode($token);
    }

    /* =========================================================
     * B) MERCHANT UI
     * ========================================================= */

    public function buildMerchantUiPayload(array $p): array
    {
        $this->assertRequired($p, [
            'merchantOrderId', 'amount', 'successUrl', 'failUrl', 'cardHolderIp', 'email'
        ]);

        $amount   = $this->normalizeAmount($p['amount']);
        $language = $p['language'] ?? 'TR';

        $hashData = $this->hashDataForRegister(
            (string)$p['merchantOrderId'],
            $amount,
            (string)$p['successUrl'],
            (string)$p['failUrl']
        );

        return [
            'action' => $this->endpointSecurePaymentUiBase(),
            'method' => 'POST',
            'fields' => [
                'HashData'        => $hashData,
                'MerchantId'      => $this->merchantId,
                'UserName'        => $this->username,
                'TransactionType' => 'Sale',
                'SuccessUrl'      => $p['successUrl'],
                'FailUrl'         => $p['failUrl'],
                'Amount'          => $amount,
                'CurrencyCode'    => '0949',
                'CardHolderIP'    => $p['cardHolderIp'],
                'MerchantOrderId' => $p['merchantOrderId'],
                'Email'           => $p['email'],
                'Language'        => $language,
            ]
        ];
    }

    /* =========================================================
     * İPTAL / İADE
     * ========================================================= */

    public function cancel(string $orderId, string $merchantOrderId): array
    {
        return $this->saleReversal('Cancel', $orderId, $merchantOrderId, null);
    }

    public function refundFull(string $orderId, string $merchantOrderId): array
    {
        return $this->saleReversal('Drawback', $orderId, $merchantOrderId, null);
    }

    public function refundPartial(string $orderId, string $merchantOrderId, string|int|float $amount): array
    {
        return $this->saleReversal(
            'PartialDrawback',
            $orderId,
            $merchantOrderId,
            $this->normalizeAmount($amount)
        );
    }

    private function saleReversal(string $type, string $orderId, string $merchantOrderId, ?string $amount): array
    {
        $hashData = $this->hashDataForSaleReversal($merchantOrderId, $amount ?? '0');

        return $this->postJson($this->endpointSaleReversal(), [
            'SaleReversalType' => $type,
            'OrderId'          => $orderId,
            'MerchantOrderId'  => $merchantOrderId,
            'merchantId'       => $this->merchantId,
            'customerId'       => $this->customerId,
            'username'         => $this->username,
            'hashData'         => $hashData,
            'amount'           => $amount ?? '0',
        ]);
    }

    /* =========================================================
     * HASH
     * ========================================================= */

    private function hashPassword(string $password): string
    {
        return base64_encode(sha1($password, true));
    }

    private function computeHash(string $data, string $key): string
    {
        return base64_encode(hash_hmac('sha512', $data, $key, true));
    }

    private function hashDataForRegister(string $merchantOrderId, string $amount, string $successUrl, string $failUrl): string
    {
        $hp = $this->hashPassword($this->password);
        return $this->computeHash(
            $this->merchantId . $merchantOrderId . $amount . $successUrl . $failUrl . $this->username . $hp,
            $hp
        );
    }

    private function hashDataForSaleReversal(string $merchantOrderId, string $amount): string
    {
        $hp = $this->hashPassword($this->password);
        return $this->computeHash(
            $this->merchantId . $merchantOrderId . $amount . $this->username . $hp,
            $hp
        );
    }

    /* =========================================================
     * HTTP
     * ========================================================= */

    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new RuntimeException(curl_error($ch));
        }

        curl_close($ch);
        return json_decode($resp, true) ?? [];
    }

    /* =========================================================
     * ENDPOINTS
     * ========================================================= */

    private function endpointSecurePaymentRegister(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SecurePaymentRegister'
            : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SecurePaymentRegister';
    }

    private function endpointSecurePaymentUiBase(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SecurePayment'
            : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SecurePayment';
    }

    private function endpointSaleReversal(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SaleReversal'
            : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SaleReversal';
    }

    /* =========================================================
     * HELPERS
     * ========================================================= */

    private function normalizeAmount(string|int|float $amount): string
    {
        if (is_int($amount)) return (string)$amount;

        $s = str_replace(['₺','TL',' '], '', (string)$amount);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        if (!is_numeric($s)) {
            throw new InvalidArgumentException('Invalid amount');
        }

        return (string)(int)round((float)$s * 100);
    }

    private function assertRequired(array $p, array $keys): void
    {
        foreach ($keys as $k) {
            if (empty($p[$k])) {
                throw new InvalidArgumentException("Missing parameter: {$k}");
            }
        }
    }

    private function pickFirstString(array $arr, array $paths): string
    {
        foreach ($paths as $path) {
            $v = $arr;
            foreach (explode('.', $path) as $p) {
                if (!isset($v[$p])) continue 2;
                $v = $v[$p];
            }
            if (is_string($v) && $v !== '') return $v;
        }
        return '';
    }
}
