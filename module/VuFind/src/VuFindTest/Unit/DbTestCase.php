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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Unit;

/**
 * Abstract base class for PHPUnit database test cases.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
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
                    [
                        'abstract_factories' =>
                            ['VuFind\Db\Table\PluginFactory'],
                        'factories' => [
                            'resource' => 'VuFind\Db\Table\Factory::getResource',
                            'user' => 'VuFind\Db\Table\Factory::getUser',
                            'userlist' => 'VuFind\Db\Table\Factory::getUserList',
                        ]
                    ]
                )
            );
            $factory->setServiceLocator($sm);
            $sm->setService('VuFind\DbTablePluginManager', $factory);
            $sm->setService(
                'VuFind\SessionManager',
                $this->getMock('Zend\Session\SessionManager')
            );

            // Override the configuration so PostgreSQL tests can work:
            $sm->setAllowOverride(true);
            $sm->setService(
                'config',
                [
                    'vufind' => [
                        'pgsql_seq_mapping'  => [
                            'comments'       => ['id', 'comments_id_seq'],
                            'oai_resumption' => ['id', 'oai_resumption_id_seq'],
                            'resource'       => ['id', 'resource_id_seq'],
                            'resource_tags'  => ['id', 'resource_tags_id_seq'],
                            'search'         => ['id', 'search_id_seq'],
                            'session'        => ['id', 'session_id_seq'],
                            'tags'           => ['id', 'tags_id_seq'],
                            'user'           => ['id', 'user_id_seq'],
                            'user_list'      => ['id', 'user_list_id_seq'],
                            'user_resource'  => ['id', 'user_resource_id_seq']
                        ]
                    ]
                ]
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
