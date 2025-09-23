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

namespace VuFind\Db\Migration;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\TableNotFoundException;
use VuFind\Db\Connection;

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
     * @param Connection      $connection    A database connection (with read rights)
     * @param MigrationLoader $loader        Helper object to find/load migration files
     * @param string          $targetVersion The VuFind version we are migrating to
     *
     * @return void
     * @throws Exception
     */
    public function __construct(
        protected Connection $connection,
        protected MigrationLoader $loader,
        protected string $targetVersion
    ) {
        $this->migrationPath = $loader->getMigrationDirForPlatform(get_class($connection->getDatabasePlatform()));
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
     * Given a directory, retrieve a list of .sql migration files within it, filtered to
     * exclude migrations that are already applied.
     *
     * @param string $path Directory path
     *
     * @return string[]
     */
    protected function getNeededMigrationsFromDir(string $path): array
    {
        // We expect the last subdirectory of $path to be a version number; let's extract it:
        $parts = explode('/', $path);
        $version = array_pop($parts);
        $appliedMigrations = $this->getAppliedMigrations($version);
        return array_diff($this->loader->getMigrationsFromDir($path), $appliedMigrations);
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
     * Get a list of failed migrations.
     *
     * @return string[]
     */
    public function getFailedMigrations(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('name')
            ->from('migrations')
            ->where('status != ?')
            ->orderBy('id', 'ASC');
        try {
            $result = $this->connection->executeQuery($queryBuilder, ['success']);
        } catch (TableNotFoundException $e) {
            // If the migrations table doesn't exist yet, there can't be any failed migrations.
            return [];
        }
        $rows = $result->fetchAllAssociative();
        return array_unique(array_column($rows, 'name'));
    }

    /**
     * Given an old version, return a list of migrations that should be applied.
     *
     * @param string $oldVersion Version we're upgrading from
     *
     * @return string[]
     */
    public function getMigrations(string $oldVersion): array
    {
        $matches = [];
        $subDirectories = $this->loader->getMigrationSubdirectoriesMatchingVersion($oldVersion, $this->migrationPath);
        foreach ($subDirectories as $next) {
            $matches = array_merge($matches, $this->getNeededMigrationsFromDir($next));
        }
        natsort($matches);
        return array_values($matches);
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
        // Special case: if it's the migration to add the migration table and it hasn't succeeded yet, we can't
        // log information, so we should stop early.
        if ($name === '11.0/000-add-migrations-table.sql' && $status !== 'success') {
            return '';
        }
        $writeToDatabase = $connection !== null;
        $connection ??= $this->connection;
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->insert('migrations')
            ->values(
                [
                    'name' => $connection->quote($name),
                    'status' => $connection->quote($status),
                    'target_version' => $connection->quote($this->targetVersion),
                ]
            );
        $sql = (string)$queryBuilder;
        if ($writeToDatabase) {
            $connection->executeQuery($queryBuilder);
        }
        return "$sql;\n";
    }

    /**
     * After a migration has succeeded, clean up history related to the migration; we only want to retain
     * details about failed migrations so we can use them for troubleshooting (and as a flag in future).
     *
     * @param null|Connection $connection Database connection to use for applying migrations
     * (if null, the method returns the SQL to apply without actually writing to the database)
     * @param string          $name       Short name of migration being applied
     *
     * @return string
     * @throws Exception
     */
    protected function cleanUpMigrationEvents(?Connection $connection, string $name): string
    {
        $writeToDatabase = $connection !== null;
        $connection ??= $this->connection;
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->delete('migrations')
            ->where('name = ' . $connection->quote($name))
            ->andWhere('status != ' . $connection->quote('success'));
        $sql = (string)$queryBuilder;
        if ($writeToDatabase) {
            $connection->executeQuery($queryBuilder);
        }
        return "$sql;\n";
    }

    /**
     * Get a short migration name from a full migration path.
     *
     * @param string $migration Full migration file path
     *
     * @return string
     */
    public function getShortMigrationName(string $migration): string
    {
        return str_replace($this->migrationPath . '/', '', $migration);
    }

    /**
     * Apply a single database migration file.
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
        $shortMigrationName = $this->getShortMigrationName($migration);
        $output .= $this->logMigrationEvent($connection, $shortMigrationName, 'start');
        $sql = file_get_contents($migration);
        foreach ($this->loader->splitSqlIntoStatements($sql) as $i => $sqlChunk) {
            $output .= $this->logMigrationEvent($connection, $shortMigrationName, "writing chunk $i");
            if ($connection) {
                $connection->executeQuery($sqlChunk);
            }
            $output .= "$sqlChunk;\n";
        }
        $output .= $this->logMigrationEvent($connection, $shortMigrationName, 'success');
        $output .= $this->cleanUpMigrationEvents($connection, $shortMigrationName);
        return $output;
    }

    /**
     * Update the migration tracker to mark a migration as applied without actually applying
     * it (useful if a user has manually applied the migration and needs to catch up). Returns
     * the SQL used for the update.
     *
     * @param string      $migration  Migration file to apply
     * @param ?Connection $connection Database connection to use for applying migrations
     * (if null, the method returns the SQL to apply without actually writing to the database)
     *
     * @return string
     */
    public function markMigrationApplied(string $migration, ?Connection $connection): string
    {
        $shortMigrationName = $this->getShortMigrationName($migration);
        return $this->logMigrationEvent($connection, $shortMigrationName, 'success');
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
