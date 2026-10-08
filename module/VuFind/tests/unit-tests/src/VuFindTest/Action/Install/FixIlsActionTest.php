<?php

/**
 * Install FixIlsAction test class.
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

namespace VuFindTest\Action\Install;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\Install\FixIlsAction;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\PathResolver;

/**
 * Install FixIlsAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixIlsActionTest extends AbstractInstallActionTestCase
{
    /**
     * Test that selecting a new driver writes the configuration and redirects to the install home page.
     *
     * @return void
     */
    public function testSelectingNewDriverRedirectsHome(): void
    {
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'install-home')->willReturn($expectedResponse);

        $action = $this->buildAction(
            FixIlsAction::class,
            config: ['System' => ['autoConfigure' => true]],
            helpers: [RedirectHelper::class => $redirectHelper]
        );
        $request = $this->getServerRequest(parsedBody: ['driver' => 'Voyager']);
        $this->assertSame($expectedResponse, $action($request, new Response()));
    }

    /**
     * Test that a failure to write the new driver configuration forwards to FixBasicConfig action.
     *
     * @return void
     */
    public function testForwardsToFixBasicConfigWhenConfigWriteFails(): void
    {
        $config = ['System' => ['autoConfigure' => true]];
        $configManager = $this->getMockConfigManager(compact('config'));
        $configManager->method('writeConfig')->willThrowException(new \Exception('cannot write'));

        $expectedResponse = new Response();
        $forwardHelper = $this->createMock(ForwardHelper::class);
        $forwardHelper->expects($this->once())->method('forwardTo')
            ->with($this->anything(), $this->isInstanceOf(ResponseInterface::class), 'Install/FixBasicConfig')
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            FixIlsAction::class,
            [ConfigManagerInterface::class => $configManager],
            helpers: [ForwardHelper::class => $forwardHelper]
        );
        $request = $this->getServerRequest(parsedBody: ['driver' => 'Voyager']);
        $this->assertSame($expectedResponse, $action($request, new Response()));
    }

    /**
     * Test that a real configured driver renders the path to its configuration file.
     *
     * @return void
     */
    public function testRendersConfigPathForRealDriver(): void
    {
        $configPath = '/usr/local/vufind/local/config/vufind/NoILS.ini';
        $location = $this->createMock(ConfigLocationInterface::class);
        $location->method('getPath')->willReturn($configPath);
        $pathResolver = $this->createMock(PathResolver::class);
        $pathResolver->method('getForcedLocalConfigLocation')->willReturn($location);

        $action = $this->buildAction(
            FixIlsAction::class,
            [PathResolver::class => $pathResolver],
            ['System' => ['autoConfigure' => true], 'Catalog' => ['driver' => 'NoILS']]
        );
        $action($this->getServerRequest(), new Response());

        $this->assertSame($configPath, $this->capturedTemplateParams['configPath']);
        $this->assertArrayNotHasKey('demo', $this->capturedTemplateParams);
    }

    /**
     * Test that a sample/demo driver offers a list of available real drivers to switch to.
     *
     * @return void
     */
    public function testOffersDriverListForDemoDriver(): void
    {
        $action = $this->buildAction(
            FixIlsAction::class,
            config: ['System' => ['autoConfigure' => true], 'Catalog' => ['driver' => 'Sample']]
        );
        $action($this->getServerRequest(), new Response());

        $this->assertTrue($this->capturedTemplateParams['demo']);
        $drivers = $this->capturedTemplateParams['drivers'];
        $this->assertNotEmpty($drivers);
        $this->assertContains('Voyager', $drivers);
        $this->assertNotContains('Sample', $drivers);
        $this->assertNotContains('Demo', $drivers);
        $this->assertNotContains('DriverInterface', $drivers);
        $this->assertNotContains('PluginManager', $drivers);
        foreach ($drivers as $driver) {
            $this->assertStringStartsNotWith('Abstract', $driver);
            $this->assertStringEndsNotWith('Factory', $driver);
            $this->assertStringEndsNotWith('Trait', $driver);
        }
    }
}
