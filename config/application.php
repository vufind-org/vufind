<?php

// Composer autoloading
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    include $autoloader;
}

if (!class_exists(\Laminas\Loader\AutoloaderFactory::class)) {
    throw new RuntimeException('Unable to load Laminas autoloader.');
}

// Return the application!
return Laminas\Mvc\Application::init(require __DIR__ . '/application.config.php');
