<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Core\MPPos;
use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Payload\RefundPayload;
use MPPos\DTO\Payload\CancelPayload;
use MPPos\DTO\Result\RefundResult;
use MPPos\Exceptions\BankException;

final class ParamPosMapper
{
    private array $cfg;

    public function __construct(array $config)
    {
        $this->cfg = $config;
    }

    public function map3DInit(PaymentPayload $p): array
    {
        foreach (['client_code','guid','username','password'] as $k) {
            if (empty($this->cfg[$k])) {
                throw new BankException("ParamPOS config '{$k}' missing");
            }
        }

        $taksit = (string)($p->installment ?? 1);
        $islemTutar  = $this->formatAmount($p->amount);
        $toplamTutar = $islemTutar;

        // TP_WMD_UCD HASH
        $hashStr =
            $this->cfg['client_code'] .
            $this->cfg['guid'] .
            $taksit .
            $islemTutar .
            $toplamTutar .
            $p->orderId;

        return [
            'd' => [
                'Code'   => $this->cfg['client_code'],
                'User'   => $this->cfg['username'],
                'Pass'   => $this->cfg['password'],
                'GUID'   => $this->cfg['guid'],

                'Siparis_ID'   => $p->orderId,
                'Islem_Tutar'  => $islemTutar,
                'Toplam_Tutar' => $toplamTutar,
                'Taksit'       => $taksit,

                'Islem_Guvenlik_Tip' =>
                    $p->paymentMethod === MPPos::NONSECURE ? 'NS' : '3D',

                'KK_No'     => $p->cardNumber,
                'KK_SK_Ay'  => $p->expiryMonth,
                'KK_SK_Yil' => $p->expiryYear,
                'KK_CVC'    => $p->cvv,
                'KK_Sahibi' => $p->cardHolder,

                'Basarili_URL' => $p->successUrl,
                'Hata_URL'     => $p->failUrl,

                'Islem_Hash' => $this->sha2b64($hashStr),
            ],
        ];
    }

    public function map3DComplete(array $cb): array
    {
        // HASH doğrulama
        $expected = base64_encode(sha1(
            $cb['islemGUID'] .
            $cb['md'] .
            $cb['mdStatus'] .
            $cb['orderId'] .
            strtolower($this->cfg['guid']),
            true
        ));

        if ($expected !== ($cb['islemHash'] ?? '')) {
            throw new BankException('3D callback hash mismatch');
        }

        if (!in_array((int)$cb['mdStatus'], [1,2,3,4], true)) {
            throw new BankException('3D doğrulama başarısız');
        }

        return [
            'd' => [
                'Code' => $this->cfg['client_code'],
                'User' => $this->cfg['username'],
                'Pass' => $this->cfg['password'],
                'GUID' => $this->cfg['guid'],

                'UCD_MD'    => $cb['md'],
                'Siparis_ID'=> $cb['orderId'],
                'Islem_GUID'=> $cb['islemGUID'],
            ],
        ];
    }

    public function mapRefund(RefundPayload $p): array
    {
        return [
            'd' => [
                'Code'   => $this->cfg['client_code'],
                'User'   => $this->cfg['username'],
                'Pass'   => $this->cfg['password'],
                'GUID'   => $this->cfg['guid'],
                'Order_ID' => $p->orderId,
                'Amount'   => $this->formatAmount($p->amount),
            ],
        ];
    }

    public function mapCancel(CancelPayload $p): array
    {
        return [
            'd' => [
                'Code'     => $this->cfg['client_code'],
                'User'     => $this->cfg['username'],
                'Pass'     => $this->cfg['password'],
                'GUID'     => $this->cfg['guid'],
                'Order_ID' => $p->orderId,
            ],
        ];
    }

    public function mapRefundResult(array $resp): RefundResult
    {
        $r = $resp['TP_Islem_Iptal_IadeResult'] ?? null;
        if (!$r || (int)$r['Sonuc'] <= 0) {
            throw new BankException((string)($r['Sonuc_Str'] ?? 'İade/iptal başarısız'));
        }

        return new RefundResult(true, $resp);
    }

    private function sha2b64(string $data): string
    {
        return base64_encode(hash('sha256', $data, true));
    }

    private function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', '');
    }
}
