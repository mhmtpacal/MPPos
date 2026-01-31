<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Exceptions\PosException;

final class ParamPosClient
{
    private string $endpoint =
        'https://testposws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx?wsdl';

    public function call(string $action, array $data): array
    {
        $xml = $this->buildSoap($action, $data);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => [
                'Content-Type: text/xml; charset=utf-8'
            ],
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_TIMEOUT         => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new PosException(curl_error($ch));
        }

        return $this->parseResponse($response);
    }

    private function buildSoap(string $action, array $data): string
    {
        $body = $this->arrayToXml($data);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <{$action} xmlns="https://turkpos.com.tr/">
            {$body}
        </{$action}>
    </soap:Body>
</soap:Envelope>
XML;
    }

    private function arrayToXml(array $data): string
    {
        $xml = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $xml .= "<{$k}>".$this->arrayToXml($v)."</{$k}>";
            } else {
                $xml .= "<{$k}>".htmlspecialchars((string)$v)."</{$k}>";
            }
        }
        return $xml;
    }

    private function parseResponse(string $xml): array
    {
        $sxe = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$sxe) {
            throw new PosException('Invalid XML response');
        }

        $json = json_decode(json_encode($sxe), true);

        return $json['soap:Body'] ?? $json;
    }
}
