<?php

/**
 * Dir config handler.
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

use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\PathResolver;
use VuFind\Exception\ConfigException;
use VuFind\Exception\FileAccess as FileAccessException;

use function is_array;

/**
 * Dir config handler.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Dir extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param PathResolver           $pathResolver  Path Resolver
     * @param ConfigManagerInterface $configManager Config Manager
     */
    public function __construct(
        PathResolver $pathResolver,
        protected ConfigManagerInterface $configManager,
    ) {
        parent::__construct($pathResolver);
    }

    /**
     * Parses the configuration in a config location.
     *
     * @param ConfigLocationInterface $configLocation     Config location
     * @param bool                    $handleParentConfig If parent configuration should be handled
     *
     * @return array
     */
    public function parseConfig(ConfigLocationInterface $configLocation, bool $handleParentConfig = true): array
    {
        $path = $configLocation->getPath();
        $subsectionPath = '';

        $subsection = $configLocation->getSubsection();
        $dirSubsection = [];
        while (!empty($subsection) && is_dir($path . $subsectionPath . DIRECTORY_SEPARATOR . $subsection[0])) {
            $subsectionPart = array_shift($subsection);
            $dirSubsection[] = $subsectionPart;
            $subsectionPath = $subsectionPath . DIRECTORY_SEPARATOR . $subsectionPart;
        }

        $data = [];
        if (!empty($subsection)) {
            $configName = array_shift($subsection);
            $subsectionLocation = $this->pathResolver->getMatchingConfigLocation($path . $subsectionPath, $configName);
            if ($subsectionLocation !== null) {
                $subsectionLocation->setSubsection($subsection);
                $this->setSubsectionLocationParent($subsectionLocation, $configLocation, $subsectionPath);
                $data[$configName] = $this->configManager->loadConfigFromLocation(
                    $subsectionLocation,
                    $handleParentConfig
                );
            }
        } else {
            foreach ($this->pathResolver->getConfigLocationsInPath($path . $subsectionPath) as $subsectionLocation) {
                $this->setSubsectionLocationParent($subsectionLocation, $configLocation, $subsectionPath);
                $data[$subsectionLocation->getConfigName()] = $this->configManager
                    ->loadConfigFromLocation($subsectionLocation, $handleParentConfig);
            }
        }

        foreach (array_reverse($dirSubsection) as $subsectionPart) {
            $data = [$subsectionPart => $data];
        }

        $config = ['data' => $data];

        if ($handleParentConfig && $parentLocation = $this->getParentLocation($configLocation)) {
            $config['parentLocation'] = $parentLocation;
            $config['mergeCallback'] = function ($parentConfig, $childConfig) use ($dirSubsection) {
                foreach ($dirSubsection as $subsectionPart) {
                    $parentConfig = $parentConfig[$subsectionPart] ?? [];
                    $childConfig = $childConfig[$subsectionPart] ?? [];
                }
                $result = array_merge($parentConfig, $childConfig);
                foreach (array_reverse($dirSubsection) as $subsectionPart) {
                    $result = [$subsectionPart => $result];
                }
                return $result;
            };
        }

        return $config;
    }

    /**
     * Set parent location on a subsection location based on the parent of the
     * base config location and the subsection's path.
     *
     * @param ConfigLocationInterface $subsectionLocation Subsection config location
     * @param ConfigLocationInterface $baseConfigLocation Base config location
     * @param string                  $subsectionPath     Path of the subsection
     *
     * @return void
     */
    protected function setSubsectionLocationParent(
        ConfigLocationInterface $subsectionLocation,
        ConfigLocationInterface $baseConfigLocation,
        string $subsectionPath
    ): void {
        if ($parentLocation = $this->getParentLocation($baseConfigLocation)) {
            $parentSubsectionPath = $parentLocation->getPath() . $subsectionPath;
            $subsectionLocationParent = $this->pathResolver->getMatchingConfigLocation(
                $parentSubsectionPath,
                $subsectionLocation->getConfigName()
            );
            if ($subsectionLocationParent === null) {
                $this->setSubsectionLocationParent($subsectionLocation, $parentLocation, $subsectionPath);
            } else {
                $subsectionLocationParent->setSubsection($subsectionLocation->getSubsection());
                $this->setSubsectionLocationParent($subsectionLocationParent, $parentLocation, $subsectionPath);
                $subsectionLocation->setDirLocationsParent($subsectionLocationParent);
            }
        }
    }

    /**
     * Get parent location for current location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return ?ConfigLocationInterface
     */
    protected function getParentLocation(ConfigLocationInterface $configLocation): ?ConfigLocationInterface
    {
        if ($dirLocationsParent = $configLocation->getDirLocationsParent()) {
            return $dirLocationsParent;
        }
        $baseLocation = $this->pathResolver->getBaseConfigLocation($configLocation->getConfigName());
        if ($baseLocation !== null && realpath($baseLocation->getPath()) !== realpath($configLocation->getPath())) {
            return $baseLocation->setSubsection($configLocation->getSubsection());
        }
        return null;
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
        if (!is_array($config)) {
            throw new ConfigException('Dir handler can only write array config.');
        }
        if ($baseLocation === null) {
            throw new ConfigException('Can not write Dir config without a base config that provides the structure.');
        }
        $destinationPath = $destinationLocation->getPath();
        if (!is_dir($destinationPath)) {
            if (!mkdir($destinationPath)) {
                throw new FileAccessException(
                    "Error: Could not create directory {$destinationPath}."
                );
            }
        }
        foreach ($config as $subConfigName => $subConfigValues) {
            $subBaseConfigLocation = $this->pathResolver
                ->getMatchingConfigLocation($baseLocation->getPath(), $subConfigName);
            if ($subBaseConfigLocation === null) {
                throw new ConfigException(
                    'Can not add config ' . $subConfigName
                    . ' without having it in the base config that provides the structure.'
                );
            }

            $subDestinationLocation = clone $subBaseConfigLocation;
            $subDestinationLocation->setBasePath($destinationPath);

            $this->configManager->writeConfig($subDestinationLocation, $subConfigValues, $subBaseConfigLocation);
        }
    }
}
