<?php

/**
 * RouteHelper Test Class
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Http;

use Closure;
use Laminas\View\Helper\Url as UrlHelper;
use VuFind\Http\RouteHelper;

/**
 * RouteHelper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RouteHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Shallowly test helper's getUrlFromRoute method
     *
     * @return void
     */
    public function testShallowGetUrlFromRoute(): void
    {
        $routeName = 'some_route';

        $urlHelper = $this->createMock(UrlHelper::class);
        $urlHelper->expects($this->once())->method('__invoke')->willReturn($routeName);
        $routeHelper = new RouteHelper(
            Closure::fromCallable($urlHelper)
        );

        $url = $routeHelper->getUrlFromRoute('', [], []);
        $this->assertSame($routeName, $url);
    }

    // /**
    //  * Data provider for testGetUrlFromRoute tests.
    //  *
    //  * @return \Iterator<string, array>
    //  */
    // public static function getUrlFromRouteProvider(): \Iterator
    // {
    //     yield 'Solr results' => [ 'search-results', [], ['query' => ['lookfor' => 'foo']], 'vufind/search/results' ];
    // }

    // /**
    //  * Test helper's getUrlFromRoute method
    //  *
    //  * @param string             $name         Name of the route
    //  * @param array              $linkParams   Parameters for the link
    //  * @param array|\Traversable $routeOptions Options for the route
    //  * @param string             $expected     Expected route URL
    //  *
    //  * @return void
    //  */
    // #[\PHPUnit\Framework\Attributes\DataProvider('getUrlFromRouteProvider')]
    // public function testGetUrlFromRoute(
    //     string $name,
    //     array $linkParams,
    //     array | \Traversable $routeOptions,
    //     string $expected
    // ): void {
    //     $routeHelper = new RouteHelper(
    //         Closure::fromCallable(new UrlHelper())
    //     );

    //     $url = $routeHelper->getUrlFromRoute($name, $linkParams, $routeOptions);
    //     $this->assertSame($expected, $url);
    // }
}
