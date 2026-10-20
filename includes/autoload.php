<?php
/**
 * autoload.php
 * -------------------------------------------------------
 * Simple PSR-style autoloader. Loads class files on demand
 * instead of requiring every file manually.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

spl_autoload_register(function (string $class) {
    $paths = [
        __DIR__ . '/../classes/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
