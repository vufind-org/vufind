<?php

/**
 * CurrencyFormatter Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Service;

use NumberFormatter;

/**
 * CurrencyFormatter Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CurrencyFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the class
     *
     * @return void
     */
    public function testFormatting()
    {
        // test default settings
        $locale = setlocale(LC_MONETARY, '');
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $defaultCurrencyCode = trim($formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE) ?: '') ?: 'USD';
        $cc = new \VuFind\Service\CurrencyFormatter();
        $this->assertEquals(
            $formatter->formatCurrency(3000, $defaultCurrencyCode),
            $cc->convertToDisplayFormat(3000)
        );
        $this->assertEquals(
            $formatter->formatCurrency(3000, 'EUR'),
            $cc->convertToDisplayFormat(3000, 'EUR')
        );

        // test overriding default currency
        $cc = new \VuFind\Service\CurrencyFormatter('EUR');
        $this->assertEquals(
            $formatter->formatCurrency(3000, 'EUR'),
            $cc->convertToDisplayFormat(3000)
        );
        $this->assertEquals(
            $formatter->formatCurrency(3000, 'USD'),
            $cc->convertToDisplayFormat(3000, 'USD')
        );

        // test overriding default locale
        $cc = new \VuFind\Service\CurrencyFormatter(null, 'de_DE');
        $this->assertEquals("3.000,00\u{a0}€", $cc->convertToDisplayFormat(3000));
        $this->assertEquals("3.000,00\u{a0}\$", $cc->convertToDisplayFormat(3000, 'USD'));

        // test overriding both
        $cc = new \VuFind\Service\CurrencyFormatter('AUD', 'de_DE');
        $this->assertEquals("3.000,00\u{a0}AU$", $cc->convertToDisplayFormat(3000));
        $this->assertEquals("3.000,00\u{a0}\$", $cc->convertToDisplayFormat(3000, 'USD'));
    }
}
