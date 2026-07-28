<?php

declare(strict_types=1);

if (!defined('GOOSIALIZE_LEADS_AUTOLOAD_REGISTERED')) {
    define('GOOSIALIZE_LEADS_AUTOLOAD_REGISTERED', true);

    $prefix = 'Grav\\Plugin\\GoosializeLeads\\';
    $root = realpath(__DIR__ . '/classes');

    if ($root !== false) {
        spl_autoload_register(static function (string $class) use ($prefix, $root): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            if (
                $relative === ''
                || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $relative) !== 1
            ) {
                return;
            }

            $candidate = $root . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            $parent = realpath(dirname($candidate));
            if (
                $parent === false
                || ($parent !== $root && !str_starts_with($parent, $root . DIRECTORY_SEPARATOR))
                || !is_file($candidate)
                || is_link($candidate)
            ) {
                return;
            }

            require $candidate;
        });
    }
}
