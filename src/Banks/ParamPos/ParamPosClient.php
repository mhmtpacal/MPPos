<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Contracts\BankClientInterface;
use MPPos\Exceptions\BankException;
use SoapClient;
use SoapFault;


final class ParamPosClient implements BankClientInterface
{
    private SoapClient $soap;

    public function __construct(string $env, array $config)
    {
        $wsdl = $config['wsdl'] ?? null;
        if (!$wsdl) {
            throw new BankException('ParamPOS wsdl is required in bankConfig[wsdl]');
        }

        $timeout = (int)($config['timeout'] ?? 20);

        // SoapClient timeout ayarları:
        ini_set('default_socket_timeout', (string)$timeout);

        $this->soap = new SoapClient($wsdl, [
            'trace'      => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'connection_timeout' => $timeout,
        ]);
    }

    public function request(string $operation, array $payload): array
    {
        // Bu proje için SOAP kullanacağız, request()’i kullanmıyoruz.
        return $this->soap($operation, $payload);
    }

    public function soap(string $operation, array $payload): array
    {
        try {
            $res = $this->soap->__soapCall($operation, [$payload]);

            // SOAP response objesini array’e normalize et
            return json_decode(json_encode($res, JSON_UNESCAPED_UNICODE), true) ?: [];
        } catch (SoapFault $e) {
            throw new BankException("ParamPOS SOAP error ({$operation}): " . $e->getMessage(), 0, $e);
        }
    }
}
