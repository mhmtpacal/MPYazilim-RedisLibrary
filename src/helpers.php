<?php

declare(strict_types=1);

use MPYazilim\RedisLibrary;

if (!function_exists('Redis')) {
    function Redis(?string $domain = null, ?string $password = null): RedisLibrary|false
    {
        static $redis = null;
        $configValue = static function (string $key): mixed {
            if (function_exists('env')) {
                $value = env($key);
                if ($value !== null) {
                    return $value;
                }
            }

            if (function_exists('config')) {
                $value = config($key);
                if ($value !== null) {
                    return $value;
                }
            }

            if (function_exists('globalConfig')) {
                $value = globalConfig($key);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        };

        if ($redis === null) {
            try {
                $redis = new RedisLibrary(
                    $domain ?? (defined('BASE') ? (string)BASE : null),
                    $password ?? (string)$configValue('redis.password')
                );
            } catch (\Throwable $e) {
                error_log('Redis baglanamadi: ' . $e->getMessage());
                return false;
            }
        }

        return $redis;
    }
}
