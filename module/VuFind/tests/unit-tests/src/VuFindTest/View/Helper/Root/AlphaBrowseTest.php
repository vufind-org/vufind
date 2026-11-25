<?php

/**
 * AlphaBrowse view helper Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Http\RouteHelper;
use VuFind\View\Helper\Root\AlphaBrowse;
use VuFind\View\Helper\Root\Url;

/**
 * AlphaBrowse view helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlphaBrowseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock RouteHelper.
     *
     * @param string $expectedQuery Expected query
     *
     * @return MockObject&RouteHelper
     */
    protected function getMockRouteHelper($expectedQuery): MockObject&RouteHelper
    {
        $mock = $this->createMock(RouteHelper::class);
        $mock->expects($this->once())->method('getUrlFromRoute')
            ->with(
                'search-results',
                [],
                $expectedQuery
            )->willReturn('foo');
        return $mock;
    }

    /**
     * Get configured AlphaBrowse helper for testing.
     *
     * @param RouteHelper $routeHelper Route helper
     * @param array       $options     Extra options
     *
     * @return AlphaBrowse
     */
    protected function getHelper(RouteHelper $routeHelper, array $options = []): AlphaBrowse
    {
        return new AlphaBrowse($routeHelper, $options);
    }

    /**
     * Test that get URL displays an appropriate link for multiple results with
     * default settings.
     *
     * @return void
     */
    public function testGetUrlWithMultipleRecordsAndDefaultSettings(): void
    {
        $routeHelper = $this->getMockRouteHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
                'dfApplied' => 1,
            ]
        );
        $helper = $this->getHelper($routeHelper);
        $item = ['heading' => 'xyzzy', 'sort_key' => 'xyzzy', 'count' => 2];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL displays an appropriate link for a single result with
     * default settings.
     *
     * @return void
     */
    public function testGetUrlWithSingleRecordAndDefaultSettings(): void
    {
        $routeHelper = $this->getMockRouteHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
                'dfApplied' => 1,
                'jumpto' => 1,
            ]
        );
        $helper = $this->getHelper($routeHelper);
        $item = ['heading' => 'xyzzy', 'sort_key' => 'xyzzy', 'count' => 1];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL properly escapes quotes in headings.
     *
     * @return void
     */
    public function testGetUrlEscapesQuotes(): void
    {
        $routeHelper = $this->getMockRouteHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"\\"xyzzy\\""',
                'dfApplied' => 1,
            ]
        );
        $helper = $this->getHelper($routeHelper);
        $item = ['heading' => '"xyzzy"', 'sort_key' => '"xyzzy"', 'count' => 100];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }

    /**
     * Test that get URL omits dfApplied when the bypass_default_filters option is
     * false.
     *
     * @return void
     */
    public function testGetUrlAppliesFilterBypassSetting(): void
    {
        $routeHelper = $this->getMockRouteHelper(
            [
                'type' => 'TitleBrowse',
                'lookfor' => '"xyzzy"',
            ]
        );
        $helper = $this->getHelper($routeHelper, ['bypass_default_filters' => false]);
        $item = ['heading' => 'xyzzy', 'sort_key' => 'xyzzy', 'count' => 100];
        $this->assertEquals('foo', $helper->getUrl('title', $item));
    }
}
