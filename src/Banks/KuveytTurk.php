<?php
declare(strict_types=1);

namespace MPPos\Banks;

use MPPos\Contracts\BankClientInterface;
use MPPos\Exceptions\BankException;
use MPPos\MPPos;
use MPPos\Support\Arr;

final class KuveytTurk implements BankClientInterface
{
    private int $timeoutSeconds = 20;

    public function __construct(
        private string $env,
        private string $merchantId,
        private string $username,
        private string $password,
        private string $customerId
    ) {
        $env = strtolower(trim($env));
        if (!in_array($env, [MPPos::ENV_TEST, MPPos::ENV_PROD], true)) {
            throw new BankException('Invalid env');
        }
        $this->env = $env;
    }

    public function setTimeoutSeconds(int $seconds): self
    {
        $this->timeoutSeconds = max(1, $seconds);
        return $this;
    }

    public function securePaymentRegister(array $bankPayload): array
    {
        $bankPayload['MerchantId'] = $this->merchantId;
        $bankPayload['CustomerId'] = $this->customerId;
        $bankPayload['UserName']   = $this->username;
        $bankPayload['HashData']   = $this->hashDataForRegister(
            (string)($bankPayload['MerchantOrderId'] ?? ''),
            (string)($bankPayload['Amount'] ?? '0'),
            (string)($bankPayload['SuccessUrl'] ?? ''),
            (string)($bankPayload['FailUrl'] ?? '')
        );

        $resp = $this->postJson($this->endpointSecurePaymentRegister(), $bankPayload);

        // Many responses include ResponseCode/ResponseMessage; bubble up as exception so callers don't parse "success" manually.
        $code = (string)($resp['ResponseCode'] ?? '');
        if ($code !== '' && $code !== '00') {
            $msg = (string)($resp['ResponseMessage'] ?? 'Bank error');
            throw new BankException("KuveytTurk SecurePaymentRegister failed: {$msg} (ResponseCode={$code})");
        }

        return $resp;
    }

    public function saleReversal(array $bankPayload): array
    {
        // Required injection
        $bankPayload['MerchantId'] = $this->merchantId;
        $bankPayload['CustomerId'] = $this->customerId;
        $bankPayload['UserName']   = $this->username;

        $merchantOrderId = (string)($bankPayload['MerchantOrderId'] ?? '');
        $amount          = (string)($bankPayload['Amount'] ?? '0');

        $bankPayload['HashData'] = $this->hashDataForSaleReversal($merchantOrderId, $amount);

        return $this->postJson($this->endpointSaleReversal(), $bankPayload);
    }

    public function securePaymentUiUrl(string $token): string
    {
        return $this->endpointSecurePaymentUiBase() . '?Token=' . rawurlencode($token);
    }

    /* ===================== HASH ===================== */

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
        $hashStr = $this->merchantId . $merchantOrderId . $amount . $successUrl . $failUrl . $this->username . $hp;
        return $this->computeHash($hashStr, $hp);
    }

    private function hashDataForSaleReversal(string $merchantOrderId, string $amount): string
    {
        // Cancel/Drawback: amount must be 0, PartialDrawback: include amount
        $hp = $this->hashPassword($this->password);
        $hashStr = $this->merchantId . $merchantOrderId . $amount . $this->username . $hp;
        return $this->computeHash($hashStr, $hp);
    }

    /* ===================== HTTP ===================== */

    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new BankException('cURL error: ' . curl_error($ch));
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) $decoded = ['raw' => $resp];

        if ($httpCode >= 400) {
            throw new BankException("HTTP {$httpCode} response", $httpCode);
        }

        return $decoded;
    }

    /* ===================== ENDPOINTS ===================== */

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
}
