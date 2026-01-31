<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Core\PosException;

final class ParamPosClient
{
    private string $endpoint = 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx?wsdl';

    public function call(string $action, array $data): array
    {
        $xml = $this->buildSoap($action, $data);

        file_put_contents('parampos.txt',$xml);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "https://turkpos.com.tr/'.$action.'"'
            ],
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);

        file_put_contents('paramres.txt',$response);

        if ($response === false) {
            throw new PosException('Curl error: '.curl_error($ch));
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
        libxml_use_internal_errors(true);

        $xml = trim($xml);
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        $dom = new \DOMDocument('1.0', 'utf-8');
        if (!$dom->loadXML($xml)) {
            throw new PosException(
                'XML parse failed: '.(libxml_get_last_error()?->message ?? 'unknown')
            );
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('t', 'https://turkpos.com.tr/');

        // 🔑 ParamPOS tüm Result node'ları
        $query = '//*[contains(local-name(), "Result")]';

        /** @var \DOMNode|null $resultNode */
        $resultNode = $xpath->query($query)->item(0);

        if (!$resultNode) {
            throw new PosException('ParamPOS result node not found');
        }

        return $this->domNodeToArray($resultNode);
    }


    private function domNodeToArray(\DOMNode $node): array
    {
        $output = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $name = $child->nodeName;
            $value = trim($child->textContent);

            // 🔥 HTML-encoded XML alanlar
            if (in_array($name, ['UCD_HTML', 'Bank_Extra'], true)) {
                $output[$name] = html_entity_decode($value);
                continue;
            }

            if ($child->childNodes->length > 1) {
                $output[$name] = $this->domNodeToArray($child);
            } else {
                $output[$name] = $value;
            }
        }

        return $output;
    }


}
