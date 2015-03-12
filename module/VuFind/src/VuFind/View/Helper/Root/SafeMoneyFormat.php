<?php
/**
 * Safe money format view helper
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use NumberFormatter, Zend\View\Helper\AbstractHelper;

/**
 * Safe money format view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SafeMoneyFormat extends AbstractHelper
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
     * @var NumberFormatter;
     */
    protected $formatter;

    /**
     * Constructor
     *
     * @param string $defaultCurrency Default currency format (ISO 4217) to use (null
     * for default from locale)
     */
    public function __construct($defaultCurrency = null)
    {
        // Initialize number formatter:
        $locale = setlocale(LC_MONETARY, 0);
        $this->formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        // Initialize default currency:
        if (null === $defaultCurrency) {
            $localeInfo = localeconv();
            $defaultCurrency = isset($localeInfo['int_curr_symbol'])
                ? trim($localeInfo['int_curr_symbol']) : '';
        }
        $this->defaultCurrency = empty($defaultCurrency) ? 'USD' : $defaultCurrency;
    }

    /**
     * Currency-rendering logic.
     *
     * @param float  $number   The number to format
     * @param string $currency Currency format (ISO 4217) to use (null for default)
     *
     * @return string
     */
    public function __invoke($number, $currency = null)
    {
        if (null === $currency) {
            $currency = $this->defaultCurrency;
        }
        $escaper = $this->getView()->plugin('escapeHtml');
        // Workaround for a problem in ICU library < 4.9 causing formatCurrency to
        // fail if locale has comma as a decimal separator.
        // (see https://bugs.php.net/bug.php?id=54538)
        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, ['en_us.UTF-8', 'en_us.UTF8', 'en_us']);
        $result = $escaper(
            $this->formatter->formatCurrency((float)$number, $currency)
        );
        setlocale(LC_NUMERIC, $locale);
        return $result;
    }
}
