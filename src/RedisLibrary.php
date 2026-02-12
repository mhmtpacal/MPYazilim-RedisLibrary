<?php

declare(strict_types=1);

namespace MPYazilim;

final class RedisLibrary
{
    protected \Redis $redis;
    protected string $prefix = "";

    protected $host;
    protected $port;
    protected $password;
    protected $domain;
    protected $database;
    protected $persistent;

    public function __construct($host,$port,$password, $database = 0, $domain = '', $persistent = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->domain = $domain;
        $this->persistent = $persistent;

        $this->redis = new \Redis();

        try {
            $timeout = 2.5;
            if ($persistent) {
                $this->redis->pconnect($host, $port, $timeout, 'mpyazilim_persistent');
            } else {
                $this->redis->connect($host, $port, $timeout);
            }

            if ($password) {
                $this->redis->auth($password);
            }

            $this->redis->select($database);
            $this->prefix = $this->getDomainPrefix() . ":";

        } catch (\RedisException $e) {
            file_put_contents('redisLog.txt',$e, FILE_APPEND);
        }
    }

    public function remember(string $key, callable $callback, int $ttl = 3600){
        $value = $this->get($key);

        if ($value !== false && $value !== null) {
            return $value;
        }

        // Callback ile veriyi al
        $value = $callback();

        // Redis'e kaydet
        if ($value !== null) {
            $this->set($key, $value, ($ttl - 3));
        }

        return $value;
    }

    /** 🔹 Domain bazlı prefix */
    public function getDomainPrefix(): string
    {
        $base = $this->domain;
        if (!str_starts_with($base, 'http://') && !str_starts_with($base, 'https://'))
            $base = 'https://' . ltrim($base, '/');

        $host = parse_url($base, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);
        $prefix = preg_replace('/[^a-z0-9]/i', '', $host);
        $prefix = substr(md5($prefix), 0, 6);

        return strtolower($prefix);
    }

    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    // -------------------------------
    // 🔹 Temel işlemler
    // -------------------------------
    public function set(string $key, $value, int $ttl = 0): bool
    {
        $key = $this->key($key);
        $value = is_array($value) ? json_encode($value) : $value;

        if ($ttl > 0)
            return $this->redis->setex($key, $ttl, $value);

        return $this->redis->set($key, $value);
    }

    public function get(string $key, bool $jsonDecode = true)
    {
        $key = $this->key($key);
        $value = $this->redis->get($key);

        if ($jsonDecode && $this->isJson($value))
            return json_decode($value, true);

        return $value;
    }

    public function delete(string $key): int
    {
        return $this->redis->del($this->key($key));
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->key($key)) > 0;
    }

    // -------------------------------
    // 🔹 Hash işlemleri
    // -------------------------------
    public function hSet(string $key, string|int $field, $value): bool
    {
        return (bool)$this->redis->hSet($this->key($key), (string)$field, $value);
    }

    public function hGet(string $key, string|int $field)
    {
        return $this->redis->hGet($this->key($key), (string)$field);
    }

    public function hDel(string $key, string|int $field): int
    {
        return $this->redis->hDel($this->key($key), (string)$field);
    }

    public function hExists(string $key, string|int $field): bool
    {
        return $this->redis->hExists($this->key($key), (string)$field);
    }

    public function hGetAll(string $key): array
    {
        return $this->redis->hGetAll($this->key($key));
    }

    public function hVals(string $key): array
    {
        return $this->redis->hVals($this->key($key));
    }

    // -------------------------------
    // 🔹 Diğerleri
    // -------------------------------
    public function increment(string $key, int $by = 1): int
    {
        return $this->redis->incrBy($this->key($key), $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->redis->decrBy($this->key($key), $by);
    }

    public function flushAll(): bool
    {
        return $this->redis->flushDB();
    }

    private function isJson($string): bool
    {
        if (!is_string($string)) return false;
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public function isConnected(): bool
    {
        try {
            return $this->redis->ping();
        } catch (\Exception $e) {
            return false;
        }
    }
}
