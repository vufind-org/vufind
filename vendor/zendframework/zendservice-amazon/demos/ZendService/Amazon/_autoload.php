<?php
$vendor = dirname(dirname(dirname(__DIR__))).'/vendor';
// Composer autoloading
if (file_exists($vendor.'/autoload.php')) {
    include $vendor.'/autoload.php';
} else {
    throw new RuntimeException('Unable to load vendors. Run `php composer.phar install`');
}
