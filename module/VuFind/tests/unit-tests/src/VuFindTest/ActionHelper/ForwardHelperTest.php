<?php

/**
 * ForwardHelper test class.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ActionConfigManager;
use VuFind\Action\ActionInterface;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Http\RouteHelper;
use VuFind\Session\Settings as SessionSettings;

/**
 * ForwardHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ForwardHelperTest extends TestCase
{
    /**
     * Test forwarding to an invalid action.
     *
     * @return void
     */
    public function testInvalidAction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown action 'foo'");
        $mockPluginManager = $this->createMock(\VuFind\Action\PluginManager::class);
        $mockPluginManager->expects($this->once())->method('has')->with('foo')->willReturn(false);
        $helper = new ForwardHelper($mockPluginManager, $this->createMock(ActionConfigManager::class));
        $helper->forwardTo(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(ResponseInterface::class),
            'foo'
        );
    }

    /**
     * Test forwarding to a valid action.
     *
     * @return void
     */
    public function testValidAction(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('withAttribute')->with('action-id', 'foo')->willReturn($request);
        $response = $this->createMock(ResponseInterface::class);
        $action = new class ($request, $response, $this) implements ActionInterface {
            /**
             * Constructor.
             *
             * @param ServerRequestInterface $expectedRequest  Expected request
             * @param ResponseInterface      $expectedResponse Expected response
             * @param ForwardHelperTest      $test             The test class (for access to assertions)
             */
            public function __construct(
                protected ServerRequestInterface $expectedRequest,
                protected ResponseInterface $expectedResponse,
                protected ForwardHelperTest $test
            ) {
            }

            /**
             * Set helper plugin manager.
             *
             * @param HelperPluginManager $helperPluginManager Helper plugin manager
             *
             * @return static
             */
            public function setHelperPluginManager(HelperPluginManager $helperPluginManager): static
            {
                throw new \Exception('Unexpected call to ' . __METHOD__);
            }

            /**
             * Set route helper.
             *
             * @param RouteHelper $routeHelper Route helper
             *
             * @return static
             */
            public function setRouteHelper(RouteHelper $routeHelper): static
            {
                throw new \Exception('Unexpected call to ' . __METHOD__);
            }

            /**
             * Set session settings.
             *
             * @param SessionSettings $sessionSettings Session settings
             *
             * @return static
             */
            public function setSessionSettings(SessionSettings $sessionSettings): static
            {
                throw new \Exception('Unexpected call to ' . __METHOD__);
            }

            /**
             * Test action handler.
             *
             * @param ServerRequestInterface $request  Request
             * @param ResponseInterface      $response Response
             *
             * @return ResponseInterface
             */
            public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
            {
                $this->test->assertSame($this->expectedRequest, $request);
                $this->test->assertSame($this->expectedResponse, $response);
                return $response;
            }
        };
        $mockPluginManager = $this->createMock(\VuFind\Action\PluginManager::class);
        $mockPluginManager->expects($this->once())->method('has')->with('foo')->willReturn(true);
        $mockPluginManager->expects($this->once())->method('get')->with('foo')->willReturn($action);
        $helper = new ForwardHelper($mockPluginManager, $this->createMock(ActionConfigManager::class));
        $this->assertEquals($response, $helper->forwardTo($request, $response, 'foo'));
    }
}
