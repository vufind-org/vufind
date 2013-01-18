<?php
/**
 * MemCache session handler
 *
 * Note: This relies on PHP's Memcache extension
 * (see http://us.php.net/manual/en/book.memcache.php)
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
namespace VuFind\Session;

/**
 * Memcache session handler
 *
 * @category VuFind2
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:session_handlers Wiki
 */
class Memcache extends AbstractBase
{
    /**
     * Memcache connection
     *
     * @var \Memcache
     */
    protected $connection = false;

    /**
     * Get connection to Memcache
     *
     * @throws \Exception
     * @return \Memcache
     */
    public function getConnection()
    {
        if (!$this->connection) {
            // Set defaults if nothing set in config file.
            $host = isset($this->config->memcache_host)
                ? $this->config->memcache_host : 'localhost';
            $port = isset($this->config->memcache_port)
                ? $this->config->memcache_port : 11211;
            $timeout = isset($this->config->memcache_connection_timeout)
                ? $this->config->memcache_connection_timeout : 1;

            // Connect to Memcache:
            $this->connection = new \Memcache();
            if (!$this->connection->connect($host, $port, $timeout)) {
                throw new \Exception(
                    "Could not connect to Memcache (host = {$host}, port = {$port})."
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
     * @return string
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
     * @return void
     */
    public function write($sess_id, $data)
    {
        return $this->getConnection()->set(
            "vufind_sessions/{$sess_id}", $data, 0, $this->lifetime
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

        // Perform Memcache-specific cleanup:
        return $this->getConnection()->delete("vufind_sessions/{$sess_id}");
    }
}