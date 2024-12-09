<?php

/**
 * Route Generator Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Route
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Route;

/**
 * Route Generator Class
 *
 * The data model object representing a user's book cart.
 *
 * @category VuFind
 * @package  Route
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RouteGenerator
{
    /**
     * Record sub-routes are generally used to access tab plug-ins, but a few
     * URLs are hard-coded to specific actions; this array lists those actions.
     *
     * @var array
     */
    protected static $nonTabRecordActions = [];

    /**
     * Cache for already added recordActions which need to be used again
     * if additional nonTabRecordActions will be added later.
     *
     * @var array
     */
    protected static $recordRoutes = [];

    /**
     * Add a dynamic route to the configuration.
     *
     * @param array  $config     Configuration array to update
     * @param string $routeName  Name of route to generate
     * @param string $controller Controller name
     * @param string $action     Action and any dynamic parts
     *
     * @return void
     */
    public function addDynamicRoute(&$config, $routeName, $controller, $action)
    {
        [$actionName] = explode('/', $action, 2);
        $config['router']['routes'][$routeName] = [
            'type'    => 'Laminas\Router\Http\Segment',
            'options' => [
                'route'    => "/$controller/$action",
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $actionName,
                ],
            ],
        ];
    }

    /**
     * Add dynamic routes to the configuration.
     *
     * @param array $config Configuration array to update
     * @param array $routes Associative array of arrays
     * (controller => [route name => action]) of routes to add.
     *
     * @return void
     */
    public function addDynamicRoutes(&$config, $routes)
    {
        // Build library card routes
        foreach ($routes as $controller => $controllerRoutes) {
            foreach ($controllerRoutes as $routeName => $action) {
                $this->addDynamicRoute($config, $routeName, $controller, $action);
            }
        }
    }

    /**
     * Add non tab record action & re-register all record routes to support it.
     *
     * @param array  $config Configuration array to update
     * @param string $action Action to add
     *
     * @return void
     */
    public function addNonTabRecordAction(&$config, $action)
    {
        self::$nonTabRecordActions[$action] = $action;
        foreach (self::$recordRoutes as $recordRoute) {
            $this->addRecordRoute(
                $config,
                $recordRoute['routeBase'],
                $recordRoute['controller']
            );
        }
    }

    /**
     * Add non tab record actions & re-register all record routes to support it.
     *
     * @param array $config  Configuration array to update
     * @param array $actions Action to add
     *
     * @return void
     */
    public function addNonTabRecordActions(&$config, $actions)
    {
        foreach ($actions as $action) {
            $this->addNonTabRecordAction($config, $action);
        }
    }

    /**
     * Add record route to the configuration.
     *
     * @param array  $config     Configuration array to update
     * @param string $routeBase  Base name to use for routes
     * @param string $controller Controller to point routes toward
     *
     * @return void
     */
    public function addRecordRoute(&$config, $routeBase, $controller)
    {
        // catch-all "tab" route:
        $config['router']['routes'][$routeBase] = [
            'type'    => 'Laminas\Router\Http\Segment',
            'options' => [
                'route'    => '/' . $controller . '/[:id[/[:tab]]]',
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'tab'        => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => 'Home',
                ],
            ],
        ];
        // special non-tab actions that each need their own route:
        foreach (self::$nonTabRecordActions as $action) {
            $config['router']['routes'][$routeBase . '-' . strtolower($action)] = [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/' . $controller . '/[:id]/' . $action,
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => $controller,
                        'action'     => $action,
                    ],
                ],
            ];
        }

        // Store the added route in case we need to add
        // more nonTabRecordActions later
        self::$recordRoutes["$controller::$routeBase"] = [
            'routeBase' => $routeBase,
            'controller' => $controller,
        ];
    }

    /**
     * Add record routes to the configuration.
     *
     * @param array $config Configuration array to update
     * @param array $routes Associative array (route base name => controller) of
     * routes to add.
     *
     * @return void
     */
    public function addRecordRoutes(&$config, $routes)
    {
        foreach ($routes as $routeBase => $controller) {
            $this->addRecordRoute($config, $routeBase, $controller);
        }
    }

    /**
     * Add a simple static route to the configuration.
     *
     * @param array  $config Configuration array to update
     * @param string $route  Controller/Action string representing route
     *
     * @return void
     */
    public function addStaticRoute(&$config, $route)
    {
        [$controller, $action] = explode('/', $route);
        $routeName = str_replace('/', '-', strtolower($route));
        $config['router']['routes'][$routeName] = [
            'type' => 'Laminas\Router\Http\Literal',
            'options' => [
                'route'    => '/' . $route,
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $action,
                ],
            ],
        ];
    }

    /**
     * Add simple static routes to the configuration.
     *
     * @param array $config Configuration array to update
     * @param array $routes Array of Controller/Action strings representing routes
     *
     * @return void
     */
    public function addStaticRoutes(&$config, $routes)
    {
        foreach ($routes as $route) {
            $this->addStaticRoute($config, $route);
        }
    }
}
