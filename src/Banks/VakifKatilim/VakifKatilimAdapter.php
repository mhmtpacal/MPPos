<?php
declare(strict_types=1);

namespace MPPos\Banks\VakifKatilim;

use MPPos\Core\AbstractPos;
use MPPos\Core\PosException;

final class VakifKatilimAdapter extends AbstractPos
{
    private VakifKatilimClient $client;
    private VakifKatilimMapper $mapper;

    public function __construct()
    {
        $this->mapper = new VakifKatilimMapper();
    }

    public function payment(): array
    {
        $this->boot();

        $data = $this->mapper->payment($this->payload);
        $hash = $this->client->buildPaymentHash(
            $data['MerchantOrderId'],
            $data['Amount'],
            $data['OkUrl'],
            $data['FailUrl'],
        );

        $fields = [
            'HashData' => $hash,
            'MerchantId' => (string)$this->account['merchant_id'],
            'SubMerchantId' => (string)($this->account['sub_merchant_id'] ?? 0),
            'CustomerId' => (string)$this->account['customer_id'],
            'UserName' => (string)$this->account['username'],
            ...$data,
        ];

        $result = $this->client->init3D($fields, $this->paymentGateUrl());

        return [
            'ok' => (bool)($result['ok'] ?? false),
            'http_code' => (int)($result['http_code'] ?? 0),
            'html' => (string)($result['body'] ?? ''),
            'error' => (string)($result['error'] ?? ''),
            'request' => $fields,
            'provider' => 'vakifkatilim',
            'type' => 'Init3D',
            'parsed' => $result['parsed'] ?? [],
        ];
    }

    public function parsePaymentResponse(): array
    {
        $this->boot();
        return $this->client->parsePaymentResponse($this->payload);
    }

    public function completePayment(): void
    {
        $this->boot();

        $auth = $this->client->parsePaymentResponse($this->payload);
        if (!($auth['ok'] ?? false)) {
            $this->lastResponse = $auth;
            return;
        }

        $data = $this->mapper->provision($this->payload, $auth);
        $fields = [
            'HashData' => $this->client->buildApiHash(),
            'MerchantId' => (string)$this->account['merchant_id'],
            'SubMerchantId' => (string)($this->account['sub_merchant_id'] ?? 0),
            'CustomerId' => (string)$this->account['customer_id'],
            'UserName' => (string)$this->account['username'],
            ...$data,
        ];

        $this->client->provision($fields, $this->provisionGateUrl());
        $this->lastResponse = $this->client->getResponse();
    }

    public function cancel(): void
    {
        $this->boot();

        $data = $this->mapper->cancel($this->payload);
        $fields = [
            'HashData' => $this->client->buildApiHash(),
            'MerchantId' => (string)$this->account['merchant_id'],
            'SubMerchantId' => (string)($this->account['sub_merchant_id'] ?? 0),
            'CustomerId' => (string)$this->account['customer_id'],
            'UserName' => (string)$this->account['username'],
            ...$data,
        ];

        $this->client->cancel($fields, $this->cancelUrl());
        $this->lastResponse = $this->client->getResponse();
    }

    public function refund(): void
    {
        $this->boot();

        $data = $this->mapper->refund($this->payload);
        $fields = [
            'HashData' => $this->client->buildApiHash(),
            'MerchantId' => (string)$this->account['merchant_id'],
            'SubMerchantId' => (string)($this->account['sub_merchant_id'] ?? 0),
            'CustomerId' => (string)$this->account['customer_id'],
            'UserName' => (string)$this->account['username'],
            ...$data,
        ];

        $this->client->refund($fields, $this->refundUrl());
        $this->lastResponse = $this->client->getResponse();
    }

    public function partialRefund(): void
    {
        $this->boot();

        $data = $this->mapper->partialRefund($this->payload);
        $fields = [
            'HashData' => $this->client->buildApiHash(),
            'MerchantId' => (string)$this->account['merchant_id'],
            'SubMerchantId' => (string)($this->account['sub_merchant_id'] ?? 0),
            'CustomerId' => (string)$this->account['customer_id'],
            'UserName' => (string)$this->account['username'],
            ...$data,
        ];

        $this->client->partialRefund($fields, $this->partialRefundUrl());
        $this->lastResponse = $this->client->getResponse();
    }

    public function getResponse(): array
    {
        return $this->lastResponse ?? [
            'ok' => false,
            'code' => 'NO_REQUEST',
            'message' => 'No transaction executed',
            'http_code' => 0,
            'type' => null,
            'provider' => 'vakifkatilim',
        ];
    }

    private function boot(): void
    {
        foreach (['merchant_id', 'customer_id', 'username', 'password'] as $key) {
            if (empty($this->account[$key])) {
                throw PosException::missingAccount($key);
            }
        }

        $this->client = new VakifKatilimClient(
            (string)$this->account['merchant_id'],
            (string)$this->account['customer_id'],
            (string)$this->account['username'],
            (string)$this->account['password'],
            (int)($this->account['timeout'] ?? 40),
        );
    }

    private function paymentGateUrl(): string
    {
        if (!empty($this->account['payment_url'])) {
            return (string)$this->account['payment_url'];
        }

        return $this->test
            ? 'https://boatest.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelPayGate';
    }

    private function provisionGateUrl(): string
    {
        if (!empty($this->account['provision_url'])) {
            return (string)$this->account['provision_url'];
        }

        return $this->test
            ? 'https://boatest.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/ThreeDModelProvisionGate';
    }

    private function cancelUrl(): string
    {
        if (!empty($this->account['cancel_url'])) {
            return (string)$this->account['cancel_url'];
        }

        return $this->test
            ? 'https://boatest.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/SaleReversal';
    }

    private function refundUrl(): string
    {
        if (!empty($this->account['refund_url'])) {
            return (string)$this->account['refund_url'];
        }

        return $this->test
            ? 'https://boatest.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/DrawBack';
    }

    private function partialRefundUrl(): string
    {
        if (!empty($this->account['partial_refund_url'])) {
            return (string)$this->account['partial_refund_url'];
        }

        return $this->test
            ? 'https://boatest.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack'
            : 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home/PartialDrawBack';
    }
}
