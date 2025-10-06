<?php

/**
 * Mix-in for accessing a real database during testing. Some user-related
 * functionality depends upon the LiveDetectionTrait for identification of a live
 * test environment.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Throwable;
use VuFind\Account\UserAccountService;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\DbServiceInterface;
use VuFind\Db\Service\PluginManager as ServiceManager;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserService;
use VuFind\Favorites\FavoritesService;
use VuFind\Favorites\FavoritesServiceFactory;
use VuFind\Record\ResourcePopulator;
use VuFindTest\Container\MockContainer;

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
    use ConfigRelatedServicesTrait;

    /**
     * Flag to allow other traits to test for the presence of this one (to enforce
     * dependencies).
     *
     * @var bool
     */
    public bool $hasLiveDatabaseTrait = true;

    /**
     * Plugin manager for database services.
     *
     * @var ?ServiceManager
     */
    protected ?ServiceManager $liveDatabaseServiceManager = null;

    /**
     * Container with database-related services configured.
     *
     * @var ?MockContainer
     */
    protected ?MockContainer $liveDatabaseContainer = null;

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
        $options = $config['caches']['doctrinemodule.cache.filesystem']['options'];
        $options['cache_dir']
            = LOCAL_CACHE_DIR . '/' . $options['cache_dir'] . '_testmode';
        if (!is_dir($options['cache_dir'])) {
            mkdir($options['cache_dir'], 0o777, true);
        }
        $cacheAdapter = new \Laminas\Cache\Storage\Adapter\Filesystem($options);
        $cacheAdapter->addPlugin(new \Laminas\Cache\Storage\Plugin\Serializer());
        $container->set(
            'doctrine.cache.filesystem',
            new \DoctrineModule\Cache\LaminasStorageCache($cacheAdapter)
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
        $container->set(
            \VuFind\Db\Entity\PluginManager::class,
            new \VuFind\Db\Entity\PluginManager($container, [])
        );
        $container->set(
            \VuFind\Db\Service\PluginManager::class,
            new \VuFind\Db\Service\PluginManager($container, [])
        );
        $entityManagerFactory = new \VuFind\Db\EntityManagerFactory(
            'orm_vufind'
        );
        $container->set(
            \Doctrine\ORM\EntityManager::class,
            $entityManagerFactory($container, 'orm_vufind')
        );
        $container->set(
            PersistenceManager::class,
            new PersistenceManager($container->get(\Doctrine\ORM\EntityManager::class))
        );
    }

    /**
     * Get a container with Doctrine dependencies included
     *
     * @return \VuFindTest\Container\MockContainer
     */
    public function getMockContainerWithDoctrineDependencies()
    {
        // Set up the bare minimum services to actually load real configs:
        $config = $this->getMergedConfig();
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFind\Log\Logger::class, $this->createMock(\Laminas\Log\LoggerInterface::class));
        $container->set('config', $config);
        $this->addConfigRelatedServicesToContainer($container, moduleConfig: $config);
        $this->addDoctrineDependenciesToContainer($container);
        return $container;
    }

    /**
     * Get a container with database-related services configured.
     *
     * @return MockContainer
     */
    public function getLiveDatabaseContainer(): MockContainer
    {
        if (!$this->liveDatabaseContainer) {
            $container = $this->getMockContainerWithDoctrineDependencies();
            $container->set(\VuFind\Log\Logger::class, $this->createMock(\Laminas\Log\LoggerInterface::class));
            $liveServiceManager = new ServiceManager($container, []);
            $container->set(ServiceManager::class, $liveServiceManager);
            $container->set(
                \VuFind\Tags\TagsService::class,
                new \VuFind\Tags\TagsService(
                    $liveServiceManager->get(TagServiceInterface::class),
                    $liveServiceManager->get(ResourceTagsServiceInterface::class),
                    $liveServiceManager->get(UserListServiceInterface::class),
                    $container->get(ResourcePopulator::class)
                )
            );
            $favoritesFactory = new FavoritesServiceFactory();
            $favoritesService = $favoritesFactory($container, FavoritesService::class);
            $container->set(FavoritesService::class, $favoritesService);
            $this->liveDatabaseContainer = $container;
        }
        return $this->liveDatabaseContainer;
    }

    /**
     * Get a real, working database service manager.
     *
     * @return ServiceManager
     */
    public function getLiveDbServiceManager(): ServiceManager
    {
        return $this->getLiveDatabaseContainer()->get(ServiceManager::class);
    }

    /**
     * Get a database service.
     *
     * @param string $service Name of service to load
     *
     * @return DbServiceInterface
     */
    public function getDbService(string $service): DbServiceInterface
    {
        return $this->getLiveDbServiceManager()->get($service);
    }

    /**
     * Get the favorites service.
     *
     * @return FavoritesService
     */
    public function getFavoritesService(): FavoritesService
    {
        return $this->getLiveDatabaseContainer()->get(FavoritesService::class);
    }

    /**
     * Static setup support function to fail if there is already data in the
     * database. We want to ensure a clean state for each test!
     *
     * @param ?string $failMessage Failure message to display if data exists (null for default).
     *
     * @return void
     */
    protected static function failIfDataExists(?string $failMessage = null): void
    {
        $test = new static('');   // create instance of current class
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
                'service' => \VuFind\Db\Service\UserService::class,
                'entity' => \VuFind\Db\Entity\User::class,
                'name' => 'users',
            ],
            [
                'service' => \VuFind\Db\Service\TagService::class,
                'entity' => \VuFind\Db\Entity\Tags::class,
                'name' => 'tags',
            ],
        ];
        foreach ($checks as $check) {
            $dbService = $test->getDbService($check['service']);
            if ($dbService->getRowCountForTable($check['entity']) > 0) {
                self::fail(
                    $failMessage ?? "Test cannot run with pre-existing {$check['name']} in database!"
                );
                return;
            }
        }
    }

    /**
     * Static teardown support function to destroy user accounts. Accounts are
     * expected to exist, and the method will fail if they are missing.
     *
     * @param string[]|string $users User(s) to delete
     *
     * @return void
     *
     * @throws \Exception
     */
    protected static function removeUsers(array|string $users): void
    {
        try {
            $test = new static('');   // create instance of current class
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
            $userService = $test->getDbService(UserService::class);
            foreach ((array)$users as $username) {
                $user = $userService->getUserByUsername($username);
                if (!empty($user)) {
                    $purgeService = new UserAccountService($test->getFavoritesService());
                    $purgeService->setDbServiceManager($test->getLiveDbServiceManager());
                    $purgeService->purgeUserData($user);
                }
            }
        } catch (Throwable $t) {
            echo "\n\nError in removeUsers(): " . (string)$t . "\n";
        }
    }
}
