<?php

/**
 * Interface SorterInterface
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @package  I18n
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\I18n;

/**
 * Interface SorterInterface
 *
 * @category VuFind
 * @package  I18n
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface SorterInterface
{
    /**
     * Compare function
     *
     * @param string $string1 First string to compare
     * @param string $string2 Second string to compare
     *
     * @return int
     */
    public function compare(string $string1, string $string2): int;

    /**
     * Sort array by values
     *
     * @param array $array Array to sort
     *
     * @return bool
     */
    public function sort(array &$array): bool;

    /**
     * Sort array by values and maintain index association
     *
     * @param array $array Array to sort
     *
     * @return bool
     */
    public function asort(array &$array): bool;

    /**
     * Natural sort by values and maintain index association
     *
     * @param array $array Array to sort
     *
     * @return bool
     */
    public function natsort(array &$array): bool;
}
