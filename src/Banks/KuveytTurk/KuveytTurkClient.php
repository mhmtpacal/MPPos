<?php
declare(strict_types=1);

namespace MPPos\Banks\KuveytTurk;

use RuntimeException;
use SimpleXMLElement;

final class KuveytTurkClient
{
    private string $endpoint;
    private string $ns = 'http://boa.net/BOA.Integration.VirtualPos/Service';
    private ?array $lastResponse = null;
    private ?string $lastType = null;

    public function __construct(
        private string $merchantId,
        private string $customerId,
        private string $username,
        private string $password,
        ?string $endpoint = null,
        private int $timeoutSeconds = 40
    ) {
        $this->endpoint = $endpoint ?: 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
    }

    public function cancel(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id'] as $key) {
            if (empty($d[$key])) {
                throw new RuntimeException("Missing field: {$key}");
            }
        }

        $amount = '0';
        $hash = $this->buildProvisionHash((string)$d['merchantOrderId'], $amount);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="{$this->ns}">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:SaleReversal>
      <ser:request>
        <ser:IsFromExternalNetwork>true</ser:IsFromExternalNetwork>
        <ser:BusinessKey>0</ser:BusinessKey>
        <ser:ResourceId>0</ser:ResourceId>
        <ser:ActionId>0</ser:ActionId>
        <ser:LanguageId>0</ser:LanguageId>
        <ser:CustomerId>{$this->customerId}</ser:CustomerId>
        <ser:MailOrTelephoneOrder>true</ser:MailOrTelephoneOrder>
        <ser:RRN>{$d['ref_ret_num']}</ser:RRN>
        <ser:Stan>{$d['transaction_id']}</ser:Stan>
        <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
        <ser:Amount>0</ser:Amount>
        <ser:ProvisionNumber>{$d['auth_code']}</ser:ProvisionNumber>
        <ser:OrderId>{$d['remote_order_id']}</ser:OrderId>
        <ser:VPosMessage>
          <ser:APIVersion>TDV2.0.0</ser:APIVersion>
          <ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag>
          <ser:HashData>{$hash}</ser:HashData>
          <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
          <ser:SubMerchantId>0</ser:SubMerchantId>
          <ser:CustomerId>{$this->customerId}</ser:CustomerId>
          <ser:UserName>{$this->username}</ser:UserName>
          <ser:BatchID>0</ser:BatchID>
          <ser:TransactionType>SaleReversal</ser:TransactionType>
          <ser:InstallmentCount>0</ser:InstallmentCount>
          <ser:Amount>0</ser:Amount>
          <ser:CancelAmount>0</ser:CancelAmount>
          <ser:DisplayAmount>0</ser:DisplayAmount>
          <ser:MerchantOrderId>{$d['merchantOrderId']}</ser:MerchantOrderId>
          <ser:CurrencyCode>0949</ser:CurrencyCode>
          <ser:TransactionSecurity>1</ser:TransactionSecurity>
        </ser:VPosMessage>
      </ser:request>
    </ser:SaleReversal>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $this->lastType = 'SaleReversal';
        $this->lastResponse = $this->postSoap($xml, 'SaleReversal');
    }

    public function refund(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id', 'amount'] as $key) {
            if (empty($d[$key])) {
                throw new RuntimeException("Missing field: {$key}");
            }
        }

        $amount = $this->normalizeLegacyRefundAmount($d['amount']);
        $hash = $this->buildProvisionHash((string)$d['merchantOrderId'], $amount);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="{$this->ns}">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:DrawBack>
      <ser:request>
        <ser:IsFromExternalNetwork>true</ser:IsFromExternalNetwork>
        <ser:BusinessKey>0</ser:BusinessKey>
        <ser:ResourceId>0</ser:ResourceId>
        <ser:ActionId>0</ser:ActionId>
        <ser:LanguageId>0</ser:LanguageId>
        <ser:CustomerId>{$this->customerId}</ser:CustomerId>
        <ser:MailOrTelephoneOrder>true</ser:MailOrTelephoneOrder>
        <ser:RRN>{$d['ref_ret_num']}</ser:RRN>
        <ser:Stan>{$d['transaction_id']}</ser:Stan>
        <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
        <ser:Amount>{$amount}</ser:Amount>
        <ser:ProvisionNumber>{$d['auth_code']}</ser:ProvisionNumber>
        <ser:OrderId>{$d['remote_order_id']}</ser:OrderId>
        <ser:VPosMessage>
          <ser:APIVersion>TDV2.0.0</ser:APIVersion>
          <ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag>
          <ser:HashData>{$hash}</ser:HashData>
          <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
          <ser:SubMerchantId>0</ser:SubMerchantId>
          <ser:CustomerId>{$this->customerId}</ser:CustomerId>
          <ser:UserName>{$this->username}</ser:UserName>
          <ser:CardType>VISA</ser:CardType>
          <ser:BatchID>0</ser:BatchID>
          <ser:TransactionType>DrawBack</ser:TransactionType>
          <ser:InstallmentCount>0</ser:InstallmentCount>
          <ser:Amount>{$amount}</ser:Amount>
          <ser:CancelAmount>{$amount}</ser:CancelAmount>
          <ser:DisplayAmount>{$amount}</ser:DisplayAmount>
          <ser:MerchantOrderId>{$d['merchantOrderId']}</ser:MerchantOrderId>
          <ser:FECAmount>0</ser:FECAmount>
          <ser:CurrencyCode>0949</ser:CurrencyCode>
          <ser:QeryId>0</ser:QeryId>
          <ser:DebtId>0</ser:DebtId>
          <ser:SurchargeAmount>0</ser:SurchargeAmount>
          <ser:SGKDebtAmount>0</ser:SGKDebtAmount>
          <ser:TransactionSecurity>1</ser:TransactionSecurity>
        </ser:VPosMessage>
      </ser:request>
    </ser:DrawBack>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $this->lastType = 'DrawBack';
        $this->lastResponse = $this->postSoap($xml, 'DrawBack');
    }

    public function partialRefund(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id', 'amount'] as $key) {
            if (empty($d[$key])) {
                throw new RuntimeException("Missing field: {$key}");
            }
        }

        $amount = $this->normalizeLegacyRefundAmount($d['amount']);
        $hash = $this->buildProvisionHash((string)$d['merchantOrderId'], $amount);

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="{$this->ns}">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:PartialDrawback>
      <ser:request>
        <ser:IsFromExternalNetwork>true</ser:IsFromExternalNetwork>
        <ser:BusinessKey>0</ser:BusinessKey>
        <ser:ResourceId>0</ser:ResourceId>
        <ser:ActionId>0</ser:ActionId>
        <ser:LanguageId>0</ser:LanguageId>
        <ser:CustomerId>{$this->customerId}</ser:CustomerId>
        <ser:MailOrTelephoneOrder>true</ser:MailOrTelephoneOrder>
        <ser:RRN>{$d['ref_ret_num']}</ser:RRN>
        <ser:Stan>{$d['transaction_id']}</ser:Stan>
        <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
        <ser:Amount>{$amount}</ser:Amount>
        <ser:ProvisionNumber>{$d['auth_code']}</ser:ProvisionNumber>
        <ser:OrderId>{$d['remote_order_id']}</ser:OrderId>
        <ser:VPosMessage>
          <ser:APIVersion>TDV2.0.0</ser:APIVersion>
          <ser:InstallmentMaturityCommisionFlag>0</ser:InstallmentMaturityCommisionFlag>
          <ser:HashData>{$hash}</ser:HashData>
          <ser:MerchantId>{$this->merchantId}</ser:MerchantId>
          <ser:SubMerchantId>0</ser:SubMerchantId>
          <ser:CustomerId>{$this->customerId}</ser:CustomerId>
          <ser:UserName>{$this->username}</ser:UserName>
          <ser:BatchID>0</ser:BatchID>
          <ser:TransactionType>PartialDrawback</ser:TransactionType>
          <ser:InstallmentCount>0</ser:InstallmentCount>
          <ser:Amount>{$amount}</ser:Amount>
          <ser:CancelAmount>{$amount}</ser:CancelAmount>
          <ser:DisplayAmount>{$amount}</ser:DisplayAmount>
          <ser:MerchantOrderId>{$d['merchantOrderId']}</ser:MerchantOrderId>
          <ser:FECAmount>0</ser:FECAmount>
          <ser:CurrencyCode>0949</ser:CurrencyCode>
          <ser:QeryId>0</ser:QeryId>
          <ser:DebtId>0</ser:DebtId>
          <ser:SurchargeAmount>0</ser:SurchargeAmount>
          <ser:SGKDebtAmount>0</ser:SGKDebtAmount>
          <ser:TransactionSecurity>1</ser:TransactionSecurity>
        </ser:VPosMessage>
      </ser:request>
    </ser:PartialDrawback>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $this->lastType = 'PartialDrawback';
        $this->lastResponse = $this->postSoap($xml, 'PartialDrawback');
    }

    public function provision(array $fields, string $url): void
    {
        foreach (['HashData', 'MerchantId', 'CustomerId', 'UserName', 'TransactionType', 'InstallmentCount', 'Amount', 'MerchantOrderId', 'TransactionSecurity', 'MD'] as $key) {
            if (!isset($fields[$key]) || $fields[$key] === '') {
                throw new RuntimeException("Missing field: {$key}");
            }
        }

        $xml = $this->buildProvisionXml($fields);

        $this->lastType = 'Sale';
        $this->lastResponse = $this->postXml($url, $xml);
    }

    public function init3D(array $fields, string $url): array
    {
        foreach (['MerchantId', 'CustomerId', 'UserName', 'HashData', 'APIVersion', 'OkUrl', 'FailUrl', 'CardNumber', 'CardExpireDateYear', 'CardExpireDateMonth', 'CardCVV2', 'CardHolderName', 'TransactionType', 'InstallmentCount', 'Amount', 'DisplayAmount', 'CurrencyCode', 'MerchantOrderId', 'TransactionSecurity', 'ClientIP'] as $key) {
            if (!isset($fields[$key]) || $fields[$key] === '') {
                throw new RuntimeException("Missing field: {$key}");
            }
        }

        return $this->postRawForm($url, $fields);
    }

    public function parsePaymentResponse(array $payload): array
    {
        $raw = $payload['AuthenticationResponse'] ?? $payload['authentication_response'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [
                'ok' => false,
                'code' => 'NO_AUTH_RESPONSE',
                'message' => 'AuthenticationResponse missing',
                'provider' => 'kuveytturk',
                'type' => 'Auth',
            ];
        }

        $xml = $this->extractXml(urldecode($raw));
        if ($xml === null) {
            return [
                'ok' => false,
                'code' => 'INVALID_AUTH_RESPONSE',
                'message' => 'AuthenticationResponse is not valid XML',
                'provider' => 'kuveytturk',
                'type' => 'Auth',
            ];
        }

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return [
                'ok' => false,
                'code' => 'INVALID_XML',
                'message' => 'AuthenticationResponse XML could not be parsed',
                'provider' => 'kuveytturk',
                'type' => 'Auth',
            ];
        }

        $response = $this->xmlToArray($sx);
        $responseCode = (string)$this->findValue($response, 'ResponseCode');
        $responseMessage = (string)$this->findValue($response, 'ResponseMessage');
        $orderId = (string)$this->findValue($response, 'OrderId');
        $merchantOrderId = (string)$this->findValue($response, 'MerchantOrderId');
        $md = (string)$this->findValue($response, 'MD');
        $hashData = (string)$this->findValue($response, 'HashData');
        $amount = (string)$this->findNestedValue($response, ['VPosMessage', 'Amount']);
        $installmentCount = (string)$this->findNestedValue($response, ['VPosMessage', 'InstallmentCount']);
        $transactionSecurity = (string)$this->findNestedValue($response, ['VPosMessage', 'TransactionSecurity']);
        $cardNumber = (string)$this->findNestedValue($response, ['VPosMessage', 'CardNumber']);
        $cardType = (string)$this->findNestedValue($response, ['VPosMessage', 'CardType']);

        if ($responseCode === '' || $orderId === '' || $merchantOrderId === '') {
            return [
                'ok' => false,
                'code' => 'MISSING_AUTH_FIELDS',
                'message' => 'AuthenticationResponse required fields missing',
                'provider' => 'kuveytturk',
                'type' => 'Auth',
            ];
        }

        $expectedHash = $this->buildAuthResponseHash($merchantOrderId, $responseCode, $orderId);
        if ($hashData !== '' && !hash_equals($expectedHash, $hashData)) {
            return [
                'ok' => false,
                'code' => 'HASH_MISMATCH',
                'message' => 'AuthenticationResponse hash mismatch',
                'provider' => 'kuveytturk',
                'type' => 'Auth',
                'merchant_order_id' => $merchantOrderId,
                'remote_order_id' => $orderId,
            ];
        }

        return [
            'ok' => $responseCode === '00' && $md !== '',
            'code' => $responseCode,
            'message' => $responseMessage,
            'provider' => 'kuveytturk',
            'type' => 'Auth',
            'merchant_order_id' => $merchantOrderId,
            'remote_order_id' => $orderId,
            'md' => $md,
            'hash_data' => $hashData,
            'amount' => $amount,
            'installment_count' => $installmentCount !== '' ? $installmentCount : '0',
            'transaction_security' => $transactionSecurity !== '' ? $transactionSecurity : '3',
            'masked_card_number' => $cardNumber,
            'card_type' => $cardType,
            'raw' => $response,
        ];
    }

    public function buildPaymentHash(
        string $merchantOrderId,
        string $amount,
        string $okUrl,
        string $failUrl
    ): string {
        $hash = $this->merchantId . $merchantOrderId . $amount . $okUrl . $failUrl . $this->username . $this->buildHashPassword();
        return base64_encode(sha1($this->toIso($hash), true));
    }

    public function buildProvisionHash(
        string $merchantOrderId,
        string $amount
    ): string {
        $hash = $this->merchantId . $merchantOrderId . $amount . $this->username . $this->buildHashPassword();
        return base64_encode(sha1($this->toIso($hash), true));
    }

    public function buildAuthResponseHash(
        string $merchantOrderId,
        string $responseCode,
        string $orderId
    ): string {
        $hash = $merchantOrderId . $responseCode . $orderId . $this->buildHashPassword();
        return base64_encode(sha1($this->toIso($hash), true));
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
                'provider' => 'kuveytturk',
            ];
        }

        $parsed = $this->lastResponse['parsed'] ?? [];

        return [
            'ok' => (bool)($parsed['status'] ?? false),
            'code' => $parsed['code'] ?? null,
            'message' => $parsed['message'] ?? '',
            'http_code' => (int)($this->lastResponse['http_code'] ?? 0),
            'type' => $this->lastType,
            'provider' => 'kuveytturk',
            'remote_order_id' => $parsed['remote_order_id'] ?? null,
            'merchant_order_id' => $parsed['merchant_order_id'] ?? null,
            'auth_code' => $parsed['auth_code'] ?? null,
            'ref_ret_num' => $parsed['ref_ret_num'] ?? null,
            'transaction_id' => $parsed['transaction_id'] ?? null,
        ];
    }

    private function postSoap(string $xml, string $type): array
    {
        $soapAction = '"' . $this->ns . '/IVirtualPosService/' . $type . '"';

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $soapAction,
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
                'http_code' => (int)($info['http_code'] ?? 0),
                'parsed' => [
                    'status' => false,
                    'code' => 'CURL_ERROR',
                    'message' => "curl_errno={$no}; curl_error={$err}",
                ],
            ];
        }

        curl_close($ch);

        $headerSize = (int)($info['header_size'] ?? 0);
        $body = substr($raw, $headerSize);

        return [
            'http_code' => (int)($info['http_code'] ?? 0),
            'parsed' => $this->parseSoapResponse($body, $type),
        ];
    }

    private function postForm(string $url, array $fields): array
    {
        $result = $this->postRawForm($url, $fields);
        $responseBody = $result['body'] ?? '';

        return [
            'http_code' => (int)($result['http_code'] ?? 0),
            'parsed' => $this->parseProvisionResponse($responseBody),
        ];
    }

    private function postXml(string $url, string $xml): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
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
                'http_code' => (int)($info['http_code'] ?? 0),
                'parsed' => [
                    'status' => false,
                    'code' => 'CURL_ERROR',
                    'message' => "curl_errno={$no}; curl_error={$err}",
                ],
            ];
        }

        curl_close($ch);

        $headerSize = (int)($info['header_size'] ?? 0);
        $responseBody = substr($raw, $headerSize);

        return [
            'http_code' => (int)($info['http_code'] ?? 0),
            'parsed' => $this->parseProvisionResponse($responseBody),
        ];
    }

    private function postRawForm(string $url, array $fields): array
    {
        $body = http_build_query($this->flattenFields($fields), '', '&');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
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

    private function parseSoapResponse(string $raw, string $type): array
    {
        $xml = $this->extractXml($raw);
        if ($xml === null) {
            return $this->fail('NO_XML', $raw);
        }

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return $this->fail('INVALID_XML', $raw);
        }

        $response = $this->xmlToArray($sx);
        $map = [
            'SaleReversal' => ['sBody', 'SaleReversalResponse', 'SaleReversalResult'],
            'DrawBack' => ['sBody', 'DrawBackResponse', 'DrawBackResult'],
            'PartialDrawback' => ['sBody', 'PartialDrawbackResponse', 'PartialDrawbackResult'],
        ];

        if (!isset($map[$type])) {
            return $this->fail('UNKNOWN_TYPE', $type);
        }

        $root = $this->getPath($response, $map[$type]);
        $successRaw = $root['Success'] ?? false;
        $success = $successRaw === true || $successRaw === 'true' || $successRaw === 1 || $successRaw === '1';

        if (!$success) {
            return [
                'status' => false,
                'code' => $root['Results']['Result']['ErrorCode'] ?? 'UNKNOWN_ERROR',
                'message' => $root['Results']['Result']['ErrorMessage'] ?? 'Unknown error',
            ];
        }

        $code = (string)($root['Value']['ResponseCode'] ?? '');

        return [
            'status' => $code === '00',
            'code' => $code,
            'message' => $root['Value']['ResponseMessage'] ?? $root['Results']['Result']['ResponseMessage'] ?? '',
        ];
    }

    private function parseProvisionResponse(string $raw): array
    {
        $xml = $this->extractXml($raw);
        if ($xml === null) {
            return $this->fail('NO_XML', $raw);
        }

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return $this->fail('INVALID_XML', $raw);
        }

        $response = $this->xmlToArray($sx);
        $responseCode = (string)$this->findValue($response, 'ResponseCode');
        $responseMessage = (string)$this->findValue($response, 'ResponseMessage');

        return [
            'status' => $responseCode === '00',
            'code' => $responseCode !== '' ? $responseCode : 'UNKNOWN_ERROR',
            'message' => $responseMessage,
            'remote_order_id' => (string)$this->findValue($response, 'OrderId'),
            'merchant_order_id' => (string)$this->findValue($response, 'MerchantOrderId'),
            'auth_code' => (string)$this->findValue($response, 'ProvisionNumber'),
            'ref_ret_num' => (string)$this->findValue($response, 'RRN'),
            'transaction_id' => (string)$this->findValue($response, 'Stan'),
            'hash_data' => (string)$this->findValue($response, 'HashData'),
            'raw' => $response,
        ];
    }

    private function buildHashPassword(): string
    {
        return base64_encode(sha1($this->toIso($this->password), true));
    }

    private function toIso(string $value): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $value);
        return $converted !== false ? $converted : $value;
    }

    private function normalizeLegacyRefundAmount(int|float|string $amount): string
    {
        return (string)($amount * 100);
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

    private function getPath(array $data, array $path): array
    {
        foreach ($path as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                return [];
            }
            $data = $data[$key];
        }

        return $data;
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

    private function flattenFields(array $fields, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($fields as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $flattened += $this->flattenFields($value, $name);
                continue;
            }

            $flattened[$name] = (string)$value;
        }

        return $flattened;
    }

    private function buildProvisionXml(array $fields): string
    {
        $apiVersion = htmlspecialchars((string)($fields['APIVersion'] ?? 'TDV2.0.0'), ENT_XML1);
        $hashData = htmlspecialchars((string)$fields['HashData'], ENT_XML1);
        $merchantId = htmlspecialchars((string)$fields['MerchantId'], ENT_XML1);
        $customerId = htmlspecialchars((string)$fields['CustomerId'], ENT_XML1);
        $userName = htmlspecialchars((string)$fields['UserName'], ENT_XML1);
        $transactionType = htmlspecialchars((string)$fields['TransactionType'], ENT_XML1);
        $installmentCount = htmlspecialchars((string)$fields['InstallmentCount'], ENT_XML1);
        $amount = htmlspecialchars((string)$fields['Amount'], ENT_XML1);
        $merchantOrderId = htmlspecialchars((string)$fields['MerchantOrderId'], ENT_XML1);
        $transactionSecurity = htmlspecialchars((string)$fields['TransactionSecurity'], ENT_XML1);
        $md = htmlspecialchars((string)$fields['MD'], ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <APIVersion>{$apiVersion}</APIVersion>
  <HashData>{$hashData}</HashData>
  <MerchantId>{$merchantId}</MerchantId>
  <CustomerId>{$customerId}</CustomerId>
  <UserName>{$userName}</UserName>
  <TransactionType>{$transactionType}</TransactionType>
  <InstallmentCount>{$installmentCount}</InstallmentCount>
  <Amount>{$amount}</Amount>
  <MerchantOrderId>{$merchantOrderId}</MerchantOrderId>
  <TransactionSecurity>{$transactionSecurity}</TransactionSecurity>
  <KuveytTurkVPosAdditionalData>
    <AdditionalData>
      <Key>MD</Key>
      <Data>{$md}</Data>
    </AdditionalData>
  </KuveytTurkVPosAdditionalData>
</KuveytTurkVPosMessage>
XML;
    }

    private function fail(string $code, string $raw): array
    {
        return [
            'status' => false,
            'code' => $code,
            'message' => trim($raw),
        ];
    }
}
