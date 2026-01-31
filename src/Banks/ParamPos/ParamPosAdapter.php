<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Core\AbstractPos;
use MPPos\Core\PosException;

final class ParamPosAdapter extends AbstractPos
{
    private ParamPosClient $client;
    private ParamPosMapper $mapper;

    public function __construct()
    {
        $this->client  = new ParamPosClient();
        $this->mapper  = new ParamPosMapper();
    }

    /**
     * 3D Init (TP_WMD_UCD)
     */
    public function payment(): array
    {
        if (!$this->payload || !$this->account) {
            throw new PosException('Payload or account not set');
        }

        $request = $this->mapper->map3DInit(
            $this->payload,
            $this->account
        );

        $this->lastResponse = $this->client->call(
            'TP_WMD_UCD',
            $request
        );

        return $this->lastResponse;
    }

    /**
     * 3D Complete (TP_WMD_Pay)
     */
    public function complete3D(
        string $ucdMd,
        string $islemGuid,
        string $orderId
    ): array {
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

        $this->lastResponse = $this->client->call(
            'TP_WMD_Pay',
            $request
        );

        return $this->lastResponse;
    }

    public function cancel(): void
    {
        return;
    }

    public function refund(): void
    {
        return;
    }

    public function partialRefund(): void
    {
        return;
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
