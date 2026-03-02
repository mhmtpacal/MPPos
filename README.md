# MPPos

MPYazilim multi-bank POS kutuphanesi.

Bankalarin `payment`, `cancel`, `refund` ve `partialRefund` islemlerini tek API altinda toplar.

| Banka | Durum |
|---|---|
| KuveytTurk V2 | Active: Payment, Cancel, Refund, PartialRefund |
| Vakif Katilim | Active: Payment, Cancel, Refund, PartialRefund |
| ParamPOS | Active: Payment, Cancel, Refund, PartialRefund |

## Gereksinimler

- PHP `8.1+`

## Kurulum

```bash
composer require mpyazilim/mppos
```

## Temel Kullanim

```php
use MPPos\MPPos;
```

## KuveytTurk

### Payment

KuveytTurk tarafinda odeme 2 adimlidir:

1. `payment()` ile 3D HTML alinur
2. callback sonrasinda `completePayment()` ile provizyon alinur

```php
$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'merchantOrderId' => 'ORD-1001',
        'amount' => 149.90,
        'card_holder' => 'TEST USER',
        'card_number' => '5188961939192544',
        'exp_month' => '06',
        'exp_year' => '25',
        'cvv' => '929',
        'ok_url' => 'https://site.com/kuveyt-ok',
        'fail_url' => 'https://site.com/kuveyt-fail',
        'client_ip' => '1.2.3.4',
        'phone' => '5555555555',
        'email' => 'user@example.com',
    ])
    ->test(true);

$init = $pos->payment();

if ($init['ok']) {
    echo $init['html'];
}
```

### Payment Callback ve Provizyon

```php
$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'AuthenticationResponse' => $_POST['AuthenticationResponse'] ?? '',
    ])
    ->test(true);

$auth = $pos->parsePaymentResponse();

if ($auth['ok']) {
    $pos->completePayment();
    $response = $pos->getResponse();
}
```

### Cancel

```php
$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'remote_order_id' => '319800289',
        'merchantOrderId' => 'ORD-1001',
        'ref_ret_num' => '035617458943',
        'auth_code' => '412371',
        'transaction_id' => '458943',
    ]);

$pos->cancel();
$response = $pos->getResponse();
```

### Refund

```php
$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'remote_order_id' => '319800289',
        'merchantOrderId' => 'ORD-1001',
        'ref_ret_num' => '035617458943',
        'auth_code' => '412371',
        'transaction_id' => '458943',
        'amount' => 149.90,
    ]);

$pos->refund();
$response = $pos->getResponse();
```

### PartialRefund

```php
$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'remote_order_id' => '319800289',
        'merchantOrderId' => 'ORD-1001',
        'ref_ret_num' => '035617458943',
        'auth_code' => '412371',
        'transaction_id' => '458943',
        'amount' => 50.00,
    ]);

$pos->partialRefund();
$response = $pos->getResponse();
```

## Vakif Katilim

### Payment

Vakif Katilim tarafinda da 3D odeme 2 adimlidir:

1. `payment()` ile bankanin 3D HTML formu alinur
2. callback sonrasinda `completePayment()` ile provizyon alinur

```php
$pos = MPPos::vakifkatilim()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'merchantOrderId' => 'ORD-2001',
        'amount' => 149.90,
        'card_holder' => 'TEST USER',
        'card_number' => '5353550000958906',
        'exp_month' => '01',
        'exp_year' => '26',
        'cvv' => '741',
        'ok_url' => 'https://site.com/vakif-ok',
        'fail_url' => 'https://site.com/vakif-fail',
        'client_ip' => '1.2.3.4',
        'address' => [
            'Type' => '1',
            'Name' => 'Test User',
            'PhoneNumber' => '5555555555',
            'AddressId' => '1',
            'Email' => 'user@example.com',
        ],
    ])
    ->test(true);

$init = $pos->payment();

if ($init['ok']) {
    echo $init['html'];
}
```

### Payment Callback ve Provizyon

Vakif Katilim callback tarafinda genelde `ResponseMessage` icinde url-encoded XML doner.

