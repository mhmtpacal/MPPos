<?php
declare(strict_types=1);

namespace MPPos\DTO\Payload;

use MPPos\Support\Arr;
use MPPos\Support\Amount;
use MPPos\Support\Validator;

final class RefundPayload
{
    public readonly string $orderId;
    public readonly int $amount; // kuruş
    public readonly ?string $transactionId;
    public readonly ?string $reason;

    public function __construct(array $data)
    {
        $this->orderId        = Arr::get($data, 'order_id');
        $this->amount         = Amount::fromInt($data['amount'] ?? 0);
        $this->transactionId = $data['transaction_id'] ?? null;
        $this->reason         = $data['reason'] ?? null;

        Validator::required([
            'order_id' => $this->orderId,
            'amount'   => $this->amount,
        ]);
    }
}
