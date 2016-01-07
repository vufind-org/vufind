<?php
/**
 * Setup autoloading
 */
if ($zf2Path = getenv('ZF2_PATH')) {
    require_once $zf2Path . '/library/Zend/Loader/StandardAutoloader.php';

    $loader = new Zend\Loader\StandardAutoloader(array(
        Zend\Loader\StandardAutoloader::AUTOREGISTER_ZF => true,
        Zend\Loader\StandardAutoloader::LOAD_NS => array(
            'ZendService' => __DIR__ . '/../library/ZendService'
        )
    ));
    $loader->register();
} elseif (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('This component has dependencies that are unmet.

Please install composer (http://getcomposer.org), and run the following
command in the root of this project:

    php /path/to/composer.phar install

After that, you should be able to run tests.');
} else {
    include_once __DIR__ . '/../vendor/autoload.php';
}


spl_autoload_register(function ($class) {
    if (0 !== strpos($class, 'ZendServiceTest\\')) {
        return false;
    }
    $normalized = str_replace('ZendServiceTest\\', '', $class);
    $filename   = __DIR__ . '/ZendService/' . str_replace(array('\\', '_'), '/', $normalized) . '.php';
    if (!file_exists($filename)) {
        return false;
    }
    return include_once $filename;
});
