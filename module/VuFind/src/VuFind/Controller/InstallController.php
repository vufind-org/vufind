<?php
/**
 * Install Controller
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Config\Writer as ConfigWriter,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Db\AdapterFactory,
    Zend\Mvc\MvcEvent,
    Zend\Crypt\Password\Bcrypt;

/**
 * Class controls VuFind auto-configuration.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class InstallController extends AbstractBase
{
    /**
     * preDispatch -- block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function preDispatch(MvcEvent $e)
    {
        // If auto-configuration is disabled, prevent any other action from being
        // accessed:
        $config = ConfigReader::getConfig();
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
        $events->attach(MvcEvent::EVENT_DISPATCH, array($this, 'preDispatch'), 1000);
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
        $config = ConfigReader::getLocalConfigPath('config.ini', null, true);
        if (!file_exists($config)) {
            return copy(ConfigReader::getBaseConfigPath('config.ini'), $config);
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
            $config = ConfigReader::getConfig();
            if (stristr($config->Site->url, 'myuniversity.edu')) {
                $status = false;
            }
        }

        return array(
            'title' => 'Basic Configuration', 'status' => $status,
            'fix' => 'fixbasicconfig'
        );
    }

    /**
     * Display repair instructions for basic configuration problems.
     *
     * @return mixed
     */
    public function fixbasicconfigAction()
    {
        $view = $this->createViewModel();
        $config = ConfigReader::getLocalConfigPath('config.ini', null, true);
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
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager');
        return array(
            'title' => 'Cache',
            'status' => !$cache->hasDirectoryCreationError(),
            'fix' => 'fixcache'
        );
    }

    /**
     * Display repair instructions for cache problems.
     *
     * @return mixed
     */
    public function fixcacheAction()
    {
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager');
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
            $test = $tags->getByText('test', false);
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return array(
            'title' => 'Database', 'status' => $status, 'fix' => 'fixdatabase'
        );
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

        // We need at least PHP v5.3.3:
        return PHP_VERSION_ID >= 50303;
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
              && function_exists('mcrypt_module_open');

        return array(
            'title' => 'Dependencies',
            'status' => $requiredFunctionsExist && $this->phpVersionIsNewEnough(),
            'fix' => 'fixdependencies'
        );
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
            $msg = "VuFind requires PHP version 5.3.3 or newer; you are running "
                . phpversion() . ".  Please upgrade.";
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
            $problems++;
        }

        // Is the mbstring library missing?
        if (!function_exists('mb_substr')) {
            $msg
                = "Your PHP installation appears to be missing the mbstring plug-in."
                ." For better language support, it is recommended that you add this."
                ." For details on how to do this, see "
                ."http://vufind.org/wiki/installation "
                ."and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
            $problems++;
        }

        // Is the GD library missing?
        if (!is_callable('imagecreatefromstring')) {
            $msg
                = "Your PHP installation appears to be missing the GD plug-in. "
                . "For better graphics support, it is recommended that you add this."
                . " For details on how to do this, see "
                . "http://vufind.org/wiki/installation "
                . "and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
            $problems++;
        }

        // Is the mcrypt library missing?
        if (!function_exists('mcrypt_module_open')) {
            $msg
                = "Your PHP installation appears to be missing the mcrypt plug-in."
                ." For better security support, it is recommended that you add this."
                ." For details on how to do this, see "
                ."http://vufind.org/wiki/installation "
                ."and look at the PHP installation instructions for your platform.";
        }


        return $this->createViewModel(array('problems' => $problems));
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
        $view->dbrootuser = $this->params()->fromPost('dbrootuser', 'root');

        $skip = $this->params()->fromPost('printsql', 'nope') == 'Skip';

        if (!preg_match('/^\w*$/', $view->dbname)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Database name must be alphanumeric.');
        } else if (!preg_match('/^\w*$/', $view->dbuser)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Database user must be alphanumeric.');
        } else if ($skip || strlen($this->params()->fromPost('submit', '')) > 0) {
            $newpass = $this->params()->fromPost('dbpass');
            $newpassConf = $this->params()->fromPost('dbpassconfirm');
            if ((empty($newpass) || empty($newpassConf))) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Password fields must not be blank.');
            } else if ($newpass != $newpassConf) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Password fields must match.');
            } else {
                // Connect to database:
                $connection = 'mysql://' . $view->dbrootuser . ':'
                    . $this->params()->fromPost('dbrootpass') . '@'
                    . $view->dbhost;
                try {
                    $db = AdapterFactory::getAdapterFromConnectionString(
                        $connection . '/mysql'
                    );
                } catch (\Exception $e) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage(
                            'Problem initializing database adapter; '
                            . 'check for missing Mysqli library.  Details: '
                            . $e->getMessage()
                        );
                    return $view;
                }
                try {
                    // Get SQL together
                    $query = 'CREATE DATABASE ' . $view->dbname;
                    $grant = "GRANT SELECT,INSERT,UPDATE,DELETE ON "
                        . $view->dbname
                        . ".* TO '{$view->dbuser}'@'{$view->dbhost}' "
                        . "IDENTIFIED BY " . $db->getPlatform()->quoteValue($newpass)
                        . " WITH GRANT OPTION";
                    $sql = file_get_contents(
                        APPLICATION_PATH . '/module/VuFind/sql/mysql.sql'
                    );
                    if ($skip == 'Skip') {
                        $omnisql = $query . ";\n". $grant
                            . ";\nFLUSH PRIVILEGES;\n\n" . $sql;
                        $this->getRequest()->getQuery()->set('sql', $omnisql);
                        return $this->forwardTo('Install', 'showsql');
                    } else {
                        $db->query($query, $db::QUERY_MODE_EXECUTE);
                        $db->query($grant, $db::QUERY_MODE_EXECUTE);
                        $db->query('FLUSH PRIVILEGES', $db::QUERY_MODE_EXECUTE);
                        $db = AdapterFactory::getAdapterFromConnectionString(
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
                        // If we made it this far, we can update the config file and
                        // forward back to the home action!
                        $string = "mysql://{$view->dbuser}:{$newpass}@"
                            . $view->dbhost . '/' . $view->dbname;
                        $config = ConfigReader::getLocalConfigPath(
                            'config.ini', null, true
                        );
                        $writer = new ConfigWriter($config);
                        $writer->set('Database', 'database', $string);
                        if (!$writer->save()) {
                            return $this->forwardTo('Install', 'fixbasicconfig');
                        }
                    }
                    return $this->redirect()->toRoute('install-home');
                } catch (\Exception $e) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage($e->getMessage());
                }
            }
        }
        return $view;
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
            array('sql' => $this->params()->fromQuery('sql'))
        );
    }

    /**
     * Check if ILS configuration is appropriate.
     *
     * @return array
     */
    protected function checkILS()
    {
        $config = ConfigReader::getConfig();
        if (in_array($config->Catalog->driver, array('Sample', 'Demo'))) {
            $status = false;
        } else {
            try {
                $catalog = $this->getILS();
                $catalog->getStatus('1');
                $status = true;
            } catch (\Exception $e) {
                $status = false;
            }
        }
        return array('title' => 'ILS', 'status' => $status, 'fix' => 'fixils');
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
            $configPath = ConfigReader::getLocalConfigPath('config.ini', null, true);
            $writer = new ConfigWriter($configPath);
            $writer->set('Catalog', 'driver', $newDriver);
            if (!$writer->save()) {
                return $this->forwardTo('Install', 'fixbasicconfig');
            }
            // Copy configuration, if applicable:
            $ilsIni = ConfigReader::getBaseConfigPath($newDriver . '.ini');
            $localIlsIni
                = ConfigReader::getLocalConfigPath("{$newDriver}.ini", null, true);
            if (file_exists($ilsIni) && !file_exists($localIlsIni)) {
                if (!copy($ilsIni, $localIlsIni)) {
                    return $this->forwardTo('Install', 'fixbasicconfig');
                }
            }
            return $this->redirect()->toRoute('install-home');
        }

        // If we got this far, check whether we have an error with a real driver
        // or if we need to warn the user that they have selected a fake driver:
        $config = ConfigReader::getConfig();
        $view = $this->createViewModel();
        if (in_array($config->Catalog->driver, array('Sample', 'Demo'))) {
            $view->demo = true;
            // Get a list of available drivers:
            $dir
                = opendir(APPLICATION_PATH . '/module/VuFind/src/VuFind/ILS/Driver');
            $drivers = array();
            $blacklist = array(
                'Sample.php', 'Demo.php', 'DriverInterface.php', 'AbstractBase.php',
                'PluginManager.php', 'PluginFactory.php'
            );
            while ($line = readdir($dir)) {
                if (stristr($line, '.php') && !in_array($line, $blacklist)) {
                    $drivers[] = str_replace('.php', '', $line);
                }
            }
            closedir($dir);
            sort($drivers);
            $view->drivers = $drivers;
        } else {
            $view->configPath = ConfigReader::getLocalConfigPath(
                "{$config->Catalog->driver}.ini", null, true
            );
        }
        return $view;
    }

    /**
     * Check if the Solr index is working.
     *
     * @return array
     */
    protected function checkSolr()
    {
        try {
            $solr = ConnectionManager::connectToIndex();
            $results = $solr->search();
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return array('title' => 'Solr', 'status' => $status, 'fix' => 'fixsolr');
    }

    /**
     * Display repair instructions for Solr problems.
     *
     * @return mixed
     */
    public function fixsolrAction()
    {
        // In Windows, localhost may fail -- see if switching to 127.0.0.1 helps:
        $config = ConfigReader::getConfig();
        $configFile = ConfigReader::getLocalConfigPath('config.ini', null, true);
        if (stristr($config->Index->url, 'localhost')) {
            $newUrl = str_replace('localhost', '127.0.0.1', $config->Index->url);
            try {
                $solr = ConnectionManager::connectToIndex(null, null, $newUrl);
                $results= $solr->search();

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
            array('localhost', '127.0.0.1'),
            $this->getRequest()->getServer()->get('HTTP_HOST'),
            $config->Index->url
        );
        $view->core = isset($config->Index->default_core)
            ? $config->Index->default_core : "biblio";
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
        $config = ConfigReader::getConfig();
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
            } catch (\VuFind\Exception\PasswordSecurity $e) {
                // Any security exception means we have a problem!
                $status = false;
            }
        }

        return array(
            'title' => 'Security', 'status' => $status, 'fix' => 'fixsecurity'
        );
    }

    /**
     * Support method for fixsecurityAction().  Returns true if the configuration
     * was modified, false otherwise.
     *
     * @param \Zend\Config\Config $config Existing VuFind configuration
     * @param ConfigWriter        $writer Config writer
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
            $enc_key = sha1(microtime(true).mt_rand(10000, 90000));
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
        // First, set encryption/hashing to true, and set the key
        $config = ConfigReader::getConfig();
        $configPath = ConfigReader::getLocalConfigPath('config.ini', null, true);
        $writer = new ConfigWriter($configPath);
        if ($this->fixSecurityConfiguration($config, $writer)) {
            // Problem writing? Show the user an error:
            if (!$writer->save()) {
                return $this->forwardTo('Install', 'fixbasicconfig');
            }

            // Success? Redirect to this action in order to reload the configuration:
            return $this->redirect()->toRoute('install-fixsecurity');
        }

        // Now we want to loop through the database and update passwords (if
        // necessary).
        $rows = $this->getTable('user')->getInsecureRows();
        if (count($rows) > 0) {
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
        }
        return $this->redirect()->toRoute('install-home');
    }

    /**
     * Disable auto-configuration.
     *
     * @return mixed
     */
    public function doneAction()
    {
        $config = ConfigReader::getLocalConfigPath('config.ini', null, true);
        $writer = new ConfigWriter($config);
        $writer->set('System', 'autoConfigure', 0);
        if (!$writer->save()) {
            return $this->forwardTo('Install', 'fixbasicconfig');
        }
        return $this->createViewModel(array('configDir' => dirname($config)));
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
        $checks = array();
        foreach ($methods as $method) {
            if (substr($method, 0, 5) == 'check') {
                $checks[] = $this->$method();
            }
        }
        return $this->createViewModel(array('checks' => $checks));
    }
}

