<?php

/**
 * Configuration manager
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config;

use VuFind\Config\Handler\HandlerInterface;
use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Feature\MergeRecursiveTrait;

use function in_array;

/**
 * Configuration manager
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ConfigManager
{
    use MergeRecursiveTrait;

    /**
     * Constructor
     *
     * @param HandlerPluginManager $configHandlerManager Config handler plugin manager
     * @param PathResolver         $pathResolver         Path resolver
     */
    public function __construct(
        protected HandlerPluginManager $configHandlerManager,
        protected PathResolver $pathResolver
    ) {
    }

    /**
     * Load config from a specific location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return array
     */
    public function loadConfig(ConfigLocationInterface $configLocation): array
    {
        $loadedConfigPaths = [];

        $configs = [];

        $currentConfigLocation = $configLocation;

        do {
            // check if config was already loaded to avoid infinite loop
            $currentConfigLocationPath = realpath($currentConfigLocation->getPath());
            if (!$currentConfigLocationPath) {
                throw new \Exception('Current path does not exist: ' . $currentConfigLocationPath);
            }
            if (in_array($currentConfigLocationPath, $loadedConfigPaths)) {
                throw new \Exception('Current path was already loaded: ' . $currentConfigLocation->getPath());
            }
            $loadedConfigPaths[] = $currentConfigLocationPath;

            $currentConfig = $this->getHandlerForLocation($currentConfigLocation)->parseConfig($currentConfigLocation);
            $configs[] = $currentConfig;
            $currentConfigLocation = null;
            if ($parentLocation = $currentConfig['parentLocation'] ?? null) {
                $currentConfigLocation = $parentLocation;
            }
        } while ($currentConfigLocation);

        $result = [];
        foreach (array_reverse($configs) as $config) {
            $data = $config['data'];
            $mergeFunction = $config['mergeCallback'] ?? [$this, 'mergeRecursive'];
            $result = $mergeFunction($result, $data);
        }
        return $result;
    }

    /**
     * Get the configuration handler for a specific location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return ?HandlerInterface
     */
    public function getHandlerForLocation(ConfigLocationInterface $configLocation): ?HandlerInterface
    {
        $handlerName = $configLocation->getHandler();
        return $this->configHandlerManager->has($handlerName) ? $this->configHandlerManager->get($handlerName) : null;
    }
}
