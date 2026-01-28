<?php
declare(strict_types=1);

namespace MPPos\Banks\KuveytTurk;

use MPPos\Core\AbstractPos;
use MPPos\Core\PosException;

final class KuveytTurkAdapter extends AbstractPos
{
    private KuveytTurkClient $client;
    private KuveytTurkMapper $mapper;

    public function __construct()
    {
        $this->mapper = new KuveytTurkMapper();
    }

    private function boot(): void
    {
        foreach (['merchant_id', 'customer_id', 'username', 'password'] as $k) {
            if (empty($this->account[$k])) {
                throw PosException::missingAccount($k);
            }
        }

        // Endpoint opsiyonel: endpoint_test / endpoint_prod / endpoint
        $endpoint = $this->resolveEndpoint();

        $this->client = new KuveytTurkClient(
            (string)$this->account['merchant_id'],
            (string)$this->account['customer_id'],
            (string)$this->account['username'],
            (string)$this->account['password'],
            $endpoint,
            (int)($this->account['timeout'] ?? 40)
        );
    }

    private function resolveEndpoint(): string
    {
        // Kuveyt Türk için net ve sabit kurallar
        if ($this->test === true) {
            return 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
        }

        return 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';
    }


    public function payment(): void
    {
        // Şimdilik KuveytTürk’te payment akışı bu kütüphanede yok.
        $this->lastResponse = [
            'ok'        => false,
            'code'      => 'NOT_IMPLEMENTED',
            'message'   => 'payment() is not implemented for kuveytturk yet',
            'http_code' => 0,
            'type'      => null,
            'provider'  => 'kuveytturk',
        ];
    }

    public function cancel(): void
    {
        $this->boot();
        $this->client->cancel($this->mapper->cancel($this->payload));
        $this->lastResponse = $this->client->getResponse();
    }

    public function refund(): void
    {
        $this->boot();
        $this->client->refund($this->mapper->refund($this->payload));
        $this->lastResponse = $this->client->getResponse();
    }

    public function partialRefund(): void
    {
        $this->boot();
        $this->client->partialRefund($this->mapper->partialRefund($this->payload));
        $this->lastResponse = $this->client->getResponse();
    }
}
