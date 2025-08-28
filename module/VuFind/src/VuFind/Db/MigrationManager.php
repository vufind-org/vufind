<?php

/**
 * Database migration manager.
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

use Composer\Semver\Comparator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

use function get_class;

/**
 * Database migration manager.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MigrationManager
{
    /**
     * Active database platform.
     *
     * @var string
     */
    protected $platform;

    /**
     * Constructor
     *
     * @param Connection $connection A database connection (with read rights)
     *
     * @return void
     * @throws Exception
     */
    public function __construct(protected Connection $connection)
    {
        $rawPlatform = strtolower(get_class($connection->getDatabasePlatform()));
        $this->platform = str_contains($rawPlatform, 'postgres') ? 'pgsql' : 'mysql';
    }

    /**
     * Given a directory, retrieve a list of .sql migration files within it.
     *
     * @param string $path Directory path
     *
     * @return string[]
     */
    protected function getMigrationsFromDir(string $path): array
    {
        $migrations = [];
        $dir = opendir($path);
        while ($next = readdir($dir)) {
            if (str_ends_with($next, '.sql')) {
                $migrations[] = "$path/$next";
            }
        }
        closedir($dir);
        return $migrations;
    }

    /**
     * Given a database platform and an old version, return a list of migrations that should be applied.
     *
     * @param string $oldVersion Version we're upgrading from
     *
     * @return string[]
     */
    public function getMigrations(string $oldVersion): array
    {
        $matches = [];
        $migrationPath = APPLICATION_PATH . '/module/VuFind/sql/migrations/' . $this->platform;
        $dir = opendir($migrationPath);
        // Make sure version number at least includes a ".0" on the end:
        if (!str_contains($oldVersion, '.')) {
            $oldVersion .= '.0';
        }
        while ($next = readdir($dir)) {
            if (preg_match('/^\d/', $next) && Comparator::greaterThan($next, $oldVersion)) {
                $matches = array_merge($matches, $this->getMigrationsFromDir($migrationPath . '/' . $next));
            }
        }
        closedir($dir);
        natsort($matches);
        return $matches;
    }

    /**
     * Apply a single database migration string.
     *
     * @param string      $migration  Migration file to apply
     * @param ?Connection $connection Database connection to use for applying migrations
     * (if null, the method returns the SQL to apply without actually writing to the database)
     *
     * @return string Processed migration SQL
     */
    public function applyMigration(string $migration, ?Connection $connection): string
    {
        $output = '';
        $sql = file_get_contents($migration);
        foreach (explode(';', $sql) as $sqlLine) {
            $trimmedLine = trim($sqlLine);
            if (!empty($trimmedLine)) {
                if ($connection) {
                    $connection->executeQuery($trimmedLine);
                }
                $output .= "$trimmedLine;\n";
            }
        }
        return $output;
    }

    /**
     * Apply a batch of database migrations.
     *
     * @param string[]    $migrations Migration files to apply
     * @param ?Connection $connection Database connection to use for applying migrations
     * (if null, the method returns the SQL to apply without actually writing to the database)
     *
     * @return string Combined migration SQL
     */
    public function applyMigrations(array $migrations, ?Connection $connection): string
    {
        $output = '';
        foreach ($migrations as $migration) {
            $output .= $this->applyMigration($migration, $connection);
        }
        return $output;
    }
}
