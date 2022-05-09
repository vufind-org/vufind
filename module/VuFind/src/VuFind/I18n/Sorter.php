<?php
declare(strict_types=1);

/**
 * Class Sorter
 *
 * PHP version 7
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
 * @package  VuFind\Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\I18n;

/**
 * Class Sorter
 *
 * @category VuFind
 * @package  VuFind\Service
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Sorter
{
    /**
     * Intl Collator
     *
     * @var \Collator
     */
    protected $collator;

    /**
     * Do respect current locale?
     *
     * @var bool
     */
    protected $respectLocale;

    /**
     * Constructor
     *
     * @param string $locale        Current user locale
     * @param bool   $respectLocale Do respect current locale?
     */
    public function __construct(string $locale = 'en', bool $respectLocale = false)
    {
        $this->collator = new \Collator($locale);
        $this->respectLocale = $respectLocale;
    }

    /**
     * Compare function
     *
     * @param string $string1 First string to compare
     * @param string $string2 Second string to compare
     *
     * @return int
     */
    public function compare(string $string1, string $string2): int
    {
        if ($this->respectLocale) {
            return $this->collator->compare($string1, $string2);
        }
        return strcasecmp($string1, $string2);
    }

    /**
     * Sort array by values
     *
     * @param array $array Array to sort
     *
     * @return bool
     */
    public function sort(array &$array): bool
    {
        if ($this->respectLocale) {
            return $this->collator->sort($array);
        }
        return sort($array);
    }

    /**
     * Sort array by values and maintain index association
     *
     * @param array $array Array to sort
     *
     * @return bool
     */
    public function asort(array &$array): bool
    {
        if ($this->respectLocale) {
            return $this->collator->asort($array);
        }
        return asort($array);
    }
}
