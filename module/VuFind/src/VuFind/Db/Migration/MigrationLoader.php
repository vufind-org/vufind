<?php

/**
 * Database migration loader (contains file system operations that support MigrationManager).
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Migration;

use Composer\Semver\Comparator;

/**
 * Database migration loader (contains file system operations that support MigrationManager).
 *
 * @category VuFind
 * @package  Db
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MigrationLoader
{
    /**
     * Get the migration directory for a specific database platform.
     *
     * @param string $platform Database platform name (can be a Doctrine driver class or a config.ini setting)
     *
     * @return string
     */
    public function getMigrationDirForPlatform(string $platform): string
    {
        $normalizedPlatform = strtolower(trim($platform));
        $isPostgres = str_contains($normalizedPlatform, 'postgres') || str_contains($normalizedPlatform, 'pgsql');
        $platformDir = $isPostgres ? 'pgsql' : 'mysql';
        return APPLICATION_PATH . '/module/VuFind/sql/migrations/' . $platformDir;
    }

    /**
     * Return an array of migration files in the specified path.
     *
     * @param string $path Path to search for migrations.
     *
     * @return string[]
     */
    public function getMigrationsFromDir(string $path): array
    {
        return glob("$path/*.sql");
    }

    /**
     * Give an array of migration subdirectories appropriate for the requested old version.
     *
     * @param string $oldVersion Version we are migrating from
     * @param string $basePath   Migration file base path
     *
     * @return string[]
     */
    public function getMigrationSubdirectoriesMatchingVersion(string $oldVersion, string $basePath): array
    {
        // Make sure version number at least includes a ".0" on the end:
        if (!str_contains($oldVersion, '.')) {
            $oldVersion .= '.0';
        }

        // Find version-appropriate subdirectories in the migration directory:
        return array_values(
            array_filter(
                glob("$basePath/*"),
                function ($path) use ($oldVersion) {
                    $parts = explode('/', $path);
                    $version = array_pop($parts);
                    return preg_match('/^\d/', $version) && Comparator::greaterThanOrEqualTo($version, $oldVersion);
                }
            )
        );
    }

    /**
     * Given a block of SQL code, split it into an array of distinct statements.
     *
     * @param string $sql SQL to split
     *
     * @return string[]
     */
    public function splitSqlIntoStatements(string $sql): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/;\s*([\r\n]|$)/', $sql))));
    }
}
