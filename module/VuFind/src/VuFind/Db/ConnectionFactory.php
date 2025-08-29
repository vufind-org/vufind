<?php

/**
 * Factory for Doctrine connection. May be used as a service or as a standard
 * Laminas factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use PDO;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\Config;
use VuFind\Config\Feature\SecretTrait;

/**
 * Factory for Doctrine connection. May be used as a service or as a standard
 * Laminas factory.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ConnectionFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    use SecretTrait;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Configuration file name when used as a factory.
     *
     * @var string
     */
    protected string $configName = 'config';

    /**
     * Connection wrapper class.
     *
     * @var string
     */
    protected string $wrapperClass = \VuFind\Db\Connection::class;

    /**
     * Constructor
     *
     * @param ?Config             $config    VuFind configuration (provided when used
     * as service; omitted when used as factory)
     * @param ?ContainerInterface $container Service container (provided when used
     * as service; omitted when used as factory)
     */
    public function __construct(
        ?Config $config = null,
        protected ?ContainerInterface $container = null
    ) {
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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory!');
        }
        $this->config = $container->get(\VuFind\Config\ConfigManager::class)->getConfigObject($this->configName);
        $this->container = $container;
        return $this->getConnection();
    }

    /**
     * Obtain a database connection using standard VuFind configuration.
     *
     * @param string $overrideUser Username override (leave null to use username
     * from config.ini)
     * @param string $overridePass Password override (leave null to use password
     * from config.ini)
     *
     * @return Connection
     */
    public function getConnection($overrideUser = null, $overridePass = null)
    {
        // Make sure object cache is initialized; Doctrine needs it:
        $this->container->get(\VuFind\Cache\Manager::class)->getCache('object');

        // Parse details from connection string if available, otherwise use
        // more granular config settings.
        if (isset($this->config->Database->database)) {
            $options = $this->getOptionsFromConnectionString(
                $this->config->Database->database,
                $overrideUser,
                $overridePass
            );
        } else {
            $dbConfig = $this->config->Database ?? new Config([]);
            $options = [
                'driver' => $this->getDriverName($dbConfig->database_driver ?? ''),
                'host' => $dbConfig->database_host ?? null,
                'user' => $overrideUser ?? $dbConfig->database_username ?? null,
                'password' => $overridePass ?? $this->getSecretFromConfig($dbConfig, 'database_password'),
                'dbname' => $dbConfig->database_name ?? null,
            ];
            if (!empty($dbConfig->database_port)) {
                $options['port'] = $dbConfig->database_port;
            }
        }

        $options['driverOptions'] = $this->getDriverOptions($options['driver']);

        return $this->getConnectionFromOptions($options);
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
                return 'pdo_mysql';
            case 'pgsql':
                return 'pdo_pgsql';
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
        // Load options from the configuration:
        $driverOptions = $this->config?->Database?->extra_options?->toArray() ?? [];

        // Apply MySQL-specific adjustments:
        if ($driver == 'pdo_mysql') {
            $driverOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]
                = $this->config->Database->verify_server_certificate ?? false;
            $sslKeyMap = [
                'client_key' => PDO::MYSQL_ATTR_SSL_KEY,
                'client_cert' => PDO::MYSQL_ATTR_SSL_CERT,
                'ca_cert' => PDO::MYSQL_ATTR_SSL_CA,
                'ca_path' => PDO::MYSQL_ATTR_SSL_CAPATH,
            ];
            $sslConfigured = false;
            foreach ($sslKeyMap as $oldKey => $newKey) {
                if (isset($driverOptions[$oldKey])) {
                    $driverOptions[$newKey] = $driverOptions[$oldKey];
                    unset($driverOptions[$oldKey]);
                }
                $sslConfigured = $sslConfigured || isset($driverOptions[$newKey]);
            }
            $useSsl = $this->config->Database->use_ssl ?? false;
            if ($useSsl && !$sslConfigured) {
                throw new \Exception(
                    'To use SSL with MySQL, please configure appropriate extra_options in '
                    . 'the [Database] section of config.ini.'
                );
            }
            if (!$useSsl && $sslConfigured) {
                throw new \Exception(
                    'Incompatible settings: SSL settings activated, but SSL disabled. '
                    . 'See use_ssl and extra_options in config.ini [Database] section.'
                );
            }
        }

        return $driverOptions;
    }

    /**
     * Obtain a database connection using an option array.
     *
     * @param array $options Options for building adapter
     *
     * @return Connection
     */
    public function getConnectionFromOptions($options)
    {
        // Set up custom options by database type:
        $driver = strtolower($options['driver']);
        switch ($driver) {
            case 'pdo_mysql':
                $options['charset'] = $this->config->Database->charset ?? 'utf8mb4';
                if (strtolower($options['charset']) === 'latin1') {
                    throw new \Exception(
                        'The latin1 encoding is no longer supported for MySQL databases in VuFind.'
                    );
                }
                break;
        }
        $options['wrapperClass'] = $this->wrapperClass;

        // Set up database connection:
        if (empty($this->container)) {
            throw new \Exception('Container is missing!');
        }
        $connection = DriverManager::getConnection(
            $options
        );

        return $connection;
    }

    /**
     * Parse database connection options from a connection string.
     *
     * @param string  $connectionString Connection string of the form
     * [db_type]://[username]:[password]@[host]/[db_name]
     * @param ?string $overrideUser     Username override (leave null to use username
     * from connection string)
     * @param ?string $overridePass     Password override (leave null to use password
     * from connection string)
     *
     * @return array
     */
    public function getOptionsFromConnectionString(
        string $connectionString,
        ?string $overrideUser = null,
        ?string $overridePass = null
    ): array {
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

        $driverName = $this->getDriverName($type);

        // Set up default options:
        $options = [
            'driver' => $driverName,
            'host' => $host,
            'user' => $username,
            'password' => $password,
            'dbname' => $dbName,
        ];
        if (!empty($port)) {
            $options['port'] = $port;
        }
        return $options;
    }
}
