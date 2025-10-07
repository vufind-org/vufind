<?php

/**
 * Mink web search test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use function intval;

/**
 * Mink web search test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class WebSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Data provider for testWebSearch()
     *
     * @return array[]
     */
    public static function webSearchProvider(): array
    {
        return [
            'blank search' => ['', 3, '', ['Fact', 'Fantasy', 'Fiction']],
            'search in full text' => ['"second record"', 1, 'second record', ['Fact']],
            'search in description' => ['three', 1, 'three', ['Fantasy']],
        ];
    }

    /**
     * Test performing a Web search
     *
     * @param string   $query                  Search query
     * @param int      $expectedCount          Expected search result count
     * @param string   $expectedFirstHighlight Expected first highlighted text on page
     * @param string[] $expectedSubjectFacets  Expected subject facet values
     *
     * @return void
     *
     * @dataProvider webSearchProvider
     */
    public function testWebSearch(
        string $query,
        int $expectedCount,
        string $expectedFirstHighlight,
        array $expectedSubjectFacets
    ): void {
        // Perform the search:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Web');
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#searchForm_lookfor', $query);
        $this->findCss($page, '.btn-primary')->click();
        $this->waitForPageLoad($page);

        // Confirm the result count:
        $this->assertEquals(
            $expectedCount,
            intval($this->findCssAndGetText($page, '.js-search-stats strong', index: 1))
        );

        // Confirm highlighting:
        if ($expectedFirstHighlight) {
            $this->assertEquals($expectedFirstHighlight, $this->findCssAndGetText($page, '#result0 mark'));
        }

        // Confirm facet values:
        $subjectText = [];
        $subjects = $page->findAll('css', '#side-panel-subject .facet-value');
        foreach ($subjects as $subject) {
            $subjectText[] = $subject->getText();
        }
        $this->assertEquals($expectedSubjectFacets, $subjectText);
    }
}
