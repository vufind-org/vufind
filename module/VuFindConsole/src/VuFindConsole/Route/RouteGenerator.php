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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Route
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindConsole\Route;

/**
 * Route Generator Class
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
     * Add console routes to the configuration.
     *
     * @param array $config Configuration array to update
     * @param array $routes Array of Controller/Action strings => route values
     *
     * @return void
     */
    public function addRoutes(& $config, $routes)
    {
        foreach ($routes as $key => $route) {
            list($controller, $action) = explode('/', $key);
            $name = $controller . '-' . $action;
            $config['console']['router']['routes'][$name] = [
                'options' => [
                    'route' => $route,
                    'defaults' => compact('controller', 'action'),
                ]
            ];
        }
    }
}
