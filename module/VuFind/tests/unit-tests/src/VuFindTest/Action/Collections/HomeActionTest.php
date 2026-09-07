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

use VuFind\Action\Collections\HomeAction;

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
}
