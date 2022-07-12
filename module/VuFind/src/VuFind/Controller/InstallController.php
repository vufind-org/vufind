<?php
/**
 * Install Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

use Laminas\Crypt\Password\Bcrypt;
use Laminas\Mvc\MvcEvent;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\Writer as ConfigWriter;

/**
 * Class controls VuFind auto-configuration.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InstallController extends AbstractBase
{
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
        return $this->createViewModel();
    }

    /**
     * Copy the basic configuration file into position and report success or
     * failure.
     *
     * @return bool
     */
    protected function installBasicConfig()
    {
        $config = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        if (!file_exists($config)) {
            return copy(ConfigLocator::getBaseConfigPath('config.ini'), $config);
        }
        return true;        // report success if file already exists
    }

    /**
     * Check if basic configuration is taken care of.
     *
     * @return array
     */
    protected function checkBasicConfig()
    {
        // Initialize status based on existence of config file...
        $status = $this->installBasicConfig();

        // See if the URL setting remains at the default (unless we already
        // know we've failed):
        if ($status) {
            $config = $this->getConfig();
            if (stristr($config->Site->url, 'myuniversity.edu')) {
                $status = false;
            }
        }

        return [
            'title' => 'Basic Configuration', 'status' => $status,
            'fix' => 'fixbasicconfig'
        ];
    }

    /**
     * Display repair instructions for basic configuration problems.
     *
     * @return mixed
     */
    public function fixbasicconfigAction()
    {
        $view = $this->createViewModel();
        $config = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        try {
            if (!$this->installBasicConfig()) {
                throw new \Exception('Cannot copy file into position.');
            }
            $writer = new ConfigWriter($config);
            $serverUrl = $this->getViewRenderer()->plugin('serverurl');
            $path = $this->url()->fromRoute('home');
            $writer->set('Site', 'url', rtrim($serverUrl($path), '/'));
            if (!$writer->save()) {
                throw new \Exception('Cannot write config to disk.');
            }
        } catch (\Exception $e) {
            $view->configDir = dirname($config);
            if (function_exists('posix_getpwuid')
                && function_exists('posix_geteuid')
            ) {
                $processUser = posix_getpwuid(posix_geteuid());
                $view->runningUser = $processUser['name'];
            }
        }
        return $view;
    }

    /**
     * Check if the cache directory is writable.
     *
     * @return array
     */
    protected function checkCache()
    {
        $cache = $this->serviceLocator->get(\VuFind\Cache\Manager::class);
        return [
            'title' => 'Cache',
            'status' => !$cache->hasDirectoryCreationError(),
            'fix' => 'fixcache'
        ];
    }

    /**
     * Display repair instructions for cache problems.
     *
     * @return mixed
     */
    public function fixcacheAction()
    {
        $cache = $this->serviceLocator->get(\VuFind\Cache\Manager::class);
        $view = $this->createViewModel();
        $view->cacheDir = $cache->getCacheDir();
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            $view->runningUser = $processUser['name'];
        }
        return $view;
    }

    /**
     * Check if the database is accessible.
     *
     * @return array
     */
    protected function checkDatabase()
    {
        try {
            // Try to read the tags table just to see if we can connect to the DB:
            $tags = $this->getTable('Tags');
            $tags->getByText('test', false);
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return [
            'title' => 'Database', 'status' => $status, 'fix' => 'fixdatabase'
        ];
    }

    /**
     * Support method for check/fix dependencies code -- do we have a new enough
     * version of PHP?
     *
     * @return bool
     */
    protected function phpVersionIsNewEnough()
    {
        // PHP_VERSION_ID was introduced in 5.2.7; if it's missing, we have a
        // problem.
        if (!defined('PHP_VERSION_ID')) {
            return false;
        }

        // We need at least PHP version as defined in MINIMAL_PHP_VERSION:
        return PHP_VERSION_ID >= $this->getMinimalPhpVersionId();
    }

    /**
     * Check for missing dependencies.
     *
     * @return array
     */
    protected function checkDependencies()
    {
        $requiredFunctionsExist
            = function_exists('mb_substr') && is_callable('imagecreatefromstring')
              && function_exists('openssl_encrypt')
              && class_exists('XSLTProcessor');

        return [
            'title' => 'Dependencies',
            'status' => $requiredFunctionsExist && $this->phpVersionIsNewEnough(),
            'fix' => 'fixdependencies'
        ];
    }

    /**
     * Show how to fix dependency problems.
     *
     * @return mixed
     */
    public function fixdependenciesAction()
    {
        $problems = 0;

        // Is our version new enough?
        if (!$this->phpVersionIsNewEnough()) {
            $msg = "VuFind requires PHP version " . $this->getMinimalPhpVersion()
                . " or newer; you are running " . phpversion()
                . ".  Please upgrade.";
            $this->flashMessenger()->addMessage($msg, 'error');
            $problems++;
        }

        // Is the mbstring library missing?
        if (!function_exists('mb_substr')) {
            $msg
                = "Your PHP installation appears to be missing the mbstring plug-in."
                . " For better language support, it is recommended that you add"
                . " this. For details on how to do this, see "
                . "https://vufind.org/wiki/installation "
                . "and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->addMessage($msg, 'error');
            $problems++;
        }

        // Is the GD library missing?
        if (!is_callable('imagecreatefromstring')) {
            $msg
                = "Your PHP installation appears to be missing the GD plug-in. "
                . "For better graphics support, it is recommended that you add this."
                . " For details on how to do this, see "
                . "https://vufind.org/wiki/installation "
                . "and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->addMessage($msg, 'error');
            $problems++;
        }

        // Is the openssl library missing?
        if (!function_exists('openssl_encrypt')) {
            $msg
                = "Your PHP installation appears to be missing the openssl plug-in."
                . " For better security support, it is recommended that you add"
                . " this. For details on how to do this, see "
                . "https://vufind.org/wiki/installation "
                . "and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->addMessage($msg, 'error');
            $problems++;
        }

        // Is the XSL library missing?
        if (!class_exists('XSLTProcessor')) {
            $msg
                = "Your PHP installation appears to be missing the XSL plug-in."
                . " For details on how to do this, see "
                . "https://vufind.org/wiki/installation "
                . "and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->addMessage($msg, 'error');
            $problems++;
        }

        return $this->createViewModel(['problems' => $problems]);
    }

    /**
     * Display repair instructions for database problems.
     *
     * @return mixed
     */
    public function fixdatabaseAction()
    {
        $view = $this->createViewModel();
        $view->dbname = $this->params()->fromPost('dbname', 'vufind');
        $view->dbuser = $this->params()->fromPost('dbuser', 'vufind');
        $view->dbhost = $this->params()->fromPost('dbhost', 'localhost');
        $view->vufindhost = $this->params()->fromPost('vufindhost', 'localhost');
        $view->dbrootuser = $this->params()->fromPost('dbrootuser', 'root');
        $view->driver = $this->params()->fromPost('driver', 'mysql');

        $skip = $this->params()->fromPost('printsql', 'nope') == 'Skip';

        if (!preg_match('/^\w*$/', $view->dbname)) {
            $this->flashMessenger()
                ->addMessage('Database name must be alphanumeric.', 'error');
        } elseif (!preg_match('/^\w*$/', $view->dbuser)) {
            $this->flashMessenger()
                ->addMessage('Database user must be alphanumeric.', 'error');
        } elseif ($skip || $this->formWasSubmitted('submit')) {
            $newpass = $this->params()->fromPost('dbpass');
            $newpassConf = $this->params()->fromPost('dbpassconfirm');
            if ((empty($newpass) || empty($newpassConf))) {
                $this->flashMessenger()
                    ->addMessage('Password fields must not be blank.', 'error');
            } elseif ($newpass != $newpassConf) {
                $this->flashMessenger()
                    ->addMessage('Password fields must match.', 'error');
            } else {
                // Connect to database:
                $connection = $view->driver . '://' . $view->dbrootuser . ':'
                    . $this->params()->fromPost('dbrootpass') . '@'
                    . $view->dbhost;
                try {
                    $dbName = ($view->driver == 'pgsql')
                        ? 'template1' : $view->driver;
                    $db = $this->serviceLocator
                        ->get(\VuFind\Db\AdapterFactory::class)
                        ->getAdapterFromConnectionString("{$connection}/{$dbName}");
                } catch (\Exception $e) {
                    $this->flashMessenger()
                        ->addMessage(
                            'Problem initializing database adapter; '
                            . 'check for missing ' . $view->driver
                            . ' library .  Details: ' . $e->getMessage(),
                            'error'
                        );
                    return $view;
                }
                try {
                    // Get SQL together
                    $escapedPass = $skip
                        ? "'" . addslashes($newpass) . "'"
                        : $db->getPlatform()->quoteValue($newpass);
                    $preCommands = $this->getPreCommands($view, $escapedPass);
                    $postCommands = $this->getPostCommands($view);
                    $sql = file_get_contents(
                        APPLICATION_PATH . "/module/VuFind/sql/{$view->driver}.sql"
                    );
                    if ($skip) {
                        $omnisql = '';
                        foreach ($preCommands as $query) {
                            $omnisql .= $query . ";\n";
                        }
                        $omnisql .= "\n" . $sql . "\n";
                        foreach ($postCommands as $query) {
                            $omnisql .= $query . ";\n";
                        }
                        $this->getRequest()->getQuery()->set('sql', $omnisql);
                        return $this->forwardTo('Install', 'showsql');
                    } else {
                        foreach ($preCommands as $query) {
                            $db->query($query, $db::QUERY_MODE_EXECUTE);
                        }
                        $dbFactory = $this->serviceLocator
                            ->get(\VuFind\Db\AdapterFactory::class);
                        $db = $dbFactory->getAdapterFromConnectionString(
                            $connection . '/' . $view->dbname
                        );
                        $statements = explode(';', $sql);
                        foreach ($statements as $current) {
                            // Skip empty sections:
                            if (strlen(trim($current)) == 0) {
                                continue;
                            }
                            $db->query($current, $db::QUERY_MODE_EXECUTE);
                        }
                        foreach ($postCommands as $query) {
                            $db->query($query, $db::QUERY_MODE_EXECUTE);
                        }
                        // If we made it this far, we can update the config file and
                        // forward back to the home action!
                        $string = "{$view->driver}://{$view->dbuser}:{$newpass}@"
                            . $view->dbhost . '/' . $view->dbname;
                        $config = ConfigLocator::getLocalConfigPath(
                            'config.ini',
                            null,
                            true
                        );
                        $writer = new ConfigWriter($config);
                        $writer->set('Database', 'database', $string);
                        if (!$writer->save()) {
                            return $this->forwardTo('Install', 'fixbasicconfig');
                        }
                    }
                    return $this->redirect()->toRoute('install-home');
                } catch (\Exception $e) {
                    $this->flashMessenger()->addMessage($e->getMessage(), 'error');
                }
            }
        }
        return $view;
    }

    /**
     * Get SQL commands needed to set up a particular database before
     * loading the main SQL file of table definitions.
     *
     * @param \Laminas\View\Model $view        View object containing DB settings.
     * @param string              $escapedPass Password to set for new DB (escaped
     * appropriately for target database).
     *
     * @return array
     */
    protected function getPreCommands($view, $escapedPass)
    {
        $create = 'CREATE DATABASE ' . $view->dbname;
        // Special case: PostgreSQL:
        if ($view->driver == 'pgsql') {
            $escape = "ALTER DATABASE " . $view->dbname
                . " SET bytea_output='escape'";
            $cuser = "CREATE USER " . $view->dbuser
                . " WITH PASSWORD {$escapedPass}";
            $grant = "GRANT ALL PRIVILEGES ON DATABASE "
                . "{$view->dbname} TO {$view->dbuser} ";
            return [$create, $escape, $cuser, $grant];
        }
        // Default: MySQL:
        $user = "CREATE USER '{$view->dbuser}'@'{$view->vufindhost}' "
            . "IDENTIFIED BY {$escapedPass}";
        $grant = "GRANT SELECT,INSERT,UPDATE,DELETE ON "
            . $view->dbname
            . ".* TO '{$view->dbuser}'@'{$view->vufindhost}' "
            . "WITH GRANT OPTION";
        $use = "USE {$view->dbname}";
        return [$create, $user, $grant, 'FLUSH PRIVILEGES', $use];
    }

    /**
     * Get SQL commands needed to set up a particular database after
     * loading the main SQL file of table definitions.
     *
     * @param \Laminas\View\Model $view View object containing DB settings.
     *
     * @return array
     */
    protected function getPostCommands($view)
    {
        // Special case: PostgreSQL:
        if ($view->driver == 'pgsql') {
            $grantTables = "GRANT ALL PRIVILEGES ON ALL TABLES IN "
                . "SCHEMA public TO {$view->dbuser} ";
            $grantSequences = "GRANT ALL PRIVILEGES ON ALL SEQUENCES"
                . " IN SCHEMA public TO {$view->dbuser} ";
            return [$grantTables, $grantSequences];
        }
        // Default: MySQL:
        return [];
    }

    /**
     * Display captured SQL commands for database action.
     *
     * @return mixed
     */
    protected function showsqlAction()
    {
        $continue = $this->params()->fromPost('continue', 'nope');
        if ($continue == 'Next') {
            return $this->redirect()->toRoute('install-home');
        }

        return $this->createViewModel(
            ['sql' => $this->params()->fromQuery('sql')]
        );
    }

    /**
     * Check if ILS configuration is appropriate.
     *
     * @return array
     */
    protected function checkILS()
    {
        $config = $this->getConfig();
        if (in_array($config->Catalog->driver, ['Sample', 'Demo'])) {
            $status = false;
        } else {
            $status = 'ils-offline' !== $this->getILS()->getOfflineMode(true);
        }
        return ['title' => 'ILS', 'status' => $status, 'fix' => 'fixils'];
    }

    /**
     * Display repair instructions for ILS problems.
     *
     * @return mixed
     */
    public function fixilsAction()
    {
        // Process incoming parameter -- user may have selected a new driver:
        $newDriver = $this->params()->fromPost('driver');
        if (!empty($newDriver)) {
            $configPath
                = ConfigLocator::getLocalConfigPath('config.ini', null, true);
            $writer = new ConfigWriter($configPath);
            $writer->set('Catalog', 'driver', $newDriver);
            if (!$writer->save()) {
                return $this->forwardTo('Install', 'fixbasicconfig');
            }
            // Copy configuration, if applicable:
            $ilsIni = ConfigLocator::getBaseConfigPath($newDriver . '.ini');
            $localIlsIni
                = ConfigLocator::getLocalConfigPath("{$newDriver}.ini", null, true);
            if (file_exists($ilsIni) && !file_exists($localIlsIni)) {
                if (!copy($ilsIni, $localIlsIni)) {
                    return $this->forwardTo('Install', 'fixbasicconfig');
                }
            }
            return $this->redirect()->toRoute('install-home');
        }

        // If we got this far, check whether we have an error with a real driver
        // or if we need to warn the user that they have selected a fake driver:
        $config = $this->getConfig();
        $view = $this->createViewModel();
        if (in_array($config->Catalog->driver, ['Sample', 'Demo'])) {
            $view->demo = true;
            // Get a list of available drivers:
            $dir
                = opendir(APPLICATION_PATH . '/module/VuFind/src/VuFind/ILS/Driver');
            $drivers = [];
            $excludeList = [
                'Sample.php', 'Demo.php', 'DriverInterface.php', 'PluginManager.php',
            ];
            while ($line = readdir($dir)) {
                if (stristr($line, '.php') && !in_array($line, $excludeList)
                    && substr($line, 0, 8) !== 'Abstract'
                    && substr($line, -11) !== 'Factory.php'
                    && substr($line, -9) !== 'Trait.php'
                ) {
                    $drivers[] = str_replace('.php', '', $line);
                }
            }
            closedir($dir);
            sort($drivers);
            $view->drivers = $drivers;
        } else {
            $view->configPath = ConfigLocator::getLocalConfigPath(
                "{$config->Catalog->driver}.ini",
                null,
                true
            );
        }
        return $view;
    }

    /**
     * Support method to test the search service
     *
     * @return void
     * @throws \Exception
     */
    protected function testSearchService()
    {
        // Try to retrieve an arbitrary ID -- this will fail if Solr is down:
        $searchService = $this->serviceLocator->get(\VuFindSearch\Service::class);
        $searchService->retrieve('Solr', '1');
    }

    /**
     * Check if the Solr index is working.
     *
     * @return array
     */
    protected function checkSolr()
    {
        try {
            $this->testSearchService();
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return ['title' => 'Solr', 'status' => $status, 'fix' => 'fixsolr'];
    }

    /**
     * Display repair instructions for Solr problems.
     *
     * @return mixed
     */
    public function fixsolrAction()
    {
        // In Windows, localhost may fail -- see if switching to 127.0.0.1 helps:
        $config = $this->getConfig();
        $configFile = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        if (stristr($config->Index->url, 'localhost')) {
            $newUrl = str_replace('localhost', '127.0.0.1', $config->Index->url);
            try {
                $this->testSearchService();

                // If we got this far, the fix worked.  Let's write it to disk!
                $writer = new ConfigWriter($configFile);
                $writer->set('Index', 'url', $newUrl);
                if (!$writer->save()) {
                    return $this->forwardTo('Install', 'fixbasicconfig');
                }
                return $this->redirect()->toRoute('install-home');
            } catch (\Exception $e) {
                // Didn't work!
            }
        }

        // If we got this far, the automatic fix didn't work, so let's just assign
        // some variables to use in offering troubleshooting advice:
        $view = $this->createViewModel();
        $view->rawUrl = $config->Index->url;
        $view->userUrl = str_replace(
            ['localhost', '127.0.0.1'],
            $this->getRequest()->getServer()->get('HTTP_HOST'),
            $config->Index->url
        );
        $view->core = $config->Index->default_core ?? "biblio";
        $view->configFile = $configFile;
        return $view;
    }

    /**
     * Check if Security configuration is set.
     *
     * @return array
     */
    protected function checkSecurity()
    {
        // Are configuration settings missing?
        $config = $this->getConfig();
        if (!isset($config->Authentication->hash_passwords)
            || !$config->Authentication->hash_passwords
            || !isset($config->Authentication->encrypt_ils_password)
            || !$config->Authentication->encrypt_ils_password
        ) {
            $status = false;
        } else {
            $status = true;
        }

        // If we're correctly configured, check that the data in the database is ok:
        if ($status) {
            try {
                $rows = $this->getTable('user')->getInsecureRows();
                $status = (count($rows) == 0);
            } catch (\Exception $e) {
                // Any exception means we have a problem!
                $status = false;
            }
        }

        return [
            'title' => 'Security', 'status' => $status, 'fix' => 'fixsecurity'
        ];
    }

    /**
     * Support method for fixsecurityAction().  Returns true if the configuration
     * was modified, false otherwise.
     *
     * @param \Laminas\Config\Config $config Existing VuFind configuration
     * @param ConfigWriter           $writer Config writer
     *
     * @return bool
     */
    protected function fixSecurityConfiguration($config, $writer)
    {
        $changed = false;

        if (!isset($config->Authentication->hash_passwords)
            || !$config->Authentication->hash_passwords
            || !isset($config->Authentication->encrypt_ils_password)
            || !$config->Authentication->encrypt_ils_password
        ) {
            $writer->set('Authentication', 'hash_passwords', true);
            $writer->set('Authentication', 'encrypt_ils_password', true);
            $changed = true;
        }
        // Only rewrite encryption key if we don't already have one:
        if (!isset($config->Authentication->ils_encryption_key)
            || empty($config->Authentication->ils_encryption_key)
        ) {
            $enc_key = sha1(microtime(true) . mt_rand(10000, 90000));
            $writer->set('Authentication', 'ils_encryption_key', $enc_key);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Display repair instructions for Security problems.
     *
     * @return mixed
     */
    public function fixsecurityAction()
    {
        // If the user doesn't want to proceed, abort now:
        $userConfirmation = $this->params()->fromPost('fix-user-table', 'Unset');
        if ($userConfirmation == 'No') {
            $msg = 'Security upgrade aborted.';
            $this->flashMessenger()->addMessage($msg, 'error');
            return $this->redirect()->toRoute('install-home');
        }

        // If we don't need to prompt the user, or if they confirmed, do the fix:
        $rows = $this->getTable('user')->getInsecureRows();
        if (count($rows) == 0 || $userConfirmation == 'Yes') {
            return $this->forwardTo('Install', 'performsecurityfix');
        }

        // If we got this far, we need to ask permission to proceed:
        $view = $this->createViewModel();
        $view->confirmUserFix = true;
        return $view;
    }

    /**
     * Perform fix for Security problems.
     *
     * @return mixed
     */
    public function performsecurityfixAction()
    {
        // This can take a while -- don't time out!
        set_time_limit(0);

        // First, set encryption/hashing to true, and set the key
        $config = $this->getConfig();
        $configPath = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        $writer = new ConfigWriter($configPath);
        if ($this->fixSecurityConfiguration($config, $writer)) {
            // Problem writing? Show the user an error:
            if (!$writer->save()) {
                return $this->forwardTo('Install', 'fixbasicconfig');
            }

            // Success? Redirect to this action in order to reload the configuration:
            return $this->redirect()->toRoute('install-performsecurityfix');
        }

        // Now we want to loop through the database and update passwords (if
        // necessary).
        $rows = $this->getTable('user')->getInsecureRows();
        if (count($rows) > 0) {
            // If we got this far, the user POSTed their confirmation -- go ahead
            // with the fix:
            $bcrypt = new Bcrypt();
            foreach ($rows as $row) {
                if ($row->password != '') {
                    $row->pass_hash = $bcrypt->create($row->password);
                    $row->password = '';
                }
                if ($row->cat_password) {
                    $row->saveCredentials($row->cat_username, $row->cat_password);
                } else {
                    $row->save();
                }
            }
            $msg = count($rows) . ' user row(s) encrypted.';
            $this->flashMessenger()->addMessage($msg, 'info');
        }
        return $this->redirect()->toRoute('install-home');
    }

    /**
     * Check if SSL configuration is set properly.
     *
     * @return array
     */
    public function checkSslCerts()
    {
        // Try to retrieve an SSL URL; if we're misconfigured, it will fail.
        try {
            $this->serviceLocator->get(\VuFindHttp\HttpService::class)
                ->get('https://google.com');
            $status = true;
        } catch (\VuFindHttp\Exception\RuntimeException $e) {
            // Any exception means we have a problem!
            $status = false;
        }

        return [
            'title' => 'SSL', 'status' => $status, 'fix' => 'fixsslcerts'
        ];
    }

    /**
     * Display repair instructions for SSL certificate problems.
     *
     * @return mixed
     */
    public function fixsslcertsAction()
    {
        // Bail out if we've fixed the problem:
        $result = $this->checkSslCerts();
        if ($result['status'] == true) {
            $this->flashMessenger()->addMessage('SSL configuration fixed.', 'info');
            return $this->redirect()->toRoute('install-home');
        }

        // Find out which test to try next:
        $try = $this->params()->fromQuery('try', 0);

        // Configurations to test:
        $configsToTest = [
            ['sslcapath' => '/etc/ssl/certs'],
            ['sslcafile' => '/etc/pki/tls/cert.pem'],
            [], // reset configuration as last attempt
        ];
        if (isset($configsToTest[$try])) {
            return $this->testSslCertConfig($configsToTest[$try], $try);
        }

        // If we got this far, we can't fix this automatically and must display
        // a message.
        $view = $this->createViewModel();
        return $view;
    }

    /**
     * Try switching to a specific SSL configuration.
     *
     * @param array $config Setting(s) to add to [Http] section of config.ini.
     * @param int   $try    Which config index are we trying right now?
     *
     * @return \Laminas\Http\Response
     */
    protected function testSslCertConfig($config, $try)
    {
        $file = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        $writer = new ConfigWriter($file);
        // Reset old settings
        $writer->clear('Http', 'sslcapath');
        $writer->clear('Http', 'sslcafile');
        // Load new settings
        foreach ($config as $setting => $value) {
            $writer->set('Http', $setting, $value);
        }
        if (!$writer->save()) {
            throw new \Exception('Cannot write config to disk.');
        }

        // Jump back to fix action so we can check if it worked (and attempt
        // the next config by incrementing the $try variable, if necessary):
        return $this->redirect()->toRoute(
            'install-fixsslcerts',
            [],
            ['query' => ['try' => $try + 1]]
        );
    }

    /**
     * Disable auto-configuration.
     *
     * @return mixed
     */
    public function doneAction()
    {
        $config = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        $writer = new ConfigWriter($config);
        $writer->set('System', 'autoConfigure', 0);
        if (!$writer->save()) {
            return $this->forwardTo('Install', 'fixbasicconfig');
        }
        return $this->createViewModel(['configDir' => dirname($config)]);
    }

    /**
     * Display summary of installation status
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Perform all checks (based on naming convention):
        $methods = get_class_methods($this);
        $checks = [];
        foreach ($methods as $method) {
            if (substr($method, 0, 5) == 'check') {
                $checks[] = $this->$method();
            }
        }
        return $this->createViewModel(['checks' => $checks]);
    }

    /**
     * Get minimal PHP version required for VuFind to run.
     *
     * @return string
     */
    protected function getMinimalPhpVersion(): string
    {
        $composer = $this->getComposerJson();
        if (empty($composer)) {
            throw new \Exception('Cannot find composer.json');
        }
        $rawVersion = $composer['require']['php']
            ?? $composer['config']['platform']['php']
            ?? '';;
        $version = preg_replace('/[^0-9. ]/', '', $rawVersion);
        $versionParts = preg_split('/[. ]/', $version);
        if (count($versionParts) >= 3) {
            return sprintf('%d.%d.%d', ...$versionParts);
        }
        throw new \Exception('Cannot parse PHP version from composer.json');
    }

    /**
     * Get minimal PHP version ID required for VuFind to run.
     *
     * @return int
     */
    protected function getMinimalPhpVersionId(): int
    {
        $version = explode('.', $this->getMinimalPhpVersion());
        return $version[0] * 10000 + $version[1] * 100 + $version[2];
    }

    /**
     * Get composer.json data as array
     *
     * @return array
     */
    protected function getComposerJson(): array
    {
        try {
            $composerJsonFileName = APPLICATION_PATH . '/composer.json';
            if (file_exists($composerJsonFileName)) {
                return json_decode(file_get_contents($composerJsonFileName), true);
            }
        } catch (\Throwable $exception) {
            return [];
        }
        return [];
    }
}
