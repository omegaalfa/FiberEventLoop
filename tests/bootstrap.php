<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 * 
 * Carrega autoloader e configurações iniciais para testes
 */

// Detecta raiz do projeto
$projectRoot = __DIR__ . '/..';

// Carrega autoloader do Composer
if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
} else {
    throw new RuntimeException('Composer autoloader não encontrado. Execute: composer install');
}

// Configurações PHPUnit
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Define timezone
date_default_timezone_set('UTC');
