<?php

declare(strict_types=1);

use MPYazilim\RedisLibrary;

if (!function_exists('Redis')) {
    function Redis(?string $domain = null): RedisLibrary|false {
        static $redis = null;
        static $currentDomain = null;

        if ($redis === null || $currentDomain !== $domain) {
            try {
                $redis = new RedisLibrary($domain);
                $currentDomain = $domain;
            } catch (\Throwable $e) {
                error_log('Redis baglanamadi: ' . $e->getMessage());
                return false;
            }
        }

        return $redis;
    }
}
