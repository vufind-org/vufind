<?php
require __DIR__ . '/constants.config.php';

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    $loader = include 'vendor/autoload.php';
}

if (!class_exists('Laminas\Loader\AutoloaderFactory')) {
    throw new RuntimeException('Unable to load Laminas autoloader.');
}

// Return the application!
return Laminas\Mvc\Application::init(require 'config/application.config.php');
