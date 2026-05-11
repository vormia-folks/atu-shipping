<?php

namespace Vormia\ATUShipping\Support;

use Vormia\ATUShipping\ATUShipping;

/**
 * Basenames of migrations shipped with this package (for pruning the migrations table on uninstall).
 */
final class AtuShippingPackageMigrationNames
{
    /**
     * @return list<string>
     */
    public static function basenames(): array
    {
        $dir = ATUShipping::migrationsPath();
        $pattern = $dir . DIRECTORY_SEPARATOR . '*.php';
        $names = [];

        foreach (glob($pattern) ?: [] as $path) {
            $names[] = basename($path, '.php');
        }

        sort($names);

        return $names;
    }
}
