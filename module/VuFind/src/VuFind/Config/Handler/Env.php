<?php

/**
 * Environment variable config handler.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
use VuFind\Exception\ConfigException;

use function is_string;

/**
 * Environment variable config handler.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Env extends AbstractBase
{
    /**
     * Parses the configuration in a config location.
     *
     * @param ConfigLocationInterface $configLocation     Config location
     * @param bool                    $handleParentConfig If parent configuration should be handled
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function parseConfig(ConfigLocationInterface $configLocation, bool $handleParentConfig = true): array
    {
        $environmentVariable = trim(file_get_contents($configLocation->getPath()));
        return ['data' => $this->getEnvVar($environmentVariable)];
    }

    /**
     * Handle an include statement.
     *
     * @param string $includeSetting Settings of the include statement
     * @param string $basePath       Base path used for relative includes
     *
     * @return mixed
     */
    public function handleInclude(string $includeSetting, string $basePath): mixed
    {
        return $this->getEnvVar($includeSetting);
    }

    /**
     * Get environment variable or throw exception if it does not exist.
     *
     * @param string $name Environemnt variable name
     *
     * @return string
     */
    protected function getEnvVar(string $name): string
    {
        $config = getenv($name);
        if (!is_string($config)) {
            throw new ConfigException('Environment variable ' . $name . ' does not exist.');
        }
        return $config;
    }
}
