<?php
declare(strict_types=1);

namespace MPPos\DTO\Payload;

use MPPos\Exceptions\ValidationException;
use MPPos\Support\Arr;
use MPPos\Support\Amount;
use MPPos\Support\Validator;

final class PaymentPayload
{
    public readonly string $orderId;
    public readonly int $amount;
    public readonly string $currency;
    public readonly string $successUrl;
    public readonly string $failUrl;
    public readonly string $email;
    public readonly string $phone;
    public readonly string $ip;
    public readonly string $cardNumber;
    public readonly string $expiryMonth;
    public readonly string $expiryYear;
    public readonly string $cvv;
    public readonly string $cardHolder;


    public function __construct(array $data)
    {
        $this->orderId    = Arr::get($data, 'order_id');
        $this->amount     = Amount::fromInt($data['amount'] ?? 0);
        $this->currency   = Arr::get($data, 'currency', 'TRY');
        $this->successUrl = Arr::get($data, 'successUrl');
        $this->failUrl    = Arr::get($data, 'failUrl');
        $this->email      = Arr::get($data, 'email');
        $this->phone      = Arr::get($data, 'phone');
        $this->ip         = Arr::get($data, 'ip');

        $this->cardNumber  = Arr::get($data, 'card.number');
        $this->expiryMonth = Arr::get($data, 'card.exp_month');
        $this->expiryYear  = Arr::get($data, 'card.exp_year');
        $this->cvv         = Arr::get($data, 'card.cvv');
        $this->cardHolder  = Arr::get($data, 'card.holder');

        Validator::required([
            'order_id'   => $this->orderId,
            'amount'     => $this->amount,
            'successUrl' => $this->successUrl,
            'failUrl'    => $this->failUrl,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'ip'         => $this->ip,
        ]);
    }
}
