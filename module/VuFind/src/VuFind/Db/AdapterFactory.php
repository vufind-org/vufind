<?php

/**
 * Database utility class. May be used as a service or as a standard
 * Laminas factory.
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
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db;

use Laminas\Config\Config;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\Feature\SecretTrait;

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
    use SecretTrait;

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
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
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
        if (isset($this->config->Database->database)) {
            // Parse details from connection string:
            return $this->getAdapterFromConnectionString(
                $this->config->Database->database,
                $overrideUser,
                $overridePass
            );
        } else {
            return $this->getAdapterFromConfig(
                $overrideUser,
                $overridePass
            );
        }
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
     * Get options for the selected driver.
     *
     * @param string $driver Driver name
     *
     * @return array
     */
    protected function getDriverOptions($driver)
    {
        switch ($driver) {
            case 'mysqli':
                return ($this->config->Database->verify_server_certificate ?? false)
                    ? [] : [MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT];
        }
        return [];
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
                $options['charset'] = $this->config->Database->charset ?? 'utf8mb4';
                if (strtolower($options['charset']) === 'latin1') {
                    throw new \Exception(
                        'The latin1 encoding is no longer supported for MySQL'
                        . ' databases in VuFind. Please convert your database'
                        . ' to utf8 using VuFind 7.x or earlier BEFORE'
                        . ' upgrading to this version.'
                    );
                }
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
    public function getAdapterFromConnectionString(
        $connectionString,
        $overrideUser = null,
        $overridePass = null
    ) {
        [$type, $details] = explode('://', $connectionString);
        preg_match('/(.+)@([^@]+)\/(.+)/', $details, $matches);
        $credentials = $matches[1] ?? null;
        $host = $port = null;
        if (isset($matches[2])) {
            if (str_contains($matches[2], ':')) {
                [$host, $port] = explode(':', $matches[2]);
            } else {
                $host = $matches[2];
            }
        }
        $dbName = $matches[3] ?? null;
        if (strstr($credentials, ':')) {
            [$username, $password] = explode(':', $credentials, 2);
        } else {
            $username = $credentials;
            $password = null;
        }
        $username = $overrideUser ?? $username;
        $password = $overridePass ?? $password;

        return $this->getAdapterFromArray([
            'driver' => $type,
            'hostname' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $dbName,
            'use_ssl' => $this->config->Database->use_ssl ?? false,
            'port' => $port ?? null,
        ]);
    }

    /**
     * Obtain a Laminas\DB connection using the config.
     *
     * @param string $overrideUser Username override (leave null to use username from config)
     * @param string $overridePass Password override (leave null to use password from config)
     *
     * @return Adapter
     */
    public function getAdapterFromConfig($overrideUser = null, $overridePass = null)
    {
        if (!isset($this->config->Database)) {
            throw new \Exception('[Database] Configuration section missing');
        }
        $config = $this->config->Database;
        return $this->getAdapterFromArray([
            'driver' => $config->database_driver ?? null,
            'hostname' => $config->database_host ?? null,
            'username' => $overrideUser ?? $config->database_username ?? null,
            'password' => $overridePass ?? $this->getSecretFromConfig($config, 'database_password'),
            'database' => $config->database_name,
            'port' => $config->database_port ?? null,
        ]);
    }

    /**
     * Obtain a Laminas\DB connection using a set of given element.
     *
     * @param array $config Config array to connect to the DB containing
     * driver (ie: mysql), username, password, hostname, database (db name), port
     *
     * @return Adapter
     */
    public function getAdapterFromArray(array $config)
    {
        $driverName = $this->getDriverName($config['driver']);

        // Set up default options:
        $options = [
            'driver' => $driverName,
            'hostname' => $config['hostname'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'database' => $config['database'] ?? null,
            'use_ssl' => $this->config->Database->use_ssl ?? false,
            'driver_options' => $this->getDriverOptions($driverName),
        ];
        if (isset($config['port'])) {
            $options['port'] = $config['port'];
        }
        // Get extra custom options from config:
        $extraOptions = $this->config?->Database?->extra_options?->toArray() ?? [];
        // Note: $options takes precedence over $extraOptions -- we don't want users
        // using extended settings to override values from core settings.
        return $this->getAdapterFromOptions($options + $extraOptions);
    }
}
