<?php

/**
 * Localization based number formatting.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Class NumberFormat
 * allows localization based formatting of numbers in view.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LocalizedNumber
{
    /**
     * Default decimal point character.
     *
     * @var string
     */
    protected $defaultDecimalPoint = '.';

    /**
     * Default thousands separator character.
     *
     * @var string
     */
    protected $defaultThousandSep = ',';

    /**
     * Constructor.
     *
     * @param Translate  $translate  Translate helper
     * @param EscapeHtml $escapeHtml EscapeHtml helper
     */
    public function __construct(
        #[Autowire(container: 'ViewHelperManager')]
        protected Translate $translate,
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtml,
    ) {
    }

    /**
     * Localize number.
     *
     * @param int|float $number     Number to format
     * @param int       $decimals   How many decimal places?
     * @param bool      $escapeHtml Should we escape the resulting text as HTML?
     *
     * @return string
     */
    public function __invoke($number, $decimals = 0, $escapeHtml = true): string
    {
        $decimalPoint = ($this->translate)(
            'number_decimal_point',
            [],
            $this->defaultDecimalPoint
        );
        $thousandSep = ($this->translate)(
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
            $formattedNumber = ($this->escapeHtml)($formattedNumber);
        }
        return $formattedNumber;
    }
}
