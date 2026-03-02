<?php
declare(strict_types=1);

namespace MPPos\Banks\VakifKatilim;

use RuntimeException;
use SimpleXMLElement;

final class VakifKatilimClient
{
    private ?array $lastResponse = null;
    private ?string $lastType = null;

    public function __construct(
        private string $merchantId,
        private string $customerId,
        private string $username,
        private string $password,
        private int $timeoutSeconds = 40
    ) {
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
    }

    public function init3D(array $fields, string $url): array
    {
        $xml = $this->buildMessageXml($fields);
        $result = $this->postXml($url, $xml);

        if (!($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'http_code' => (int)($result['http_code'] ?? 0),
                'body' => '',
                'error' => (string)($result['error'] ?? 'Unknown error'),
                'parsed' => [],
            ];
        }

        $parsed = $this->parseTransactionResponse((string)($result['body'] ?? ''));

        return [
            'ok' => (bool)($parsed['ok'] ?? false),
            'http_code' => (int)($result['http_code'] ?? 0),
            'body' => (string)($parsed['html'] ?? ''),
            'error' => (string)($parsed['message'] ?? ''),
            'parsed' => $parsed,
        ];
    }

    public function provision(array $fields, string $url): void
    {
        $xml = $this->buildMessageXml($fields);
        $this->lastType = 'Sale';
        $this->lastResponse = $this->postParsedXml($url, $xml);
    }

    public function cancel(array $fields, string $url): void
    {
        $xml = $this->buildMessageXml($fields);
        $this->lastType = 'SaleReversal';
        $this->lastResponse = $this->postParsedXml($url, $xml);
    }

    public function refund(array $fields, string $url): void
    {
        $xml = $this->buildMessageXml($fields);
        $this->lastType = 'DrawBack';
        $this->lastResponse = $this->postParsedXml($url, $xml);
    }

    public function partialRefund(array $fields, string $url): void
    {
        $xml = $this->buildMessageXml($fields);
        $this->lastType = 'PartialDrawBack';
        $this->lastResponse = $this->postParsedXml($url, $xml);
    }

    public function parsePaymentResponse(array $payload): array
    {
        $raw = $payload['ResponseMessage'] ?? $payload['response_message'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $xml = urldecode($raw);
            $parsed = $this->parseTransactionResponse($xml);
            if (($parsed['code'] ?? '') !== '') {
                return $parsed;
            }
        }

        $responseCode = (string)($payload['ResponseCode'] ?? $payload['response_code'] ?? '');
        $responseMessage = (string)($payload['ResponseMessage'] ?? $payload['response_message'] ?? '');
        $merchantOrderId = (string)($payload['MerchantOrderId'] ?? $payload['merchant_order_id'] ?? '');
        $orderId = (string)($payload['OrderId'] ?? $payload['order_id'] ?? '0');
        $md = (string)($payload['MD'] ?? $payload['md'] ?? '');
        $hashData = (string)($payload['HashData'] ?? $payload['hash_data'] ?? '');
        $rrn = (string)($payload['RRN'] ?? $payload['ref_ret_num'] ?? '');
        $stan = (string)($payload['Stan'] ?? $payload['transaction_id'] ?? '');
        $provisionNumber = (string)($payload['ProvisionNumber'] ?? $payload['auth_code'] ?? '');

        return [
            'ok' => $responseCode === '00' && $md !== '',
            'code' => $responseCode,
            'message' => $responseMessage,
            'provider' => 'vakifkatilim',
            'type' => 'Auth',
            'merchant_order_id' => $merchantOrderId,
            'remote_order_id' => $orderId,
            'md' => $md,
            'hash_data' => $hashData,
            'ref_ret_num' => $rrn,
            'transaction_id' => $stan,
            'auth_code' => $provisionNumber,
        ];
    }

    public function buildPaymentHash(
        string $merchantOrderId,
        string $amount,
        string $okUrl,
        string $failUrl
    ): string {
        $data = $this->merchantId . $merchantOrderId . $amount . $okUrl . $failUrl . $this->username . $this->buildHashPassword();
        return $this->computeHash($data);
    }

    public function buildApiHash(): string
    {
        $data = $this->merchantId . $this->username . $this->buildHashPassword();
        return $this->computeHash($data);
    }

    public function getResponse(): array
    {
        if ($this->lastResponse === null) {
            return [
                'ok' => false,
                'code' => 'NO_REQUEST',
                'message' => 'No transaction executed',
                'http_code' => 0,
                'type' => null,
                'provider' => 'vakifkatilim',
            ];
        }

        $parsed = $this->lastResponse['parsed'] ?? [];

        return [
            'ok' => (bool)($parsed['ok'] ?? false),
            'code' => $parsed['code'] ?? null,
            'message' => $parsed['message'] ?? '',
            'http_code' => (int)($this->lastResponse['http_code'] ?? 0),
            'type' => $this->lastType,
            'provider' => 'vakifkatilim',
            'remote_order_id' => $parsed['remote_order_id'] ?? null,
            'merchant_order_id' => $parsed['merchant_order_id'] ?? null,
            'auth_code' => $parsed['auth_code'] ?? null,
            'ref_ret_num' => $parsed['ref_ret_num'] ?? null,
            'transaction_id' => $parsed['transaction_id'] ?? null,
        ];
    }

