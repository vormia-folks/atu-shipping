<?php

namespace Vormia\ATUShipping;

class ATUShipping
{
    public const VERSION = '1.3.0';

    /**
     * Absolute path to the package stubs.
     */
    public static function stubsPath(string $suffix = ''): string
    {
        $base = __DIR__ . '/stubs';

        return $suffix ? $base . '/' . ltrim($suffix, '/') : $base;
    }

    /**
     * Absolute path to package database migrations (loaded via loadMigrationsFrom).
     */
    public static function migrationsPath(): string
    {
        return dirname(__DIR__) . '/database/migrations';
    }
}
