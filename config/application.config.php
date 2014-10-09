<?php

// Set up modules:
$modules = array(
    'VuFindHttp', 'VuFindTheme', 'VuFindSearch', 'VuFind', 'VuFindAdmin'
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

// Set up cache directory:
$baseDir = ($local = getenv('VUFIND_LOCAL_DIR')) ? $local : 'data';
$cacheDir = $baseDir . '/cache/configs';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}

// Build configuration:
return array(
    'modules' => array_unique($modules),
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'config_cache_enabled' => (APPLICATION_ENV != 'development'),
        'module_map_cache_enabled' => (APPLICATION_ENV != 'development'),
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