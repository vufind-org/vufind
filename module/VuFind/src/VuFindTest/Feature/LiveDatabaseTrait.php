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

use Throwable;
use VuFind\Account\UserAccountService;
use VuFind\Db\Service\DbServiceInterface;
use VuFind\Db\Service\PluginManager as ServiceManager;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Table\Gateway;
use VuFind\Db\Table\PluginManager as TableManager;
use VuFind\Favorites\FavoritesService;
use VuFind\Favorites\FavoritesServiceFactory;
use VuFind\Record\ResourcePopulator;
use VuFindTest\Container\MockContainer;

use function count;

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
    public bool $hasLiveDatabaseTrait = true;

    /**
     * Container connected to live database.
     *
     * @var ?MockContainer
     */
    protected ?MockContainer $liveDatabaseContainer = null;

    /**
     * Get a real, working table manager.
     *
     * @return MockContainer
     */
    public function getLiveDatabaseContainer(): MockContainer
    {
        if (!$this->liveDatabaseContainer) {
            // Set up the bare minimum services to actually load real configs:
            $config = include APPLICATION_PATH . '/module/VuFind/config/module.config.php';
            $container = new MockContainer($this);
            $configManager = new \VuFind\Config\PluginManager(
                $container,
                $config['vufind']['config_reader']
            );
            $container->set(\VuFind\Config\PluginManager::class, $configManager);
            $this->addPathResolverToContainer($container);
            $adapterFactory = new \VuFind\Db\AdapterFactory(
                $configManager->get('config')
            );
            $container->set(
                \Laminas\Db\Adapter\Adapter::class,
                $adapterFactory->getAdapter()
            );
            $container->set('config', $config);
            $container->set(\VuFind\Log\Logger::class, $this->createMock(\Laminas\Log\LoggerInterface::class));
            $container->set(
                \VuFind\Db\Row\PluginManager::class,
                new \VuFind\Db\Row\PluginManager($container, [])
            );
            $liveTableManager = new TableManager($container, []);
            $container->set(TableManager::class, $liveTableManager);
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
     * Get a real, working table manager.
     *
     * @return TableManager
     */
    public function getLiveTableManager(): TableManager
    {
        return $this->getLiveDatabaseContainer()->get(TableManager::class);
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
     * Get a table object.
     *
     * @param string $table Name of table to load
     *
     * @return Gateway
     */
    public function getTable(string $table): Gateway
    {
        return $this->getLiveTableManager()->get($table);
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
                'table' => \VuFind\Db\Table\User::class,
                'name' => 'users',
            ],
            [
                'table' => \VuFind\Db\Table\Tags::class,
                'name' => 'tags',
            ],
        ];
        foreach ($checks as $check) {
            $table = $test->getTable($check['table']);
            if (count($table->select()) > 0) {
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
            $userTable = $test->getTable(\VuFind\Db\Table\User::class);
            foreach ((array)$users as $username) {
                $user = $userTable->getByUsername($username, false);
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
