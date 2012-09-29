<?php

/**
 * Proxy service PHPUnit bootstrap.
 *
 * @author    David Maus <maus@hab.de>
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @copyright Copyright (C) Villanova University 2011
 */

$zf2 = realpath(__DIR__ . '/../../vendor/zf2/library/');

if (!class_exists('Zend\Loader\AutoloaderFactory')) {
    require_once(
        realpath("{$zf2}/Zend/Loader/AutoloaderFactory.php")
    );
    \Zend\Loader\AutoloaderFactory::factory(
        array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    'Zend' => "{$zf2}/Zend"
                )
            )
        )
    );
}

$loader = \Zend\Loader\AutoloaderFactory::getRegisteredAutoloader('Zend\Loader\StandardAutoloader');
$loader->registerNamespace('VuFindProxy', realpath(__DIR__ . '/../../src/VuFindProxy'));
$loader->registerNamespace('VuFindTest', realpath(__DIR__ . '/src/VuFindTest'));