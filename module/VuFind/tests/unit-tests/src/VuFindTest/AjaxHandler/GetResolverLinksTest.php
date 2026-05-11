<?php

/**
 * GetResolverLinks test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

namespace VuFindTest\AjaxHandler;

use GuzzleHttp\Psr7\Response;
use VuFind\AjaxHandler\GetResolverLinks;
use VuFind\AjaxHandler\GetResolverLinksFactory;
use VuFind\Resolver\Driver\DriverInterface;
use VuFind\Resolver\Driver\PluginManager;
use VuFind\Session\Settings;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * GetResolverLinks test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetResolverLinksTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Set up configuration for a test.
     *
     * @param array $config Configuration to set.
     *
     * @return void
     */
    protected function setupConfig($config = []): void
    {
        $this->container->set(
            \VuFind\Config\ConfigManagerInterface::class,
            $this->getMockConfigManager(compact('config'))
        );
    }

    /**
     * Test the AJAX handler's basic response.
     *
     * @return void
     */
    public function testResponse(): void
    {
        // Set up session settings:
        $ss = $this->container->createMock(Settings::class, ['disableWrite']);
        $ss->expects($this->once())->method('disableWrite');
        $this->container->set(Settings::class, $ss);

        // Data to exercise all cases in the code:
        $fixtureData = [
            ['service_type' => 'getDOI'],
            ['service_type' => 'getHolding'],
            ['service_type' => 'getWebService'],
            ['service_type' => 'getFullTxt'],
            ['service_type' => 'getUnexpectedThing'],
        ];

        // Set up resolver plugin manager:
        $mockPlugin = $this->container->createMock(DriverInterface::class);
        $mockPlugin->expects($this->once())
            ->method('fetchLinks')->with('foo')
            ->willReturn('bar');
        $mockPlugin->expects($this->once())
            ->method('parseLinks')->with('bar')
            ->willReturn($fixtureData);
        $mockPlugin->expects($this->once())
            ->method('supportsMoreOptionsLink')
            ->willReturn(false);
        $rm = $this->container->createMock(PluginManager::class);
        $rm->expects($this->once())->method('has')->with('generic')->willReturn(true);
        $rm->expects($this->once())->method('get')->with('generic')->willReturn($mockPlugin);
        $this->container->set(PluginManager::class, $rm);

        $request = $this->getRequest(
            [
                'openurl' => 'foo',
                'searchClassId' => 'scl',
            ]
        );

        // Set up view helper and renderer:
        $renderer = $this->container->createMock(TemplateRendererInterface::class);
        $expectedTemplateParams = [
            'openUrlBase' => false,
            'openUrl' => 'foo',
            'print' => [
                [
                    'service_type' => 'getHolding',
                ],
            ],
            'electronic' => [
                [
                    'service_type' => 'getDOI',
                    'title' => 'Get full text',
                    'coverage' => '',
                ],
                [
                    'service_type' => 'getFullTxt',
                ],
                [
                    'service_type' => 'getUnexpectedThing',
                ],
            ],
            'services' => [
                [
                    'service_type' => 'getWebService',
                ],
            ],
            'searchClassId' => 'scl',
            'moreOptionsLink' => '',
        ];
        $renderer->expects($this->once())->method('renderTemplateAsString')
            ->with(
                $request,
                'ajax/resolverLinks.phtml',
                $expectedTemplateParams
            )->willReturn('html');
        $this->container->set(TemplateRendererInterface::class, $renderer);

        // Set up configuration:
        $this->setupConfig();

        // Build and test the ajax handler:
        $factory = new GetResolverLinksFactory();
        $handler = $factory($this->container, GetResolverLinks::class);
        $this->assertEquals(
            [
                [
                    'html' => 'html',
                ],
            ],
            $handler->handleRequest($request)
        );
    }
}
