<?php

/**
 * Safe money format view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\EscapeHtml;
use VuFind\Service\CurrencyFormatter;

/**
 * Safe money format view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SafeMoneyFormat extends AbstractHelper
{
    /**
     * CurrencyFormatter
     *
     * @var CurrencyFormatter
     */
    protected $currencyFormatter;

    /**
     * Escape helper
     *
     * @var EscapeHtml
     */
    protected $escapeHtml;

    /**
     * Constructor
     *
     * @param CurrencyFormatter $currencyFormatter Currency formatter
     * @param EscapeHtml        $escapeHtml        Escaper
     */
    public function __construct(
        CurrencyFormatter $currencyFormatter,
        EscapeHtml $escapeHtml
    ) {
        $this->currencyFormatter = $currencyFormatter;
        $this->escapeHtml = $escapeHtml;
    }

    /**
     * Convert currency to display format and escape the result
     *
     * @param float  $number   The number to format
     * @param string $currency Currency format (ISO 4217) to use (null for default)
     *
     * @return string
     */
    public function __invoke($number, $currency = null)
    {
        $result = ($this->escapeHtml)(
            $this->currencyFormatter->convertToDisplayFormat($number, $currency)
        );
        return $result;
    }
}
