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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Handler;

use VuFind\Config\ConfigManager;
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
     * Constructor
     *
     * @param PathResolver  $pathResolver  Path Resolver
     * @param ConfigManager $configManager Config Manager
     */
    public function __construct(
        PathResolver $pathResolver,
        protected ConfigManager $configManager,
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

        $subsection = $configLocation->getSubsection();
        $dirSubsection = [];
        while (!empty($subsection) && is_dir($path . DIRECTORY_SEPARATOR . $subsection[0])) {
            $subsectionPart = array_shift($subsection);
            $dirSubsection[] = $subsectionPart;
            $path = $path . DIRECTORY_SEPARATOR . $subsectionPart;
        }

        $config = [];
        if (!empty($subsection)) {
            $configName = array_shift($subsection);
            $location = $this->pathResolver->getMatchingConfigLocation($path, $configName);
            if ($subsection !== null) {
                $location->setSubsection($subsection);
            }
            $config[$configName] = $this->configManager->loadConfigFromLocation($location, $handleParentConfig);
        } else {
            foreach ($this->pathResolver->getConfigLocationsInPath($path) as $location) {
                $config[$location->getConfigName()] = $this->configManager
                    ->loadConfigFromLocation($location, $handleParentConfig);
            }
        }

        foreach (array_reverse($dirSubsection) as $subsectionPart) {
            $config = [$subsectionPart => $config];
        }
        return ['data' => $config];
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
