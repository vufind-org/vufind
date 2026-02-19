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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * @param string    $value               Setting value
     * @param ?callable $formatValueCallback Optional callback to format values
     * @param string    $separator           Separator
     *
     * @return array
     */
    protected function explodeSetting(
        string $value,
        ?callable $formatValueCallback = null,
        string $separator = ':',
    ): array {
        if ('' === $value) {
            return [];
        }
        $result = explode($separator, $value);
        if ($formatValueCallback) {
            $result = array_map($formatValueCallback, $result);
        }
        return $result;
    }

    /**
     * Explode a comma-delimited setting to an array of trimmed values
     *
     * @param string    $value               Setting value
     * @param ?callable $formatValueCallback Optional callback to format values
     *
     * @return array
     */
    protected function explodeListSetting(string $value, ?callable $formatValueCallback = null): array
    {
        $formatValueCallback = function ($value) use ($formatValueCallback) {
            $value = trim($value);
            if ($formatValueCallback !== null) {
                $value = $formatValueCallback($value);
            }
            return $value;
        };
        return $this->explodeSetting($value, $formatValueCallback, ',');
    }
}
