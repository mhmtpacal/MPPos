<?php
declare(strict_types=1);

namespace MPPos\Banks;

use InvalidArgumentException;

final class VakifKatilim
{
    public const ENV_TEST = 'test';
    public const ENV_PROD = 'prod';

    private string $env;
    private string $merchantId;
    private string $customerId;
    private string $userName;
    private string $apiPassword; // plain
    private string $okUrl;
    private string $failUrl;

    public function __construct(
        string $env,
        string $merchantId,
        string $customerId,
        string $userName,
        string $apiPassword,
        string $okUrl,
        string $failUrl
    ) {
        if (!in_array($env, [self::ENV_TEST, self::ENV_PROD], true)) {
            throw new InvalidArgumentException('Invalid env');
        }

        $this->env         = $env;
        $this->merchantId  = $merchantId;
        $this->customerId  = $customerId;
        $this->userName    = $userName;
        $this->apiPassword = $apiPassword;
        $this->okUrl       = $okUrl;
        $this->failUrl     = $failUrl;
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
        $this->requireFields($p, [
            'orderId',
            'amount'
        ]);

        $amount = $this->formatAmount($p['amount']);

        $hashPassword = $this->computeHash($this->apiPassword);

        $hashString =
            $this->merchantId .
            $p['orderId'] .
            $amount .
            $this->okUrl .
            $this->failUrl .
            $this->userName .
            $hashPassword;

        $hashData = $this->computeHash($hashString);

        return [
            'action' => $this->gatewayUrl(),
            'method' => 'POST',
            'fields' => [
                // Sistem
                'MerchantId'       => $this->merchantId,
                'CustomerId'       => $this->customerId,
                'UserName'         => $this->userName,

                'MerchantOrderId'  => $p['orderId'],
                'Amount'           => $amount,
                'CurrencyCode'     => '0949',

                'OkUrl'            => $this->okUrl,
                'FailUrl'          => $this->failUrl,

                'TransactionSecurity' => '3',
                'InstallmentCount'    => '0',

                'HashData'         => $hashData,
            ]
        ];
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

    /* =====================================================
     * HELPERS
     * ===================================================== */

    /**
     * 12.34 → 1234
     */
    private function formatAmount(string|int|float $amount): string
    {
        if (is_int($amount)) {
            return (string)$amount;
        }

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
        return $this->env === self::ENV_TEST
            ? 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';
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
