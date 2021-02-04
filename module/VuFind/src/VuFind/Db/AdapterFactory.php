<?php
/**
 * Database utility class. May be used as a service or as a standard
 * Laminas factory.
 *
 * PHP version 7
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
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\Config\Config;
use Laminas\Db\Adapter\Adapter;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;

/**
 * Database utility class. May be used as a service or as a standard
 * Laminas factory.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdapterFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config $config VuFind configuration (provided when used as service;
     * omitted when used as factory)
     */
    public function __construct(Config $config = null)
    {
        $this->config = $config ?: new Config([]);
    }

    /**
     * Create an object (glue code for FactoryInterface compliance)
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory!');
        }
        $this->config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        return $this->getAdapter();
    }

    /**
     * Obtain a Laminas\DB connection using standard VuFind configuration.
     *
     * @param string $overrideUser Username override (leave null to use username
     * from config.ini)
     * @param string $overridePass Password override (leave null to use password
     * from config.ini)
     *
     * @return Adapter
     */
    public function getAdapter($overrideUser = null, $overridePass = null)
    {
        // Parse details from connection string:
        if (!isset($this->config->Database->database)) {
            throw new \Exception('"database" setting missing');
        }
        return $this->getAdapterFromConnectionString(
            $this->config->Database->database, $overrideUser, $overridePass
        );
    }

    /**
     * Translate the connection string protocol into a driver name.
     *
     * @param string $type Database type from connection string
     *
     * @return string
     */
    public function getDriverName($type)
    {
        switch (strtolower($type)) {
        case 'mysql':
            return 'mysqli';
        case 'oci8':
            return 'Oracle';
        case 'pgsql':
            return 'Pdo_Pgsql';
        }
        return $type;
    }

    /**
     * Obtain a Laminas\DB connection using an option array.
     *
     * @param array $options Options for building adapter
     *
     * @return Adapter
     */
    public function getAdapterFromOptions($options)
    {
        // Set up custom options by database type:
        $driver = strtolower($options['driver']);
        switch ($driver) {
        case 'mysqli':
            $options['charset'] = $this->config->Database->charset ?? 'utf8';
            $options['options'] = ['buffer_results' => true];
            break;
        }

        // Set up database connection:
        $adapter = new Adapter($options);

        // Special-case setup:
        if ($driver == 'pdo_pgsql' && isset($this->config->Database->schema)) {
            // Set schema
            $statement = $adapter->createStatement(
                'SET search_path TO ' . $this->config->Database->schema
            );
            $statement->execute();
        }

        return $adapter;
    }

    /**
     * Obtain a Laminas\DB connection using a connection string.
     *
     * @param string $connectionString Connection string of the form
     * [db_type]://[username]:[password]@[host]/[db_name]
     * @param string $overrideUser     Username override (leave null to use username
     * from connection string)
     * @param string $overridePass     Password override (leave null to use password
     * from connection string)
     *
     * @return Adapter
     */
    public function getAdapterFromConnectionString($connectionString,
        $overrideUser = null, $overridePass = null
    ) {
        list($type, $details) = explode('://', $connectionString);
        preg_match('/(.+)@([^@]+)\/(.+)/', $details, $matches);
        $credentials = $matches[1] ?? null;
        if (isset($matches[2])) {
            if (strpos($matches[2], ':') !== false) {
                list($host, $port) = explode(':', $matches[2]);
            } else {
                $host = $matches[2];
            }
        }
        $dbName = $matches[3] ?? null;
        if (strstr($credentials, ':')) {
            list($username, $password) = explode(':', $credentials, 2);
        } else {
            $username = $credentials;
            $password = null;
        }
        $username = null !== $overrideUser ? $overrideUser : $username;
        $password = null !== $overridePass ? $overridePass : $password;

        // Set up default options:
        $options = [
            'driver' => $this->getDriverName($type),
            'hostname' => $host ?? null,
            'username' => $username,
            'password' => $password,
            'database' => $dbName
        ];
        if (!empty($port)) {
            $options['port'] = $port;
        }
        return $this->getAdapterFromOptions($options);
    }
}
