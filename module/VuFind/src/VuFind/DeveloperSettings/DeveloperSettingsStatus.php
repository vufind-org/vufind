<?php

/**
 * Developer settings status enum
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Developer_Settings
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\DeveloperSettings;

use function in_array;

/**
 * Developer settings status enum
 *
 * @category VuFind
 * @package  Developer_Settings
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
enum DeveloperSettingsStatus: string
{
    case DISABLED = 'disabled';
    case OPTIONAL = 'optional';
    case ENFORCED = 'enforced';

    /**
     * Helper method to check if given setting value from config is considered being enabled.
     *
     * @param string $setting Setting value usually obtained from a configuration file.
     *
     * @return bool
     */
    public static function settingEnabled(string $setting): bool
    {
        return in_array(
            self::tryFrom($setting),
            [
                self::OPTIONAL,
                self::ENFORCED,
            ]
        );
    }
}
