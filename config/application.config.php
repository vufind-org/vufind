<?php
$config = array(
    'modules' => array(
        'VuFindHttp', 'VuFindTheme', 'VuFind',
    ),
    'module_listener_options' => array( 
        'config_glob_paths'    => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'config_cache_enabled' => false,
        'cache_dir'            => 'data/cache',
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
if (PHP_SAPI == 'cli' && !defined('VUFIND_PHPUNIT_RUNNING')) {
    $config['modules'][] = 'VuFindConsole';
}
if (APPLICATION_ENV == 'development') {
    $config['modules'][] = 'VuFindDevTools';
}
if ($localModules = getenv('VUFIND_LOCAL_MODULES')) {
    $localModules = array_map('trim', explode(',', $localModules));
    foreach ($localModules as $current) {
        if (!empty($current)) {
            $config['modules'][] = $current;
        }
    }
}
return $config;