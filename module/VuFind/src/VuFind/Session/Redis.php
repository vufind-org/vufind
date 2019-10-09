<?php
/**
 * Redis session handler
 *
 * Note: This relies on Pecl Redis extension
 * (see https://github.com/phpredis/phpredis)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019. // ?
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
 * @package  Session_Handlers
 * @author   Veros Kaplan <cpk-dev@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
namespace VuFind\Session;

/**
 * Redis session handler
 *
 * @category VuFind2
 * @package  Session_Handlers
 * @author   Veros Kaplan <cpk-dev@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
class Redis extends AbstractBase
{
    /**
     * Redis connection
     *
     * @var \Redis
     */
    protected $connection = false;
    protected $redisVersion = 3;

    /**
     * Get connection to Redis
     *
     * @throws \Exception
     * @return \Redis
     */
    public function getConnection()
    {
        if (!$this->connection) {
            // Set defaults if nothing set in config file.
            $host = isset($this->config->redis_host)
                ? $this->config->redis_host : 'localhost';
            $port = isset($this->config->redis_port)
                ? $this->config->redis_port : 6379;
            $timeout = isset($this->config->redis_connection_timeout)
                ? $this->config->redis_connection_timeout : 0.5;
            $auth = isset($this->config->redis_auth)
                ? $this->config->redis_auth : false;
            $redis_db = isset($this->config->redis_db)
                ? $this->config->redis_db : 0;
            $this->redisVersion = isset($this->config->redis_version)
                ? int($this->config->redis_version) : 3;

            // Connect to Redis
            $this->connection = new \Redis();
            if (!$this->connection->connect($host, $port, $timeout)) {
                throw new \Exception(
                    "Could not connect to Redis (host = {$host}, port = {$port})."
                );
            }
            if ($auth) {
                if (!$this->connection->auth($auth)) {
                    throw new \exception(
                        "unable to authenticate auth to redis (host = {$host}, port = {$port})."
                    );
                }
            }
            if (!$this->connection->select($redis_db)) {
                throw new \Exception(
                    "Unable to change Redis database to $redis_db."
                );
            }
        }
        return $this->connection;
    }

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sess_id The session ID to read
     *
     * @return string or FALSE
     */
    public function read($sess_id)
    {
        return $this->getConnection()->get("vufind_sessions/{$sess_id}");
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
        return $this->getConnection()->setEx(
            "vufind_sessions/{$sess_id}", $this->lifetime, $data
        );
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sess_id The session ID to destroy
     *
     * @return void
     */
    public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Perform Redis-specific cleanup
        if ($this->redisVersion >= 4) {
		return $this->getConnection()->unlink("vufind_sessions/{$sess_id}");
        } else {
		return $this->getConnection()->del("vufind_sessions/{$sess_id}");
        }
    }
}
