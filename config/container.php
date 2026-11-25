<?php

// Composer autoloading
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    include $autoloader;
}

if (!class_exists(\Laminas\Loader\AutoloaderFactory::class)) {
    throw new RuntimeException('Unable to load Laminas autoloader.');
}

// Prepare service manager:
$applicationConfig = require __DIR__ . '/application.config.php';
$serviceManager = new \Laminas\ServiceManager\ServiceManager();
$serviceManager->configure($applicationConfig['service_manager'] ?? []);
$serviceManager->setService('ApplicationConfig', $applicationConfig);

// Load modules:
$moduleManager = $serviceManager->get(\VuFind\ModuleManager\ModuleManager::class);
$moduleManager->initialize();

// Return service manager:
return $serviceManager;
