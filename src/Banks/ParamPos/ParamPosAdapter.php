<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Core\AbstractPos;
use MPPos\Core\PosException;

final class ParamPosAdapter extends AbstractPos
{
    private ParamPosClient $client;
    private ParamPosMapper $mapper;

    public function __construct()
    {
        $this->client = new ParamPosClient();
        $this->mapper = new ParamPosMapper();
    }

    /**
     * 3D Init (TP_WMD_UCD)
     */
    public function payment(): array
    {
        if (!$this->payload || !$this->account) {
            throw new PosException('Payload or account not set');
        }

        $request = $this->mapper->map3DInit($this->payload, $this->account);

        $this->lastResponse = $this->client->call('TP_WMD_UCD', $request);

        return $this->lastResponse;
    }

    /**
     * 3D Complete (TP_WMD_Pay)
     * Not in interface; optional helper method.
     */
    public function complete3D(string $ucdMd, string $islemGuid, string $orderId): array
    {
        if (!$this->account) {
            throw new PosException('Account not set');
        }

        foreach (['client_code', 'username', 'password', 'guid'] as $k) {
            if (empty($this->account[$k])) {
                throw new PosException("ParamPOS account[$k] missing");
            }
        }

        $request = [
            'G' => [
                'CLIENT_CODE'     => $this->account['client_code'],
                'CLIENT_USERNAME' => $this->account['username'],
                'CLIENT_PASSWORD' => $this->account['password'],
            ],
            'GUID'       => $this->account['guid'],
            'UCD_MD'     => $ucdMd,
            'Islem_GUID' => $islemGuid,
            'Siparis_ID' => $orderId,
        ];

        $this->lastResponse = $this->client->call('TP_WMD_Pay', $request);

        return $this->lastResponse;
    }

    public function cancel(): void
    {
        $this->ensureRefundPayload();

        $this->refundInternal(
            'IPTAL',
            (string)$this->payload['order_id'],
            $this->payload['amount']
        );
    }

    public function refund(): void
    {
        $this->ensureRefundPayload();

        $this->refundInternal(
            'IADE',
            (string)$this->payload['order_id'],
            $this->payload['amount']
        );
    }

    public function partialRefund(): void
    {
        $this->ensureRefundPayload();

        // ParamPOS aynı servisle kısmi iade yapıyor: IADE + Tutar
        $this->refundInternal(
            'IADE',
            (string)$this->payload['order_id'],
            $this->payload['amount']
        );
    }

    private function ensureRefundPayload(): void
    {
        if (!$this->account) {
            throw new PosException('Account not set');
        }

        if (!$this->payload) {
            throw new PosException('Payload not set');
        }

        foreach (['order_id', 'amount'] as $k) {
            if (!isset($this->payload[$k]) || $this->payload[$k] === '' || $this->payload[$k] === null) {
                throw new PosException("Payload[$k] missing");
            }
        }
    }

    private function refundInternal(string $durum, string $orderId, int|float $amount): void
    {
        $request = $this->mapper->mapCancelRefund(
            $this->account,
            $durum,
            $orderId,
            $amount
        );

        $this->lastResponse = $this->client->call(
            'TP_Islem_Iptal_Iade_Kismi2',
            $request
        );
    }

    public function getResponse(): array
    {
        return $this->lastResponse ?? [
            'ok'        => false,
            'code'      => 'NO_REQUEST',
            'message'   => 'No transaction executed',
            'http_code' => 0,
            'type'      => null,
            'provider'  => 'parampos',
        ];
    }
}
