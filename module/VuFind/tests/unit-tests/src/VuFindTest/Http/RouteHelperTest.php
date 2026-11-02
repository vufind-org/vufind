<?php

/**
 * RouteHelper Test Class.
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
 * RouteHelper Test Class.
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
     * Shallowly test helper's getUrlFromRoute method.
     *
     * @return void
     */
    public function testShallowGetUrlFromRoute(): void
    {
        $routeName = 'some-route';
        $routeParams = ['record' => 1];
        $queryParams = ['foo' => 'bar'];
        $routeResult = '/some/route/1?foo=bar';

        $urlHelper = $this->createMock(UrlHelper::class);
        $urlHelper->expects($this->once())
            ->method('__invoke')
            ->with($routeName, $routeParams, ['query' => $queryParams, 'normalize_path' => false])
            ->willReturn($routeResult);
        $routeHelper = new RouteHelper(
            Closure::fromCallable(fn () => $urlHelper)
        );

        $url = $routeHelper->getUrlFromRoute($routeName, $routeParams, $queryParams);
        $this->assertSame($routeResult, $url);
    }
}
