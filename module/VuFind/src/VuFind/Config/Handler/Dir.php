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
     * @param ConfigManager $configManager Config Manager
     */
    public function __construct(
        protected ConfigManager $configManager,
    ) {
    }

    /**
     * Parses the configuration in a config location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return array
     */
    public function parseConfig(ConfigLocationInterface $configLocation): array
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
            $location = $this->getMatchingConfigLocation($path, $configName);
            if ($subsection !== null) {
                $location->setSubsection($subsection);
            }
            $config[$configName] = $this->configManager->loadConfigFromLocation($location);
        } else {
            foreach ($this->getConfigLocationsInPath($path) as $location) {
                $config[$location->getConfigName()] = $this->configManager->loadConfigFromLocation($location);
            }
        }

        foreach (array_reverse($dirSubsection) as $subsectionPart) {
            $config = [$subsectionPart => $config];
        }
        return ['data' => $config];
    }
}
