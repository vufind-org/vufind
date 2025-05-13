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
     * @return void
     */
    public function setPath(string $path): void;

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
     * @return void
     */
    public function setBasePath(string $basePath): void;

    /**
     * Get the file name.
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * Set the file name
     *
     * @param string $fileName string
     *
     * @return void
     */
    public function setFileName(string $fileName): void;

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
     * @return void
     */
    public function setConfigName(string $configName): void;

    /**
     * Get the name of the configuration handler to be used for this location.
     *
     * @return string
     */
    public function getHandler(): string;
}
