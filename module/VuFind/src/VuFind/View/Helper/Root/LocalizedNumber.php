<?php

/**
 * Localization based number formatting
 *
 * PHP version 8
 *
 * Copyright (C) snowflake productions gmbh 2014.
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
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Class NumberFormat
 * allows localization based formatting of numbers in view
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LocalizedNumber extends AbstractHelper
{
    /**
     * Default decimal point character
     *
     * @var string
     */
    protected $defaultDecimalPoint = '.';

    /**
     * Default thousands separator character
     *
     * @var string
     */
    protected $defaultThousandSep = ',';

    /**
     * Localize number
     *
     * @param int|float $number     Number to format
     * @param int       $decimals   How many decimal places?
     * @param bool      $escapeHtml Should we escape the resulting text as HTML?
     *
     * @return string
     */
    public function __invoke($number, $decimals = 0, $escapeHtml = true)
    {
        $translator = $this->getView()->plugin('translate');

        $decimalPoint = $translator(
            'number_decimal_point',
            [],
            $this->defaultDecimalPoint
        );
        $thousandSep = $translator(
            'number_thousands_separator',
            [],
            $this->defaultThousandSep
        );
        $formattedNumber = number_format(
            $number,
            $decimals,
            $decimalPoint,
            $thousandSep
        );
        if ($escapeHtml) {
            $escaper = $this->getView()->plugin('escapeHtml');
            $formattedNumber = $escaper($formattedNumber);
        }

        return $formattedNumber;
    }
}
