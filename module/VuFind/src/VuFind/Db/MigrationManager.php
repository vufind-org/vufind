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
use Doctrine\DBAL\Exception\TableNotFoundException;

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
     * Base path for migration files.
     *
     * @var string
     */
    protected $migrationPath;

    /**
     * Constructor
     *
     * @param Connection $connection    A database connection (with read rights)
     * @param string     $targetVersion The VuFind version we are migrating to
     *
     * @return void
     * @throws Exception
     */
    public function __construct(protected Connection $connection, protected string $targetVersion)
    {
        $rawPlatform = strtolower(get_class($connection->getDatabasePlatform()));
        $platform = str_contains($rawPlatform, 'postgres') ? 'pgsql' : 'mysql';
        $this->migrationPath = APPLICATION_PATH . '/module/VuFind/sql/migrations/' . $platform;
    }

    /**
     * Get a list of successfully applied migrations for the provided version.
     *
     * @param string $version Version directory containing migrations
     *
     * @return string[]
     */
    protected function getAppliedMigrations(string $version): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('name')
            ->from('migrations')
            ->where('name like ?')
            ->andWhere('status = ?');
        try {
            $result = $this->connection->executeQuery($queryBuilder, ["$version/%", 'success'])->fetchAllAssociative();
        } catch (TableNotFoundException $e) {
            // If the migrations table doesn't exist, we haven't applied any migrations yet!
            return [];
        }
        return array_map(fn ($filename) => "{$this->migrationPath}/$filename", array_column($result, 'name'));
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
        $parts = explode('/', $path);
        $lastPart = array_pop($parts);
        $appliedMigrations = $this->getAppliedMigrations($lastPart);
        $migrations = glob("$path/*.sql");
        return array_diff($migrations, $appliedMigrations);
    }

    /**
     * Use the database to determine the most likely source version based on past migrations.
     *
     * @return string
     */
    public function determineOldVersion(): string
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('target_version')
            ->from('migrations')
            ->where('status = ?')
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);
        try {
            $result = $this->connection->executeQuery($queryBuilder, ['success']);
        } catch (TableNotFoundException $e) {
            // If the migrations table doesn't exist yet, we know we're on 10.x. We'll default to 10.0,
            // but it doesn't really make a difference since there were no migrations made during the
            // 10.x release line.
            return '10.0';
        }
        $row = $result->fetchAssociative();
        return $row['target_version'] ?? '10.0';
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
        $dir = opendir($this->migrationPath);
        // Make sure version number at least includes a ".0" on the end:
        if (!str_contains($oldVersion, '.')) {
            $oldVersion .= '.0';
        }
        while ($next = readdir($dir)) {
            if (preg_match('/^\d/', $next) && Comparator::greaterThanOrEqualTo($next, $oldVersion)) {
                $matches = array_merge($matches, $this->getMigrationsFromDir($this->migrationPath . '/' . $next));
            }
        }
        closedir($dir);
        natsort($matches);
        return $matches;
    }

    /**
     * Log a migration event to the migrations table (if connection provided). Return the SQL used to log the event.
     *
     * @param null|Connection $connection Database connection to use for applying migrations
     * (if null, the method returns the SQL to apply without actually writing to the database)
     * @param string          $name       Short name of migration being applied
     * @param string          $status     Status message
     *
     * @return string
     * @throws Exception
     */
    protected function logMigrationEvent(?Connection $connection, string $name, string $status): string
    {
        $queryBuilder = $connection ? $connection->createQueryBuilder() : $this->connection->createQueryBuilder();
        $queryBuilder->insert('migrations')
            ->values(
                [
                    'name' => $this->connection->quote($name),
                    'status' => $this->connection->quote($status),
                    'target_version' => $this->connection->quote($this->targetVersion),
                ]
            );
        $sql = (string)$queryBuilder;
        if ($connection) {
            $connection->executeQuery($queryBuilder);
        }
        return "$sql;\n";
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
        $shortMigrationName = str_replace($this->migrationPath . '/', '', $migration);
        if ($shortMigrationName !== '11.0/000-add-migrations-table.sql') {
            $output .= $this->logMigrationEvent($connection, $shortMigrationName, 'start');
        }
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
        $output .= $this->logMigrationEvent($connection, $shortMigrationName, 'success');
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
