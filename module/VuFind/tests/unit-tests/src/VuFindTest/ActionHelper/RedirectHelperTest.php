<?php

/**
 * RedirectHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use Generator;
use InvalidArgumentException;
use Laminas\Diactoros\Response;
use Laminas\Router\AssembledUrl;
use Laminas\Router\RouteStackInterface;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Http\RequestHelper;
use VuFind\Http\RouteHelper;

/**
 * RedirectHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RedirectHelperTest extends TestCase
{
    /**
     * Test redirecting to an invalid route.
     *
     * @return void
     */
    public function testInvalidRoute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown route');
        $helper = new RedirectHelper($this->getRouteHelper([], []));
        $helper->redirectToRoute(new Response(), 'invalid-route', [], []);
    }

    /**
     * Data provider for testValidRoute().
     *
     * @return Generator<string, array>
     */
    public static function validRouteProvider(): Generator
    {
        yield 'no params' => [[], []];
        yield 'route params' => [['id' => 1], []];
        yield 'query params' => [[], ['foo' => 'bar']];
        yield 'route and query params' => [['id' => 1], ['foo' => 'bar']];
    }

    /**
     * Test redirecting to a URL.
     *
     * @return void
     */
    public function testRedirectToUrl(): void
    {
        $helper = new RedirectHelper($this->createMock(RouteHelper::class));
        $response = $helper->redirectToUrl(new Response(), 'http://localhost/somewhere');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['http://localhost/somewhere'], $response->getHeader('Location'));
    }

    /**
     * Test redirecting to a valid route.
     *
     * @param array $routeParams Route params
     * @param array $queryParams Query params
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validRouteProvider')]
    public function testValidRoute(array $routeParams, array $queryParams): void
    {
        $helper = new RedirectHelper($this->getRouteHelper($routeParams, $queryParams));
        $response = $helper->redirectToRoute(new Response(), 'some-route', $routeParams, $queryParams);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['/some/route'], $response->getHeader('Location'));
    }

    /**
     * Get RouteHelper with mock Url helper.
     *
     * @param array $expectedRouteParams Expected route parameters
     * @param array $expectedQueryParams Expected query parameters
     *
     * @return RouteHelper
     */
    protected function getRouteHelper(array $expectedRouteParams, array $expectedQueryParams): RouteHelper
    {
        $expectedOptions = [
            'normalize_path' => false,
            'name' => 'some-route',
        ];
        if ($expectedQueryParams) {
            $expectedOptions['query'] = $expectedQueryParams;
        }
        $router = $this->createMock(RouteStackInterface::class);
        $router->expects($this->once())
            ->method('assemble')
            ->willReturnCallback(
                function (array $params, array $options) use ($expectedRouteParams, $expectedOptions) {
                    if ('some-route' !== ($options['name'] ?? null)) {
                        throw new InvalidArgumentException('Unknown route');
                    }
                    $this->assertSame($expectedRouteParams, $params);
                    $this->assertSame($expectedOptions, $options);
                    return new AssembledUrl('/some/route');
                }
            );
        return new RouteHelper(new RequestHelper(), $router);
    }
}
