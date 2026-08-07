<?php

/**
 * VuFind YAML Configuration Reader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

/**
 * VuFind YAML Configuration Reader.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class YamlReader
{
    /**
     * Constructor.
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(
        protected ConfigManagerInterface $configManager,
    ) {
    }

    /**
     * Return a configuration.
     *
     * @param string $filename       Config file name
     * @param bool   $useLocalConfig Use local configuration if available
     * @param bool   $forceReload    Reload even if config has been internally cached in the class.
     *
     * @return array
     */
    public function get($filename, $useLocalConfig = true, $forceReload = false)
    {
        return $this->configManager->getConfigArray(
            $filename,
            forceReload: $forceReload,
            useLocalConfig: $useLocalConfig
        );
    }
}
