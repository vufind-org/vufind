<?php

/**
 * Database builder (for creating the database required by the system).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
use Doctrine\DBAL\Exception as DBALException;
use Exception;

use function in_array;
use function strlen;

/**
 * Database builder (for creating the database required by the system).
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DbBuilder
{
    /**
     * Constructor
     *
     * @param ConnectionFactory $dbFactory Database connection factory
     *
     * @return void
     */
    public function __construct(protected ConnectionFactory $dbFactory)
    {
    }

    /**
     * Get SQL commands needed to set up a particular database before
     * loading the main SQL file of table definitions.
     *
     * @param string $driver      Database driver to use
     * @param string $newName     Name of database to create
     * @param string $vufindHost  Name of VuFind host (for use in creating users)
     * @param string $newUser     Username for connecting to new database (will be created)
     * @param string $escapedPass Password to set for new DB (escaped appropriately for target database).
     *
     * @return array
     */
    protected function getPreCommands(
        string $driver,
        string $newName,
        string $vufindHost,
        string $newUser,
        string $escapedPass
    ): array {
        $create = 'CREATE DATABASE ' . $newName;
        // Special case: PostgreSQL:
        if ($driver == 'pgsql') {
            $escape = 'ALTER DATABASE ' . $newName
                . " SET bytea_output='escape'";
            $cuser = 'CREATE USER ' . $newUser
                . " WITH PASSWORD {$escapedPass}";
            $grant = 'GRANT ALL PRIVILEGES ON DATABASE '
                . "{$newName} TO {$newUser}";
            return [$create, $escape, $cuser, $grant];
        }
        // Default: MySQL:
        $user = "CREATE USER '{$newUser}'@'{$vufindHost}' "
            . "IDENTIFIED BY {$escapedPass}";
        $grant = 'GRANT SELECT,INSERT,UPDATE,DELETE ON '
            . $newName
            . ".* TO '{$newUser}'@'{$vufindHost}' "
            . 'WITH GRANT OPTION';
        $use = "USE {$newName}";
        return [$create, $user, $grant, 'FLUSH PRIVILEGES', $use];
    }

    /**
     * Get SQL commands needed to set up a particular database after
     * loading the main SQL file of table definitions.
     *
     * @param string $driver  Database driver to use
     * @param string $newUser Username for connecting to new database (will be created)
     *
     * @return array
     */
    protected function getPostCommands(string $driver, string $newUser): array
    {
        // Special case: PostgreSQL:
        if ($driver == 'pgsql') {
            $grantTables = 'GRANT ALL PRIVILEGES ON ALL TABLES IN '
                . "SCHEMA public TO {$newUser}";
            $grantSequences = 'GRANT ALL PRIVILEGES ON ALL SEQUENCES'
                . " IN SCHEMA public TO {$newUser}";
            return [$grantTables, $grantSequences];
        }
        // Default: MySQL:
        return [];
    }

    /**
     * Load the main blob of SQL to initialize the database.
     *
     * @param string $driver Database driver to use
     *
     * @return string
     */
    protected function getMainSql(string $driver): string
    {
        // We use the same file to initialize the MariaDB and MySQL databases:
        $sqlFilename = $driver === 'mariadb' ? 'mysql' : $driver;
        return file_get_contents(
            APPLICATION_PATH . "/module/VuFind/sql/{$sqlFilename}.sql"
        );
    }

    /**
     * Get a database connection using the provided root credentials.
     *
     * @param string  $driver   Database driver to use
     * @param string  $dbHost   Name of database host
     * @param string  $rootUser Root username for connecting to database
     * @param string  $rootPass Root password for connecting to database
     * @param ?string $dbName   Database to connect to (null = default)
     *
     * @return Connection
     * @throws Exception
     */
    protected function getRootDatabaseConnection(
        string $driver,
        string $dbHost,
        string $rootUser,
        string $rootPass,
        ?string $dbName = null
    ): Connection {
        // We need a default database name to use to establish a connection:
        $dbName ??= ($driver == 'pgsql') ? 'template1' : 'mysql';
        return $this->dbFactory->getConnectionFromOptions(
            [
                'driver' => $this->dbFactory->getDriverName($driver),
                'host' => $dbHost,
                'user' => $rootUser,
                'password' => $rootPass,
                'dbname' => $dbName,
            ]
        );
    }

    /**
     * Build the database. Return the SQL used for the operation. Throw an exception on error.
     *
     * @param string   $newName       Name of database to create
     * @param string   $newUser       Username for connecting to new database (will be created)
     * @param string   $newPass       Password for new user
     * @param string   $driver        Database driver to use
     * @param string   $dbHost        Name of database host
     * @param string   $vufindHost    Name of VuFind host (for use in creating users)
     * @param string   $rootUser      Root username for connecting to database
     * @param string   $rootPass      Root password for connecting to database
     * @param bool     $returnSqlOnly Set to true to return SQL without actually manipulating the database
     * @param string[] $steps         Array of steps to run (legal values: pre, main, post); omit for all steps
     *
     * @return string
     * @throws Exception
     * @throws DBALException
     */
    public function build(
        string $newName,
        string $newUser,
        string $newPass,
        string $driver = 'mysql',
        string $dbHost = 'localhost',
        string $vufindHost = 'localhost',
        string $rootUser = 'root',
        string $rootPass = '',
        bool $returnSqlOnly = false,
        array $steps = []
    ): string {
        try {
            $db = $returnSqlOnly ? null : $this->getRootDatabaseConnection($driver, $dbHost, $rootUser, $rootPass);
        } catch (\Exception $e) {
            throw new \Exception(
                'Problem initializing database adapter; '
                . 'check for missing ' . $driver
                . ' library. Details: ' . $e->getMessage(),
                'error',
                $e
            );
        }

        // Invert the steps list into a list of steps we should skip (no skipping if empty list):
        $allSteps = ['pre', 'main', 'post'];
        $skip = $steps ? array_diff($allSteps, $steps) : [];

        // Get SQL together
        $escapedPass = $db
            ? $db->quote($newPass)
            : "'" . addslashes($newPass) . "'";
        $preCommands = in_array('pre', $skip)
            ? [] : $this->getPreCommands($driver, $newName, $vufindHost, $newUser, $escapedPass);
        $sql = in_array('main', $skip) ? '' : $this->getMainSql($driver);
        $postCommands = in_array('post', $skip)
            ? [] : $this->getPostCommands($driver, $newUser);
        $omnisql = '';
        foreach ($preCommands as $query) {
            $omnisql .= $query . ";\n";
            if ($db) {
                $db->executeQuery($query);
            }
        }
        if ($sql) {
            $omnisql .= "\n" . $sql . "\n";
            if ($db) {
                // If we're already connected to the database, we should reconnect now using the name of
                // the newly created database.
                $db = $this->getRootDatabaseConnection($driver, $dbHost, $rootUser, $rootPass, $newName);
                $statements = preg_split('/;\s*([\r\n]|$)/', $sql);
                foreach ($statements as $current) {
                    // Skip empty sections:
                    if (strlen(trim($current)) == 0) {
                        continue;
                    }
                    $db->executeQuery($current);
                }
            }
        }
        foreach ($postCommands as $query) {
            $omnisql .= $query . ";\n";
            if ($db) {
                $db->executeQuery($query);
            }
        }
        return $omnisql;
    }
}
