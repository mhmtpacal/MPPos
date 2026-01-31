<?php
declare(strict_types=1);

namespace MPPos\Banks\ParamPos;

use MPPos\Core\PosException;

final class ParamPosMapper
{
    public function map3DInit(array $payload, array $account): array
    {
        foreach ([
                     'client_code','username','password','guid'
                 ] as $k) {
            if (empty($account[$k])) {
                throw new PosException("ParamPOS account[$k] missing");
            }
        }

        foreach ([
                     'order_id','amount','card_holder','card_number',
                     'exp_month','exp_year','cvv','phone',
                     'success_url','fail_url','ip'
                 ] as $k) {
            if (empty($payload[$k])) {
                throw new PosException("Payload[$k] missing");
            }
        }

        $amount  = number_format($payload['amount'] / 100, 2, ',', '');
        $taksit  = (string)($payload['installment'] ?? '1');

        $hash = $this->hash(
            $account['client_code'],
            $account['guid'],
            $taksit,
            $amount,
            $amount,
            $payload['order_id']
        );

        return [
            'G' => [
                'CLIENT_CODE'     => $account['client_code'],
                'CLIENT_USERNAME' => $account['username'],
                'CLIENT_PASSWORD' => $account['password'],
            ],
            'GUID'                => $account['guid'],
            'KK_Sahibi'           => $payload['card_holder'],
            'KK_No'               => $payload['card_number'],
            'KK_SK_Ay'            => $payload['exp_month'],
            'KK_SK_Yil'           => $payload['exp_year'],
            'KK_CVC'              => $payload['cvv'],
            'KK_Sahibi_GSM'       => $payload['phone'],
            'Hata_URL'            => $payload['fail_url'],
            'Basarili_URL'        => $payload['success_url'],
            'Siparis_ID'          => $payload['order_id'],
            'Siparis_Aciklama'    => $payload['description'] ?? '',
            'Taksit'              => $taksit,
            'Islem_Tutar'         => $amount,
            'Toplam_Tutar'        => $amount,
            'Islem_Hash'          => $hash,
            'Islem_Guvenlik_Tip'  => 'NS',
            'IPAdr'               => $payload['ip'],
            'Ref_URL'             => $payload['success_url'],
        ];
    }

    private function hash(
        string $clientCode,
        string $guid,
        string $taksit,
        string $islemTutar,
        string $toplamTutar,
        string $siparisId
    ): string {
        $str = $clientCode.$guid.$taksit.$islemTutar.$toplamTutar.$siparisId;

        return base64_encode(
            sha1(
                mb_convert_encoding($str, 'ISO-8859-9'),
                true
            )
        );
    }
}
