<?php

/**
 * Finna Module Configuration
 * 
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 *
 */

namespace Finna\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'cover' => 'Finna\Controller\CoverController'
        ),
    ),
/*    'service_manager' => array(
        'allow_override' => true,
        'factories' => array(
            'VuFind\ILSConnection' => function ($sm) {
                $catalog = new \Finna\ILS\Connection(
                    $sm->get('VuFind\Config')->get('config')->Catalog,
                    $sm->get('VuFind\ILSDriverPluginManager'),
                    $sm->get('VuFind\Config')
                );
                return $catalog->setHoldConfig($sm->get('VuFind\ILSHoldSettings'));
            }
        )
    ),*/
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => array(
        'plugin_managers' => array(
            'search_backend' => array(
                'factories' => array(
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                ),
                'aliases' => array(
                    // Allow Solr core names to be used as aliases for services:
                    'biblio' => 'Solr',
                )
            ),
            'ils_driver' => array(
                'factories' => array(
                    'multibackend' => function ($sm) {
                        return new \Finna\ILS\Driver\MultiBackend(
                            $sm->getServiceLocator()->get('VuFind\Config')
                        );
                    },
/*                    'voyager' => function ($sm) {
                        return new \Finna\ILS\Driver\Voyager(
                            $sm->getServiceLocator()->get('VuFind\DateConverter')
                        );
                    },
                    'voyagerrestful' => function ($sm) {
                        $ils = $sm->getServiceLocator()->get('VuFind\ILSHoldSettings');
                        return new \Finna\ILS\Driver\VoyagerRestful(
                            $sm->getServiceLocator()->get('VuFind\DateConverter'),
                            $ils->getHoldsMode(), $ils->getTitleHoldsMode()
                        );
                    },*/
                )
            )
        )
    )
);

return $config;