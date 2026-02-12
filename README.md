# MPYazilim RedisLibrary

Basit Redis wrapper kutuphanesi.

## Kurulum

```bash
composer require mpyazilim/redislibrary
```

## Konfigurasyon

Kutuphane asagidaki `env` degerlerini kullanir:

- `redis.host`
- `redis.port`
- `redis.password`
- `redis.db`
- `redis.persistent`
- `redis.active` (tum Redis fonksiyonlari icin)

`redis.active=false` ise tum Redis fonksiyonlari (RedisRemember dahil) direkt `false` doner.

`.env` ornegi:

```env
redis.active = true
redis.host = 127.0.0.1
redis.port = 6379
redis.password = pass
redis.db = 0
redis.persistent = 1
```

## Kullanim

Tum temel islemler statik olarak cagirilir:

Kutuphaneyi kurunca `Redis()` fonksiyonu Composer autoload ile otomatik gelir.

## RedisRemember

`RedisRemember` callback sonucunu cache'ler. Key varsa direkt doner, yoksa callback calisir ve Redis'e yazar.

```php

$users = Redis()::RedisRemember('users:list', 300, function () {
    // Ornek: DB sorgusu
    return [
        ['id' => 1, 'name' => 'Ali'],
        ['id' => 2, 'name' => 'Ayse'],
    ];
});
```

Callback'e disaridan degisken gecmek icin `use (...)` kullanabilirsin:

```php

$status = 'active';
$limit = 20;

$users = Redis()::RedisRemember("users:list:$status:$limit", 300, function () use ($status, $limit) {
    // Ornek: parametreli sorgu
    return userRepository()->listByStatus($status, $limit);
});
```

## Constructor Override (Opsiyonel)

Ihtiyac olursa sadece `domain` ve `password` override edilebilir:

```php
$redis = new \MPYazilim\RedisLibrary('example.com', 'custom-password');
```

Parametreler verilmezse varsayilan olarak env degerleri kullanilir.

Fonksiyon ilk cagride baglanti olusturur ve sonraki cagrilarda ayni instance'i kullanir.