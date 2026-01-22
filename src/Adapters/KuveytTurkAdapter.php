<?php
declare(strict_types=1);

namespace MPPos\Adapters;

use MPPos\Contracts\PosAdapterInterface;
use MPPos\Banks\KuveytTurk;
use MPPos\DTO\FormPayload;
use MPPos\DTO\PosPayload;
use MPPos\Exceptions\PosException;
use MPPos\MPPos;
use MPPos\Support\Arr;

final class KuveytTurkAdapter implements PosAdapterInterface
{
    private KuveytTurk $client;

    public function __construct(array $config, string|bool $env)
    {
        $env = is_bool($env) ? ($env ? MPPos::ENV_TEST : MPPos::ENV_PROD) : $env;

        foreach (['merchantId', 'customerId', 'password'] as $k) {
            if (empty($config[$k])) {
                throw new PosException("KuveytTurk config missing: {$k}");
            }
        }

        $username = (string)($config['username'] ?? $config['userName'] ?? '');
        if ($username === '') {
            throw new PosException('KuveytTurk config missing: username');
        }

        $this->client = new KuveytTurk(
            env: (string)$env,
            merchantId: (string)$config['merchantId'],
            username: $username,
            password: (string)$config['password'],
            customerId: (string)$config['customerId']
        );
    }

    public function createMerchantForm(PosPayload $p): FormPayload
    {
        // Kuveyt Türk: Merchant UI "SecurePayment" endpoint’ine POST edilecek alanlar
        $fields = [
            'TransactionType' => 'Sale',
            'TokenType'       => 'SecureCommonPayment',
            'SuccessUrl'      => $p->successUrl,
            'FailUrl'         => $p->failUrl,
            'Amount'          => (string)$p->amount,
            'CurrencyCode'    => (string)($p->currencyCode ?? '0949'),
            'CardHolderIP'    => $p->ip,
            'MerchantOrderId' => $p->orderId,
            'Email'           => $p->email,
            'Language'        => (string)($p->language ?? 'TR'),
        ];

        if ($p->installmentCount !== null) {
            $fields['InstallmentCount'] = (string)$p->installmentCount;
        }
        if ($p->deferringCount !== null) {
            $fields['DeferringCount'] = (string)$p->deferringCount;
        }

        // Hash + MerchantId/UserName client tarafından inject ediliyor,
        // fakat Merchant UI formunda bankaya gidecek alanlar içinde de olmalı:
        // Bu yüzden “register servis payload” formatı ile aynı kalacak şekilde
        // register üzerinden token almadan form oluşturmak istiyorsan:
        // Kuveyt Türk UI endpoint’i token’lı kullanımda GET ile açılır.
        // Merchant UI POST akışında doküman/uygulama farkı olabiliyor.
        // En sağlam yöntem: registerToken() ile token al, sonra redirectUrl.
        //
        // Yine de POST form istiyorsan: SecurePaymentRegister’a değil SecurePayment’e POST ediyorsun.
        // Banka beklentisi aynı isimlerle ilerliyor. HashData şart.
        //
        // Bu nedenle: HashData + MerchantId + UserName ekliyoruz:
        $registerLike = $this->client->securePaymentRegister($fields);
        $token = Arr::pick($registerLike, ['Token', 'Data.Token', 'Result.Token'], '');

        if ($token === '') {
            // bazı ortamlarda register cevap formatı farklı olabilir.
            // raw döndürüp debug edebilmen için PosException fırlatıyorum.
            throw new PosException('Token could not be parsed from SecurePaymentRegister response');
        }

        // UI’ya en temiz yol token ile yönlendirme:
        return new FormPayload(
            action: $this->client->securePaymentUiUrl($token),
            method: 'GET',
            fields: [] // GET redirect
        );
    }

    public function registerToken(PosPayload $p): array
    {
        $payload = [
            'TransactionType' => 'Sale',
            'TokenType'       => 'SecureCommonPayment',
            'SuccessUrl'      => $p->successUrl,
            'FailUrl'         => $p->failUrl,
            'Amount'          => (string)$p->amount,
            'CurrencyCode'    => (string)($p->currencyCode ?? '0949'),
            'CardHolderIP'    => $p->ip,
            'MerchantOrderId' => $p->orderId,
            'Email'           => $p->email,
            'Language'        => (string)($p->language ?? 'TR'),
        ];

        if ($p->installmentCount !== null) $payload['InstallmentCount'] = (string)$p->installmentCount;
        if ($p->deferringCount !== null)   $payload['DeferringCount']   = (string)$p->deferringCount;

        $raw = $this->client->securePaymentRegister($payload);

        $token = Arr::pick($raw, ['Token', 'Data.Token', 'Result.Token'], '');
        if ($token === '') {
            throw new PosException('Token could not be parsed');
        }

        return [
            'token'       => $token,
            'redirectUrl' => $this->client->securePaymentUiUrl($token),
            'raw'         => $raw,
        ];
    }

    public function cancel(string $orderId, string $merchantOrderId): array
    {
        return $this->client->saleReversal([
            'SaleReversalType' => 'Cancel',
            'OrderId'          => $orderId,
            'MerchantOrderId'  => $merchantOrderId,
            'Amount'           => '0',
        ]);
    }

    public function refundFull(string $orderId, string $merchantOrderId): array
    {
        return $this->client->saleReversal([
            'SaleReversalType' => 'Drawback',
            'OrderId'          => $orderId,
            'MerchantOrderId'  => $merchantOrderId,
            'Amount'           => '0',
        ]);
    }

    public function refundPartial(string $orderId, string $merchantOrderId, int $amountCents): array
    {
        return $this->client->saleReversal([
            'SaleReversalType' => 'PartialDrawback',
            'OrderId'          => $orderId,
            'MerchantOrderId'  => $merchantOrderId,
            'Amount'           => (string)$amountCents,
        ]);
    }

    public function verifyCallback(array $data): bool
    {
        // Kuveyt Türk callback: en az ResponseCode kontrol
        // (Hash verify'ı istersen ekleriz; bankanın callback alanlarını tam netleştirmek lazım)
        return (($data['ResponseCode'] ?? null) === '00');
    }

    public function getName(): string
    {
        return 'KuveytTürk';
    }
}
