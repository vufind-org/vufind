<?php

/**
 * Abstract base class for PHPUnit database test cases.
 *
 * PHP version 7
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

use Laminas\ServiceManager\ServiceManager;

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
     * Add table manager to service manager.
     *
     * @param ServiceManager $sm Service manager
     *
     * @return void
     */
    protected function addTableManager(ServiceManager $sm)
    {
        $factory = new \VuFind\Db\Table\PluginManager(
            $sm,
            [
                'abstract_factories' =>
                    ['VuFind\Db\Table\PluginFactory'],
                'factories' => [
                    'changetracker' =>
                        'VuFind\Db\Table\Factory::getChangeTracker',
                    'comments' => 'VuFind\Db\Table\Factory::getComments',
                    'externalsession' =>
                        'VuFind\Db\Table\Factory::getExternalSession',
                    'oairesumption' =>
                        'VuFind\Db\Table\Factory::getOaiResumption',
                    'record' => 'VuFind\Db\Table\Factory::getRecord',
                    'resource' => 'VuFind\Db\Table\Factory::getResource',
                    'resourcetags' =>
                        'VuFind\Db\Table\Factory::getResourceTags',
                    'search' => 'VuFind\Db\Table\Factory::getSearch',
                    'session' => 'VuFind\Db\Table\Factory::getSession',
                    'tags' => 'VuFind\Db\Table\Factory::getTags',
                    'user' => 'VuFind\Db\Table\Factory::getUser',
                    'usercard' => 'VuFind\Db\Table\Factory::getUserCard',
                    'userlist' => 'VuFind\Db\Table\Factory::getUserList',
                    'userresource' =>
                        'VuFind\Db\Table\Factory::getUserResource',
                ],
            ]
        );
        $sm->setService('VuFind\Db\Table\PluginManager', $factory);
    }

    /**
     * Add row manager to service manager.
     *
     * @param ServiceManager $sm Service manager
     *
     * @return void
     */
    protected function addRowManager(ServiceManager $sm)
    {
        $factory = new \VuFind\Db\Row\PluginManager($sm);
        $sm->setService('VuFind\Db\Row\PluginManager', $factory);
    }

    /**
     * Get a service manager.
     *
     * @return \Laminas\ServiceManager\ServiceManager
     */
    public function getServiceManager()
    {
        // Get parent service manager:
        $sm = parent::getServiceManager();

        // Add database service:
        if (!$sm->has(\VuFind\Db\Table\PluginManager::class)) {
            $dbFactory = new \VuFind\Db\AdapterFactory(
                $sm->get(\VuFind\Config\PluginManager::class)->get('config')
            );
            $sm->setService('Laminas\Db\Adapter\Adapter', $dbFactory->getAdapter());
            $this->addTableManager($sm);
            $this->addRowManager($sm);
            $sm->setService(
                'Laminas\Session\SessionManager',
                $this->createMock(\Laminas\Session\SessionManager::class)
            );

            // Override the configuration so PostgreSQL tests can work:
            $sm->setAllowOverride(true);
            $sm->setService(
                'config',
                [
                    'vufind' => [
                        'pgsql_seq_mapping'  => [
                            'comments'         => ['id', 'comments_id_seq'],
                            'external_session' => ['id', 'external_session_id_seq'],
                            'oai_resumption'   => ['id', 'oai_resumption_id_seq'],
                            'record'           => ['id', 'record_id_seq'],
                            'resource'         => ['id', 'resource_id_seq'],
                            'resource_tags'    => ['id', 'resource_tags_id_seq'],
                            'search'           => ['id', 'search_id_seq'],
                            'session'          => ['id', 'session_id_seq'],
                            'tags'             => ['id', 'tags_id_seq'],
                            'user'             => ['id', 'user_id_seq'],
                            'user_list'        => ['id', 'user_list_id_seq'],
                            'user_resource'    => ['id', 'user_resource_id_seq'],
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
        $sm->setService(\VuFind\Tags::class, new \VuFind\Tags());
        return $sm->get(\VuFind\Db\Table\PluginManager::class)->get($table);
    }
}
