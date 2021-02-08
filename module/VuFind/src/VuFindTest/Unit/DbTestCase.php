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

use Psr\Container\ContainerInterface;

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
     * Table manager connected to live database.
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    protected $liveTableManager = null;

    /**
     * Add table manager to service manager.
     *
     * @param ContainerInterface $sm Service manager
     *
     * @return void
     */
    protected function addTableManager(ContainerInterface $sm)
    {
        $factory = new \VuFind\Db\Table\PluginManager(
            $sm,
            [
                'abstract_factories' =>
                    ['VuFind\Db\Table\PluginFactory'],
            ]
        );
        $sm->set('VuFind\Db\Table\PluginManager', $factory);
    }

    /**
     * Add row manager to service manager.
     *
     * @param ContainerInterface $sm Service manager
     *
     * @return void
     */
    protected function addRowManager(ContainerInterface $sm)
    {
        $factory = new \VuFind\Db\Row\PluginManager($sm);
        $sm->set('VuFind\Db\Row\PluginManager', $factory);
    }

    /**
     * Get a service manager.
     *
     * @return ContainerInterface
     */
    public function getServiceManager()
    {
        // Get parent service manager:
        $sm = parent::getServiceManager();

        // Add database service:
        static $serviceAdded = false;
        if (!$serviceAdded) {
            $serviceAdded = true;
            $dbFactory = new \VuFind\Db\AdapterFactory(
                $sm->get(\VuFind\Config\PluginManager::class)->get('config')
            );
            $sm->set('Laminas\Db\Adapter\Adapter', $dbFactory->getAdapter());
            $this->addTableManager($sm);
            $this->addRowManager($sm);
            $sm->set(
                'Laminas\Session\SessionManager',
                $this->createMock(\Laminas\Session\SessionManager::class)
            );

            // Override the configuration so PostgreSQL tests can work:
            $sm->set(
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
     * Get a real, working table manager.
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    public function getLiveTableManager()
    {
        if (!$this->liveTableManager) {
            // Set up the bare minimum services to actually load real configs:
            $config = require(
                APPLICATION_PATH . '/module/VuFind/config/module.config.php'
            );
            $container = new \VuFindTest\Container\MockContainer($this);
            $configManager = new \VuFind\Config\PluginManager(
                $container, $config['vufind']['config_reader']
            );
            $container->set(\VuFind\Config\PluginManager::class, $configManager);
            $adapterFactory = new \VuFind\Db\AdapterFactory(
                $configManager->get('config')
            );
            $container->set(
                \Laminas\Db\Adapter\Adapter::class, $adapterFactory->getAdapter()
            );
            $container->set(\VuFind\Tags::class, new \VuFind\Tags());
            $container->set('config', $config);
            $container->set(
                \VuFind\Db\Row\PluginManager::class,
                new \VuFind\Db\Row\PluginManager($container, [])
            );
            $this->liveTableManager = new \VuFind\Db\Table\PluginManager(
                $container, []
            );
            $container->set(
                \VuFind\Db\Table\PluginManager::class, $this->liveTableManager
            );
        }
        return $this->liveTableManager;
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
        return $this->getLiveTableManager()->get($table);
    }
}
