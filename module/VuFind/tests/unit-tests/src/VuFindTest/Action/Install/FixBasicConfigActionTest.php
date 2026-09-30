<?php

/**
 * Install FixBasicConfigAction test class.
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
use VuFind\Action\Install\FixBasicConfigAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;

/**
 * Install FixBasicConfigAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixBasicConfigActionTest extends AbstractInstallActionTestCase
{
    /**
     * Test that a successful config fix writes the site and Solr URLs and redirects to the install home page.
     *
     * @return void
     */
    public function testWritesConfigAndRedirectsHomeOnSuccess(): void
    {
        $captured = [];
        $routeHelper = $this->createMock(RouteHelper::class);
        $routeHelper->method('getUrlFromRoute')->with('home')->willReturn('/');
        $serverUrlHelper = $this->createMock(ServerUrlHelper::class);
        $serverUrlHelper->method('getUrlForPath')->willReturn('https://vufind.example.edu/');

        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->anything(), 'install-home')->willReturn($expectedResponse);

        $action = $this->buildActionMock(
            FixBasicConfigAction::class,
            ['installBasicConfig', 'getFixedSecurityConfiguration', 'getSolrUrlFromImportConfig', 'changeConfig'],
            ['System' => ['autoConfigure' => true]],
            [RedirectHelper::class => $redirectHelper],
            $routeHelper
        );
        $this->setProperty($action, 'serverUrlHelper', $serverUrlHelper);
        $action->method('installBasicConfig')->willReturn(true);
        $action->method('getFixedSecurityConfiguration')->willReturn([]);
        $action->method('getSolrUrlFromImportConfig')->willReturn('http://localhost:8983/solr');
        $action->method('changeConfig')->willReturnCallback(
            function (string $configName, array $config) use (&$captured): void {
                $captured = $config;
            }
        );

        $this->assertSame($expectedResponse, $action($this->getServerRequest(), new Response()));
        $this->assertSame('https://vufind.example.edu', $captured['Site']['url']);
        $this->assertSame('http://localhost:8983/solr', $captured['Index']['url']);
    }

    /**
     * Test that a failure to copy the base configuration renders the troubleshooting template with the error message.
     *
     * @return void
     */
    public function testRendersErrorWhenBasicConfigCannotBeInstalled(): void
    {
        $action = $this->buildActionMock(
            FixBasicConfigAction::class,
            ['installBasicConfig', 'getForcedLocalConfigPath'],
            ['System' => ['autoConfigure' => true]]
        );
        $action->method('installBasicConfig')->willReturn(false);
        $action->method('getForcedLocalConfigPath')->willReturn('/usr/local/vufind/local/config/vufind/config.ini');
        $action($this->getServerRequest(), new Response());

        $this->assertSame('Cannot copy file into position.', $this->capturedTemplateParams['errorMessage']);
        $this->assertArrayHasKey('configDir', $this->capturedTemplateParams);
        $this->assertArrayHasKey('runningUser', $this->capturedTemplateParams);
    }
}
