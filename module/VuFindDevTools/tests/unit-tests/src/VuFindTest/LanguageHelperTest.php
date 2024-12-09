<?php

/**
 * Unit tests for language helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Controller;

use Laminas\I18n\Translator\TextDomain;
use VuFindDevTools\LanguageHelper;

/**
 * Unit tests for language helper.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LanguageHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test language mappings.
     *
     * @return void
     */
    public function testGetLangName()
    {
        $h = $this->getMockHelper();

        // config-driven case:
        $this->assertEquals('English', $h->getLangName('en'));

        // special cases:
        $this->assertEquals('British English', $h->getLangName('en-gb'));
        $this->assertEquals('Brazilian Portuguese', $h->getLangName('pt-br'));

        // unknown case:
        $this->assertEquals('??', $h->getLangName('??'));
    }

    /**
     * Test language comparison.
     *
     * @return void
     */
    public function testComparison()
    {
        $l1 = new TextDomain(['1' => 'one', '2' => 'two', '3' => 'three']);
        $l2 = new TextDomain(['2' => 'two', '4' => 'four']);
        $h = $this->getMockHelper();
        $expected = [
            'notInL1' => [4],
            'notInL2' => [1, 3],
            'l1Percent' => '150.00',
            'l2Percent' => '66.67',
        ];
        $this->assertEquals($expected, $h->compareLanguages($l1, $l2, $l1, $l2));
    }

    /**
     * Get a mock controller.
     *
     * @return Controller
     */
    protected function getMockHelper()
    {
        return new LanguageHelper(
            $this->createMock(\VuFind\I18n\Translator\Loader\ExtendedIni::class),
            ['en' => 'English']
        );
    }
}
