<?php
use Zend\Loader\AutoloaderFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;

// Set flag that we're in test mode
define('VUFIND_PHPUNIT_RUNNING', 1);

// Set path to this module
define('VUFIND_PHPUNIT_MODULE_PATH', __DIR__);

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__DIR__) . '/../../..'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'testing'));

// Define path to local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define('LOCAL_OVERRIDE_DIR', (getenv('VUFIND_LOCAL_DIR') ? getenv('VUFIND_LOCAL_DIR') : ''));

chdir(APPLICATION_PATH);

// Ensure vendor/ is on include_path; some PEAR components may not load correctly
// otherwise (i.e. File_MARC may cause a "Cannot redeclare class" error by pulling
// from the shared PEAR directory instead of the local copy):
$pathParts = array();
$pathParts[] = APPLICATION_PATH . '/vendor';
$pathParts[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $pathParts));

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

// Setup autoloader for VuFindTest classes
$loader = Zend\Loader\AutoloaderFactory::getRegisteredAutoloader(
    Zend\Loader\AutoloaderFactory::STANDARD_AUTOLOADER
);
$loader->registerNamespace('VuFindTest', __DIR__ . '/../../src/VuFindTest');

// Use output buffering -- some tests involve HTTP headers and will fail if there is output.
ob_start();