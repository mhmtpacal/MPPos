<?php
declare(strict_types=1);

namespace MPPos\Contracts;

use MPPos\Core\Capabilities;
use MPPos\DTO\Payload\PaymentPayload;
use MPPos\DTO\Payload\RefundPayload;
use MPPos\DTO\Payload\CancelPayload;
use MPPos\DTO\Result\PaymentResult;
use MPPos\DTO\Result\RefundResult;

interface PosAdapterInterface
{
    public function payment(PaymentPayload $payload): PaymentResult;

    public function refund(RefundPayload $payload): RefundResult;

    public function cancel(CancelPayload $payload): RefundResult;

    public function capabilities(): Capabilities;
}
