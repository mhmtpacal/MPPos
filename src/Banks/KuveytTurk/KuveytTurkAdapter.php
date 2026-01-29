<?php
declare(strict_types=1);

namespace MPPos\Banks\KuveytTurk;

use MPPos\Contracts\PosAdapterInterface;
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

    // ========= INTERNAL =========

    private function boot(): void
    {
        foreach (['merchant_id', 'customer_id', 'username', 'password'] as $k) {
            if (empty($this->account[$k])) {
                throw PosException::missingAccount($k);
            }
        }

        $endpoint = $this->test
            ? 'https://boatest.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic'
            : 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic';

        $this->client = new KuveytTurkClient(
            (string)$this->account['merchant_id'],
            (string)$this->account['customer_id'],
            (string)$this->account['username'],
            (string)$this->account['password'],
            $endpoint,
            (int)($this->account['timeout'] ?? 40)
        );
    }

    // ========= ACTIONS =========

    public function payment(): array
    {
        file_put_contents('test2.txt','PAYMENT CALL: ' . spl_object_id($this));

        $this->boot();

        $data = $this->mapper->payment($this->payload);

        $hash = $this->client->buildPaymentHash(
            $data['MerchantOrderId'],
            $data['Amount'],
            $data['OkUrl'],
            $data['FailUrl'],
        );

        return [
            'action' => $this->test
                ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate'
                : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate',
            'method' => 'POST',
            'fields' => [
                'hidden' => [
                    'APIVersion' => 'TDV2.0.0',
                    'MerchantId' => $this->account['merchant_id'],
                    'CustomerId' => $this->account['customer_id'],
                    'UserName'   => $this->account['username'],
                    'HashData'   => $hash,
                    ...$data,
                ],
                'card_fields' => [
                    'CardNumber',
                    'CardExpireDateMonth',
                    'CardExpireDateYear',
                    'CardCVV2',
                    'CardHolderName',
                ],
            ],
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

    public function getResponse(): array
    {
        return $this->lastResponse ?? [
            'ok'        => false,
            'code'      => 'NO_REQUEST',
            'message'   => 'No transaction executed',
            'http_code' => 0,
            'type'      => null,
            'provider'  => 'kuveytturk',
        ];
    }
}
