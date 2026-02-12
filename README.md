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

## Kullanim

Tum temel islemler statik olarak cagirilir:

```php
use MPYazilim\RedisLibrary;

RedisLibrary::set('user:1', ['name' => 'Mehmet'], 300);
$user = RedisLibrary::get('user:1');

$exists = RedisLibrary::has('user:1');
RedisLibrary::delete('user:1');
```

## RedisRemember

`RedisRemember` callback sonucunu cache'ler. Key varsa direkt doner, yoksa callback calisir ve Redis'e yazar.

```php
use MPYazilim\RedisLibrary;

$users = RedisLibrary::RedisRemember('users:list', 300, function () {
    // Ornek: DB sorgusu
    return [
        ['id' => 1, 'name' => 'Ali'],
        ['id' => 2, 'name' => 'Ayse'],
    ];
});
```

Callback'e disaridan degisken gecmek icin `use (...)` kullanabilirsin:

```php
use MPYazilim\RedisLibrary;

$status = 'active';
$limit = 20;

$users = RedisLibrary::RedisRemember("users:list:$status:$limit", 300, function () use ($status, $limit) {
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

## Manuel Domain/Password Girme (Statik Kullanimda)

Statik kullanimda (`RedisLibrary::set()` gibi) manuel `domain` ve `password` vermek icin once `configure` cagrisi yap:

```php
use MPYazilim\RedisLibrary;

RedisLibrary::configure('example.com', 'my-redis-password');

RedisLibrary::set('manual:key', 'value', 120);
$value = RedisLibrary::get('manual:key');
```

Tekrar env degerlerine donmek icin:

```php
RedisLibrary::configure();
```
