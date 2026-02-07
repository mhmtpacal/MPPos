<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Core\PosException;
use DOMDocument;
use DOMXPath;
use DOMNode;

final class ParamPosClient
{
    private string $endpoint =
        'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx?wsdl';

    private const RAW_XML_FIELDS = [
        'UCD_HTML',
        'RedirectHtml',
        'Bank_Extra',
    ];

    private const RAW_RESPONSE_FIELDS = [
        'UCD_HTML',
        'Bank_Extra',
    ];

    public function call(string $action, array $data): array
    {
        $xml = $this->buildSoap($action, $data);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "https://turkpos.com.tr/'.$action.'"',
            ],
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new PosException('Curl error: '.curl_error($ch));
        }

        return $this->parseResponse($response);
    }

    /* ------------------------------------------------------------------ */

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
                continue;
            }

            if (in_array($k, self::RAW_XML_FIELDS, true)) {
                $xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
            } else {
                $xml .= "<{$k}>".htmlspecialchars((string)$v, ENT_XML1)."</{$k}>";
            }
        }

        return $xml;
    }

    /* ------------------------------------------------------------------ */

    private function parseResponse(string $xml): array
    {
        libxml_use_internal_errors(true);

        $xml = trim($xml);
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        $dom = new DOMDocument('1.0', 'utf-8');
        if (!$dom->loadXML($xml)) {
            throw new PosException(
                'XML parse failed: '.(libxml_get_last_error()?->message ?? 'unknown')
            );
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('t', 'https://turkpos.com.tr/');

        // 🔑 ParamPOS birden fazla Result döndürebilir → en sondaki gerçek sonuçtur
        $nodes = $xpath->query('//*[contains(local-name(), "Result")]');

        if ($nodes->length === 0) {
            throw new PosException('ParamPOS result node not found');
        }

        $resultNode = $nodes->item($nodes->length - 1);

        return $this->domNodeToArray($resultNode, $dom);
    }

    private function domNodeToArray(DOMNode $node, DOMDocument $dom): array
    {
        $output = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $name = $child->nodeName;

            // 🔥 HTML içeren alanlar
            if (in_array($name, self::RAW_RESPONSE_FIELDS, true)) {
                $html = html_entity_decode(
                    $dom->saveHTML($child),
                    ENT_QUOTES | ENT_HTML5
                );

                // wrapper tag temizle
                $html = preg_replace(
                    '#^<'.$name.'>|</'.$name.'>$#',
                    '',
                    $html
                );

                // ParamPOS ~ bug
                $output[$name] = trim($html, "~ \n\r\t");
                continue;
            }

            if ($child->childNodes->length > 1) {
                $output[$name] = $this->domNodeToArray($child, $dom);
            } else {
                $output[$name] = trim($child->textContent);
            }
        }

        return $output;
    }
}
