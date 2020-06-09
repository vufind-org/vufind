<?php

/**
 * Search subsystem PHPUnit bootstrap.
 *
 * @author    David Maus <maus@hab.de>
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @copyright Copyright (C) Villanova University 2011
 */
require_once 'Laminas/Loader/AutoloaderFactory.php';
\Laminas\Loader\AutoloaderFactory::factory(
    [
        'Laminas\Loader\StandardAutoloader' => [
            'namespaces' => [
                'FinnaSearch' => realpath(__DIR__ . '/../../src/FinnaSearch'),
                'VuFindTest' => realpath(__DIR__ . '/src/VuFindTest'),
            ],
            'autoregister_zf' => true
        ]
    ]
);

define('PHPUNIT_SEARCH_FIXTURES', realpath(__DIR__ . '/fixtures'));