```php
$pos = MPPos::vakifkatilim()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'ResponseMessage' => $_POST['ResponseMessage'] ?? '',
        'ResponseCode' => $_POST['ResponseCode'] ?? '',
        'MerchantOrderId' => $_POST['MerchantOrderId'] ?? '',
        'OrderId' => $_POST['OrderId'] ?? '',
        'MD' => $_POST['MD'] ?? '',
        'HashData' => $_POST['HashData'] ?? '',
    ])
    ->test(true);

$auth = $pos->parsePaymentResponse();

if ($auth['ok']) {
    $pos->completePayment();
    $response = $pos->getResponse();
}
```

### Cancel

```php
$pos = MPPos::vakifkatilim()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'merchantOrderId' => 'ORD-2001',
        'remote_order_id' => '15161',
        'amount' => 149.90,
    ]);

$pos->cancel();
$response = $pos->getResponse();
```

### Refund

```php
$pos = MPPos::vakifkatilim()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'merchantOrderId' => 'ORD-2001',
        'remote_order_id' => '15161',
    ]);

$pos->refund();
$response = $pos->getResponse();
```

### PartialRefund

```php
$pos = MPPos::vakifkatilim()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username' => '...',
        'password' => '...',
    ])
    ->payload([
        'merchantOrderId' => 'ORD-2001',
        'remote_order_id' => '15161',
        'amount' => 50.00,
    ]);

$pos->partialRefund();
$response = $pos->getResponse();
```

Not: Vakif Katilim test URL'leri dokumanda acik verilmedigi icin gerekirse `account()` icine
`payment_url`, `provision_url`, `cancel_url`, `refund_url`, `partial_refund_url` override alanlari verilebilir.

## ParamPOS

### Payment

```php
$pos = MPPos::parampos()
    ->account([
        'client_code' => '...',
        'username' => '...',
        'password' => '...',
        'guid' => '...',
    ])
    ->payload([
        'order_id' => 'ORD-3001',
        'amount' => 14990,
        'card_holder' => 'TEST USER',
        'card_number' => '4546711234567894',
        'exp_month' => '12',
        'exp_year' => '26',
        'cvv' => '000',
        'success_url' => 'https://site.com/param-ok',
        'fail_url' => 'https://site.com/param-fail',
        'phone' => '5555555555',
        'ip' => '1.2.3.4',
    ]);

$response = $pos->payment();
```

### Payment Complete3D

```php
$response = MPPos::parampos()
    ->account([
        'client_code' => '...',
        'username' => '...',
        'password' => '...',
        'guid' => '...',
    ])
    ->complete3D(
        ucdMd: $_POST['md'] ?? '',
        islemGuid: $_POST['islemGUID'] ?? '',
        orderId: $_POST['orderId'] ?? ''
    );
```

### Cancel

```php
$pos = MPPos::parampos()
    ->account([
        'client_code' => '...',
        'username' => '...',
        'password' => '...',
        'guid' => '...',
    ])
    ->payload([
        'order_id' => 'ORD-3001',
        'amount' => 14990,
    ]);

$pos->cancel();
$response = $pos->getResponse();
```

### Refund

```php
$pos = MPPos::parampos()
    ->account([
        'client_code' => '...',
        'username' => '...',
        'password' => '...',
        'guid' => '...',
    ])
    ->payload([
        'order_id' => 'ORD-3001',
        'amount' => 14990,
    ]);

$pos->refund();
$response = $pos->getResponse();
```

### PartialRefund

```php
$pos = MPPos::parampos()
    ->account([
        'client_code' => '...',
        'username' => '...',
        'password' => '...',
        'guid' => '...',
    ])
    ->payload([
        'order_id' => 'ORD-3001',
        'amount' => 5000,
    ]);

$pos->partialRefund();
$response = $pos->getResponse();
```

## Response

Tum banka islemlerinden sonra sonuc verisi `getResponse()` ile alinabilir.

```php
Array
(
    [ok] => true
    [code] => 00
    [message] => PROVIZYON ALINDI
    [http_code] => 200
    [type] => Sale
    [provider] => vakifkatilim
)
```
