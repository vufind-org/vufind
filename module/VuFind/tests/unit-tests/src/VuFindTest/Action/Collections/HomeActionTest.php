<?php

/**
 * Collections HomeAction test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Action\Collections;

use VuFind\Action\AbstractAction;
use VuFind\Action\Collections\HomeAction;
use VuFind\I18n\Sorter;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results as SearchResults;
use VuFindSearch\Command\AlphabeticBrowseCommand;

/**
 * Collections HomeAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomeActionTest extends AbstractCollectionsActionTestCase
{
    /**
     * Test that the alphabetic browse formats browse strings into display text and exposes the initial-letter list.
     *
     * @return void
     */
    public function testAlphabeticBrowseFormatsResults(): void
    {
        $searchService = $this->getMockSearchService([
            'Browse' => [
                'totalCount' => 2,
                'offset' => 0,
                'startRow' => 1,
                'items' => [
                    ['heading' => 'Collection One{{{_ID_}}}coll1', 'count' => 5],
                    ['heading' => 'Collection Two{{{_ID_}}}coll2', 'count' => 3],
                ],
            ],
        ]);
        $action = $this->buildAction(
            HomeAction::class,
            ['Collections' => ['browseType' => 'Alphabetic']],
            $searchService
        );

        $this->invokeAction($action, ['from' => 'Col']);
        $params = $this->capturedTemplateParams;

        $this->assertSame('Col', $params['from']);
        $this->assertSame(
            [
                ['displayText' => 'Collection One', 'count' => 5, 'value' => 'coll1'],
                ['displayText' => 'Collection Two', 'count' => 3, 'value' => 'coll2'],
            ],
            $params['result']
        );

        $this->assertSame(array_merge(range('0', '9'), range('A', 'Z')), $params['letters']);

        $this->assertArrayNotHasKey('nextpage', $params);
        $this->assertArrayNotHasKey('prevpage', $params);
    }

    /**
     * Test that the alphabetic browse adds next/previous page links when the result set warrants them.
     *
     * @return void
     */
    public function testAlphabeticBrowseAddsPageLinks(): void
    {
        $searchService = $this->getMockSearchService([
            'Browse' => [
                'totalCount' => 30,
                'offset' => 0,
                'startRow' => 2,
                'items' => [],
            ],
        ]);
        $action = $this->buildAction(
            HomeAction::class,
            ['Collections' => ['browseType' => 'Alphabetic', 'browseLimit' => 20]],
            $searchService
        );

        $this->invokeAction($action, ['page' => 3]);
        $params = $this->capturedTemplateParams;

        $this->assertSame(4, $params['nextpage']);
        $this->assertSame(2, $params['prevpage']);
    }

    /**
     * Test that a configured browse delimiter is used when splitting browse strings.
     *
     * @return void
     */
    public function testAlphabeticBrowseUsesConfiguredDelimiter(): void
    {
        $searchService = $this->getMockSearchService([
            'Browse' => [
                'totalCount' => 1,
                'offset' => 0,
                'startRow' => 1,
                'items' => [
                    ['heading' => 'Collection One##coll1', 'count' => 2],
                ],
            ],
        ]);
        $action = $this->buildAction(
            HomeAction::class,
            ['Collections' => ['browseType' => 'Alphabetic', 'browseDelimiter' => '##']],
            $searchService
        );

        $this->invokeAction($action);
        $params = $this->capturedTemplateParams;

        $this->assertSame(
            [['displayText' => 'Collection One', 'count' => 2, 'value' => 'coll1']],
            $params['result']
        );
    }

    /**
     * Test that an empty first page of alphabetic results triggers a retry with the previous page.
     *
     * @return void
     */
    public function testAlphabeticBrowseStepsBackWhenPageEmpty(): void
    {
        $searchService = $this->getMockSearchService([
            'Browse' => ['totalCount' => 0, 'offset' => 0, 'startRow' => 0, 'items' => []],
        ]);
        $action = $this->buildAction(
            HomeAction::class,
            ['Collections' => ['browseType' => 'Alphabetic']],
            $searchService
        );

        $this->invokeAction($action);

        $command = $this->capturedCommand;
        $this->assertInstanceOf(AlphabeticBrowseCommand::class, $command);
        $this->assertSame(-1, $command->getPage());
        $this->assertSame([], $this->capturedTemplateParams['result']);
    }

    /**
     * Test that the index browse splits facet values on the delimiter and sorts them alphabetically.
     *
     * @return void
     */
    public function testIndexBrowseFormatsAndSortsFacets(): void
    {
        $searchResults = $this->getMockSearchResults([
            ['value' => 'Zebra Collection{{{_ID_}}}z1', 'count' => 1],
            ['value' => 'Apple Collection{{{_ID_}}}a1', 'count' => 2],
        ]);
        $action = $this->buildAction(
            HomeAction::class,
            [],
            $this->createStub(\VuFindSearch\Service::class),
            searchResults: $searchResults,
            sorter: new Sorter(new \Collator('en'))
        );

        $this->invokeAction($action);
        $params = $this->capturedTemplateParams;

        $this->assertSame(
            [
                ['value' => 'a1', 'count' => 2, 'displayText' => 'Apple Collection'],
                ['value' => 'z1', 'count' => 1, 'displayText' => 'Zebra Collection'],
            ],
            $params['result']
        );
    }

    /**
     * Test that index browse filters from the request are applied to the search and passed to the template.
     *
     * @return void
     */
    public function testIndexBrowseAppliesFilters(): void
    {
        $filterList = [['value' => 'Book', 'field' => 'format']];
        $params = $this->createMock(Params::class);
        $params->expects($this->once())->method('addFilter')->with('format:Book');
        $params->method('getFilterList')->willReturn($filterList);
        $searchResults = $this->createMock(SearchResults::class);
        $searchResults->method('getParams')->willReturn($params);
        $searchResults->method('getFullFieldFacets')->willReturn(
            ['hierarchy_browse' => ['data' => ['list' => []]]]
        );
        $action = $this->buildAction(
            HomeAction::class,
            [],
            $this->createStub(\VuFindSearch\Service::class),
            searchResults: $searchResults,
            sorter: new Sorter(new \Collator('en'))
        );

        $this->invokeAction($action, ['filter' => ['format:Book']]);

        $this->assertSame($filterList, $this->capturedTemplateParams['filters']);
    }

    /**
     * Test that index browse offsets the result window by page and adds next/previous page links when "from" is empty.
     *
     * @return void
     */
    public function testIndexBrowsePaginates(): void
    {
        $this->invokeAction($this->buildThreeItemIndexBrowseAction(), ['page' => 1]);
        $params = $this->capturedTemplateParams;

        $this->assertSame(2, $params['nextpage']);
        $this->assertSame(0, $params['prevpage']);
        $this->assertSame(
            [['value' => 'b', 'count' => 1, 'displayText' => 'Beta']],
            $params['result']
        );
    }

    /**
     * Build a three-item index browse action (browse limit 1).
     *
     * @return AbstractAction
     */
    protected function buildThreeItemIndexBrowseAction(): AbstractAction
    {
        $searchResults = $this->getMockSearchResults([
            ['value' => 'Alpha{{{_ID_}}}a', 'count' => 1],
            ['value' => 'Beta{{{_ID_}}}b', 'count' => 1],
            ['value' => 'Gamma{{{_ID_}}}g', 'count' => 1],
        ]);
        return $this->buildAction(
            HomeAction::class,
            ['Collections' => ['browseLimit' => 1]],
            $this->createStub(\VuFindSearch\Service::class),
            searchResults: $searchResults,
            sorter: new Sorter(new \Collator('en'))
        );
    }

    /**
     * Test that a negative page is clamped to the start of the result set.
     *
     * @return void
     */
    public function testIndexBrowseClampsNegativePage(): void
    {
        $this->invokeAction($this->buildThreeItemIndexBrowseAction(), ['page' => -1]);
        $params = $this->capturedTemplateParams;

        $this->assertSame(
            [['value' => 'a', 'count' => 1, 'displayText' => 'Alpha']],
            $params['result']
        );
        $this->assertSame(0, $params['nextpage']);
        $this->assertArrayNotHasKey('prevpage', $params);
    }

    /**
     * Test that a page beyond the end is clamped to the last item of the result set.
     *
     * @return void
     */
    public function testIndexBrowseClampsPageBeyondEnd(): void
    {
        $this->invokeAction($this->buildThreeItemIndexBrowseAction(), ['page' => 10]);
        $params = $this->capturedTemplateParams;

        $this->assertSame(
            [['value' => 'g', 'count' => 1, 'displayText' => 'Gamma']],
            $params['result']
        );
        $this->assertSame(9, $params['prevpage']);
        $this->assertArrayNotHasKey('nextpage', $params);
    }
}
