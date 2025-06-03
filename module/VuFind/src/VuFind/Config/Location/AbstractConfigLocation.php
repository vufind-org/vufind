<?php

/**
 * Abstract config location
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

use function dirname;

/**
 * Abstract config location
 *
 * @category VuFind
 * @package  Config_Location
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractConfigLocation implements ConfigLocationInterface
{
    /**
     * Path to directory that contains the configuration.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Name of the file that contains the configuration.
     *
     * @var string
     */
    protected string $fileName;

    /**
     * Name of the configuration.
     *
     * @var string
     */
    protected string $configName;

    /**
     * Path to subsection of the configuration.
     *
     * @var array
     */
    protected array $subsection = [];

    /**
     * Constructor
     *
     * @param string  $path       Path to configuration
     * @param ?string $configName Optional configuration name (default is file name)
     */
    public function __construct(string $path, ?string $configName = null)
    {
        $this->setPath($path);
        if (null !== $configName) {
            $this->configName = $configName;
        } else {
            $this->configName = pathinfo($this->getFileName(), PATHINFO_FILENAME);
        }
    }

    /**
     * Get the complete path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $this->fileName;
    }

    /**
     * Set the complete path.
     *
     * @param string $path Path
     *
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->setBasePath(dirname($path));
        $this->setFileName(basename($path));
    }

    /**
     * Get the base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the base path.
     *
     * @param string $basePath Base path
     *
     * @return void
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    /**
     * Get the file name.
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Set the file name.
     *
     * @param string $fileName string
     *
     * @return void
     */
    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * Get the config name.
     *
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * Set the config name.
     *
     * @param string $configName Config name
     *
     * @return void
     */
    public function setConfigName(string $configName): void
    {
        $this->configName = $configName;
    }

    /**
     * Get subsection of the configuration.
     *
     * @return array
     */
    public function getSubsection(): array
    {
        return $this->subsection;
    }

    /**
     * Set subsection of the configuration.
     *
     * @param array $subsection Subsection
     *
     * @return void
     */
    public function setSubsection(array $subsection): void
    {
        $this->subsection = $subsection;
    }

    /**
     * Get the extension of the file.
     *
     * @return ?string
     */
    protected function getExtension(): ?string
    {
        $extension = pathinfo($this->getFileName(), PATHINFO_EXTENSION);
        return !empty($extension) ? $extension : null;
    }
}
