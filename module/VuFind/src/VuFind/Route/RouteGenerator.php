<?php
/**
 * Route Generator Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Route
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Route;

/**
 * Route Generator Class
 *
 * The data model object representing a user's book cart.
 *
 * @category VuFind2
 * @package  Route
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RouteGenerator
{
    /**
     * Record sub-routes are generally used to access tab plug-ins, but a few
     * URLs are hard-coded to specific actions; this array lists those actions.
     *
     * @var array
     */
    protected $nonTabRecordActions;

    /**
     * Constructor
     *
     * @param array $nonTabRecordActions List of non-tab record actions (null
     * for default).
     */
    public function __construct(array $nonTabRecordActions = null)
    {
        if (null === $nonTabRecordActions) {
            $this->nonTabRecordActions = [
                'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save',
                'Email', 'SMS', 'Cite', 'Export', 'RDF', 'Hold', 'BlockedHold',
                'Home', 'StorageRetrievalRequest', 'AjaxTab',
                'BlockedStorageRetrievalRequest', 'ILLRequest', 'BlockedILLRequest',
                'PDF',
            ];
        } else {
            $this->nonTabRecordActions = $nonTabRecordActions;
        }
    }

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
    public function addDynamicRoute(& $config, $routeName, $controller, $action)
    {
        list($actionName) = explode('/', $action, 2);
        $config['router']['routes'][$routeName] = [
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => "/$controller/$action",
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $actionName,
                ]
            ]
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
    public function addDynamicRoutes(& $config, $routes)
    {
        // Build library card routes
        foreach ($routes as $controller => $controllerRoutes) {
            foreach ($controllerRoutes as $routeName => $action) {
                $this->addDynamicRoute($config, $routeName, $controller, $action);
            }
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
    public function addRecordRoute(& $config, $routeBase, $controller)
    {
        // catch-all "tab" route:
        $config['router']['routes'][$routeBase] = [
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => '/' . $controller . '/[:id[/[:tab]]]',
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => 'Home',
                ]
            ]
        ];
        // special non-tab actions that each need their own route:
        foreach ($this->nonTabRecordActions as $action) {
            $config['router']['routes'][$routeBase . '-' . strtolower($action)] = [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => '/' . $controller . '/[:id]/' . $action,
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => $controller,
                        'action'     => $action,
                    ]
                ]
            ];
        }
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
    public function addRecordRoutes(& $config, $routes)
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
    public function addStaticRoute(& $config, $route)
    {
        list($controller, $action) = explode('/', $route);
        $routeName = str_replace('/', '-', strtolower($route));
        $config['router']['routes'][$routeName] = [
            'type' => 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'    => '/' . $route,
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $action,
                ]
            ]
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
    public function addStaticRoutes(& $config, $routes)
    {
        foreach ($routes as $route) {
            $this->addStaticRoute($config, $route);
        }
    }
}
