<?php

/**
 * Interface for config handler classes
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

use VuFind\Config\Location\ConfigLocationInterface;

/**
 * Interface for config handler classes
 *
 * This interface class is the definition of the required methods for
 * loading configuration.
 *
 * The parameters are of no major concern as you can define the purpose of the
 * parameters for each method for whatever purpose your driver needs.
 * The most important element here is what the method will return. All methods
 * may throw exceptions in case of errors.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface HandlerInterface
{
    /**
     * Parses the configuration in a config location.
     *
     * Returns an associative array.
     * Must contain the configuration as an array under the key 'data'.
     * May contain the following keys:
     * - parentLocation (Config location of the parent config)
     * - mergeCallback (A callback that specifies how the parent config should be merged)
     *
     * @param ConfigLocationInterface $configLocation     Config location
     * @param bool                    $handleParentConfig If parent configuration should be handled
     *
     * @return array
     */
    public function parseConfig(ConfigLocationInterface $configLocation, bool $handleParentConfig = true): array;

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
    ): void;
}
