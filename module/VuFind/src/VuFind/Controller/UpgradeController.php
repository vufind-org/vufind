<?php

/**
 * Upgrade Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use ArrayObject;
use Composer\Semver\Comparator;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Upgrade as ConfigUpgrader;
use VuFind\Config\Version;
use VuFind\Config\Writer;
use VuFind\Cookie\Container as CookieContainer;
use VuFind\Cookie\CookieManager;
use VuFind\Crypt\Base62;
use VuFind\Crypt\BlockCipher;
use VuFind\Db\AdapterFactory;
use VuFind\Db\MigrationManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Db\Service\ShortlinksServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Record\ResourcePopulator;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Tags\TagsService;

use function count;
use function dirname;
use function in_array;
use function strlen;

/**
 * Class controls VuFind upgrading.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UpgradeController extends AbstractBase
{
    use Feature\ConfigPathTrait;
    use Feature\SecureDatabaseTrait;

    /**
     * Cookie container
     *
     * @var CookieContainer
     */
    protected $cookie;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Are we capturing SQL instead of executing it?
     *
     * @var bool
     */
    protected $logsql = false;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm               Service manager
     * @param CookieManager           $cookieManager    Cookie manager
     * @param Container               $sessionContainer Session container
     * @param ConfigUpgrader          $configUpgrader   Config upgrader
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        CookieManager $cookieManager,
        Container $sessionContainer,
        protected ConfigUpgrader $configUpgrader
    ) {
        parent::__construct($sm);

        // We want to use cookies for tracking the state of the upgrade, since the
        // session is unreliable -- if the user upgrades a configuration that uses
        // a different session handler than the default one, we'll lose track of our
        // upgrade state in the middle of the process!
        $this->cookie = new CookieContainer('vfup', $cookieManager);

        // ...however, once the configuration piece of the upgrade is done, we can
        // safely use the session for storing some values. We'll use this for the
        // temporary storage of root database credentials, since it is unwise to
        // send such sensitive values around as cookies!
        $this->session = $sessionContainer;

        // We should also use the session for storing warnings once we know it will
        // be stable; this will prevent the cookies from getting too big.
        if (!isset($this->session->warnings)) {
            $this->session->warnings = new ArrayObject();
        }
    }

    /**
     * Use preDispatch event to block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function validateAutoConfigureConfig(MvcEvent $e)
    {
        // If auto-configuration is disabled, prevent any other action from being
        // accessed:
        $config = $this->getConfig();
        if (
            !isset($config->System->autoConfigure)
            || !$config->System->autoConfigure
        ) {
            $routeMatch = $e->getRouteMatch();
            $routeMatch->setParam('action', 'disabled');
        }
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();
        $events = $this->getEventManager();
        $events->attach(
            MvcEvent::EVENT_DISPATCH,
            [$this, 'validateAutoConfigureConfig'],
            1000
        );
    }

    /**
     * Display disabled message.
     *
     * @return mixed
     */
    public function disabledAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('install/disabled');
        return $view;
    }

    /**
     * Display a fatal error message.
     *
     * @return mixed
     */
    public function errorAction()
    {
        // Just display template
        return $this->createViewModel();
    }

    /**
     * Upgrade the configuration files.
     *
     * @return mixed
     */
    public function fixconfigAction()
    {
        try {
            $this->configUpgrader->run(
                $this->cookie->newVersion,
            );
            $this->cookie->warnings = $this->configUpgrader->getWarnings();
            $this->cookie->configOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        } catch (Exception $e) {
            $extra = is_a($e, \VuFind\Exception\FileAccess::class)
                ? '  Check file permissions.' : '';
            $this->flashMessenger()->addMessage(
                'Config upgrade failed: ' . $e->getMessage() . $extra,
                'error'
            );
            return $this->forwardTo('Upgrade', 'Error');
        }
    }

    /**
     * Get a database adapter for root access using credentials in session.
     *
     * @return Adapter
     */
    protected function getRootDbAdapter()
    {
        // Use static cache to avoid loading adapter more than once on
        // subsequent calls.
        static $adapter = false;
        if (!$adapter) {
            $factory = $this->getService(AdapterFactory::class);
            $adapter = $factory->getAdapter(
                $this->session->dbRootUser,
                $this->session->dbRootPass
            );
        }
        return $adapter;
    }

    /**
     * Do we have root DB credentials stored?
     *
     * @return bool
     */
    protected function hasDatabaseRootCredentials()
    {
        return isset($this->session->dbRootUser)
            && isset($this->session->dbRootPass);
    }

    /**
     * Configure the database encoding.
     *
     * @param string $charset Encoding setting to use.
     *
     * @throws Exception
     * @return void
     */
    protected function setDbEncodingConfiguration($charset)
    {
        $config = $this->getForcedLocalConfigPath('config.ini');
        $writer = new Writer($config);
        $writer->set('Database', 'charset', $charset);
        if (!$writer->save()) {
            throw new Exception('Problem writing DB encoding to config.ini');
        }
    }

    /**
     * Support method for fixdatabaseAction() -- clean up legacy 'VuFind'
     * source values in the database.
     *
     * @return void
     */
    protected function fixVuFindSourceInDatabase()
    {
        if ($count = $this->getDbService(ResourceServiceInterface::class)->renameSource('VuFind', 'Solr')) {
            $this->session->warnings
                ->append('Converted ' . $count . ' legacy "VuFind" source value(s) in resource table');
        }
    }

    /**
     * Support method for fixdatabaseAction() -- add checksums to search table rows.
     *
     * @return void
     */
    protected function fixSearchChecksumsInDatabase()
    {
        $manager = $this->getService(ResultsManager::class);
        $searchService = $this->getDbService(SearchServiceInterface::class);
        $searchRows = $searchService->getSavedSearchesWithMissingChecksums();
        if (count($searchRows) > 0) {
            foreach ($searchRows as $searchRow) {
                $searchObj = $searchRow->getSearchObject()?->deminify($manager);
                if (!$searchObj) {
                    throw new Exception("Missing search data for row {$searchRow->getId()}.");
                }
                $url = $searchObj->getUrlQuery()->getParams();
                $checksum = crc32($url) & 0xFFFFFFF;
                $searchRow->setChecksum($checksum);
                $searchService->persistEntity($searchRow);
            }
            $this->session->warnings->append(
                'Added checksum to ' . count($searchRows) . ' rows in search table'
            );
        }
    }

    /**
     * Look up relevant database migrations and return them as a string (empty string if none needed).
     *
     * @return string
     */
    public function getDatabaseMigrations(): string
    {
        $adapter = $this->getService(Adapter::class);
        $rawPlatform = strtolower($adapter->getDriver()->getDatabasePlatformName());
        $platform = match ($rawPlatform) {
            'postgresql' => 'pgsql',
            default => $rawPlatform,
        };
        $migrationManager = new MigrationManager();
        $sql = '';
        foreach ($migrationManager->getMigrations($platform, $this->cookie->oldVersion) as $migration) {
            $sql .= file_get_contents($migration) . "\n";
        }
        return $sql;
    }

    /**
     * Apply migrations to the database. Return null if successful, or a Laminas view model if
     * user input is required.
     *
     * @return ?ViewModel
     */
    public function applyDatabaseMigrations(): ?ViewModel
    {
        $migrationSql = trim($this->getDatabaseMigrations());
        if (!empty($migrationSql) && !$this->logsql) {
            if (!$this->hasDatabaseRootCredentials()) {
                return $this->forwardTo('Upgrade', 'GetDbCredentials');
            }
            $adapter = $this->getRootDbAdapter();
            foreach (explode(';', $migrationSql) as $sqlLine) {
                $trimmedLine = trim($sqlLine);
                if (!empty($trimmedLine)) {
                    $adapter->query($trimmedLine, $adapter::QUERY_MODE_EXECUTE);
                }
            }
            // Don't keep DB credentials in session longer than necessary:
            unset($this->session->dbRootUser);
            unset($this->session->dbRootPass);
            $this->session->sql = '';
        } else {
            $this->session->sql = $migrationSql;
        }
        return null;
    }

    /**
     * Upgrade the database.
     *
     * @return mixed
     */
    public function fixdatabaseAction()
    {
        try {
            // If we haven't already tried it, attempt a structure update:
            if (!isset($this->session->sql)) {
                if ($result = $this->applyDatabaseMigrations()) {
                    return $result;
                }
            }

            // If we have SQL to show, stop at this point to allow the changes to be made before progressing any
            // further:
            if (!empty($this->session->sql)) {
                return $this->forwardTo('Upgrade', 'ShowSql');
            }

            // Now that database structure is addressed, we can fix database
            // content -- the checks below should be platform-independent.

            // Check for legacy tag bugs:
            $anonymousTags = $this->getDbService(ResourceTagsServiceInterface::class)->getAnonymousCount();
            if ($anonymousTags > 0 && !isset($this->cookie->skipAnonymousTags)) {
                $this->getRequest()->getQuery()->set('anonymousCnt', $anonymousTags);
                return $this->redirect()->toRoute('upgrade-fixanonymoustags');
            }
            $dupeTags = $this->getService(TagsService::class)->getDuplicateTags();
            if (count($dupeTags) > 0 && !isset($this->cookie->skipDupeTags)) {
                return $this->redirect()->toRoute('upgrade-fixduplicatetags');
            }

            // fix shortlinks
            $this->fixshortlinks();

            // Clean up the "VuFind" source, if necessary.
            $this->fixVuFindSourceInDatabase();
        } catch (Exception $e) {
            $this->flashMessenger()->addMessage(
                'Database upgrade failed: ' . $e->getMessage(),
                'error'
            );
            return $this->forwardTo('Upgrade', 'Error');
        }

        // Add checksums to all saved searches but catch exceptions (e.g. in case
        // column checksum does not exist yet because of sqllog).
        try {
            $this->fixSearchChecksumsInDatabase();
        } catch (Exception $e) {
            $this->session->warnings->append(
                'Could not fix checksums in table search - maybe column ' .
                'checksum is missing? Exception thrown with ' .
                'message: ' . $e->getMessage()
            );
        }

        $this->cookie->databaseOkay = true;
        return $this->redirect()->toRoute('upgrade-home');
    }

    /**
     * Prompt the user for database credentials.
     *
     * @return mixed
     */
    public function showsqlAction()
    {
        $continue = $this->params()->fromPost('continue', 'nope');
        if (str_contains($continue, 'Next')) {
            // Clear the SQL out but leave it set; this will prevent the user from
            // getting caught in a loop -- we won't show them the migrations another
            // time this session.
            $this->session->sql = '';
            return $this->redirect()->toRoute('upgrade-home');
        }

        return $this->createViewModel(['sql' => $this->session->sql]);
    }

    /**
     * Prompt the user for database credentials.
     *
     * @return mixed
     */
    public function getdbcredentialsAction()
    {
        $print = $this->params()->fromPost('printsql', 'nope');
        if ($print == 'Skip') {
            $this->logsql = true;
            return $this->forwardTo('Upgrade', 'FixDatabase');
        } else {
            $dbrootuser = $this->params()->fromPost('dbrootuser', 'root');

            // Process form submission:
            if ($this->formWasSubmitted()) {
                $pass = $this->params()->fromPost('dbrootpass');

                // Test the connection:
                try {
                    // Query a table known to exist
                    $factory = $this->getService(AdapterFactory::class);
                    $db = $factory->getAdapter($dbrootuser, $pass);
                    $db->query('SELECT * FROM user;');
                    $this->session->dbRootUser = $dbrootuser;
                    $this->session->dbRootPass = $pass;
                    return $this->forwardTo('Upgrade', 'FixDatabase');
                } catch (Exception $e) {
                    $this->flashMessenger()->addMessage(
                        'Could not connect; please try again.',
                        'error'
                    );
                }
            }
        }

        return $this->createViewModel(['dbrootuser' => $dbrootuser]);
    }

    /**
     * Prompt the user about fixing anonymous tags.
     *
     * @return mixed
     */
    public function fixanonymoustagsAction()
    {
        // Handle skip action:
        if (strlen($this->params()->fromPost('skip', '')) > 0) {
            $this->cookie->skipAnonymousTags = true;
            return $this->forwardTo('Upgrade', 'FixDatabase');
        }

        // Handle submit action:
        if ($this->formWasSubmitted()) {
            $username = $this->params()->fromPost('username');
            if (empty($username)) {
                $this->flashMessenger()
                    ->addMessage('Username must not be empty.', 'error');
            } else {
                $user = $this->getDbService(UserServiceInterface::class)->getUserByUsername($username);
                if (!$user) {
                    $this->flashMessenger()->addMessage("User {$username} not found.", 'error');
                } else {
                    $this->getDbService(ResourceTagsServiceInterface::class)->assignAnonymousTags($user);
                    $this->session->warnings->append(
                        "Assigned all anonymous tags to {$user->getUsername()}."
                    );
                    return $this->forwardTo('Upgrade', 'FixDatabase');
                }
            }
        }

        return $this->createViewModel(
            [
                'anonymousTags' => $this->params()->fromQuery('anonymousCnt'),
            ]
        );
    }

    /**
     * Prompt the user about fixing duplicate tags.
     *
     * @return mixed
     */
    public function fixduplicatetagsAction()
    {
        // Handle skip action:
        if (strlen($this->params()->fromPost('skip', '')) > 0) {
            $this->cookie->skipDupeTags = true;
            return $this->forwardTo('Upgrade', 'FixDatabase');
        }

        // Handle submit action:
        if ($this->formWasSubmitted()) {
            $this->getService(TagsService::class)->fixDuplicateTags();
            return $this->forwardTo('Upgrade', 'FixDatabase');
        }

        return $this->createViewModel();
    }

    /**
     * Fix missing metadata in the resource table.
     *
     * @return mixed
     * @throws Exception
     */
    public function fixmetadataAction()
    {
        // User requested skipping this step?  No need to do further work:
        if (strlen($this->params()->fromPost('skip', '')) > 0) {
            $this->cookie->metadataOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        }

        // This can take a while -- don't time out!
        set_time_limit(0);

        // Check for problems:
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $problems = $resourceService->findMissingMetadata();

        // No problems?  We're done here!
        if (count($problems) == 0) {
            $this->cookie->metadataOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        }

        // Process submit button:
        if ($this->formWasSubmitted()) {
            $resourcePopulator = $this->getService(ResourcePopulator::class);
            foreach ($problems as $problem) {
                $recordId = $problem->getRecordId();
                $source = $problem->getSource();
                try {
                    $driver = $this->getRecordLoader()->load($recordId, $source);
                    $resourceService->persistEntity(
                        $resourcePopulator->assignMetadata($problem, $driver)
                    );
                } catch (RecordMissingException $e) {
                    $this->session->warnings->append(
                        "Unable to load metadata for record {$source}:{$recordId}"
                    );
                } catch (\Exception $e) {
                    $this->session->warnings->append(
                        "Problem saving metadata updates for record {$source}:{$recordId}"
                    );
                }
            }
            $this->cookie->metadataOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        }
    }

    /**
     * Make sure we only skip the actions the user wants us to.
     *
     * @return void
     */
    protected function processSkipParam()
    {
        $skip = $this->params()->fromPost('skip', []);
        foreach (['config', 'database', 'metadata'] as $action) {
            $this->cookie->{$action . 'Okay'} = in_array($action, (array)$skip);
        }
    }

    /**
     * Prompt the user for a source version (to upgrade from 2.x+).
     *
     * @return mixed
     * @throws Exception
     */
    public function getsourceversionAction()
    {
        // Process form submission:
        $version = $this->params()->fromPost('sourceversion');
        if (!empty($version)) {
            $this->cookie->newVersion = $newVersion = Version::getBuildVersion();
            if (Comparator::lessThan($version, '10.0')) {
                $this->flashMessenger()->addErrorMessage(
                    'Illegal version number; please upgrade to at least version 10.x before proceeding.'
                );
            } elseif (Comparator::greaterThanOrEqualTo($version, $newVersion)) {
                $this->flashMessenger()->addMessage(
                    "Source version must be less than {$newVersion}.",
                    'error'
                );
            } else {
                $this->cookie->oldVersion = $version;
                // Clear out request to avoid infinite loop:
                $this->getRequest()->getPost()->set('sourceversion', '');
                $this->processSkipParam();
                return $this->forwardTo('Upgrade', 'Home');
            }
        }
    }

    /**
     * Organize and run critical, blocking checks
     *
     * @return string|null
     */
    protected function performCriticalChecks()
    {
        // Run through a series of checks to be sure there are no critical issues.
        return $this->criticalCheckForInsecureDatabase()
            ?? $this->criticalCheckForBlowfishEncryption()
            ?? null;
    }

    /**
     * Display summary of installation status
     *
     * @return mixed
     */
    public function homeAction()
    {
        // If the cache is messed up, nothing is going to work right -- check that
        // first:
        $cache = $this->getService(CacheManager::class);
        if ($cache->hasDirectoryCreationError()) {
            return $this->redirect()->toRoute('install-fixcache');
        }

        // First find out which version we are upgrading:
        if (!isset($this->cookie->oldVersion) || !isset($this->cookie->newVersion)) {
            return $this->forwardTo('Upgrade', 'GetSourceVersion');
        }

        // Check for critical upgrades
        $criticalFixForward = $this->performCriticalChecks() ?? null;
        if ($criticalFixForward !== null) {
            return $this->forwardTo('Upgrade', $criticalFixForward);
        }

        // Now make sure we have a configuration file ready:
        if (!isset($this->cookie->configOkay) || !$this->cookie->configOkay) {
            return $this->redirect()->toRoute('upgrade-fixconfig');
        }

        // Now make sure the database is up to date:
        if (!isset($this->cookie->databaseOkay) || !$this->cookie->databaseOkay) {
            return $this->redirect()->toRoute('upgrade-fixdatabase');
        }

        // Check for missing metadata in the resource table; note that we do a
        // redirect rather than a forward here so that a submit button clicked
        // in the database action doesn't cause the metadata action to also submit!
        if (!isset($this->cookie->metadataOkay) || !$this->cookie->metadataOkay) {
            return $this->redirect()->toRoute('upgrade-fixmetadata');
        }

        // We're finally done -- display any warnings that we collected during
        // the process.
        $allWarnings = array_merge(
            $this->cookie->warnings ?? [],
            (array)$this->session->warnings
        );
        foreach ($allWarnings as $warning) {
            $this->flashMessenger()->addMessage($warning, 'info');
        }

        return $this->createViewModel(
            ['configDir' => dirname($this->getForcedLocalConfigPath('config.ini'))]
        );
    }

    /**
     * Start over with the upgrade process in case of an error.
     *
     * @return mixed
     */
    public function resetAction()
    {
        foreach (array_keys($this->cookie->getAllValues()) as $k) {
            unset($this->cookie->$k);
        }
        $storage = $this->session->getManager()->getStorage();
        $storage[$this->session->getName()]
            = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
        return $this->forwardTo('Upgrade', 'Home');
    }

    /**
     * Generate base62 encoding to migrate old shortlinks
     *
     * @throws Exception
     *
     * @return void
     */
    protected function fixshortlinks()
    {
        $shortlinks = $this->getDbService(ShortlinksServiceInterface::class);
        $base62 = new Base62();

        try {
            $results = $shortlinks->getShortLinksWithMissingHashes();

            foreach ($results as $result) {
                $result->setHash($base62->encode($result->getId()));
                $shortlinks->persistEntity($result);
            }

            if (count($results) > 0) {
                $this->session->warnings->append(
                    'Added hash value(s) to ' . count($results) . ' short links.'
                );
            }
        } catch (Exception $e) {
            $this->session->warnings->append(
                'Could not fix hashes in table shortlinks - maybe column ' .
                'hash is missing? Exception thrown with ' .
                'message: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check for insecure database settings
     *
     * @return string|null
     */
    protected function criticalCheckForInsecureDatabase()
    {
        if (!empty($this->cookie->ignoreInsecureDb)) {
            return null;
        }
        return $this->hasSecureDatabase() ? null : 'CriticalFixInsecureDatabase';
    }

    /**
     * Check for deprecated and insecure use of blowfish encryption
     *
     * @return string|null
     */
    protected function criticalCheckForBlowfishEncryption()
    {
        $config = $this->getConfig();
        $encryptionEnabled = $config->Authentication->encrypt_ils_password ?? false;
        $algo = $config->Authentication->ils_encryption_algo ?? 'blowfish';
        return ($encryptionEnabled && $algo === 'blowfish')
            ? 'CriticalFixBlowfish' : null;
    }

    /**
     * Lead users through the steps required to fix an insecure database
     *
     * @return mixed
     */
    public function criticalFixInsecureDatabaseAction()
    {
        if ($this->params()->fromQuery('ignore')) {
            $this->cookie->ignoreInsecureDb = 1;
            return $this->redirect()->toRoute('upgrade-home');
        }
        return $this->createViewModel();
    }

    /**
     * Lead users through the steps required to replace blowfish quickly and easily
     *
     * @return mixed
     */
    public function criticalFixBlowfishAction()
    {
        // Test that blowfish is still working
        $blowfishIsWorking = true;
        try {
            $newcipher = $this->serviceLocator->get(BlockCipher::class)->setAlgorithm('blowfish');
            $newcipher->setKey('akeyforatest');
            $newcipher->encrypt('youfoundtheeasteregg!');
        } catch (Exception $e) {
            $blowfishIsWorking = false;
        }

        // Get new settings
        [$newAlgorithm, $exampleKey] = $this->getSecureAlgorithmAndKey();
        return $this->createViewModel(
            compact('newAlgorithm', 'exampleKey', 'blowfishIsWorking')
        );
    }
}
