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
        ?string        $endpoint = null,
        private int    $timeoutSeconds = 40
    )
    {
        $this->endpoint = $endpoint ?: 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
    }

    /**
     * SALE REVERSAL (İPTAL)
     */
    public function cancel(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id'] as $k) {
            if (empty($d[$k])) {
                throw new RuntimeException("Missing field: {$k}");
            }
        }

        // ❗ İPTALDE TUTAR DAİMA 0
        $amount = '0';

        $hash = $this->buildHash(
            $this->merchantId,
            (string)$d['merchantOrderId'],
            $amount,
            $this->username,
            $this->password
        );

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
        $this->lastResponse = $this->post($xml, 'SaleReversal');
    }

    /**
     * DRAWBACK (İADE)
     */
    public function refund(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id', 'amount'] as $k) {
            if (empty($d[$k])) {
                throw new RuntimeException("Missing field: {$k}");
            }
        }

        // Senin mevcut davranışın: (amount - 0.01) * 100
        $amount = (string)((((float)$d['amount']) - 0.01) * 100);

        $hash = $this->buildHash(
            $this->merchantId,
            (string)$d['merchantOrderId'],
            $amount,
            $this->username,
            $this->password
        );

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
        $this->lastResponse = $this->post($xml, 'DrawBack');
    }

    /**
     * PARTIAL DRAWBACK (KISMİ İADE)
     */
    public function partialRefund(array $d): void
    {
        foreach (['remote_order_id', 'merchantOrderId', 'ref_ret_num', 'auth_code', 'transaction_id', 'amount'] as $k) {
            if (empty($d[$k])) {
                throw new RuntimeException("Missing field: {$k}");
            }
        }

        // Senin mevcut davranışın: (amount - 0.01) * 100
        $amount = (string)((((float)$d['amount']) - 0.01) * 100);

        $hash = $this->buildHash(
            $this->merchantId,
            (string)$d['merchantOrderId'],
            $amount,
            $this->username,
            $this->password
        );

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
        $this->lastResponse = $this->post($xml, 'PartialDrawback');
    }

    private function post(string $xml, string $type): array
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

    private function buildHash(
        string $merchantId,
        string $merchantOrderId,
        string $amount,
        string $username,
        string $password
    ): string
    {
        $hashedPassword = base64_encode(sha1($this->toIso($password), true));
        $data = $merchantId . $merchantOrderId . $amount . $username . $hashedPassword;
        return base64_encode(sha1($this->toIso($data), true));
    }

    private function toIso(string $str): string
    {
        $out = @iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $str);
        return $out !== false ? $out : $str;
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
            'SaleReversal' => [
                'root' => ['sBody', 'SaleReversalResponse', 'SaleReversalResult'],
            ],
            'DrawBack' => [
                'root' => ['sBody', 'DrawBackResponse', 'DrawBackResult'],
            ],
            'PartialDrawback' => [
                'root' => ['sBody', 'PartialDrawbackResponse', 'PartialDrawbackResult'],
            ],
        ];

        if (!isset($map[$type])) {
            return $this->fail('UNKNOWN_TYPE', $type);
        }

        $root = $this->getPath($response, $map[$type]['root']);

        $successRaw = $root['Success'] ?? false;

        $success = (
            $successRaw === true ||
            $successRaw === 'true' ||
            $successRaw === 1 ||
            $successRaw === '1'
        );

        if (!$success) {
            return [
                'status' => false,
                'code' => $root['Results']['Result']['ErrorCode'] ?? 'UNKNOWN_ERROR',
                'message' => $root['Results']['Result']['ErrorMessage'] ?? 'Unknown error',
            ];
        }

        $code = (string)($root['Value']['ResponseCode'] ?? '');
        $status = ($code === '00');

        return [
            'status' => $status,
            'code' => $code,
            'message' => $root['Value']['ResponseMessage']
                ?? $root['Results']['Result']['ResponseMessage']
                    ?? '',
        ];
    }

    public function buildPaymentHash(
        string $merchantOrderId,
        string $amount,
        string $okUrl,
        string $failUrl
    ): string {
        $password = $this->password;
        $hashedPassword = base64_encode(sha1(mb_convert_encoding($password, 'ISO-8859-9', 'UTF-8'), true));
        $hashStr = $this->merchantId . $merchantOrderId . $amount . $okUrl . $failUrl . $this->username . $hashedPassword;

        // 4️⃣ HashData
        return base64_encode(
            sha1(mb_convert_encoding($hashStr, 'ISO-8859-9', 'UTF-8'), true)
        );
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
        ];
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        return json_decode($json, true);
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
            if (!isset($data[$key])) {
                return [];
            }
            $data = $data[$key];
        }
        return $data;
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
