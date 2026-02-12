<?php

declare(strict_types=1);

namespace MPYazilim;

final class RedisLibrary
{
    protected \Redis $redis;
    protected string $prefix = '';

    protected string $host;
    protected int $port;
    protected string $password;
    protected string $domain;
    protected int $database;
    protected bool $persistent;
    protected bool $active;

    private static ?self $instance = null;
    private static ?bool $overrideActive = null;
    private static ?string $overrideDomain = null;
    private static ?string $overridePassword = null;
    private static ?string $overrideHost = null;
    private static ?int $overridePort = null;
    private static ?int $overrideDatabase = null;
    private static ?bool $overridePersistent = null;

    public function __construct(
        ?string $domain = null,
        ?string $password = null,
        ?string $host = null,
        ?int $port = null,
        ?int $database = null,
        ?bool $persistent = null,
        ?bool $active = null
    )
    {
        $this->host = $host ?? self::$overrideHost ?? (string)(self::env('redis.host') ?? '127.0.0.1');
        $this->port = $port ?? self::$overridePort ?? (int)(self::env('redis.port') ?? 6379);
        $this->password = $password ?? self::$overridePassword ?? (string)(self::env('redis.password') ?? '');
        $this->database = $database ?? self::$overrideDatabase ?? (int)(self::env('redis.db') ?? 0);
        $this->domain = $domain ?? (defined('BASE') ? (string)BASE : '');
        $this->persistent = $persistent ?? self::$overridePersistent ?? self::toBool(self::env('redis.persistent') ?? false);
        $this->active = $active ?? self::$overrideActive ?? self::toBool(self::env('redis.active') ?? true);

        $this->redis = new \Redis();

        if (!$this->active) {
            return;
        }

        try {
            $timeout = 2.5;

            if ($this->persistent) {
                $this->redis->pconnect($this->host, $this->port, $timeout, 'mpyazilim_persistent');
            } else {
                $this->redis->connect($this->host, $this->port, $timeout);
            }

            if ($this->password !== '') {
                $this->redis->auth($this->password);
            }

            $this->redis->select($this->database);
            $this->prefix = $this->getDomainPrefix() . ':';
        } catch (\RedisException $e) {
            error_log('Redis connection error: ' . $e->getMessage());
        }
    }

    private static function instance(): self|false
    {
        if (!self::isActive()) {
            return false;
        }

        if (self::$instance === null) {
            try {
                self::$instance = new self(
                    self::$overrideDomain,
                    self::$overridePassword,
                    self::$overrideHost,
                    self::$overridePort,
                    self::$overrideDatabase,
                    self::$overridePersistent,
                    self::$overrideActive
                );
            } catch (\Throwable $e) {
                error_log('Redis baglanamadi: ' . $e->getMessage());
                return false;
            }
        }

        return self::$instance;
    }

    public static function configure(
        ?string $domain = null,
        ?string $password = null,
        ?string $host = null,
        ?int $port = null,
        ?int $database = null,
        ?bool $persistent = null,
        ?bool $active = null
    ): void
    {
        self::$overrideDomain = $domain;
        self::$overridePassword = $password;
        self::$overrideHost = $host;
        self::$overridePort = $port;
        self::$overrideDatabase = $database;
        self::$overridePersistent = $persistent;
        self::$overrideActive = $active;
        self::$instance = null;
    }

    public static function RedisRemember(string $key, int $ttl, callable $callback)
    {
        if (!self::isActive()) {
            return false;
        }

        return self::remember($key, $callback, $ttl);
    }

    public static function remember(string $key, callable $callback, int $ttl = 3600)
    {
        if (!self::isActive()) {
            return false;
        }

        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        $value = self::get($key);

        if ($value !== false && $value !== null) {
            return $value;
        }

        $value = $callback();

        if ($value !== null) {
            $safeTtl = max(1, $ttl - 3);
            self::set($key, $value, $safeTtl);
        }

        return $value;
    }

    public static function set(string $key, $value, int $ttl = 0): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        $namespacedKey = $instance->key($key);
        $storedValue = is_array($value) ? json_encode($value) : $value;

        if ($ttl > 0) {
            return (bool)$instance->redis->setex($namespacedKey, $ttl, $storedValue);
        }

        return (bool)$instance->redis->set($namespacedKey, $storedValue);
    }

    public static function get(string $key, bool $jsonDecode = true)
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        $value = $instance->redis->get($instance->key($key));

        if ($jsonDecode && $instance->isJson($value)) {
            return json_decode($value, true);
        }

        return $value;
    }

    public static function delete(string $key): int|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (int)$instance->redis->del($instance->key($key));
    }

    public static function deleteByPattern(string $pattern): int|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        $iterator = null;
        $deleted = 0;
        $searchPattern = $instance->key($pattern);

        while (true) {
            $keys = $instance->redis->scan($iterator, $searchPattern, 1000);

            if (is_array($keys) && !empty($keys)) {
                $deleted += (int)$instance->redis->del($keys);
            }

            if ($iterator === 0) {
                break;
            }
        }

        return $deleted;
    }

    public static function has(string $key): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return $instance->redis->exists($instance->key($key)) > 0;
    }

    public static function hSet(string $key, string|int $field, $value): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (bool)$instance->redis->hSet($instance->key($key), (string)$field, $value);
    }

    public static function hGet(string $key, string|int $field)
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return $instance->redis->hGet($instance->key($key), (string)$field);
    }

    public static function hDel(string $key, string|int $field): int|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (int)$instance->redis->hDel($instance->key($key), (string)$field);
    }

    public static function hExists(string $key, string|int $field): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return $instance->redis->hExists($instance->key($key), (string)$field);
    }

    public static function hGetAll(string $key): array|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return $instance->redis->hGetAll($instance->key($key));
    }

    public static function hVals(string $key): array|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return $instance->redis->hVals($instance->key($key));
    }

    public static function increment(string $key, int $by = 1): int|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (int)$instance->redis->incrBy($instance->key($key), $by);
    }

    public static function decrement(string $key, int $by = 1): int|false
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (int)$instance->redis->decrBy($instance->key($key), $by);
    }

    public static function flushAll(): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        return (bool)$instance->redis->flushDB();
    }

    public static function isConnected(): bool
    {
        $instance = self::instance();
        if (!$instance) {
            return false;
        }

        try {
            return (bool)$instance->redis->ping();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getDomainPrefix(): string
    {
        $base = $this->domain;

        if ($base === '') {
            return 'global';
        }

        if (!str_starts_with($base, 'http://') && !str_starts_with($base, 'https://')) {
            $base = 'https://' . ltrim($base, '/');
        }

        $host = (string)parse_url($base, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $prefix = preg_replace('/[^a-z0-9]/i', '', $host) ?? $host;
        $prefix = substr(md5($prefix), 0, 6);

        return strtolower($prefix);
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string)$value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function isActive(): bool
    {
        return self::$overrideActive ?? self::toBool(self::env('redis.active') ?? true);
    }

    private static function env(string $key): mixed
    {
        if (!function_exists('env')) {
            return null;
        }

        return env($key);
    }
}
