<?php

/**
 * Abstract base class for PHPUnit database test cases.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Unit;

/**
 * Abstract base class for PHPUnit database test cases.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */

abstract class DbTestCase extends TestCase
{
    /**
     * Get a service manager.
     *
     * @return \Zend\ServiceManager\ServiceManager
     */
    public function getServiceManager()
    {
        // Get parent service manager:
        $sm = parent::getServiceManager();

        // Add database service:
        if (!$sm->has('VuFind\DbTablePluginManager')) {
            $dbFactory = new \VuFind\Db\AdapterFactory(
                $sm->get('VuFind\Config')->get('config')
            );
            $sm->setService('VuFind\DbAdapter', $dbFactory->getAdapter());
            $factory = new \VuFind\Db\Table\PluginManager(
                new \Zend\ServiceManager\Config(
                    array(
                        'abstract_factories' =>
                            array('VuFind\Db\Table\PluginFactory')
                    )
                )
            );
            $factory->setServiceLocator($sm);
            $sm->setService('VuFind\DbTablePluginManager', $factory);

            // Override the configuration so PostgreSQL tests can work:
            $sm->setAllowOverride(true);
            $sm->setService(
                'config',
                array(
                    'vufind' => array(
                        'pgsql_seq_mapping'  => array(
                            'comments'       => array('id', 'comments_id_seq'),
                            'oai_resumption' => array('id', 'oai_resumption_id_seq'),
                            'resource'       => array('id', 'resource_id_seq'),
                            'resource_tags'  => array('id', 'resource_tags_id_seq'),
                            'search'         => array('id', 'search_id_seq'),
                            'session'        => array('id', 'session_id_seq'),
                            'tags'           => array('id', 'tags_id_seq'),
                            'user'           => array('id', 'user_id_seq'),
                            'user_list'      => array('id', 'user_list_id_seq'),
                            'user_resource'  => array('id', 'user_resource_id_seq')
                        )
                    )
                )
            );
        }
        return $sm;
    }

    /**
     * Get a table object.
     *
     * @param string $table Name of table to load
     *
     * @return \VuFind\Db\Table\Gateway
     */
    public function getTable($table)
    {
        $sm = $this->getServiceManager();
        return $sm->get('VuFind\DbTablePluginManager')->get($table);
    }
}