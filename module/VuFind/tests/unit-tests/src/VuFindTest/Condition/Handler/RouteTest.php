<?php

/**
 * Route handler test
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Condition\Handler;

use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Condition\Handler\Route;

/**
 * Route handler test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RouteTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get application mock.
     *
     * @return MockObject&Application
     */
    protected function getApplicationMock(): MockObject&Application
    {
        $routeMatchMock = $this->createMock(RouteMatch::class);
        $routeMatchMock->expects($this->once())->method('getMatchedRouteName')
            ->willReturn('test-route');

        $mvcEventMock = $this->createMock(MvcEvent::class);
        $mvcEventMock->expects($this->once())->method('getRouteMatch')
            ->willReturn($routeMatchMock);

        $applicationMock = $this->createMock(Application::class);
        $applicationMock->expects($this->once())->method('getMvcEvent')
            ->willReturn($mvcEventMock);

        return $applicationMock;
    }

    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        $routeHandler = new Route($this->getApplicationMock());
        $this->assertTrue($routeHandler->checkCondition([
            'type' => 'route',
            'comparator' => '=',
            'checkedValues' => 'test-route',
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $routeHandler = new Route($this->getApplicationMock());
        $this->assertFalse($routeHandler->checkCondition([
            'type' => 'route',
            'comparator' => '=',
            'checkedValues' => 'other-route',
        ]));
    }
}
