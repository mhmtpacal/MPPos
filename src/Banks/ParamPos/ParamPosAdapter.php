<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Core\Capabilities;
use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Payload\RefundPayload;
use MPPos\DTO\Payload\CancelPayload;
use MPPos\DTO\Result\PaymentResult;
use MPPos\DTO\Result\RefundResult;

final class ParamPosAdapter implements PosAdapterInterface
{
    private ParamPosClient $client;
    private ParamPosMapper $mapper;

    public function __construct(string $env, array $config)
    {
        $this->client = new ParamPosClient($env, $config);
        $this->mapper = new ParamPosMapper($config);
    }

    public function payment(PaymentPayload $payload): PaymentResult
    {
        // 3D başlat: TP_WMD_UCD (veya dokümanın söylediği operasyon)
        $req  = $this->mapper->map3DInit($payload);
        $resp = $this->client->soap('TP_WMD_UCD', $req);

        $html = $this->mapper->extractUcdHtml($resp);
        $url  = $this->mapper->extractUcdUrl($resp);

        // Bazı sistemler URL, bazıları HTML döndürebilir:
        if ($html) {
            return new PaymentResult(
                redirectRequired: false,
                redirectUrl: null,
                form: null,
                html: $html,
                raw: $resp
            );
        }

        if ($url) {
            return new PaymentResult(
                redirectRequired: true,
                redirectUrl: $url,
                form: null,
                html: null,
                raw: $resp
            );
        }

        return new PaymentResult(
            redirectRequired: false,
            redirectUrl: null,
            form: null,
            html: null,
            raw: $resp
        );
    }

    public function refund(RefundPayload $payload): RefundResult
    {
        $req  = $this->mapper->mapRefund($payload);
        $resp = $this->client->soap('TP_Islem_Iptal_Iade', $req);

        return $this->mapper->mapRefundResult($resp);
    }

    public function cancel(CancelPayload $payload): RefundResult
    {
        $req  = $this->mapper->mapCancel($payload);
        $resp = $this->client->soap('TP_Islem_Iptal_Iade', $req);

        return $this->mapper->mapRefundResult($resp);
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            merchantForm: false,
            hostedForm: false,
            partialRefund: true,
            cancel: true
        );
    }
}
