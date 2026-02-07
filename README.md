# MPPos
MPYazılım Çoklu Banka Pos Kütüphanesi

***


Bankaların **ödeme, iptal, iade ve kısmi iade** altyapılarını destekleyen,  
çoklu banka entegrasyonu için geliştirilmiş PHP kütüphanesi.

| Desteklenen Bankalar | Desteklenen Yöntemler                                  |
|----------------------|--------------------------------------------------------|
| KuveytTürk V2        | 🟡 Ödeme<br/>🟢 İptal<br/> 🟢 İade<br/> 🟢 Kısmi İade  |
| ParamPos             | 🟢 Ödeme<br/>🟢 İptal<br/> 🟢 İade<br/> 🟢 Kısmi İade  |


---

### Gereksinimler
- PHP **8.1** ve üzeri

### Kurulum

```bash
-> Stable sürüm
composer require mpyazilim/mppos
```

---

### Kullanım

```php
use MPPos\MPPos;

$pos = MPPos::kuveytturk()
    ->account([
        'merchant_id' => '...',
        'customer_id' => '...',
        'username'    => '...',
        'password'    => '...',
    ])
    ->payload([
        'remote_order_id' => '123',
        'merchantOrderId' => 'ORD-123',
        'ref_ret_num'     => '999999',
        'auth_code'       => 'ABC123',
        'transaction_id'  => '000001',
        'amount'          => 149.90,
    ])
    ->test(true);

$pos->refund();

$response = $pos->getResponse();
```
---
### Response

Tüm banka işlemlerinden sonra sonuç verisi `getResponse()` metodu ile alınır.  
Response formatı **tüm bankalar için standarttır**.

### Örnek Başarılı Response

```php
Array
(
    [ok] => true
    [code] => 00
    [message] => PROVİZYON VERİLDİ
    [http_code] => 200
    [type] => DrawBack
    [provider] => kuveytturk
)