<?php

/**
 * Trait providing support for converting delimited settings to arrays
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config\Feature;

/**
 * Trait providing support for converting delimited settings to arrays
 *
 * @category VuFind
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ExplodeSettingTrait
{
    /**
     * Explode a delimited setting to an array
     *
     * @param string $value     Setting value
     * @param bool   $trim      Whether to trim the values (disabled by default to
     * ensure any valid blank entry does not get trimmed, and to avoid doing extra
     * work on each execution)
     * @param string $separator Separator
     *
     * @return array
     */
    protected function explodeSetting(
        string $value,
        $trim = false,
        string $separator = ':'
    ): array {
        if ('' === $value) {
            return [];
        }
        $result = explode($separator, $value);
        if ($trim) {
            $result = array_map('trim', $result);
        }
        return $result;
    }

    /**
     * Explode a comma-delimited setting to an array of trimmed values
     *
     * @param string $value Setting value
     *
     * @return array
     */
    protected function explodeListSetting(string $value): array
    {
        return $this->explodeSetting($value, true, ',');
    }
}
