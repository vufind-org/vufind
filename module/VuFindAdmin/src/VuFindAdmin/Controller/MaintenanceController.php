<?php

/**
 * Admin Maintenance Controller
 *
 * PHP version 8
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Controller;

use DateTime;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Log\LoggerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Db\Service\Feature\DeleteExpiredInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Http\GuzzleService;

use function ini_get;
use function intval;

/**
 * Class helps maintain database
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MaintenanceController extends AbstractAdmin
{
    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Guzzle service
     *
     * @var GuzzleService
     */
    protected $guzzleService;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm            Service locator
     * @param CacheManager            $cacheManager  Cache manager
     * @param GuzzleService           $guzzleService Guzzle service
     * @param LoggerInterface         $logger        Logger
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        CacheManager $cacheManager,
        GuzzleService $guzzleService,
        LoggerInterface $logger
    ) {
        parent::__construct($sm);
        $this->cacheManager = $cacheManager;
        $this->guzzleService = $guzzleService;
        $this->logger = $logger;
    }

    /**
     * System Maintenance
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $cacheManager = $this->getService(\VuFind\Cache\Manager::class);
        $view->caches = $cacheManager->getCacheList();
        $view->nonPersistentCaches = $cacheManager->getNonPersistentCacheList();
        $view->scripts = $this->getScripts();
        $view->setTemplate('admin/maintenance/home');
        return $view;
    }

    /**
     * Get a list of the names of scripts available to run through the admin panel.
     *
     * @return array
     */
    protected function getScripts(): array
    {
        // Load the AdminScripts.ini settings
        $config = $this->getService(\VuFind\Config\PluginManager::class)
            ->get('AdminScripts')->toArray();
        $globalConfig = $config['Global'] ?? [];
        unset($config['Global']);

        // Filter out any commands that the current user does not have permission to run:
        $permission = $this->permission();
        $filter = function ($script) use ($permission, $globalConfig) {
            $requiredPermission = $script['permission'] ?? $globalConfig['defaultPermission'] ?? null;
            return empty($requiredPermission) || $permission->isAuthorized($requiredPermission);
        };
        return array_filter($config, $filter);
    }

    /**
     * Run script action.
     *
     * @return mixed
     */
    public function scriptAction()
    {
        $script = $this->params()->fromRoute('name');
        $scripts = $this->getScripts();
        $details = $scripts[$script] ?? null;
        if (empty($details['command'])) {
            $this->flashMessenger()->addErrorMessage('Unknown command: ' . $script);
        } else {
            $code = $output = null;
            exec($details['command'], $output, $code);
            $successCode = intval($details['successCode'] ?? 0);
            if ($code !== $successCode) {
                $this->flashMessenger()->addErrorMessage(
                    "Command failed; expected $successCode but received $code"
                );
            } else {
                $this->flashMessenger()->addSuccessMessage(
                    "Success ($script)! Output = " . implode("\n", $output)
                );
            }
        }
        return $this->redirect()->toRoute('admin/maintenance');
    }

    /**
     * Clear cache(s).
     *
     * @return mixed
     */
    public function clearcacheAction()
    {
        $cache = null;
        $cacheManager = $this->getService(\VuFind\Cache\Manager::class);
        foreach ($this->params()->fromQuery('cache', []) as $cache) {
            $cacheManager->getCache($cache)->flush();
        }
        // If cache is unset, we didn't go through the loop above, so no message
        // needs to be displayed.
        if (isset($cache)) {
            $this->flashMessenger()->addSuccessMessage('Cache(s) cleared.');
        }
        return $this->forwardTo('AdminMaintenance', 'Home');
    }

    /**
     * Delete expired searches.
     *
     * @return mixed
     */
    public function deleteexpiredsearchesAction()
    {
        // Delete the expired searches--this cleans up any junk left in the
        // database from old search histories that were not caught by the
        // session garbage collector.
        return $this->expire(
            SearchServiceInterface::class,
            '%%count%% expired searches deleted.',
            'No expired searches to delete.'
        );
    }

    /**
     * Delete expired sessions.
     *
     * @return mixed
     */
    public function deleteexpiredsessionsAction()
    {
        // Delete the expired sessions--this cleans up any junk left in the
        // database by the session garbage collector.
        return $this->expire(
            SessionServiceInterface::class,
            '%%count%% expired sessions deleted.',
            'No expired sessions to delete.'
        );
    }

    /**
     * Update browscap cache action.
     *
     * @return mixed
     */
    public function updatebrowscapcacheAction()
    {
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }
        $this->updateBrowscapCache();
        return $this->forwardTo('AdminMaintenance', 'Home');
    }

    /**
     * Abstract delete method.
     *
     * @param string $serviceName   Service to operate on.
     * @param string $successString String for reporting success.
     * @param string $failString    String for reporting failure.
     * @param int    $minAge        Minimum age allowed for expiration (also used
     * as default value).
     *
     * @return mixed
     */
    protected function expire($serviceName, $successString, $failString, $minAge = 2)
    {
        $daysOld = intval($this->params()->fromQuery('daysOld', $minAge));
        if ($daysOld < $minAge) {
            $this->flashMessenger()->addErrorMessage(
                str_replace(
                    '%%age%%',
                    $minAge,
                    'Expiration age must be at least %%age%% days.'
                )
            );
        } else {
            $service = $this->getDbService($serviceName);
            if (!$service instanceof DeleteExpiredInterface) {
                throw new \Exception("Unsupported service: $serviceName");
            }
            $count = $service->deleteExpired(new DateTime("now - $daysOld days"));
            if ($count == 0) {
                $msg = $failString;
            } else {
                $msg = str_replace('%%count%%', $count, $successString);
            }
            $this->flashMessenger()->addSuccessMessage($msg);
        }
        return $this->forwardTo('AdminMaintenance', 'Home');
    }

    /**
     * Update browscap cache.
     *
     * Note that there's also similar functionality in BrowscapCommand CLI utility.
     *
     * @return void
     */
    protected function updateBrowscapCache(): void
    {
        ini_set('memory_limit', '1024M');
        $type = $this->params()->fromQuery('cacheType', 'standard');
        switch ($type) {
            case 'full':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_FULL;
                break;
            case 'lite':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_LITE;
                break;
            case 'standard':
                $type = \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI;
                break;
            default:
                $this->flashMessenger()->addErrorMessage('Invalid browscap file-type specified');
                return;
        }

        $cache = new SimpleCacheDecorator($this->cacheManager->getCache('browscap'));
        $client = $this->guzzleService->createClient();

        $bc = new \BrowscapPHP\BrowscapUpdater($cache, new \Laminas\Log\PsrLoggerAdapter($this->logger), $client);
        try {
            $bc->checkUpdate();
        } catch (\BrowscapPHP\Exception\NoNewVersionException $e) {
            $this->flashMessenger()
                ->addSuccessMessage('No newer browscap version available. Clear the cache to force update.');
            return;
        } catch (\BrowscapPHP\Exception\FetcherException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->logger->err((string)$e);
            return;
        } catch (\BrowscapPHP\Exception\NoCachedVersionException $e) {
            // Fall through...
        } catch (\Exception $e) {
            // Output the exception and continue (assume we don't have a current version):
            $this->flashMessenger()->addWarningMessage($e->getMessage());
            $this->logger->warn((string)$e);
        }
        try {
            $bc->update($type);
            $this->logger->info('Browscap cache updated');
            $this->flashMessenger()->addSuccessMessage('Browscap cache successfully updated.');
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->logger->warn((string)$e);
        }
    }
}
