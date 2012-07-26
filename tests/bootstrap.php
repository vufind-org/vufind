<?php
use Zend\Loader\AutoloaderFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;

// Set flag that we're in test mode
define('VUFIND_PHPUNIT_RUNNING', 1);

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', dirname(__DIR__));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'testing'));

// Define path to local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define('LOCAL_OVERRIDE_DIR', (getenv('VUFIND_LOCAL_DIR') ? getenv('VUFIND_LOCAL_DIR') : ''));

chdir(APPLICATION_PATH);

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    $loader = include 'vendor/autoload.php';
}

if (!class_exists('Zend\Loader\AutoloaderFactory')) {
    throw new RuntimeException('Unable to load ZF2.');
}

// Get application stack configuration
$configuration = include 'config/application.config.php';

// Setup service manager
$serviceManager = new ServiceManager(new ServiceManagerConfig($configuration['service_manager']));
$serviceManager->setService('ApplicationConfig', $configuration);
$serviceManager->get('ModuleManager')->loadModules();