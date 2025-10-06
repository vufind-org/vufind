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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * Location of the configuration in the parent directory that might be specified in DirLocations.ini.
     *
     * @var ?ConfigLocationInterface
     */
    protected ?ConfigLocationInterface $dirLocationsParent = null;

    /**
     * Constructor
     *
     * @param string  $path       Path to configuration
     * @param ?string $configName Optional configuration name (default is file name)
     */
    public function __construct(string $path, ?string $configName = null)
    {
        $this->setPath($path);
        $this->configName = $configName ?? $this->getDefaultConfigName();
    }

    /**
     * Get default config name.
     *
     * @return string
     */
    protected function getDefaultConfigName(): string
    {
        return $this->getFileName();
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
     * @return static
     */
    public function setPath(string $path): static
    {
        $this->setBasePath(dirname($path))
            ->setFileName(basename($path));
        return $this;
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
     * @return static
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = $basePath;
        return $this;
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
     * @return static
     */
    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
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
     * @return static
     */
    public function setConfigName(string $configName): static
    {
        $this->configName = $configName;
        return $this;
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
     * @return static
     */
    public function setSubsection(array $subsection): static
    {
        $this->subsection = $subsection;
        return $this;
    }

    /**
     * Get the location of the configuration in the parent directory that might be specified in DirLocations.ini.
     *
     * @return ?ConfigLocationInterface
     */
    public function getDirLocationsParent(): ?ConfigLocationInterface
    {
        return $this->dirLocationsParent;
    }

    /**
     * Set the location of the configuration in the parent directory that might be specified in DirLocations.ini.
     *
     * @param ?ConfigLocationInterface $dirLocationsParent Parent location
     *
     * @return static
     */
    public function setDirLocationsParent(?ConfigLocationInterface $dirLocationsParent): static
    {
        $this->dirLocationsParent = $dirLocationsParent;
        return $this;
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

    /**
     * Get cache key.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        $path = $this->getPath();
        if ($realPath = realpath($path)) {
            $path = $realPath;
        }
        return $path . '_' . implode('_', $this->getSubsection());
    }
}
