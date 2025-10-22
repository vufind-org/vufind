<?php

/**
 * Abstract config handler base class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Handler;

use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\PathResolver;
use VuFind\Exception\ConfigException;
use VuFind\Exception\FileAccess as FileAccessException;

use function get_class;

/**
 * Abstract config handler base class.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractBase implements HandlerInterface
{
    /**
     * Constructor
     *
     * @param PathResolver $pathResolver Path Resolver
     */
    public function __construct(
        protected PathResolver $pathResolver,
    ) {
    }

    /**
     * Write configuration to a specific location.
     *
     * @param ConfigLocationInterface  $destinationLocation Destination location for the config
     * @param array|string             $config              Config to write
     * @param ?ConfigLocationInterface $baseLocation        Location of a base configuration that can provide additional
     * structure (e.g. comments)
     *
     * @return void
     */
    public function writeConfig(
        ConfigLocationInterface $destinationLocation,
        array|string $config,
        ?ConfigLocationInterface $baseLocation
    ): void {
        throw new ConfigException('Writing is not supported by handler: ' . get_class($this));
    }

    /**
     * Create a backup of a file.
     *
     * @param string $file Path to file
     *
     * @return void
     *
     * @throws FileAccessException
     */
    protected function backupFile(string $file): void
    {
        $backupFile = $file . '.bak.' . time();
        if (file_exists($file) && !copy($file, $backupFile)) {
            throw new FileAccessException(
                "Error: Could not copy {$file} to {$backupFile}."
            );
        }
    }

    /**
     * Create a new config location object on a path based on another config location.
     *
     * @param ConfigLocationInterface $configLocation Original config location
     * @param string                  $path           New config location path
     *
     * @return ConfigLocationInterface
     *
     * @throws FileAccessException
     */
    protected function getParentLocationOnPath(
        ConfigLocationInterface $configLocation,
        string $path
    ): ConfigLocationInterface {
        $parentLocation = $this->pathResolver->getConfigLocationOnPath($path);
        if ($parentLocation === null) {
            throw new FileAccessException("Error: $path does not exist.");
        }
        $parentLocation->setConfigName($configLocation->getConfigName())
            // parent locations on a different path should still refer to the same
            // parent configuration directory
            ->setDirLocationsParent($configLocation->getDirLocationsParent());
        return $parentLocation;
    }
}
