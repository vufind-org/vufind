<?php

/**
 * Mix-in for accessing a real database during testing.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
namespace VuFindTest\Feature;

/**
 * Mix-in for accessing a real database during testing.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait LiveDatabaseTrait
{
    /**
     * Flag to allow other traits to test for the presence of this one (to enforce
     * dependencies).
     *
     * @var bool
     */
    public $hasLiveDatabaseTrait = true;

    /**
     * Table manager connected to live database.
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $liveTableManager = null;

    /**
     * Get a real, working table manager.
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    public function getLiveTableManager()
    {
        if (!$this->liveTableManager) {
            // Set up the bare minimum services to actually load real configs:
            $config = include APPLICATION_PATH
                . '/module/VuFind/config/module.config.php';
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
