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
     * Locale (for restoration after testing)
     *
     * @var string
     */
    protected $locale;

    /**
     * Standard setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        // store current default and set a value for consistency in testing
        $this->locale = setlocale(LC_MONETARY, 0);
        $locales = ['en_US.UTF8', 'en_US.UTF-8', 'en_US'];
        if (false === setlocale(LC_MONETARY, $locales)) {
            $this->markTestSkipped('Problem setting up locale');
        }
    }

    /**
     * Standard teardown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        // restore current default
        setlocale(LC_MONETARY, $this->locale);
    }

    /**
     * Test the class
     *
     * @return void
     */
    public function testFormatting()
    {
        // test default settings
        $cc = new \VuFind\Service\CurrencyFormatter();
        $this->assertEquals('$3,000.00', $cc->convertToDisplayFormat(3000));
        $this->assertEquals('€3,000.00', $cc->convertToDisplayFormat(3000, 'EUR'));

        // test override default currency
        $cc = new \VuFind\Service\CurrencyFormatter('EUR');
        $this->assertEquals('€3,000.00', $cc->convertToDisplayFormat(3000));
        $this->assertEquals('$3,000.00', $cc->convertToDisplayFormat(3000, 'USD'));

        // test override default locale
        $cc = new \VuFind\Service\CurrencyFormatter(null, 'de_DE');
        $this->assertEquals("3.000,00\u{a0}€", $cc->convertToDisplayFormat(3000));
        $this->assertEquals("3.000,00\u{a0}\$", $cc->convertToDisplayFormat(3000, 'USD'));
    }
}
