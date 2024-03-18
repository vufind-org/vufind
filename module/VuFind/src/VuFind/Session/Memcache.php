<?php

/**
 * MemCache session handler
 *
 * Note: This relies on PHP's Memcache extension
 * (see http://us.php.net/manual/en/book.memcache.php)
 *
 * PHP version 8
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

use Laminas\Config\Config;

use function get_class;
use function in_array;

/**
 * Memcache session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class Memcache extends AbstractBase
{
    /**
     * Memcache connection
     *
     * @var \Memcache
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param Config                    $config Session configuration ([Session] section of config.ini)
     * @param \Memcache|\Memcached|null $client Optional Memcache client object
     */
    public function __construct(Config $config = null, object $client = null)
    {
        parent::__construct($config);
        $this->connect($config, $client);
    }

    /**
     * Set up the connection to Memcache.
     *
     * @param ?Config                   $config Session configuration ([Session] section of config.ini)
     * @param \Memcache|\Memcached|null $client Optional Memcache client object
     *
     * @return void
     */
    protected function connect(?Config $config, ?object $client): void
    {
        // Set defaults if nothing set in config file.
        $host = $config->memcache_host ?? 'localhost';
        $port = $config->memcache_port ?? 11211;
        $timeout = $config->memcache_connection_timeout ?? 1;
        $clientClass = $config->memcache_client ?? 'Memcache';

        // Create/validate client object:
        if (!in_array($clientClass, ['Memcache', 'Memcached'])) {
            throw new \Exception("Unsupported Memcache client: $clientClass");
        }
        $this->connection = $client ?? new $clientClass();
        if (!($this->connection instanceof $clientClass)) {
            throw new \Exception(
                'Unexpected Memcache client class: ' . get_class($this->connection)
            );
        }

        // Prepare a connection exception, just in case we need it:
        $connectionException = new \Exception(
            "Could not connect to $clientClass (host = {$host}, port = {$port})."
        );

        // Establish connection:
        switch ($clientClass) {
            case 'Memcache':
                if (!$this->connection->connect($host, $port, $timeout)) {
                    throw $connectionException;
                }
                break;
            case 'Memcached':
                $this->connection
                    ->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $timeout);
                if (!$this->connection->addServer($host, $port)) {
                    throw $connectionException;
                }
                break;
        }
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
        // For some reason, Memcache tests fail if we do not pass exactly three
        // parameters to the get method, even though this seems inconsistent with
        // the documentation. This mechanism makes the tests pass, but may be worth
        // revisiting in the future.
        $extraParams = $this->connection instanceof \Memcache ? [null, null] : [];
        $value = $this->connection
            ->get("vufind_sessions/{$sessId}", ...$extraParams);
        return empty($value) ? '' : $value;
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

        // Perform Memcache-specific cleanup:
        return $this->connection->delete("vufind_sessions/{$sessId}");
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    protected function saveSession($sessId, $data): bool
    {
        // Memcached and Memcache have different set() signatures, so we need to
        // behave differently depending on the class of the connection.
        if ($this->connection instanceof \Memcached) {
            return $this->connection->set(
                "vufind_sessions/{$sessId}",
                $data,
                $this->lifetime
            );
        }
        // The third parameter (0) here is $flags, which we do not use.
        return $this->connection->set(
            "vufind_sessions/{$sessId}",
            $data,
            0,
            $this->lifetime
        );
    }
}
