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

use Laminas\Router\AssembledUrl;
use Laminas\Router\RouteStackInterface;
use VuFind\Http\RequestHelper;
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
        $assembledRouteResult = new AssembledUrl($routeResult);

        $router = $this->createMock(RouteStackInterface::class);
        $router->expects($this->once())
            ->method('assemble')
            ->with($routeParams, ['name' => $routeName, 'query' => $queryParams, 'normalize_path' => false])
            ->willReturn($assembledRouteResult);
        $routeHelper = new RouteHelper(new RequestHelper(), $router);

        $url = $routeHelper->getUrlFromRoute($routeName, $routeParams, $queryParams);
        $this->assertSame($routeResult, $url);
    }
}
