<?php

/**
 * Mink test class for alphabetic browse.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink test class for alphabetic browse.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class AlphabrowseTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testTitleSearchNormalization
     *
     * @return array
     */
    public function titleSearchNormalizationProvider(): array
    {
        return [
            'bracket stripping' => ['[arithmetic facts]', 'Arithmetic Facts'],
            'multi-bracket stripping' => ['[[[[[arithmetic facts]]]]]', 'Arithmetic Facts'],
            'accent stripping' => ['arithmétic facts', 'Arithmetic Facts'],
            'punctuation collapsing' => ['arithmetic facts /:/:', 'Arithmetic Facts'],
            'whitespace collapsing' => ['arithmetic      facts', 'Arithmetic Facts'],
        ];
    }

    /**
     * Test that appropriate normalization is applied to title searches.
     *
     * @param string $query              Alphabrowse query to perform
     * @param string $expectedFirstTitle Expected first title in result list
     *
     * @return void
     *
     * @dataProvider titleSearchNormalizationProvider
     */
    public function testTitleSearchNormalization($query, $expectedFirstTitle): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Alphabrowse/Home');
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#alphaBrowseForm_source', 'title');
        $this->findCssAndSetValue($page, '#alphaBrowseForm_from', $query);
        $this->clickCss($page, '#alphaBrowseForm .btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $expectedFirstTitle,
            $this->findCss($page, 'table.alphabrowse td.title')->getText()
        );
    }

    /**
     * Test that extra attributes are escaped correctly.
     *
     * @return void
     */
    public function testExtraAttributeEscaping(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Alphabrowse/Home?source=lcc&from=PS3552.R878+T47+2011');
        $page = $session->getPage();
        $extras = $this->findCss($page, 'table.alphabrowse td.lcc ~ td');
        $text = $extras->getText();
        $this->assertStringContainsString('<HTML> The Basics', $text);
    }
}
