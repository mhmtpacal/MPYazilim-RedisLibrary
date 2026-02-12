<?php

declare(strict_types=1);

use MPYazilim\RedisLibrary;

if (!function_exists('Redis')) {
    function Redis(
        ?string $domain = null,
        ?string $password = null,
        ?string $host = null,
        ?int $port = null,
        ?int $database = null,
        ?bool $persistent = null,
        ?bool $active = null
    ): RedisLibrary|false {
        static $redis = null;

        if ($redis === null) {
            try {
                $redis = new RedisLibrary(
                    $domain ?? (defined('BASE') ? (string)BASE : null),
                    $password,
                    $host,
                    $port,
                    $database,
                    $persistent,
                    $active
                );
            } catch (\Throwable $e) {
                error_log('Redis baglanamadi: ' . $e->getMessage());
                return false;
            }
        }

        return $redis;
    }
}
