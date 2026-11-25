<?php

use Laminas\View\HelperPluginManagerInterface;

require __DIR__ . '/constants.config.php';

// Set up modules:
$modules = [
    'Laminas\Cache',
    'Laminas\Cache\Storage\Adapter\BlackHole',
    'Laminas\Cache\Storage\Adapter\Filesystem',
    'Laminas\Cache\Storage\Adapter\Memcached',
    'Laminas\Cache\Storage\Adapter\Memory',
    'Laminas\Router',
    'Laminas\View',
    'Lmc\Rbac',
    'Laminas\I18n',
    'VuFindTheme',
    'VuFindSearch',
    'VuFind',
    'VuFindAdmin',
    'VuFindApi',
];
if (PHP_SAPI === 'cli' && APPLICATION_ENV !== 'testing') {
    $modules[] = 'VuFindConsole';
}
if (APPLICATION_ENV === 'development' || APPLICATION_ENV === 'testing') {
    if (!isset($_SERVER['HTTP_X_VUFIND_DISABLE_WHOOPS'])) {
        $modules[] = 'WhoopsErrorHandler';
    }
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
$baseDir = ($local = getenv('VUFIND_LOCAL_DIR')) ? $local : APPLICATION_PATH . '/data';
$cacheDir = ($cache = getenv('VUFIND_CACHE_DIR')) ? $cache : $baseDir . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}
if (PHP_SAPI === 'cli') {
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

// Enable caching unless in CLI mode, dev mode or running tests:
$useCache = PHP_SAPI !== 'cli' && APPLICATION_ENV !== 'development' && APPLICATION_ENV !== 'testing';

// Build configuration:
return [
    'modules' => array_unique($modules),
    'module_listener_options' => [
        'config_glob_paths'    => [
            APPLICATION_PATH . '/config/autoload/{,*.}{global,local}.php',
        ],
        'config_cache_enabled' => $useCache,
        'module_map_cache_enabled' => $useCache,
        'check_dependencies' => APPLICATION_ENV === 'development',
        'cache_dir'            => $cacheDir,
        'module_paths' => [
            APPLICATION_PATH . '/module',
            APPLICATION_PATH . '/vendor',
        ],
        'use_laminas_loader' => false,
    ],
    'service_manager' => [
        'use_defaults' => true,
        'factories'    => [
            \VuFind\ModuleManager\ModuleManager::class => \VuFind\ModuleManager\ModuleManagerFactory::class,
            // Laminas MVC back-compatibility:
            \Laminas\EventManager\EventManager::class => \VuFind\EventManager\EventManagerFactory::class,
            \Laminas\EventManager\SharedEventManager::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        ],
        'aliases' => [
            // Laminas MVC back-compatibility:
            'ViewRenderer' => \Laminas\View\Renderer\PhpRenderer::class,
            'EventManager' => \Laminas\EventManager\EventManagerInterface::class,
            \Laminas\EventManager\EventManagerInterface::class => \Laminas\EventManager\EventManager::class,
            'SharedEventManager' => \Laminas\EventManager\SharedEventManagerInterface::class,
            \Laminas\EventManager\SharedEventManagerInterface::class => \Laminas\EventManager\SharedEventManager::class,
            'HttpRouter' => \Laminas\Router\Http\TreeRouteStack::class,

            // Back-compatibility with older laminas-view:
            'ViewHelperManager' => HelperPluginManagerInterface::class,
        ],
        'shared' => [
            // Laminas MVC back-compatibility:
            'EventManager' => false,
        ],
    ],
    'listeners' => [
    ],
];
