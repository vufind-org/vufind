<?php

// Set up modules:
$modules = [
    'Zend\Router', 'ZfcRbac',
    'VuFindTheme', 'VuFindSearch', 'VuFind', 'VuFindAdmin', 'VuFindApi'
];
if (PHP_SAPI == 'cli' && APPLICATION_ENV !== 'testing') {
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

// Enable caching unless in dev mode or running tests:
$useCache = APPLICATION_ENV != 'development' && APPLICATION_ENV != 'testing';

// Build configuration:
return [
    'modules' => array_unique($modules),
    'module_listener_options' => [
        'config_glob_paths'    => [
            'config/autoload/{,*.}{global,local}.php',
        ],
        'config_cache_enabled' => $useCache,
        'module_map_cache_enabled' => $useCache,
        'check_dependencies' => (APPLICATION_ENV == 'development'),
        'cache_dir'            => $cacheDir,
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
