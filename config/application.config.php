<?php

// Set up modules:
$modules = [
    'Zend\Router', 'ZfcRbac',
    'VuFindTheme', 'VuFindSearch', 'VuFind', 'VuFindAdmin', 'VuFindApi'
];
if (PHP_SAPI == 'cli' && !defined('VUFIND_PHPUNIT_RUNNING')) {
    $modules[] = 'Zend\Mvc\Console';
    $modules[] = 'VuFindConsole';
}
if (APPLICATION_ENV == 'development') {
    array_push($modules, 'Zf2Whoops');
    $modules[] = 'VuFindDevTools';
}
if ($localModules = getenv('VUFIND_LOCAL_MODULES')) {
    $localModules = array_map('trim', explode(',', $localModules));
    foreach ($localModules as $current) {
        if (!empty($current)) {
            $modules[] = $current;
        }
    }
}

// Set up cache directory (be sure to keep separate cache for CLI vs. web and
// to account for potentially variant environment settings):
$baseDir = ($local = getenv('VUFIND_LOCAL_DIR')) ? $local : 'data';
$cacheDir = ($cache = getenv('VUFIND_CACHE_DIR')) ? $cache : $baseDir . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}
if (PHP_SAPI == 'cli') {
    $cacheDir .= '/cli';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir);
    }
    $cacheDir .= '/configs';
} else {
    $cacheDir .= '/configs';
}
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}
$cacheHash = md5(
    APPLICATION_ENV
    . (defined('VUFIND_LOCAL_DIR') ? VUFIND_LOCAL_DIR : '')
    . implode(',', $modules)
);
$cacheDir .= '/' . $cacheHash;
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}

defined('CONFIG_PATH') || define('CONFIG_PATH', __DIR__ . '/config.php');

defined('CONFIG_CACHE_DIR') || define('CONFIG_CACHE_DIR', $cacheDir);

defined('CONFIG_CACHE_PATH')
|| define('CONFIG_CACHE_PATH', CONFIG_CACHE_DIR . '/config-cache.php');

// Enable caching unless in dev mode or running tests:
defined('CONFIG_CACHE_ENABLED')
|| define('CONFIG_CACHE_ENABLED', getenv('VUFIND_CONFIG_CACHE_ENABLED')
    ?? APPLICATION_ENV != 'development' && !defined('VUFIND_PHPUNIT_RUNNING'));

// Build configuration:
return [
    'modules' => array_unique($modules),
    'module_listener_options' => [
        'config_glob_paths'    => [
            'config/autoload/{,*.}{global,local}.php',
        ],
        'config_cache_enabled' => CONFIG_CACHE_ENABLED,
        'module_map_cache_enabled' => CONFIG_CACHE_ENABLED,
        'check_dependencies' => (APPLICATION_ENV == 'development'),
        'cache_dir'            => CONFIG_CACHE_DIR,
        'module_paths' => [
            './module',
            './vendor',
        ],
    ],
    'service_manager' => [
        'use_defaults' => true,
        'factories'    => [
        ],
    ],
];
