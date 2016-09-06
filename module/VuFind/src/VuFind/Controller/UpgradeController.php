<?php
/**
 * Upgrade Controller
 *
 * PHP version 5
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
use ArrayObject, VuFind\Config\Locator as ConfigLocator,
    VuFind\Cookie\Container as CookieContainer,
    VuFind\Exception\RecordMissing as RecordMissingException,
    Zend\Mvc\MvcEvent;

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
    /**
     * Cookie container
     *
     * @var CookieContainer
     */
    protected $cookie;

    /**
     * Session container
     *
     * @var \Zend\Session\Container
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
     * @param \VuFind\Cookie\CookieManager $cookieManager    Cookie manager
     * @param \Zend\Session\Container      $sessionContainer Session container
     */
    public function __construct(\VuFind\Cookie\CookieManager $cookieManager,
        \Zend\Session\Container $sessionContainer
    ) {
        // We want to use cookies for tracking the state of the upgrade, since the
        // session is unreliable -- if the user upgrades a configuration that uses
        // a different session handler than the default one, we'll lose track of our
        // upgrade state in the middle of the process!
        $this->cookie = new CookieContainer('vfup', $cookieManager);

        // ...however, once the configuration piece of the upgrade is done, we can
        // safely use the session for storing some values.  We'll use this for the
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
        if (!isset($config->System->autoConfigure)
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
            MvcEvent::EVENT_DISPATCH, [$this, 'validateAutoConfigureConfig'], 1000
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
     * Figure out which version(s) are being used.
     *
     * @return mixed
     */
    public function establishversionsAction()
    {
        $this->cookie->newVersion = \VuFind\Config\Version::getBuildVersion();
        $this->cookie->oldVersion = \VuFind\Config\Version::getBuildVersion(
            $this->cookie->sourceDir
        );

        // Block upgrade when encountering common errors:
        if (empty($this->cookie->oldVersion)) {
            $this->flashMessenger()
                ->addMessage('Cannot determine source version.', 'error');
            unset($this->cookie->oldVersion);
            return $this->forwardTo('Upgrade', 'Error');
        }
        if (empty($this->cookie->newVersion)) {
            $this->flashMessenger()
                ->addMessage('Cannot determine destination version.', 'error');
            unset($this->cookie->newVersion);
            return $this->forwardTo('Upgrade', 'Error');
        }
        if ($this->cookie->newVersion == $this->cookie->oldVersion) {
            $this->flashMessenger()
                ->addMessage('Cannot upgrade version to itself.', 'error');
            unset($this->cookie->newVersion);
            return $this->forwardTo('Upgrade', 'Error');
        }

        // If we got this far, everything is okay:
        return $this->forwardTo('Upgrade', 'Home');
    }

    /**
     * Upgrade the configuration files.
     *
     * @return mixed
     */
    public function fixconfigAction()
    {
        $localConfig
            = dirname(ConfigLocator::getLocalConfigPath('config.ini', null, true));
        $confDir = $this->cookie->oldVersion < 2
            ? $this->cookie->sourceDir . '/web/conf'
            : $localConfig;
        $upgrader = new \VuFind\Config\Upgrade(
            $this->cookie->oldVersion, $this->cookie->newVersion, $confDir,
            dirname(ConfigLocator::getBaseConfigPath('config.ini')), $localConfig
        );
        try {
            $upgrader->run();
            $this->cookie->warnings = $upgrader->getWarnings();
            $this->cookie->configOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        } catch (\Exception $e) {
            $extra = is_a($e, 'VuFind\Exception\FileAccess')
                ? '  Check file permissions.' : '';
            $this->flashMessenger()->addMessage(
                'Config upgrade failed: ' . $e->getMessage() . $extra, 'error'
            );
            return $this->forwardTo('Upgrade', 'Error');
        }
    }

    /**
     * Get a database adapter for root access using credentials in session.
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getRootDbAdapter()
    {
        // Use static cache to avoid loading adapter more than once on
        // subsequent calls.
        static $adapter = false;
        if (!$adapter) {
            $factory = $this->getServiceLocator()->get('VuFind\DbAdapterFactory');
            $adapter = $factory->getAdapter(
                $this->session->dbRootUser, $this->session->dbRootPass
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
     * @throws \Exception
     * @return void
     */
    protected function setDbEncodingConfiguration($charset)
    {
        $config = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        $writer = new \VuFind\Config\Writer($config);
        $writer->set('Database', 'charset', $charset);
        if (!$writer->save()) {
            throw new \Exception('Problem writing DB encoding to config.ini');
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
        $resource = $this->getTable('resource');
        $resourceWhere = ['source' => 'VuFind'];
        $resourceRows = $resource->select($resourceWhere);
        if (count($resourceRows) > 0) {
            $resource->update(['source' => 'Solr'], $resourceWhere);
            $this->session->warnings->append(
                'Converted ' . count($resourceRows)
                . ' legacy "VuFind" source value(s) in resource table'
            );
        }

        $userStatsFields = $this->getTable('userstatsfields');
        $usfWhere = ['field' => 'recordSource', 'value' => 'VuFind'];
        $usfRows = $userStatsFields->select($usfWhere);
        if (count($usfRows) > 0) {
            $userStatsFields->update(['value' => 'Solr'], $usfWhere);
            $this->session->warnings->append(
                'Converted ' . count($usfRows)
                . ' legacy "VuFind" source value(s) in user_stats_fields table'
            );
        }
    }

    /**
     * Support method for fixdatabaseAction() -- add checksums to search table rows.
     *
     * @return void
     */
    protected function fixSearchChecksumsInDatabase()
    {
        $manager = $this->getServiceLocator()
            ->get('VuFind\SearchResultsPluginManager');
        $search = $this->getTable('search');
        $searchWhere = ['checksum' => null, 'saved' => 1];
        $searchRows = $search->select($searchWhere);
        if (count($searchRows) > 0) {
            foreach ($searchRows as $searchRow) {
                $searchObj = $searchRow->getSearchObject()->deminify($manager);
                $url = $searchObj->getUrlQuery()->getParams();
                $checksum = crc32($url) & 0xFFFFFFF;
                $searchRow->checksum = $checksum;
                $searchRow->save();
            }
            $this->session->warnings->append(
                'Added checksum to ' . count($searchRows) . ' rows in search table'
            );
        }
    }

    /**
     * Attempt to perform a MySQL upgrade; return either a string containing SQL
     * (if we are in "log SQL" mode), an empty string (if we are successful but
     * not logging SQL) or a Zend Framework object representing forward/redirect
     * (if we need to obtain user input).
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     *
     * @return mixed
     */
    protected function upgradeMySQL($adapter)
    {
        $sql = '';

        // Set up the helper with information from our SQL file:
        $this->dbUpgrade()
            ->setAdapter($adapter)
            ->loadSql(APPLICATION_PATH . '/module/VuFind/sql/mysql.sql');

        // Check for missing tables.  Note that we need to finish dealing with
        // missing tables before we proceed to the missing columns check, or else
        // the missing tables will cause fatal errors during the column test.
        $missingTables = $this->dbUpgrade()->getMissingTables();
        if (!empty($missingTables)) {
            // Only manipulate DB if we're not in logging mode:
            if (!$this->logsql) {
                if (!$this->hasDatabaseRootCredentials()) {
                    return $this->forwardTo('Upgrade', 'GetDbCredentials');
                }
                $this->dbUpgrade()->setAdapter($this->getRootDbAdapter());
                $this->session->warnings->append(
                    "Created missing table(s): " . implode(', ', $missingTables)
                );
            }
            $sql .= $this->dbUpgrade()
                ->createMissingTables($missingTables, $this->logsql);
        }

        // Check for missing columns.
        $mT = $this->logsql ? $missingTables : [];
        $missingCols = $this->dbUpgrade()->getMissingColumns($mT);
        if (!empty($missingCols)) {
            // Only manipulate DB if we're not in logging mode:
            if (!$this->logsql) {
                if (!$this->hasDatabaseRootCredentials()) {
                    return $this->forwardTo('Upgrade', 'GetDbCredentials');
                }
                $this->dbUpgrade()->setAdapter($this->getRootDbAdapter());
                $this->session->warnings->append(
                    "Added column(s) to table(s): "
                    . implode(', ', array_keys($missingCols))
                );
            }
            $sql .= $this->dbUpgrade()
                ->createMissingColumns($missingCols, $this->logsql);
        }

        // Check for modified columns.
        $mC = $this->logsql ? $missingCols : [];
        $modifiedCols = $this->dbUpgrade()->getModifiedColumns($mT, $mC);
        if (!empty($modifiedCols)) {
            // Only manipulate DB if we're not in logging mode:
            if (!$this->logsql) {
                if (!$this->hasDatabaseRootCredentials()) {
                    return $this->forwardTo('Upgrade', 'GetDbCredentials');
                }
                $this->dbUpgrade()->setAdapter($this->getRootDbAdapter());
                $this->session->warnings->append(
                    "Modified column(s) in table(s): "
                    . implode(', ', array_keys($modifiedCols))
                );
            }
            $sql .= $this->dbUpgrade()
                ->updateModifiedColumns($modifiedCols, $this->logsql);
        }

        // Check for encoding problems.
        $encProblems = $this->dbUpgrade()->getEncodingProblems();
        if (!empty($encProblems)) {
            if (!isset($this->session->dbChangeEncoding)) {
                return $this->forwardTo('Upgrade', 'GetDbEncodingPreference');
            }

            if ($this->session->dbChangeEncoding) {
                // Only manipulate DB if we're not in logging mode:
                if (!$this->logsql) {
                    if (!$this->hasDatabaseRootCredentials()) {
                        return $this->forwardTo('Upgrade', 'GetDbCredentials');
                    }
                    $this->dbUpgrade()->setAdapter($this->getRootDbAdapter());
                    $this->session->warnings->append(
                        "Modified encoding settings in table(s): "
                        . implode(', ', array_keys($encProblems))
                    );
                }
                $sql .= $this->dbUpgrade()
                    ->fixEncodingProblems($encProblems, $this->logsql);
                $this->setDbEncodingConfiguration('utf8');
            } else {
                // User has requested that we skip encoding conversion:
                $this->setDbEncodingConfiguration('latin1');
            }
        }

        // Check for collation problems.
        $colProblems = $this->dbUpgrade()->getCollationProblems();
        if (!empty($colProblems)) {
            if (!$this->logsql) {
                if (!$this->hasDatabaseRootCredentials()) {
                    return $this->forwardTo('Upgrade', 'GetDbCredentials');
                }
                $this->dbUpgrade()->setAdapter($this->getRootDbAdapter());
            }
            $sql .= $this->dbUpgrade()
                ->fixCollationProblems($colProblems, $this->logsql);
            $this->session->warnings->append(
                "Modified collation(s) in table(s): "
                . implode(', ', array_keys($colProblems))
            );
        }

        // Don't keep DB credentials in session longer than necessary:
        unset($this->session->dbRootUser);
        unset($this->session->dbRootPass);

        return $sql;
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
                // If this is a MySQL connection, we can do an automatic upgrade;
                // if VuFind is using a different database, we have to prompt the
                // user to check the migrations directory and upgrade manually.
                $adapter = $this->getServiceLocator()->get('VuFind\DbAdapter');
                $platform = $adapter->getDriver()->getDatabasePlatformName();
                if (strtolower($platform) == 'mysql') {
                    $upgradeResult = $this->upgradeMySQL($adapter);
                    if (!is_string($upgradeResult)) {
                        return $upgradeResult;
                    }
                    $this->session->sql = $upgradeResult;
                } else {
                    $this->session->sql = '';
                    $this->session->warnings->append(
                        'Automatic database upgrade not supported for ' . $platform
                        . '. Check for manual migration scripts in the '
                        . '$VUFIND_HOME/module/VuFind/sql/migrations directory.'
                    );
                }
            }

            // Now that database structure is addressed, we can fix database
            // content -- the checks below should be platform-independent.

            // Check for legacy tag bugs:
            $resourceTagsTable = $this->getTable('ResourceTags');
            $anonymousTags = $resourceTagsTable->getAnonymousCount();
            if ($anonymousTags > 0 && !isset($this->cookie->skipAnonymousTags)) {
                $this->getRequest()->getQuery()->set('anonymousCnt', $anonymousTags);
                return $this->redirect()->toRoute('upgrade-fixanonymoustags');
            }
            $dupeTags = $this->getTable('Tags')->getDuplicates();
            if (count($dupeTags) > 0 && !isset($this->cookie->skipDupeTags)) {
                return $this->redirect()->toRoute('upgrade-fixduplicatetags');
            }

            // Clean up the "VuFind" source, if necessary.
            $this->fixVuFindSourceInDatabase();
        } catch (\Exception $e) {
            $this->flashMessenger()->addMessage(
                'Database upgrade failed: ' . $e->getMessage(), 'error'
            );
            return $this->forwardTo('Upgrade', 'Error');
        }

        // Add checksums to all saved searches but catch exceptions (e.g. in case
        // column checksum does not exist yet because of sqllog).
        try {
            $this->fixSearchChecksumsInDatabase();
        } catch (\Exception $e) {
            $this->session->warnings->append(
                'Could not fix checksums in table search - maybe column ' .
                'checksum is missing? Exception thrown with ' .
                'message: ' . $e->getMessage()
            );
        }

        $this->cookie->databaseOkay = true;
        if (!empty($this->session->sql)) {
            return $this->forwardTo('Upgrade', 'ShowSql');
        }
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
        if ($continue == 'Next') {
            unset($this->session->sql);
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
            if ($this->formWasSubmitted('submit')) {
                $pass = $this->params()->fromPost('dbrootpass');

                // Test the connection:
                try {
                    // Query a table known to exist
                    $factory = $this->getServiceLocator()
                        ->get('VuFind\DbAdapterFactory');
                    $db = $factory->getAdapter($dbrootuser, $pass);
                    $db->query("SELECT * FROM user;");
                    $this->session->dbRootUser = $dbrootuser;
                    $this->session->dbRootPass = $pass;
                    return $this->forwardTo('Upgrade', 'FixDatabase');
                } catch (\Exception $e) {
                    $this->flashMessenger()->addMessage(
                        'Could not connect; please try again.', 'error'
                    );
                }
            }
        }

        return $this->createViewModel(['dbrootuser' => $dbrootuser]);
    }

    /**
     * Prompt the user for action on encoding problems.
     *
     * @return mixed
     */
    public function getdbencodingpreferenceAction()
    {
        $action = $this->params()->fromPost('encodingaction', '');
        if ($action == 'Change') {
            $this->session->dbChangeEncoding = true;
            return $this->forwardTo('Upgrade', 'FixDatabase');
        } else if ($action == 'Keep') {
            $this->session->dbChangeEncoding = false;
            return $this->forwardTo('Upgrade', 'FixDatabase');
        }
        return $this->createViewModel();
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
        if ($this->formWasSubmitted('submit')) {
            $user = $this->params()->fromPost('username');
            if (empty($user)) {
                $this->flashMessenger()
                    ->addMessage('Username must not be empty.', 'error');
            } else {
                $userTable = $this->getTable('User');
                $user = $userTable->getByUsername($user, false);
                if (empty($user) || !is_object($user) || !isset($user->id)) {
                    $this->flashMessenger()
                        ->addMessage("User {$user} not found.", 'error');
                } else {
                    $table = $this->getTable('ResourceTags');
                    $table->assignAnonymousTags($user->id);
                    $this->session->warnings->append(
                        "Assigned all anonymous tags to {$user->username}."
                    );
                    return $this->forwardTo('Upgrade', 'FixDatabase');
                }
            }
        }

        return $this->createViewModel(
            [
                'anonymousTags' => $this->params()->fromQuery('anonymousCnt')
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
        if ($this->formWasSubmitted('submit')) {
            $this->getTable('Tags')->fixDuplicateTags();
            return $this->forwardTo('Upgrade', 'FixDatabase');
        }

        return $this->createViewModel();
    }

    /**
     * Fix missing metadata in the resource table.
     *
     * @return mixed
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
        $table = $this->getTable('Resource');
        $problems = $table->findMissingMetadata();

        // No problems?  We're done here!
        if (count($problems) == 0) {
            $this->cookie->metadataOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        }

        // Process submit button:
        if ($this->formWasSubmitted('submit')) {
            $converter = $this->getServiceLocator()->get('VuFind\DateConverter');
            foreach ($problems as $problem) {
                try {
                    $driver = $this->getRecordLoader()
                        ->load($problem->record_id, $problem->source);
                    $problem->assignMetadata($driver, $converter)->save();
                } catch (RecordMissingException $e) {
                    $this->session->warnings->append(
                        "Unable to load metadata for record "
                        . "{$problem->source}:{$problem->record_id}"
                    );
                }
            }
            $this->cookie->metadataOkay = true;
            return $this->forwardTo('Upgrade', 'Home');
        }
    }

    /**
     * Prompt the user for a source directory (to upgrade from 1.x).
     *
     * @return mixed
     */
    public function getsourcedirAction()
    {
        // Process form submission:
        $dir = $this->params()->fromPost('sourcedir');
        if (!empty($dir)) {
            if (!is_dir($dir)) {
                $this->flashMessenger()
                    ->addMessage($dir . ' does not exist.', 'error');
            } else if (!file_exists($dir . '/build.xml')) {
                $this->flashMessenger()->addMessage(
                    'Could not find build.xml in source directory;'
                    . ' upgrade does not support VuFind versions prior to 1.1.',
                    'error'
                );
            } else {
                $this->cookie->sourceDir = rtrim($dir, '\/');
                // Clear out request to avoid infinite loop:
                $this->getRequest()->getPost()->set('sourcedir', '');
                return $this->forwardTo('Upgrade', 'Home');
            }
        }

        return $this->createViewModel();
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
     */
    public function getsourceversionAction()
    {
        // Process form submission:
        $version = $this->params()->fromPost('sourceversion');
        if (!empty($version)) {
            $this->cookie->newVersion = \VuFind\Config\Version::getBuildVersion();
            if (floor($version) < 2) {
                $this->flashMessenger()
                    ->addMessage('Illegal version number.', 'error');
            } else if ($version >= $this->cookie->newVersion) {
                $this->flashMessenger()->addMessage(
                    "Source version must be less than {$this->cookie->newVersion}.",
                    'error'
                );
            } else {
                $this->cookie->oldVersion = $version;
                $this->cookie->sourceDir = realpath(APPLICATION_PATH);
                // Clear out request to avoid infinite loop:
                $this->getRequest()->getPost()->set('sourceversion', '');
                $this->processSkipParam();
                return $this->forwardTo('Upgrade', 'Home');
            }
        }

        // If we got this far, we need to send the user back to the form:
        return $this->forwardTo('Upgrade', 'GetSourceDir');
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
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager');
        if ($cache->hasDirectoryCreationError()) {
            return $this->redirect()->toRoute('install-fixcache');
        }

        // First find out which version we are upgrading:
        if (!isset($this->cookie->sourceDir)
            || !is_dir($this->cookie->sourceDir)
        ) {
            return $this->forwardTo('Upgrade', 'GetSourceDir');
        }

        // Next figure out which version(s) are involved:
        if (!isset($this->cookie->oldVersion)
            || !isset($this->cookie->newVersion)
        ) {
            return $this->forwardTo('Upgrade', 'EstablishVersions');
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
            isset($this->cookie->warnings) ? $this->cookie->warnings : [],
            (array)$this->session->warnings
        );
        foreach ($allWarnings as $warning) {
            $this->flashMessenger()->addMessage($warning, 'info');
        }

        return $this->createViewModel(
            [
                'configDir' => dirname(
                    ConfigLocator::getLocalConfigPath('config.ini', null, true)
                ),
                'importDir' => LOCAL_OVERRIDE_DIR . '/import',
                'oldVersion' => $this->cookie->oldVersion
            ]
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
}
