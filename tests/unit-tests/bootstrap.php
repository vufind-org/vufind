<?php

/**
 * Proxy servce PHPUnit bootstrap.
 *
 * @author    David Maus <maus@hab.de>
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @copyright Copyright (C) Villanova University 2011
 *
 */

require_once('Zend/Loader/AutoloaderFactory.php');
\Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'VuFindProxy' => realpath(__DIR__ . '/../../src/VuFindProxy'),
                'VuFindTest' => realpath(__DIR__ . '/src/VuFindTest'),
            ),
            'autoregister_zf' => true
        )
    )
);
