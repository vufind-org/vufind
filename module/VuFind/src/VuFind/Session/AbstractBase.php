<?php
/**
 * Base class for session handling
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
namespace VuFind\Session;
use Zend\Session\SaveHandler\SaveHandlerInterface;

/**
 * Base class for session handling
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
abstract class AbstractBase implements SaveHandlerInterface,
    \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait {
        getDbTable as getTable;
    }

    /**
     * Session lifetime in seconds
     *
     * @var int
     */
    protected $lifetime = 3600;

    /**
     * Session configuration settings
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Whether writes are disabled, i.e. any changes to the session are not written
     * to the storage
     *
     * @var bool
     */
    protected $writesDisabled = false;

    /**
     * Disable session writing, i.e. make it read-only
     *
     * @return void
     */
    public function disableWrites()
    {
        $this->writesDisabled = true;
    }

    /**
     * Set configuration.
     *
     * @param \Zend\Config\Config $config Session configuration ([Session] section of
     * config.ini)
     *
     * @return void
     */
    public function setConfig($config)
    {
        if (isset($config->lifetime)) {
            $this->lifetime = $config->lifetime;
        }
        $this->config = $config;
    }

    /**
     * Open function, this works like a constructor in classes and is executed
     * when the session is being opened.
     *
     * @param string $sess_path Session save path
     * @param string $sess_name Session name
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function open($sess_path, $sess_name)
    {
        return true;
    }

    /**
     * Close function, this works like a destructor in classes and is executed
     * when the session operation is done.
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * IMPORTANT:  The functionality defined in this method is global to all session
     *             mechanisms.  If you override this method, be sure to still call
     *             parent::destroy() in addition to any new behavior.
     *
     * @param string $sess_id The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sess_id)
    {
        $table = $this->getTable('Search');
        $table->destroySession($sess_id);
        return true;
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $sess_maxlifetime Maximum session lifetime.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function gc($sess_maxlifetime)
    {
        // how often does this get called (if at all)?

        // *** 08/Oct/09 - Greg Pendlebury
        // Clearly this is being called. Production installs with
        //   thousands of sessions active are showing no old sessions.
        // What I can't do is reproduce for testing. It might need the
        //   search delete code from 'destroy()' if it is not calling it.
        // *** 09/Oct/09 - Greg Pendlebury
        // Anecdotal testing Today and Yesterday seems to indicate destroy()
        //   is called by the garbage collector and everything is good.
        // Something to keep in mind though.
        return true;
    }

    /**
     * Write function that is called when session data is to be saved.
     *
     * @param string $sess_id The current session ID
     * @param string $data    The session data to write
     *
     * @return bool
     */
    public function write($sess_id, $data)
    {
        if ($this->writesDisabled) {
            return true;
        }
        return $this->saveSession($sess_id, $data);
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sess_id The current session ID
     * @param string $data    The session data to write
     *
     * @return bool
     */
    abstract protected function saveSession($sess_id, $data);
}
