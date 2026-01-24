<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Core\Capabilities;
use MPPos\Core\MPPos;
use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Payload\RefundPayload;
use MPPos\DTO\Payload\CancelPayload;
use MPPos\DTO\Result\PaymentResult;
use MPPos\DTO\Result\RefundResult;
use MPPos\Exceptions\BankException;

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
        // 3D / NS başlat
        $req  = $this->mapper->map3DInit($payload);
        $resp = $this->client->soap('TP_WMD_UCD', $req);

        $r = $resp['TP_WMD_UCDResult'] ?? null;
        if (!$r) {
            throw new BankException('ParamPOS invalid response');
        }

        if ((int)$r['Sonuc'] <= 0) {
            throw new BankException((string)($r['Sonuc_Str'] ?? 'ParamPOS error'));
        }

        $ucdHtml = trim((string)($r['UCD_HTML'] ?? ''));

        // NONSECURE → işlem bitti
        if ($ucdHtml === 'NONSECURE') {
            if ((int)($r['Islem_ID'] ?? 0) <= 0) {
                throw new BankException('NONSECURE işlem başarısız');
            }

            return PaymentResult::success(
                transactionId: (string)$r['Islem_ID'],
                raw: $resp
            );
        }

        // 3D → HTML basılacak
        return PaymentResult::html3D(
            html: $ucdHtml,
            raw: $resp
        );
    }

    /**
     * 3D dönüş sonrası çağrılır
     */
    public function complete3D(array $callback): PaymentResult
    {
        $req  = $this->mapper->map3DComplete($callback);
        $resp = $this->client->soap('TP_WMD_Pay', $req);

        $r = $resp['TP_WMD_PayResult'] ?? null;
        if (!$r) {
            throw new BankException('ParamPOS invalid 3D complete response');
        }

        if ((int)$r['Sonuc'] <= 0) {
            throw new BankException((string)($r['Sonuc_Str'] ?? '3D ödeme başarısız'));
        }

        return PaymentResult::success(
            transactionId: (string)$r['Islem_ID'],
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
