<?php

/**
 * Currency formatter
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Service;

use NumberFormatter;

/**
 * Currency formatter
 *
 * @category VuFind
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CurrencyFormatter
{
    /**
     * Default currency format (ISO 4217) to use.
     *
     * @var string
     */
    protected $defaultCurrency;

    /**
     * Number formatter.
     *
     * @var NumberFormatter
     */
    protected $formatter;

    /**
     * Constructor
     *
     * @param string $defaultCurrency Default currency format (ISO 4217) to use (null
     * for default from system locale)
     * @param string $locale          Locale to use for number formatting (null for
     * default system locale)
     */
    public function __construct($defaultCurrency = null, $locale = null)
    {
        // Initialize number formatter:
        $locale ??= setlocale(LC_MONETARY, 0);
        $this->formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        // Initialize default currency:
        if (null === $defaultCurrency) {
            $defaultCurrency = trim($this->formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE) ?: '');
        }
        $this->defaultCurrency = empty($defaultCurrency) ? 'USD' : $defaultCurrency;
    }

    /**
     * Convert currency from float to display format
     *
     * @param float  $number   The number to format
     * @param string $currency Currency format (ISO 4217) to use (null for default)
     *
     * @return string
     */
    public function convertToDisplayFormat($number, $currency = null)
    {
        return $this->formatter->formatCurrency(
            (float)$number,
            $currency ?: $this->defaultCurrency
        );
    }
}
