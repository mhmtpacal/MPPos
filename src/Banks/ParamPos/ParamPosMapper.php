<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

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
        foreach ([
                     'client_code',
                     'guid',
                     'username',
                     'password'
                 ] as $k) {
            if (empty($this->cfg[$k])) {
                throw new BankException("ParamPOS bankConfig[{$k}] is required");
            }
        }

        $taksit = (string)($this->cfg['installment'] ?? '0');

        // Dokümana göre virgüllü format
        $islemTutar  = $this->formatAmount($p->amount);
        $toplamTutar = $islemTutar;

        // === HASH STRING (DOKÜMANA %100 UYUMLU) ===
        $hashStr =
            $this->cfg['client_code'] .
            $this->cfg['guid'] .
            $taksit .
            $islemTutar .
            $toplamTutar .
            $p->orderId .
            $p->failUrl .
            $p->successUrl;

        $islemHash = $this->sha2b64($hashStr);

        return [
            'd' => [
                // Kimlik
                'Code' => $this->cfg['client_code'],
                'User' => $this->cfg['username'],
                'Pass' => $this->cfg['password'],
                'GUID' => $this->cfg['guid'],

                // Sipariş
                'Siparis_ID'   => $p->orderId,
                'Islem_Tutar'  => $islemTutar,
                'Toplam_Tutar' => $toplamTutar,
                'Taksit'       => $taksit,

                // Kart (doküman zorunlu kılıyor)
                'KK_No'     => $p->cardNumber,
                'KK_SK_Ay'  => $p->expiryMonth,
                'KK_SK_Yil' => $p->expiryYear,
                'KK_CVC'    => $p->cvv,
                'KK_Sahibi' => $p->cardHolder,

                // URL
                'Basarili_URL' => $p->successUrl,
                'Hata_URL'     => $p->failUrl,

                // Hash
                'Islem_Hash' => $islemHash,
            ],
        ];
    }


    public function extractUcdHtml(array $resp): ?string
    {
        // Response içindeki path PDF’e göre değişir (TP_WMD_UCDResult / Sonuc / UCD_HTML)
        // Şimdilik olası anahtarları kontrol ediyorum:
        $candidates = [
            $resp['TP_WMD_UCDResult']['UCD_HTML'] ?? null,
            $resp['UCD_HTML'] ?? null,
            $resp['Sonuc']['UCD_HTML'] ?? null,
        ];

        foreach ($candidates as $v) {
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return null;
    }

    public function extractUcdUrl(array $resp): ?string
    {
        $candidates = [
            $resp['TP_WMD_UCDResult']['UCD_URL'] ?? null,
            $resp['UCD_URL'] ?? null,
            $resp['Sonuc']['UCD_URL'] ?? null,
        ];

        foreach ($candidates as $v) {
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return null;
    }

    public function mapRefund(RefundPayload $p): array
    {
        return [
            'd' => [
                'Code'   => $this->cfg['client_code'] ?? '',
                'User'   => $this->cfg['username'] ?? '',
                'Pass'   => $this->cfg['password'] ?? '',
                'GUID'   => $this->cfg['guid'] ?? '',
                'Order_ID' => $p->orderId,
                'Amount'   => $this->formatAmount($p->amount),
            ],
        ];
    }

    public function mapCancel(CancelPayload $p): array
    {
        return [
            'd' => [
                'Code'     => $this->cfg['client_code'] ?? '',
                'User'     => $this->cfg['username'] ?? '',
                'Pass'     => $this->cfg['password'] ?? '',
                'GUID'     => $this->cfg['guid'] ?? '',
                'Order_ID' => $p->orderId,
            ],
        ];
    }

    public function mapRefundResult(array $resp): RefundResult
    {
        // PDF’deki “Sonuc / Sonuc_Str / Islem_ID” gibi alanlara göre netleşecek.
        $ok = ($resp['Sonuc'] ?? null) == 1 || ($resp['Result'] ?? null) === '1';

        if (!$ok) {
            $msg = (string)($resp['Sonuc_Str'] ?? $resp['Message'] ?? 'Refund/Cancel failed');
            throw new BankException($msg);
        }

        return new RefundResult(true, $resp);
    }

    private function sha2b64(string $data): string
    {
        // sha256 raw -> base64
        return base64_encode(hash('sha256', $data, true));
    }

    private function formatAmount(int $amountCents): string
    {
        // 149900 -> "1499,00" (TR format)
        $val = $amountCents / 100;
        return number_format($val, 2, ',', '');
    }
}
