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
use VuFind\Config\Reader as ConfigReader, VuFind\Config\Writer as ConfigWriter,
    VuFind\Connection\Manager as ConnectionManager,
    Zend\Mvc\MvcEvent;

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
     * @return void
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
     * @return void
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
        $cache = \VuFind\Cache\Manager::getInstance();
        return array(
            'title' => 'Cache',
            'status' => !$cache->hasDirectoryCreationError(),
            'fix' => 'fixcache'
        );
    }

    /**
     * Display repair instructions for cache problems.
     *
     * @return void
     */
    public function fixcacheAction()
    {
        /* TODO
        $cache = \VuFind\Cache\Manager::getInstance();
        $this->view->cacheDir = $cache->getCacheDir();
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            $this->view->runningUser = $processUser['name'];
        }
         */
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
            $tags = new \VuFind\Db\Table\Tags();
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
     * Check for missing dependencies.
     *
     * @return array
     */
    protected function checkDependencies()
    {
        $status
            = function_exists('mb_substr') && is_callable('imagecreatefromstring');
        return array(
            'title' => 'Dependencies', 'status' => $status,
            'fix' => 'fixdependencies'
        );
    }

    /**
     * Show how to fix dependency problems.
     *
     * @return void
     */
    public function fixdependenciesAction()
    {
        /* TODO
        $this->view->problems = 0;

        // Is the mbstring library missing?
        if (!function_exists('mb_substr')) {
            $msg
                = "Your PHP installation appears to be missing the mbstring plug-in."
                ." For better language support, it is recommended that you add this."
                ." For details on how to do this, see "
                ."http://vufind.org/wiki/installation "
                ."and look at the PHP installation instructions for your platform.";
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
            $this->view->problems++;
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
            $this->view->problems++;
        }
         */
    }

    /**
     * Display repair instructions for database problems.
     *
     * @return void
     */
    public function fixdatabaseAction()
    {
        /* TODO
        $this->view->dbname = $this->_request->getParam('dbname', 'vufind');
        $this->view->dbuser = $this->_request->getParam('dbuser', 'vufind');
        $this->view->dbhost = $this->_request->getParam('dbhost', 'localhost');
        $this->view->dbrootuser = $this->_request->getParam('dbrootuser', 'root');

        if (!preg_match('/^\w*$/', $this->view->dbname)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Database name must be alphanumeric.');
        } else if (!preg_match('/^\w*$/', $this->view->dbuser)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Database user must be alphanumeric.');
        } else if (strlen($this->_request->getParam('submit', '')) > 0) {
            $newpass = $this->_request->getParam('dbpass');
            $newpassConf = $this->_request->getParam('dbpassconfirm');
            if (empty($newpass) || empty($newpassConf)) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Password fields must not be blank.');
            } else if ($newpass != $newpassConf) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Password fields must match.');
            } else {
                // Connect to database:
                $params = array(
                    'host' => $this->view->dbhost,
                    'username' => $this->view->dbrootuser,
                    'password' => $this->_request->getParam('dbrootpass'),
                    'dbname' => null
                );
                $db = Zend_Db::factory('mysqli', $params);
                try {
                    $connection = $db->getConnection();
                    $query = 'CREATE DATABASE ' . $this->view->dbname;
                    if (!$connection->query($query)) {
                        throw new \Exception($connection->error);
                    }
                    $grant = "GRANT SELECT,INSERT,UPDATE,DELETE ON "
                        . $this->view->dbname
                        . ".* TO '{$this->view->dbuser}'@'{$this->view->dbhost}' "
                        . "IDENTIFIED BY '"
                        . $connection->real_escape_string($newpass)
                        . "' WITH GRANT OPTION";
                    if (!$connection->query($grant)) {
                        throw new \Exception($connection->error);
                    };
                    if (!$connection->query('FLUSH PRIVILEGES')) {
                        throw new \Exception($connection->error);
                    }
                    $connection->select_db($this->view->dbname);
                    $sql = file_get_contents(APPLICATION_PATH . '/sql/mysql.sql');
                    $statements = explode(';', $sql);
                    foreach ($statements as $current) {
                        // Skip empty sections:
                        if (strlen(trim($current)) == 0) {
                            continue;
                        }
                        if (!$connection->query($current)) {
                            throw new \Exception($connection->error);
                        }
                    }
                    // If we made it this far, we can update the config file and
                    // forward back to the home action!
                    $string = "mysql://{$this->view->dbuser}:{$newpass}@"
                        . $this->view->dbhost . '/' . $this->view->dbname;
                    $config = LOCAL_OVERRIDE_DIR . '/application/configs/config.ini';
                    $writer = new VF_Config_Writer($config);
                    $writer->set('Database', 'database', $string);
                    if (!$writer->save()) {
                        return $this->_forward('fixbasicconfig');
                    }
                    return $this->_redirect('/Install');
                } catch (\Exception $e) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage($e->getMessage());
                }
            }
        }
         */
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
                $catalog = ConnectionManager::connectToCatalog();
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
     * @return void
     */
    public function fixilsAction()
    {
        /* TODO
        // Process incoming parameter -- user may have selected a new driver:
        $newDriver = $this->_request->getParam('driver');
        if (!empty($newDriver)) {
            $configPath = LOCAL_OVERRIDE_DIR . '/application/configs/config.ini';
            $writer = new VF_Config_Writer($configPath);
            $writer->set('Catalog', 'driver', $newDriver);
            if (!$writer->save()) {
                return $this->_forward('fixbasicconfig');
            }
            // Copy configuration, if applicable:
            $ilsIni = APPLICATION_PATH . '/configs/' . $newDriver . '.ini';
            if (file_exists($ilsIni)) {
                $success = copy(
                    $ilsIni,
                    LOCAL_OVERRIDE_DIR . "/application/configs/{$newDriver}.ini"
                );
                if (!$success) {
                    return $this->_forward('fixbasicconfig');
                }
            }
            return $this->_redirect('/Install');
        }

        // If we got this far, check whether we have an error with a real driver
        // or if we need to warn the user that they have selected a fake driver:
        $config = ConfigReader::getConfig();
        if (in_array($config->Catalog->driver, array('Sample', 'Demo'))) {
            $this->view->demo = true;
            // Get a list of available drivers:
            $dir = opendir(APPLICATION_PATH . '/../library/VF/ILS/Driver');
            $this->view->drivers = array();
            $blacklist = array('Sample.php', 'Demo.php', 'Interface.php');
            while ($line = readdir($dir)) {
                if (stristr($line, '.php') && !in_array($line, $blacklist)) {
                    $this->view->drivers[] = str_replace('.php', '', $line);
                }
            }
            closedir($dir);
            sort($this->view->drivers);
        } else {
            $this->view->configPath = LOCAL_OVERRIDE_DIR
                . "/application/configs/{$config->Catalog->driver}.ini";
        }
         */
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
     * @return void
     */
    public function fixsolrAction()
    {
        /* TODO
        // In Windows, localhost may fail -- see if switching to 127.0.0.1 helps:
        $config = ConfigReader::getConfig();
        $configFile = LOCAL_OVERRIDE_DIR . '/application/configs/config.ini';
        if (stristr($config->Index->url, 'localhost')) {
            $newUrl = str_replace('localhost', '127.0.0.1', $config->Index->url);
            try {
                $solr = ConnectionManager::connectToIndex(null, null, $newUrl);
                $results= $solr->search();

                // If we got this far, the fix worked.  Let's write it to disk!
                $writer = new VF_Config_Writer($configFile);
                $writer->set('Index', 'url', $newUrl);
                if (!$writer->save()) {
                    return $this->_forward('fixbasicconfig');
                }
                return $this->_redirect('/Install');
            } catch (\Exception $e) {
                // Didn't work!
            }
        }

        // If we got this far, the automatic fix didn't work, so let's just assign
        // some variables to use in offering troubleshooting advice:
        $this->view->rawUrl = $config->Index->url;
        $this->view->userUrl = str_replace(
            array('localhost', '127.0.0.1'), $this->_request->getHttpHost(),
            $config->Index->url
        );
        $this->view->core = isset($config->Index->default_core)
            ? $config->Index->default_core : "biblio";
        $this->view->configFile = $configFile;
         */
    }

    /**
     * Disable auto-configuration.
     *
     * @return void
     */
    public function doneAction()
    {
        /* TODO
        $configDir = LOCAL_OVERRIDE_DIR . '/application/configs';
        $writer = new VF_Config_Writer($configDir . '/config.ini');
        $writer->set('System', 'autoConfigure', 0);
        if (!$writer->save()) {
            return $this->_forward('fixbasicconfig');
        }
        $this->view->configDir = $configDir;
         */
    }

    /**
     * Display summary of installation status
     *
     * @return void
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

