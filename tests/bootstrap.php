<?php

declare(strict_types=1);

// Autoload do Composer
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Composer autoloader not found at: $autoloader\n");
    exit(1);
}

require_once $autoloader;
