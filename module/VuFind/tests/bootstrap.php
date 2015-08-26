<?php

// Set flag that we're in test mode
define('VUFIND_PHPUNIT_RUNNING', 1);

// Set path to this module
define('VUFIND_PHPUNIT_MODULE_PATH', __DIR__);

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__DIR__) . '/../..'));

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
$pathParts = [];
$pathParts[] = APPLICATION_PATH . '/vendor';
$pathParts[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $pathParts));

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    $loader = include 'vendor/autoload.php';
    $loader = new Composer\Autoload\ClassLoader();
    $loader->add('VuFindTest', __DIR__ . '/unit-tests/src');
    $loader->add('VuFindTest', __DIR__ . '/../src');
    // Dynamically discover all module src directories:
    $modules = opendir(__DIR__ . '/../..');
    while ($mod = readdir($modules)) {
        $mod = trim($mod, '.'); // ignore . and ..
        $dir = empty($mod) ? false : realpath(__DIR__ . "/../../{$mod}/src");
        if (!empty($dir) && is_dir($dir . '/' . $mod)) {
            $loader->add($mod, $dir);
        }
    }
    $loader->register();
}

define('PHPUNIT_SEARCH_FIXTURES', realpath(__DIR__ . '/../../VuFindSearch/tests/unit-tests/fixtures'));