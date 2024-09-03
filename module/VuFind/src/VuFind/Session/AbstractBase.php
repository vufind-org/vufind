<?php

/**
 * Base class for session handling
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use Laminas\Config\Config;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ExternalSessionServiceInterface;
use VuFind\Db\Service\SearchServiceInterface;

/**
 * Base class for session handling
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
abstract class AbstractBase implements HandlerInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait {
        getDbTable as getTable;
    }
    // Note that we intentionally omit the DbServiceAwareInterface above; the service
    // manager is injected by AbstractBaseFactory explicitly for compatibility with
    // the secure delegator factory, so we don't need to auto-inject it.
    use DbServiceAwareTrait;

    /**
     * Session lifetime in seconds
     *
     * @var int
     */
    protected $lifetime = 3600;

    /**
     * Whether writes are disabled, i.e. any changes to the session are not written
     * to the storage
     *
     * @var bool
     */
    protected $writesDisabled = false;

    /**
     * Constructor
     *
     * @param Config $config Session configuration ([Session] section of
     * config.ini)
     */
    public function __construct(Config $config = null)
    {
        if (isset($config->lifetime)) {
            $this->lifetime = $config->lifetime;
        }
    }

    /**
     * Enable session writing (default)
     *
     * @return void
     */
    public function enableWrites()
    {
        $this->writesDisabled = false;
    }

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
    public function open($sess_path, $sess_name): bool
    {
        return true;
    }

    /**
     * Close function, this works like a destructor in classes and is executed
     * when the session operation is done.
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * IMPORTANT:  The functionality defined in this method is global to all session
     *             mechanisms. If you override this method, be sure to still call
     *             parent::destroy() in addition to any new behavior.
     *
     * @param string $sessId The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sessId): bool
    {
        $this->getDbService(SearchServiceInterface::class)->destroySession($sessId);
        $this->getDbService(ExternalSessionServiceInterface::class)->destroySession($sessId);
        return true;
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $sessMaxLifetime Maximum session lifetime.
     *
     * @return int|false
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function gc($sessMaxLifetime): int|false
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
        return 0;
    }

    /**
     * Write function that is called when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    public function write($sessId, $data): bool
    {
        if ($this->writesDisabled) {
            return true;
        }
        return $this->saveSession($sessId, $data);
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    abstract protected function saveSession($sessId, $data): bool;
}
