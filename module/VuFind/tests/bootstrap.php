<?php

// Define application environment (default to testing)
defined('APPLICATION_ENV')
    || define(
        'APPLICATION_ENV',
        (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'testing')
    );

require __DIR__ . '/../../../config/constants.config.php';

chdir(APPLICATION_PATH);

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    $loader = include 'vendor/autoload.php';
    $loader = new Composer\Autoload\ClassLoader();
    $loader->addClassMap(['minSO' => __DIR__ . '/../src/VuFind/Search/minSO.php']);
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
