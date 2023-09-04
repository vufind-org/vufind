<?php

/**
 * Mink test class for blended search.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use function count;
use function intval;

/**
 * Mink test class for blended search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    0
 */
class BlendedSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get config settings for Blender.ini.
     *
     * @return array
     */
    protected function getBlenderIniOverrides()
    {
        return [
            'Backends' => [
                'Solr' => 'Items in Library',
                'SolrAuth' => 'Authors',
            ],
            'Blending' => [
                'initialResults' => [
                    'Solr',
                    'Solr',
                    'SolrAuth',
                    'SolrAuth',
                ],
                'blockSize' => 7,
            ],
            'Basic_Searches' => [
                'AllFields' => 'adv_search_all',
                'Title' => 'adv_search_title',
                'Author' => 'adv_search_author',
                'Subject' => 'adv_search_subject',
            ],
            'Advanced_Searches' => [
                'AllFields' => 'adv_search_all',
                'Title' => 'adv_search_title',
                'Author' => 'adv_search_author',
                'Subject' => 'adv_search_subject',
            ],
            'CheckboxFacets' => [
                'blender_backend:Solr' => 'Items in Library',
                'blender_backend:SolrAuth' => 'Authors',
            ],
            'General' => [
                'default_side_recommend[]'
                    => 'SideFacetsDeferred:Results:CheckboxFacets:Blender',
            ],
        ];
    }

    /**
     * Test disabled blended search
     *
     * @return void
     */
    public function testDisabledSearch()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Blender/Results');
        $page = $session->getPage();
        $this->assertEquals(
            'An error has occurred',
            $this->findCss($page, '.alert-danger p')->getText()
        );
    }

    /**
     * Data provider for testSearch
     *
     * @return array
     */
    public function getSearchData(): array
    {
        return [
            [
                ['page' => 1],
                $this->getExpectedLabels(1),
                'Blender/Results',
            ],
            [
                ['page' => 2],
                $this->getExpectedLabels(2),
                'Blender/Results',
            ],
            [
                ['page' => 1],
                $this->getExpectedLabels(1),
                'Search/Blended', // legacy path
            ],
            [
                ['page' => 2],
                $this->getExpectedLabels(2),
                'Search/Blended', // legacy path
            ],
        ];
    }

    /**
     * Test blended search
     *
     * @param array  $queryParams    Query parameters
     * @param array  $expectedLabels Expected labels
     * @param string $path           URL path
     *
     * @dataProvider getSearchData
     *
     * @return void
     */
    public function testSearch(array $queryParams, array $expectedLabels, string $path): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'SearchTabs' => [
                        'Solr' => 'Catalog',
                        'Blender' => 'Blended',
                    ],
                ],
                'Blender' => $this->getBlenderIniOverrides(),
            ],
            ['Blender']
        );

        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . "/$path?"
            . http_build_query($queryParams)
        );
        $page = $session->getPage();

        $text = $this->findCss($page, '.search-stats strong')->getText();
        [$start, $limit] = explode(' - ', $text);
        $offset = (($queryParams['page'] ?? 1) - 1) * 20;
        $this->assertEquals(1 + $offset, intval($start));
        $this->assertEquals(20 + $offset, intval($limit));

        for ($i = 0; $i < count($expectedLabels); $i++) {
            $this->assertEquals(
                $expectedLabels[$i],
                $this->findCss($page, '.result span.label-source', null, $i)
                    ->getText(),
                "Result index $i"
            );
        }

        // Go to record screen and check active tab:
        $this->clickCss($page, '#result0 .title');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Blended',
            $this->findCss($page, '.searchbox li.active')->getText()
        );
    }

    /**
     * Test checkbox filters
     *
     * @return void
     */
    public function testSearchCheckboxFilter(): void
    {
        $this->changeConfigs(
            [
                'Blender' => $this->getBlenderIniOverrides(),
            ],
            ['Blender']
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Blended');
        $page = $session->getPage();

        // Click first:
        $this->clickCss($page, 'a.checkbox-filter');
        $this->waitForPageLoad($page);
        foreach ($this->findCss($page, '.result span.label-source') as $label) {
            $this->assertEquals('Items in Library', $label->getText());
        }
        // Reset:
        $this->clickCss($page, 'a.checkbox-filter');
        $this->waitForPageLoad($page);
        // Click second:
        $this->clickCss($page, 'a.checkbox-filter', null, 1);
        $this->waitForPageLoad($page);
        $this->waitForPageLoad($page);
        foreach ($this->findCss($page, '.result span.label-source') as $label) {
            $this->assertEquals('Authors', $label->getText());
        }
    }

    /**
     * Test advanced search (and Blender as default)
     *
     * @return void
     */
    public function testAdvancedSearch(): void
    {
        $this->changeConfigs(
            [
                'Blender' => $this->getBlenderIniOverrides(),
                'config' => [
                    'Site' => [
                        'defaultModule' => 'Blender',
                    ],
                ],
            ],
            ['Blender']
        );

        // Go to start page and verify advanced search link:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->assertStringEndsWith(
            '/Blender/Advanced',
            $this->findCss($page, '.advanced-search-link')->getAttribute('href')
        );

        // Go to advanced search and do an empty search:
        $this->clickCss($page, '.advanced-search-link');
        $this->clickCss($page, '.adv-submit .btn-primary');

        $expectedLabels = $this->getExpectedLabels(1);
        for ($i = 0; $i < count($expectedLabels); $i++) {
            $this->assertEquals(
                $expectedLabels[$i],
                $this->findCss($page, '.result span.label-source', null, $i)
                    ->getText(),
                "Result index $i"
            );
        }

        $this->clickCss($page, '.adv_search_links a');
        $this->clickcss($page, '.add_search_link');

        // Add search terms:
        $this->findCssAndSetValue($page, '#search_lookfor0_0', 'Foo');
        $this->findCssAndSetValue($page, '#search_type0_0', 'Title');
        $this->findCssAndSetValue($page, '#search_lookfor0_1', 'Bar');
        $this->findCssAndSetValue($page, '#search_type0_1', 'Author');
        $this->clickCss($page, '.adv-submit .btn-primary');

        $this->assertEquals(
            'Your search - (Title:Foo AND Author:Bar) - did not match any resources.',
            $this->findCss($page, '.mainbody p')->getText()
        );
    }

    /**
     * Test disabled advanced search
     *
     * @return void
     */
    public function testDisabledAdvancedSearch(): void
    {
        $blenderConfig = $this->getBlenderIniOverrides();
        $blenderConfig['Advanced_Searches'] = [];
        $this->changeConfigs(
            [
                'Blender' => $blenderConfig,
                'config' => [
                    'Site' => [
                        'defaultModule' => 'Blender',
                    ],
                ],
            ],
            ['Blender']
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        $this->unFindCss($page, '.advanced-search-link');
    }

    /**
     * Get expected labels for the first result pages
     *
     * @param int $page Page (1 or 2)
     *
     * @return array
     */
    protected function getExpectedLabels(int $page): array
    {
        $expected = array_fill(0, 20, 'Items in Library');

        if (1 === $page) {
            $expected[2] = 'Authors';
            $expected[3] = 'Authors';
            $expected[7] = 'Authors';
        }
        return $expected;
    }
}
