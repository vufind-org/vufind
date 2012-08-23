<?php
/**
 * Database utility class.
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
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db;
use VuFind\Config\Reader as ConfigReader, Zend\Db\Adapter\Adapter;

/**
 * Database utility class.
 *
 * @category VuFind2
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AdapterFactory
{
    /**
     * Obtain a Zend\DB connection using standard VuFind configuration.
     *
     * @param string $overrideUser Username override (leave null to use username
     * from config.ini)
     * @param string $overridePass Password override (leave null to use password
     * from config.ini)
     *
     * @return object
     */
    public static function getAdapter($overrideUser = null, $overridePass = null)
    {
        // Parse details from connection string:
        $config = ConfigReader::getConfig();
        return static::getAdapterFromConnectionString(
            $config->Database->database, $overrideUser, $overridePass
        );
    }

    /**
     * Obtain a Zend\DB connection using a connection string.
     *
     * @param string $connectionString Connection string of the form
     * [db_type]://[username]:[password]@[host]/[db_name]
     * @param string $overrideUser     Username override (leave null to use username
     * from connection string)
     * @param string $overridePass     Password override (leave null to use password
     * from connection string)
     *
     * @return object
     */
    public static function getAdapterFromConnectionString($connectionString,
        $overrideUser = null, $overridePass = null
    ) {
        list($type, $details) = explode('://', $connectionString);
        preg_match('/(.+)@([^@]+)\/(.+)/', $details, $matches);
        $credentials = isset($matches[1]) ? $matches[1] : null;
        $host = isset($matches[2]) ? $matches[2] : null;
        $dbName = isset($matches[3]) ? $matches[3] : null;
        if (strstr($credentials, ':')) {
            list($username, $password) = explode(':', $credentials, 2);
        } else {
            $username = $credentials;
            $password = null;
        }
        $username = !is_null($overrideUser) ? $overrideUser : $username;
        $password = !is_null($overridePass) ? $overridePass : $password;

        // Translate database type for compatibility with legacy config files:
        switch (strtolower($type)) {
        case 'mysql':
            $type = 'mysqli';
            break;
        case 'oci8':
            $type = 'Oracle';
            break;
        case 'pgsql':
            $type = 'Pdo_Pgsql';
            break;
        }

        // Set up default options:
        $options = array(
            'driver' => $type,
            'hostname' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $dbName
        );

        // Set up custom options by database type:
        switch (strtolower($type)) {
        case 'mysqli':
            $options['options'] = array('buffer_results' => true);
            break;
        }

        // Set up database connection:
        return new Adapter($options);
    }
}
