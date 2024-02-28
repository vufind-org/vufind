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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\GetResolverLinks;
use VuFind\AjaxHandler\GetResolverLinksFactory;
use VuFind\Resolver\Driver\DriverInterface;
use VuFind\Resolver\Driver\PluginManager;
use VuFind\Session\Settings;

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
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Set up configuration for a test.
     *
     * @param array $config Configuration to set.
     *
     * @return void
     */
    protected function setupConfig($config = [])
    {
        $this->container->set(
            \VuFind\Config\PluginManager::class,
            $this->getMockConfigPluginManager(compact('config'))
        );
    }

    /**
     * Test the AJAX handler's basic response.
     *
     * @return void
     */
    public function testResponse()
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
            ->method('fetchLinks')->with($this->equalTo('foo'))
            ->will($this->returnValue('bar'));
        $mockPlugin->expects($this->once())
            ->method('parseLinks')->with($this->equalTo('bar'))
            ->will($this->returnValue($fixtureData));
        $mockPlugin->expects($this->once())
            ->method('supportsMoreOptionsLink')
            ->will($this->returnValue(false));
        $rm = $this->container->createMock(PluginManager::class, ['get']);
        $rm->expects($this->once())->method('get')->with($this->equalTo('generic'))
            ->will($this->returnValue($mockPlugin));
        $this->container->set(PluginManager::class, $rm);

        // Set up view helper and renderer:
        $view = $this->container->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $expectedViewParams = [
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
        $view->expects($this->once())->method('render')
            ->with(
                $this->equalTo('ajax/resolverLinks.phtml'),
                $this->equalTo($expectedViewParams)
            )->will($this->returnValue('html'));
        $this->container->set('ViewRenderer', $view);

        // Set up configuration:
        $this->setupConfig();

        // Build and test the ajax handler:
        $factory = new GetResolverLinksFactory();
        $handler = $factory($this->container, GetResolverLinks::class);
        $params = $this->getParamsHelper(
            [
                'openurl' => 'foo',
                'searchClassId' => 'scl',
            ]
        );
        $this->assertEquals(
            [
                [
                    'html' => 'html',
                ],
            ],
            $handler->handleRequest($params)
        );
    }
}
