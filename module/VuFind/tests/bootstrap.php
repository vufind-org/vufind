<?php

// Define path to application directory
defined('APPLICATION_PATH')
    || define(
        'APPLICATION_PATH',
        (getenv('VUFIND_APPLICATION_PATH') ? getenv('VUFIND_APPLICATION_PATH')
            : dirname(__DIR__) . '/../..')
    );

// Define application environment
defined('APPLICATION_ENV')
    || define(
        'APPLICATION_ENV',
        (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'testing')
    );

// Define default search backend identifier
defined('DEFAULT_SEARCH_BACKEND') || define('DEFAULT_SEARCH_BACKEND', 'Solr');

// Define path to local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define(
        'LOCAL_OVERRIDE_DIR',
        (getenv('VUFIND_LOCAL_DIR') ? getenv('VUFIND_LOCAL_DIR') : '')
    );

// Define path to cache directory
defined('LOCAL_CACHE_DIR')
    || define(
        'LOCAL_CACHE_DIR',
        (getenv('VUFIND_CACHE_DIR')
            ? getenv('VUFIND_CACHE_DIR')
            : (strlen(LOCAL_OVERRIDE_DIR) > 0 ? LOCAL_OVERRIDE_DIR . '/cache' : ''))
    );

chdir(APPLICATION_PATH);

// Ensure vendor/ is on include_path; some PEAR components may not load correctly
// otherwise (i.e. a component may cause a "Cannot redeclare class" error by pulling
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

// Make sure local config dir exists:
if ($localDir = \VuFind\Config\Locator::getLocalConfigPath('', null, true)) {
    if (!file_exists($localDir)) {
        mkdir($localDir, 0777, true);
    }
}
