<?php

/**
 * Mix-in for accessing a real database during testing. Some user-related
 * functionality depends upon the LiveDetectionTrait for identification of a live
 * test environment.
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
    use PathResolverTrait;

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
     * Get merged module config for database access.
     *
     * @return array
     */
    protected function getMergedConfig(): array
    {
        $dm = new \DoctrineModule\Module();
        $dmConfig = $dm->getConfig();
        $dmo = new \DoctrineORMModule\Module();
        $dmoConfig = $dmo->getConfig();
        $vfConfig
            = include APPLICATION_PATH . '/module/VuFind/config/module.config.php';
        return array_replace_recursive($dmConfig, $dmoConfig, $vfConfig);
    }

    /**
     * Set up minimum Doctrine dependencies in the provided container.
     *
     * @param object $container Container to populate
     *
     * @return void
     */
    protected function addDoctrineDependenciesToContainer($container): void
    {
        $container->setAlias(
            'doctrine.entitymanager.orm_vufind',
            \Doctrine\ORM\EntityManager::class
        );
        $container->setAlias(
            'doctrine.connection.orm_vufind',
            \VuFind\Db\Connection::class
        );
        $connectionFactory = new \VuFind\Db\ConnectionFactory();
        $container->set(
            \VuFind\Db\Connection::class,
            $connectionFactory($container, \VuFind\Db\Connection::class)
        );
        $config = $container->get('config');
        $cacheFactory = new \DoctrineModule\Service\CacheFactory(('filesystem'));
        $cacheDir = $config['doctrine']['cache']['filesystem']['directory'] . '_testmode';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }
        $container->set(
            'doctrine.cache.filesystem',
            new \DoctrineModule\Cache\LaminasStorageCache(
                new \Laminas\Cache\Storage\Adapter\Filesystem(compact('cacheDir'))
            )
        );
        $driverFactory = new \DoctrineModule\Service\DriverFactory('orm_default');
        $container->set(
            'doctrine.driver.orm_default',
            $driverFactory($container, 'orm_default')
        );
        $configFactory
            = new \DoctrineORMModule\Service\ConfigurationFactory('orm_vufind');
        $container->set(
            'doctrine.configuration.orm_vufind',
            $configFactory($container, 'orm_vufind')
        );
        $eventManagerFactory
            = new \DoctrineModule\Service\EventManagerFactory('orm_default');
        $container->set(
            'doctrine.eventmanager.orm_default',
            $eventManagerFactory($container, 'orm_default')
        );
        $entityResolverFactory
            = new \DoctrineORMModule\Service\EntityResolverFactory('orm_default');
        $container->set(
            'doctrine.entity_resolver.orm_default',
            $entityResolverFactory($container, 'orm_default')
        );
        $entityManagerFactory = new \DoctrineORMModule\Service\EntityManagerFactory(
            'orm_vufind'
        );
        $container->set(
            \Doctrine\ORM\EntityManager::class,
            $entityManagerFactory($container, 'orm_vufind')
        );
        $container->set(
            \VuFind\Db\Entity\PluginManager::class,
            new \VuFind\Db\Entity\PluginManager($container, [])
        );
        $container->set(
            \VuFind\Db\Service\PluginManager::class,
            new \VuFind\Db\Service\PluginManager($container, [])
        );
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
            $config = $this->getMergedConfig();
            $container = new \VuFindTest\Container\MockContainer($this);
            $container->set('config', $config);
            $configManager = new \VuFind\Config\PluginManager(
                $container,
                $config['vufind']['config_reader']
            );
            $container->set(\VuFind\Config\PluginManager::class, $configManager);
            $this->addPathResolverToContainer($container);
            $this->addDoctrineDependenciesToContainer($container);
            $adapterFactory = new \VuFind\Db\AdapterFactory(
                $configManager->get('config')
            );
            $container->set(
                \Laminas\Db\Adapter\Adapter::class,
                $adapterFactory->getAdapter()
            );
            $container->set(\VuFind\Tags::class, new \VuFind\Tags());
            $container->set(
                \VuFind\Db\Row\PluginManager::class,
                new \VuFind\Db\Row\PluginManager($container, [])
            );
            $this->liveTableManager = new \VuFind\Db\Table\PluginManager(
                $container,
                []
            );
            $container->set(
                \VuFind\Db\Table\PluginManager::class,
                $this->liveTableManager
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

    /**
     * Static setup support function to fail if there is already data in the
     * database. We want to ensure a clean state for each test!
     *
     * @return void
     */
    protected static function failIfDataExists(): void
    {
        $test = new static();   // create instance of current class
        // Fail if the test does not include the LiveDetectionTrait.
        if (!$test->hasLiveDetectionTrait ?? false) {
            self::fail(
                'Test requires LiveDetectionTrait, but it is not used.'
            );
        }
        // If CI is not running, all tests were skipped, so no work is necessary:
        if (!$test->continuousIntegrationRunning()) {
            return;
        }
        // Fail if there are already records in the database (we don't want to run
        // this on a real system -- it's only meant for the continuous integration
        // server)
        $checks = [
            [
                'table' => \VuFind\Db\Table\User::class,
                'name' => 'users'
            ],
            [
                'table' => \VuFind\Db\Table\Tags::class,
                'name' => 'tags'
            ],
        ];
        foreach ($checks as $check) {
            $table = $test->getTable($check['table']);
            if (count($table->select()) > 0) {
                self::fail(
                    "Test cannot run with pre-existing {$check['name']} in database!"
                );
                return;
            }
        }
    }

    /**
     * Static teardown support function to destroy user accounts. Accounts are
     * expected to exist, and the method will fail if they are missing.
     *
     * @param array|string $users User(s) to delete
     *
     * @return void
     *
     * @throws \Exception
     */
    protected static function removeUsers($users)
    {
        $test = new static();   // create instance of current class
        // Fail if the test does not include the LiveDetectionTrait.
        if (!$test->hasLiveDetectionTrait ?? false) {
            self::fail(
                'Test requires LiveDetectionTrait, but it is not used.'
            );
        }
        // If CI is not running, all tests were skipped, so no work is necessary:
        if (!$test->continuousIntegrationRunning()) {
            return;
        }
        // Delete test user
        $userTable = $test->getTable(\VuFind\Db\Table\User::class);
        foreach ((array)$users as $username) {
            $user = $userTable->getByUsername($username, false);
            if (!empty($user)) {
                $user->delete();
            }
        }
    }
}
