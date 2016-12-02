<?php

/**
 * Route generator tests.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindConsoleTest\Route;
use VuFindConsole\Route\RouteGenerator;

/**
 * Route generator tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test route generation
     *
     * @return void
     */
    public function testGeneration()
    {
        $config = [];
        $routes = [
            'controller1/action1' => 'controller1 action1',
            'controller2/action2' => 'controller2 action2',
        ];
        $generator = new RouteGenerator();
        $generator->addRoutes($config, $routes);
        $expected = [
            'console' => [
                'router' => [
                    'routes' => [
                        'controller1-action1' => [
                            'options' => [
                                'route' => 'controller1 action1',
                                'defaults' => [
                                    'controller' => 'controller1',
                                    'action' => 'action1',
                                ],
                            ],
                        ],
                        'controller2-action2' => [
                            'options' => [
                                'route' => 'controller2 action2',
                                'defaults' => [
                                    'controller' => 'controller2',
                                    'action' => 'action2',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $config);
    }
}
