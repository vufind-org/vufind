<?php

/**
 * Interface for configuration locations
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config_Location
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Location;

/**
 * Interface for configuration locations
 *
 * This is a representation for the location of a configuration. It is based on a file or directory
 * on the system.
 *
 * E.g.
 * "/usr/local/vufind/local/config/vufind/config.ini"
 * is the path for a file configuration or
 * "/usr/local/vufind/local/config/vufind/RecordDataFormatter"
 * is the path for a directory configuration.
 *
 * The file name is the name of the file or directory that contains the configuration
 * (e.g. "config.ini" or "RecordDataFormatter", respectively)
 *
 * The base path is the path without the filename
 * (e.g. "/usr/local/vufind/local/config/vufind/" in both cases)
 *
 * The configuration name is the name for the configuration used internally. Most of the time it will
 * be the file name without extension ("config" or "RecordDataFormatter", respectively) but it can also be
 * something different. E.g. when myParent.ini is the parent of config.ini it will still have the config
 * name "config" even if that is not part of the file name.
 *
 * A specified subsection of the configuration can be used to optimize the loading process if only that
 * section is required.
 *
 * @category VuFind
 * @package  Config_Location
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface ConfigLocationInterface
{
    /**
     * Get the complete path.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Set the complete path.
     *
     * @param string $path Path
     *
     * @return static
     */
    public function setPath(string $path): static;

    /**
     * Get the base path.
     *
     * @return string
     */
    public function getBasePath(): string;

    /**
     * Set the base path.
     *
     * @param string $basePath Base path
     *
     * @return static
     */
    public function setBasePath(string $basePath): static;

    /**
     * Get the file name.
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * Set the file name.
     *
     * @param string $fileName string
     *
     * @return static
     */
    public function setFileName(string $fileName): static;

    /**
     * Get the config name.
     *
     * @return string
     */
    public function getConfigName(): string;

    /**
     * Set the config name.
     *
     * @param string $configName Config name
     *
     * @return static
     */
    public function setConfigName(string $configName): static;

    /**
     * Get subsection of the configuration.
     *
     * @return array
     */
    public function getSubsection(): array;

    /**
     * Set subsection of the configuration.
     *
     * @param array $subsection Subsection
     *
     * @return static
     */
    public function setSubsection(array $subsection): static;

    /**
     * Get the location of the configuration in the parent directory that might be specified in DirLocations.ini.
     *
     * @return ?ConfigLocationInterface
     */
    public function getDirLocationsParent(): ?ConfigLocationInterface;

    /**
     * Set the location of the configuration in the parent directory that might be specified in DirLocations.ini.
     *
     * @param ?ConfigLocationInterface $dirLocationsParent Parent location
     *
     * @return static
     */
    public function setDirLocationsParent(?ConfigLocationInterface $dirLocationsParent): static;

    /**
     * Get the name of the configuration handler to be used for this location.
     *
     * @return string
     */
    public function getHandler(): string;

    /**
     * Get cache key.
     *
     * @return string
     */
    public function getCacheKey(): string;
}
