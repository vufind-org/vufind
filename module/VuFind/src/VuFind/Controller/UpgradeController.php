<?php
/**
 * Upgrade Controller
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
use ArrayObject, VuFind\Cache\Manager as CacheManager,
    VuFind\Cookie\Container as CookieContainer,
    VuFind\Db\Table\Resource as ResourceTable,
    VuFind\Exception\RecordMissing as RecordMissingException, VuFind\Record,
    Zend\Session\Container as SessionContainer;

/**
 * Class controls VuFind upgrading.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UpgradeController extends AbstractBase
{
    protected $cookie;
    protected $session;

    /**
     * Constructor
     */
    public function __construct()
    {
        // We want to use cookies for tracking the state of the upgrade, since the
        // session is unreliable -- if the user upgrades a configuration that uses
        // a different session handler than the default one, we'll lose track of our
        // upgrade state in the middle of the process!
        $this->cookie = new CookieContainer('vfup');

        // ...however, once the configuration piece of the upgrade is done, we can
        // safely use the session for storing some values.  We'll use this for the
        // temporary storage of root database credentials, since it is unwise to
        // send such sensitive values around as cookies!
        $this->session = new SessionContainer('upgrade');

        // We should also use the session for storing warnings once we know it will
        // be stable; this will prevent the cookies from getting too big.
        if (!isset($this->session->warnings)) {
            $this->session->warnings = new ArrayObject();
        }
    }

    /**
     * Support method -- given a directory, extract a version number from the
     * build.xml file within that directory.
     *
     * @param string $dir Directory to search for build.xml
     *
     * @return string
     */
    protected function getVersion($dir)
    {
        $xml = simplexml_load_file($dir . '/build.xml');
        if (!$xml) {
            throw new \Exception('Cannot load ' . $dir . '/build.xml.');
        }
        $parts = $xml->xpath('/project/property[@name="version"]/@value');
        return (string)$parts[0];
    }

    /**
     * Display a fatal error message.
     *
     * @return void
     */
    public function errorAction()
    {
        // Just display template
        return $this->createViewModel();
    }

    /**
     * Figure out which version(s) are being used.
     *
     * @return void
     */
    public function establishversionsAction()
    {
        $this->cookie->oldVersion = $this->getVersion(realpath(APPLICATION_PATH));
        $this->cookie->newVersion = $this->getVersion($this->cookie->sourceDir);

        // Block upgrade when encountering common errors:
        if (empty($this->cookie->oldVersion)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Cannot determine source version.');
            unset($this->cookie->oldVersion);
            return $this->forward()->dispatch('Upgrade', array('action' => 'Error'));
        }
        if (empty($this->cookie->newVersion)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Cannot determine destination version.');
            unset($this->cookie->newVersion);
            return $this->forward()->dispatch('Upgrade', array('action' => 'Error'));
        }
        if ($this->cookie->newVersion == $this->cookie->oldVersion) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Cannot upgrade version to itself.');
            unset($this->cookie->newVersion);
            return $this->forward()->dispatch('Upgrade', array('action' => 'Error'));
        }

        // If we got this far, everything is okay:
        return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
    }

    /**
     * Upgrade the configuration files.
     *
     * @return void
     */
    public function fixconfigAction()
    {
        /* TODO
        $upgrader = new VF_Config_Upgrade(
            $this->cookie->oldVersion, $this->cookie->newVersion,
            $this->cookie->sourceDir . '/web/conf',
            APPLICATION_PATH . '/configs',
            LOCAL_OVERRIDE_DIR . '/application/configs'
        );
        try {
            $upgrader->run();
            $this->cookie->warnings = $upgrader->getWarnings();
            $this->cookie->configOkay = true;
            return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
        } catch (\Exception $e) {
            $extra = is_a($e, 'VF_Exception_FileAccess')
                ? '  Check file permissions.' : '';
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Config upgrade failed: ' . $e->getMessage() . $extra);
            return $this->forward()->dispatch('Upgrade', array('action' => 'Error'));
        }
         */
    }

    /**
     * Upgrade the database.
     *
     * @return void
     */
    public function fixdatabaseAction()
    {
        /* TODO
        try {
            // Set up the helper with information from our SQL file:
            $this->_helper->dbUpgrade->loadSql(APPLICATION_PATH . '/sql/mysql.sql');

            // Check for missing tables.  Note that we need to finish dealing with
            // missing tables before we proceed to the missing columns check, or else
            // the missing tables will cause fatal errors during the column test.
            $missingTables = $this->_helper->dbUpgrade->getMissingTables();
            if (!empty($missingTables)) {
                if (!isset($this->session->dbRootUser)
                    || !isset($this->session->dbRootPass)
                ) {
                    return $this->forward()
                        ->dispatch('Upgrade', array('action' => 'GetDbCredentials'));
                }
                $db = VF_DB::connect(
                    $this->session->dbRootUser, $this->session->dbRootPass
                );
                $this->_helper->dbUpgrade->createMissingTables($missingTables, $db);
                $this->session->warnings->append(
                    "Created missing table(s): " . implode(', ', $missingTables
                );
            }

            // Check for missing columns.
            $missingCols = $this->_helper->dbUpgrade->getMissingColumns();
            if (!empty($missingCols)) {
                if (!isset($this->session->dbRootUser)
                    || !isset($this->session->dbRootPass)
                ) {
                    return $this->forward()
                        ->dispatch('Upgrade', array('action' => 'GetDbCredentials'));
                }
                if (!isset($db)) {  // connect to DB if not already connected
                    $db = VF_DB::connect(
                        $this->session->dbRootUser, $this->session->dbRootPass
                    );
                }
                $this->_helper->dbUpgrade->createMissingColumns($missingCols, $db);
                $this->session->warnings->append(
                    "Added column(s) to table(s): "
                    . implode(', ', array_keys($missingCols))
                );
            }

            // Check for modified columns.
            $modifiedCols = $this->_helper->dbUpgrade->getModifiedColumns();
            if (!empty($modifiedCols)) {
                if (!isset($this->session->dbRootUser)
                    || !isset($this->session->dbRootPass)
                ) {
                    return $this->forward()
                        ->dispatch('Upgrade', array('action' => 'GetDbCredentials'));
                }
                if (!isset($db)) {  // connect to DB if not already connected
                    $db = VF_DB::connect(
                        $this->session->dbRootUser, $this->session->dbRootPass
                    );
                }
                $this->_helper->dbUpgrade->updateModifiedColumns($modifiedCols, $db);
                $this->session->warnings->append(
                    "Modified column(s) in table(s): "
                    . implode(', ', array_keys($modifiedCols))
                );
            }

            // Don't keep DB credentials in session longer than necessary:
            unset($this->session->dbRootUser);
            unset($this->session->dbRootPass);

            // Check for legacy "anonymous tag" bug:
            $anonymousTags = VuFind_Model_Db_Tags::getAnonymousCount();
            if ($anonymousTags > 0 && !isset($this->cookie->skipAnonymousTags)) {
                $this->view->anonymousCount = $anonymousTags;
                    return $this->forward()
                        ->dispatch('Upgrade', array('action' => 'FixAnonymousTags'));
            }
        } catch (\Exception $e) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Database upgrade failed: ' . $e->getMessage());
            return $this->forward()->dispatch('Upgrade', array('action' => 'Error'));
        }

        $this->cookie->databaseOkay = true;
        return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
         */
    }

    /**
     * Prompt the user for database credentials.
     *
     * @return void
     */
    public function getdbcredentialsAction()
    {
        /* TODO
        $this->view->dbrootuser = $this->_request->getParam('dbrootuser', 'root');

        // Process form submission:
        if (strlen($this->_request->getParam('submit', '')) > 0) {
            $pass = $this->_request->getParam('dbrootpass');

            // Test the connection:
            try {
                $db = VF_DB::connect($this->view->dbrootuser, $pass);
                $db->query("SELECT * FROM user;");  // query a table known to exist
                $this->session->dbRootUser = $this->view->dbrootuser;
                $this->session->dbRootPass = $pass;
                return $this->forward()
                    ->dispatch('Upgrade', array('action' => 'FixDatabase'));
            } catch (\Exception $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Could not connect; please try again.');
            }
        }
         */
    }

    /**
     * Prompt the user about fixing anonymous tags.
     *
     * @return void
     */
    public function fixanonymoustagsAction()
    {
        /* TODO
        // Handle skip action:
        if (strlen($this->_request->getParam('skip', '')) > 0) {
            $this->cookie->skipAnonymousTags = true;
            return $this->forward()
                ->dispatch('Upgrade', array('action' => 'FixDatabase'));
        }

        // Handle submit action:
        if (strlen($this->_request->getParam('submit', '')) > 0) {
            $user = $this->_request->getParam('username');
            if (empty($user)) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Username must not be empty.');
            } else {
                $user = VuFind_Model_Db_User::getByUsername($user, false);
                if (empty($user) || !is_object($user) || !isset($user->id)) {
                    $this->flashMessenger()->setNamespace('error')
                        ->addMessage("User {$user} not found.");
                } else {
                    $table = new VuFind_Model_Db_ResourceTags();
                    $table->assignAnonymousTags($user->id);
                    $this->session->warnings->append(
                        "Assigned all anonymous tags to {$user->username}."
                    );
                    return $this->forward()
                        ->dispatch('Upgrade', array('action' => 'FixDatabase'));
                }
            }
        }
         */
    }

    /**
     * Fix missing metadata in the resource table.
     *
     * @return void
     */
    public function fixmetadataAction()
    {
        // User requested skipping this step?  No need to do further work:
        if (strlen($this->params()->fromPost('skip', '')) > 0) {
            $this->cookie->metadataOkay = true;
            return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
        }

        // Check for problems:
        $table = new ResourceTable();
        $problems = $table->findMissingMetadata();

        // No problems?  We're done here!
        if (count($problems) == 0) {
            $this->cookie->metadataOkay = true;
            return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
        }

        // Process submit button:
        if (strlen($this->params()->fromPost('submit', '')) > 0) {
            foreach ($problems as $problem) {
                try {
                    $driver = Record::load($problem->record_id, $problem->source);
                    $problem->assignMetadata($driver)->save();
                } catch (RecordMissingException $e) {
                    $this->session->warnings->append(
                        "Unable to load metadata for record "
                        . "{$problem->source}:{$problem->record_id}"
                    );
                }
            }
            $this->cookie->metadataOkay = true;
            return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
        }
    }

    /**
     * Prompt the user for a source directory.
     *
     * @return void
     */
    public function getsourcedirAction()
    {
        // Process form submission:
        $dir = $this->params()->fromPost('sourcedir');
        if (!empty($dir)) {
            if (!is_dir($dir)) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($dir . ' does not exist.');
            } else if (!file_exists($dir . '/build.xml')) {
                $this->flashMessenger()->setNamespace('error')->addMessage(
                    'Could not find build.xml in source directory;'
                    . ' upgrade does not support VuFind versions prior to 1.1.'
                );
            } else {
                $this->cookie->sourceDir = rtrim($dir, '\/');
                // Clear out request to avoid infinite loop:
                $this->getRequest()->getPost()->set('sourcedir', '');
                return $this->forward()
                    ->dispatch('Upgrade', array('action' => 'Home'));
            }
        }

        return $this->createViewModel();
    }

    /**
     * Display summary of installation status
     *
     * @return void
     */
    public function homeAction()
    {
        // If the cache is messed up, nothing is going to work right -- check that
        // first:
        $cache = CacheManager::getInstance();
        if ($cache->hasDirectoryCreationError()) {
            return $this->redirect()->toRoute('install-fixcache');
        }

        // First find out which version we are upgrading:
        if (!isset($this->cookie->sourceDir)
            || !is_dir($this->cookie->sourceDir)
        ) {
            return $this->forward()
                ->dispatch('Upgrade', array('action' => 'GetSourceDir'));
        }

        // Next figure out which version(s) are involved:
        if (!isset($this->cookie->oldVersion)
            || !isset($this->cookie->newVersion)
        ) {
            return $this->forward()
                ->dispatch('Upgrade', array('action' => 'EstablishVersions'));
        }

        /* TODO
        // Now make sure we have a configuration file ready:
        if (!isset($this->cookie->configOkay)) {
            return $this->redirect()->toRoute('upgrade-fixconfig');
        }

        // Now make sure the database is up to date:
        if (!isset($this->cookie->databaseOkay)) {
            return $this->redirect()->toRoute('upgrade-fixdatabase');
        }
         */

        // Check for missing metadata in the resource table; note that we do a
        // redirect rather than a forward here so that a submit button clicked
        // in the database action doesn't cause the metadata action to also submit!
        if (!isset($this->cookie->metadataOkay)) {
            return $this->redirect()->toRoute('upgrade-fixmetadata');
        }

        // We're finally done -- display any warnings that we collected during
        // the process.
        $allWarnings = array_merge(
            isset($this->cookie->warnings) ? $this->cookie->warnings : array(),
            (array)$this->session->warnings
        );
        foreach ($allWarnings as $warning) {
            $this->flashMessenger()->setNamespace('info')
                ->addMessage($warning);
        }

        return $this->createViewModel();
    }

    /**
     * Start over with the upgrade process in case of an error.
     *
     * @return void
     */
    public function resetAction()
    {
        foreach ($this->cookie->getAllValues() as $k => $v) {
            unset($this->cookie->$k);
        }
        $storage = $this->session->getManager()->getStorage();
        $storage[$this->session->getName()]
            = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
        return $this->forward()->dispatch('Upgrade', array('action' => 'Home'));
    }
}

