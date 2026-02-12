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
redis.password = CCQ7TAcVAakQjqGyd0XuOYpjS
redis.db = 0
redis.persistent = 1
```

## Kullanim

Kutuphaneyi kurunca `Redis()` helper fonksiyonu Composer autoload ile otomatik gelir.
Tum kullanim bu helper uzerinden yapilabilir:

```php
Redis()::set('user:1', ['name' => 'Mehmet'], 300);
$user = Redis()::get('user:1');

$exists = Redis()::has('user:1');
Redis()::delete('user:1');
```

## Remember

`remember` callback sonucunu cache'ler. Key varsa direkt doner, yoksa callback calisir ve Redis'e yazar.

```php
$users = Redis()::remember('users:list', function () {
    return [
        ['id' => 1, 'name' => 'Ali'],
        ['id' => 2, 'name' => 'Ayse'],
    ];
}, 300);
```
