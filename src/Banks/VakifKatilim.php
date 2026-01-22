<?php
declare(strict_types=1);

namespace MPPos\Banks;

use InvalidArgumentException;
use MPPos\MPPos;
use RuntimeException;

final class VakifKatilim
{
    private string $env;
    private string $merchantId;
    private string $customerId;
    private string $userName;
    private string $apiPassword; // plain

    public function __construct(
        string $env,
        string $merchantId,
        string $customerId,
        string $userName,
        string $apiPassword
    )
    {
        $env = strtolower(trim($env));
        if (!in_array($env, [MPPos::ENV_TEST, MPPos::ENV_PROD], true)) {
            throw new InvalidArgumentException('Invalid env');
        }

        $this->env = $env;
        $this->merchantId = $merchantId;
        $this->customerId = $customerId;
        $this->userName = $userName;
        $this->apiPassword = $apiPassword;
    }

    /* =====================================================
     * FORM OLUŞTUR (BANKAYA DİREKT POST)
     * ===================================================== */

    /**
     * Frontend'in basacağı banka form datasını üretir
     * Kart inputları UI tarafında eklenir
     */
    public function createForm(array $p): array
    {
        // Back-compat: allow okUrl instead of successUrl
        if (!isset($p['successUrl']) && isset($p['okUrl'])) {
            $p['successUrl'] = $p['okUrl'];
        }

        $this->requireFields($p, [
            'orderId',
            'amount',
            'successUrl',
            'failUrl',
        ]);

        $amount = $this->formatAmount($p['amount']);
        $okUrl = (string)$p['successUrl'];
        $failUrl = (string)$p['failUrl'];

        $hashPassword = $this->computeHash($this->apiPassword);

        $hashString =
            $this->merchantId .
            $p['orderId'] .
            $amount .
            $okUrl .
            $failUrl .
            $this->userName .
            $hashPassword;

        $hashData = $this->computeHash($hashString);

        return [
            'action' => $this->gatewayUrl(),
            'method' => 'POST',
            'fields' => [
                // Sistem
                'MerchantId' => $this->merchantId,
                'CustomerId' => $this->customerId,
                'UserName' => $this->userName,

                'MerchantOrderId' => $p['orderId'],
                'Amount' => $amount,
                'CurrencyCode' => '0949',

                'OkUrl' => $okUrl,
                'FailUrl' => $failUrl,

                'TransactionSecurity' => '3',
                'InstallmentCount' => '0',

                'HashData' => $hashData,
            ]
        ];
    }

    /* =====================================================
     * CANCEL / REFUND
     * ===================================================== */

    public function cancel(string $orderId, string $merchantOrderId): array
    {
        return $this->postForm($this->endpointSaleReversal(), $this->buildReversalPayload(
            orderId: $orderId,
            merchantOrderId: $merchantOrderId,
            amount: '0'
        ));
    }

    public function refundFull(string $orderId, string $merchantOrderId): array
    {
        return $this->postForm($this->endpointDrawBack(), $this->buildReversalPayload(
            orderId: $orderId,
            merchantOrderId: $merchantOrderId,
            amount: '0'
        ));
    }

    public function refundPartial(string $orderId, string $merchantOrderId, string|int|float $amount): array
    {
        $amount = $this->formatAmount($amount);

        return $this->postForm($this->endpointPartialDrawBack(), $this->buildReversalPayload(
            orderId: $orderId,
            merchantOrderId: $merchantOrderId,
            amount: $amount
        ));
    }

    /* =====================================================
     * HASH (PDF BİREBİR)
     * ===================================================== */

    /**
     * SHA1 + ISO-8859-9 + Base64
     */
    private function computeHash(string $data): string
    {
        $data = mb_convert_encoding($data, 'ISO-8859-9', 'UTF-8');
        return base64_encode(sha1($data, true));
    }

    /**
     * Common reversal payload used by SaleReversal/DrawBack/PartialDrawBack.
     *
     * Note: Vakif Katilim documents vary by integration; if the bank requires
     * different field names, adjust here in one place.
     *
     * @return array<string, string>
     */
    private function buildReversalPayload(string $orderId, string $merchantOrderId, string $amount): array
    {
        $hashPassword = $this->computeHash($this->apiPassword);

        // Pattern is aligned with other Vakif Katilim hash samples:
        // MerchantId + MerchantOrderId + Amount + UserName + Hash(Password)
        $hashString = $this->merchantId . $merchantOrderId . $amount . $this->userName . $hashPassword;
        $hashData = $this->computeHash($hashString);

        return [
            'MerchantId' => $this->merchantId,
            'CustomerId' => $this->customerId,
            'UserName' => $this->userName,
            'HashData' => $hashData,
            'OrderId' => $orderId,
            'MerchantOrderId' => $merchantOrderId,
            'Amount' => $amount,
            'CurrencyCode' => '0949',
        ];
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    /**
     * 12.34 -> 1234
     */
    private function formatAmount(string|int|float $amount): string
    {
        $s = trim((string)$amount);
        $s = str_replace(['₺', 'TL', ' '], '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        if (!is_numeric($s)) {
            throw new InvalidArgumentException('Invalid amount');
        }

        return (string)(int)round((float)$s * 100);
    }

    private function gatewayUrl(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';
    }

    private function endpointSaleReversal(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal';
    }

    private function endpointDrawBack(): string
    {
        // PDF link showed "/DrawBacke" (sic). Keep as-is.
        return $this->env === MPPos::ENV_TEST
            ? 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBacke'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBacke';
    }

    private function endpointPartialDrawBack(): string
    {
        return $this->env === MPPos::ENV_TEST
            ? 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack';
    }

    /**
     * @param array<string, string> $fields
     */
    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new RuntimeException(curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : ['raw' => $resp];
    }

    private function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Missing field: {$field}");
            }
        }
    }
}
