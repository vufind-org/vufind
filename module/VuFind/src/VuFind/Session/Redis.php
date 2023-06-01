<?php

/**
 * Redis session handler
 *
 * Note: Using phpredis extension (see https://github.com/phpredis/phpredis) is
 * optional, this class use Credis in standalone mode by default
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2019.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Veros Kaplan <cpk-dev@mzk.cz>
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use Laminas\Config\Config;

/**
 * Redis session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Veros Kaplan <cpk-dev@mzk.cz>
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class Redis extends AbstractBase
{
    use \VuFind\Service\Feature\RetryTrait;

    /**
     * Redis connection
     *
     * @var \Credis_Client
     */
    protected $connection;

    /**
     * Redis version
     *
     * @var int
     */
    protected $redisVersion = 3;

    /**
     * Constructor
     *
     * @param \Credis_Client $connection Redis connection object
     * @param Config         $config     Session configuration ([Session] section of
     * config.ini)
     */
    public function __construct(\Credis_Client $connection, Config $config = null)
    {
        parent::__construct($config);
        $this->redisVersion = (int)($config->redis_version ?? 3);
        $this->connection = $connection;
        $this->retryOptions['retryCount'] = 2;
    }

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sessId The session ID to read
     *
     * @return string
     */
    public function read($sessId): string
    {
        return $this->callWithRetry(
            function () use ($sessId): string {
                $session = $this->connection->get("vufind_sessions/{$sessId}");
                return $session !== false ? $session : '';
            }
        );
    }

    /**
     * Write function that is called when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    protected function saveSession($sessId, $data): bool
    {
        return $this->callWithRetry(
            function () use ($sessId, $data): bool {
                return $this->connection->setex(
                    "vufind_sessions/{$sessId}",
                    $this->lifetime,
                    $data
                );
            }
        );
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sessId The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sessId): bool
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sessId);

        // Perform Redis-specific cleanup
        $unlinkMethod = ($this->redisVersion >= 4) ? 'unlink' : 'del';
        return $this->callWithRetry(
            function () use ($unlinkMethod, $sessId): bool {
                $this->connection->$unlinkMethod("vufind_sessions/{$sessId}");
                return true;
            }
        );
    }
}
