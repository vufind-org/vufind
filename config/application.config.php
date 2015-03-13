<?php

// Set up modules:
$modules = array(
    'ZfcRbac', 'VuFindTheme', 'VuFindSearch', 'VuFind', 'VuFindAdmin'
);
if (PHP_SAPI == 'cli' && !defined('VUFIND_PHPUNIT_RUNNING')) {
    $modules[] = 'VuFindConsole';
}
if (APPLICATION_ENV == 'development') {
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
if (PHP_SAPI == 'cli') {
    $cacheDir = $baseDir . '/cache/cli';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir);
    }
    $cacheDir .= '/configs';
} else {
    $cacheDir = $baseDir . '/cache/configs';
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

// Enable caching unless in dev mode or running tests:
$useCache = APPLICATION_ENV != 'development' && !defined('VUFIND_PHPUNIT_RUNNING');

// Build configuration:
return array(
    'modules' => array_unique($modules),
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'config_cache_enabled' => $useCache,
        'module_map_cache_enabled' => $useCache,
        'check_dependencies' => (APPLICATION_ENV == 'development'),
        'cache_dir'            => $cacheDir,
        'module_paths' => array(
            './module',
            './vendor',
        ),
    ),
    'service_manager' => array(
        'use_defaults' => true,
        'factories'    => array(
        ),
    ),
);