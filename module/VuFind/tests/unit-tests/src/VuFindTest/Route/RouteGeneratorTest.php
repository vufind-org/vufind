<?php

/**
 * Route Generator Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Route;

use VuFind\Route\RouteGenerator;

/**
 * Route Generator Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RouteGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test addDynamicRoutes()
     *
     * @return void
     */
    public function testAddDynamicRoutes(): void
    {
        $generator = new RouteGenerator();
        $routes = [
            'Controller1' => ['route1' => 'foo/:dynamic'],
            'Controller2' => ['route2' => 'bar/:dynamic'],
        ];
        $config = [];
        $generator->addDynamicRoutes($config, $routes);
        $expected = [
            'route1' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/foo/:dynamic',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'foo',
                    ],
                ],
            ],
            'route2' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/bar/:dynamic',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'bar',
                    ],
                ],
            ],
        ];
        $this->assertEquals(
            ['router' => ['routes' => $expected]],
            $config
        );
    }

    /**
     * Test addStaticRoutes()
     *
     * @return void
     */
    public function testAddStaticRoutes(): void
    {
        $generator = new RouteGenerator();
        $config = [];
        $generator->addStaticRoutes($config, ['foo/bar', 'Baz/Xyzzy']);
        $expected = [
            'foo-bar' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route' => '/foo/bar',
                    'defaults' => [
                        'controller' => 'foo',
                        'action' => 'bar',
                    ],
                ],
            ],
            'baz-xyzzy' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route' => '/Baz/Xyzzy',
                    'defaults' => [
                        'controller' => 'Baz',
                        'action' => 'Xyzzy',
                    ],
                ],
            ],
        ];
        $this->assertEquals(
            ['router' => ['routes' => $expected]],
            $config
        );
    }

    /**
     * Test addRecordRoutes()
     *
     * @return void
     */
    public function testAddRecordRoutes(): void
    {
        $generator = new RouteGenerator();
        $config = [];
        $routeConfig = ['route1' => 'Controller1', 'route2' => 'Controller2'];
        $generator->addRecordRoutes($config, $routeConfig);
        $generator->addNonTabRecordActions($config, ['NonTabAction']);

        $expected = [
            'route1' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/[:id[/[:tab]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'tab' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'Home',
                    ],
                ],
            ],
            'route1-nontabaction' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/[:id]/NonTabAction',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'NonTabAction',
                    ],
                ],
            ],
            'route2' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/[:id[/[:tab]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'tab' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'Home',
                    ],
                ],
            ],
            'route2-nontabaction' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/[:id]/NonTabAction',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'NonTabAction',
                    ],
                ],
            ],
        ];

        $this->assertEquals(
            ['router' => ['routes' => $expected]],
            $config
        );
    }

    /**
     * Test addRecordRoutes() using a subclass
     *
     * @return void
     */
    public function testAddRecordRoutesWithSubclass(): void
    {
        $generator = new RouteGenerator();
        $config = [];
        $routeConfig = ['route1' => 'Controller1', 'route2' => 'Controller2'];
        $generator->addRecordRoutes($config, $routeConfig);
        $generator->addNonTabRecordActions($config, ['NonTabAction']);
        $extendedGenerator = new class () extends RouteGenerator {
        };
        $extendedGenerator->addNonTabRecordActions(
            $config,
            ['NonTabActionExtended']
        );

        $expected = [
            'route1' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/[:id[/[:tab]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'tab' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'Home',
                    ],
                ],
            ],
            'route1-nontabaction' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/[:id]/NonTabAction',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'NonTabAction',
                    ],
                ],
            ],
            'route1-nontabactionextended' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller1/[:id]/NonTabActionExtended',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller1',
                        'action' => 'NonTabActionExtended',
                    ],
                ],
            ],
            'route2' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/[:id[/[:tab]]]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'tab' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'Home',
                    ],
                ],
            ],
            'route2-nontabaction' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/[:id]/NonTabAction',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'NonTabAction',
                    ],
                ],
            ],
            'route2-nontabactionextended' => [
                'type' => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route' => '/Controller2/[:id]/NonTabActionExtended',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Controller2',
                        'action' => 'NonTabActionExtended',
                    ],
                ],
            ],
        ];

        $this->assertEquals(
            ['router' => ['routes' => $expected]],
            $config
        );
    }
}