    private function postParsedXml(string $url, string $xml): array
    {
        $result = $this->postXml($url, $xml);
        if (!($result['ok'] ?? false)) {
            return [
                'http_code' => (int)($result['http_code'] ?? 0),
                'parsed' => [
                    'ok' => false,
                    'code' => 'CURL_ERROR',
                    'message' => (string)($result['error'] ?? 'Unknown error'),
                ],
            ];
        }

        return [
            'http_code' => (int)($result['http_code'] ?? 0),
            'parsed' => $this->parseTransactionResponse((string)($result['body'] ?? '')),
        ];
    }

    private function postXml(string $url, string $xml): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=utf-8',
                'Connection: close',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            $no = curl_errno($ch);
            curl_close($ch);

            return [
                'ok' => false,
                'http_code' => (int)($info['http_code'] ?? 0),
                'body' => '',
                'error' => "curl_errno={$no}; curl_error={$err}",
            ];
        }

        curl_close($ch);

        $headerSize = (int)($info['header_size'] ?? 0);

        return [
            'ok' => ((int)($info['http_code'] ?? 0)) >= 200 && ((int)($info['http_code'] ?? 0)) < 400,
            'http_code' => (int)($info['http_code'] ?? 0),
            'body' => substr($raw, $headerSize),
            'error' => '',
        ];
    }

    private function buildMessageXml(array $fields): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<VPosMessageContract xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'AdditionalData' && is_array($value)) {
                $xml .= '<AdditionalData><AdditionalDataList>';
                foreach ($value as $item) {
                    $xml .= '<VPosAdditionalData>';
                    foreach (['Key', 'Data', 'Description'] as $subKey) {
                        if (isset($item[$subKey]) && $item[$subKey] !== '') {
                            $xml .= '<' . $subKey . '>' . $this->escape((string)$item[$subKey]) . '</' . $subKey . '>';
                        }
                    }
                    $xml .= '</VPosAdditionalData>';
                }
                $xml .= '</AdditionalDataList></AdditionalData>';
                continue;
            }

            if ($key === 'Addresses' && is_array($value)) {
                $xml .= '<Addresses>';
                foreach ($value as $item) {
                    $xml .= '<VPosAddressContract>';
                    foreach ($item as $subKey => $subValue) {
                        if ($subValue === null || $subValue === '') {
                            continue;
                        }
                        $xml .= '<' . $subKey . '>' . $this->escape((string)$subValue) . '</' . $subKey . '>';
                    }
                    $xml .= '</VPosAddressContract>';
                }
                $xml .= '</Addresses>';
                continue;
            }

            $xml .= '<' . $key . '>' . $this->escape((string)$value) . '</' . $key . '>';
        }

        $xml .= '</VPosMessageContract>';

        return $xml;
    }

    private function parseTransactionResponse(string $raw): array
    {
        $xml = $this->extractXml($raw);
        if ($xml === null) {
            return [
                'ok' => false,
                'code' => 'NO_XML',
                'message' => trim($raw),
                'provider' => 'vakifkatilim',
            ];
        }

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return [
                'ok' => false,
                'code' => 'INVALID_XML',
                'message' => trim($raw),
                'provider' => 'vakifkatilim',
            ];
        }

        $response = $this->xmlToArray($sx);
        $responseCode = $this->findValue($response, 'ResponseCode');
        $responseMessage = $this->findValue($response, 'ResponseMessage');
        $pareqHtml = $this->findValue($response, 'PareqHtmlFormString');
        $md = $this->findValue($response, 'MD');

        return [
            'ok' => $responseCode === '00' || $pareqHtml !== '',
            'code' => $responseCode,
            'message' => $responseMessage,
            'provider' => 'vakifkatilim',
            'html' => $pareqHtml,
            'md' => $md,
            'remote_order_id' => $this->findValue($response, 'OrderId'),
            'merchant_order_id' => $this->findValue($response, 'MerchantOrderId'),
            'auth_code' => $this->findValue($response, 'ProvisionNumber'),
            'ref_ret_num' => $this->findValue($response, 'RRN'),
            'transaction_id' => $this->findValue($response, 'Stan'),
            'hash_data' => $this->findValue($response, 'HashData'),
            'amount' => $this->findNestedValue($response, ['VPosMessage', 'Amount']),
            'installment_count' => $this->findNestedValue($response, ['VPosMessage', 'InstallmentCount']),
            'transaction_security' => $this->findNestedValue($response, ['VPosMessage', 'TransactionSecurity']),
            'ok_url' => $this->findNestedValue($response, ['VPosMessage', 'OkUrl']),
            'fail_url' => $this->findNestedValue($response, ['VPosMessage', 'FailUrl']),
            'raw' => $response,
        ];
    }

    private function buildHashPassword(): string
    {
        return $this->computeHash($this->password);
    }

    private function computeHash(string $value): string
    {
        return base64_encode(sha1($this->toIso($value), true));
    }

    private function toIso(string $value): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $value);
        return $converted !== false ? $converted : $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        return json_decode((string)$json, true) ?? [];
    }

    private function extractXml(string $raw): ?string
    {
        $pos = strpos($raw, '<');
        if ($pos === false) {
            return null;
        }

        $xml = substr($raw, $pos);
        return preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$2$3', $xml);
    }

    private function findValue(array $data, string $needle): string
    {
        if (array_key_exists($needle, $data) && !is_array($data[$needle])) {
            return (string)$data[$needle];
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }
            $found = $this->findValue($value, $needle);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    private function findNestedValue(array $data, array $path): string
    {
        $current = $data;
        foreach ($path as $key) {
            if (!isset($current[$key])) {
                return '';
            }
            $current = $current[$key];
        }

        return is_array($current) ? '' : (string)$current;
    }
}
