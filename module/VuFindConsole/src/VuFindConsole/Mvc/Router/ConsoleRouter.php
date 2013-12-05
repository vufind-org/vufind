<?php
/**
 * VuFind Console Router
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
 * @package  Mvc_Router
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindConsole\Mvc\Router;
use Zend\Mvc\Router\Http\RouteMatch, Zend\Mvc\Router\RouteStackInterface,
    Zend\Stdlib\RequestInterface as Request;

/**
 * VuFind Console Router
 *
 * @category VuFind2
 * @package  Mvc_Router
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ConsoleRouter implements RouteStackInterface
{
    /**
     * Present working directory
     *
     * @var string
     */
    protected $pwd = '';

    /**
     * Constructor
     *
     * @param string $pwd Present working directory
     */
    public function __construct($pwd = null)
    {
        if (null !== $pwd) {
            $this->pwd = $pwd;
        } else if (defined('CLI_DIR')) {
            $this->pwd = CLI_DIR;
        }
    }

    /**
     * Create a new route with given options.
     *
     * @param array|\Traversable $options Router options
     *
     * @return void
     */
    public static function factory($options = array())
    {
        return new ConsoleRouter(isset($options['pwd']) ? $options['pwd'] : null);
    }

    /**
     * Match a given request.
     *
     * @param Request $request Request to match
     *
     * @return RouteMatch
     */
    public function match(Request $request)
    {
        // Get command line arguments and present working directory from
        // server superglobal:
        $filename = $request->getScriptName();

        // Convert base filename (minus .php extension and underscores) and
        // containing directory name into action and controller, respectively:
        $baseFilename = str_replace('_', '', basename($filename));
        $baseFilename = substr($baseFilename, 0, strlen($baseFilename) - 4);
        $baseDirname = basename(dirname(realpath($this->pwd . '/' . $filename)));
        $routeMatch = new RouteMatch(
            array('controller' => $baseDirname, 'action' => $baseFilename), 1
        );

        // Override standard routing:
        $routeMatch->setMatchedRouteName('default');
        return $routeMatch;
    }

    /**
     * Assemble the route.
     *
     * @param array $params  Route parameters
     * @param array $options Route options
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function assemble(array $params = array(), array $options = array())
    {
        throw new \Exception('assemble not supported');
    }

    /**
     * Add a route to the stack.
     *
     * @param string  $name     Route name
     * @param mixed   $route    Route details
     * @param integer $priority Priority
     *
     * @return RouteStackInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function addRoute($name, $route, $priority = null)
    {
        throw new \Exception('addRoute not supported');
    }

    /**
     * Add multiple routes to the stack.
     *
     * @param array|\Traversable $routes Routes to add
     *
     * @return RouteStackInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function addRoutes($routes)
    {
        throw new \Exception('addRoutes not supported');
    }

    /**
     * Remove a route from the stack.
     *
     * @param string $name Route name
     *
     * @return RouteStackInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function removeRoute($name)
    {
        throw new \Exception('removeRoute not supported');
    }

    /**
     * Remove all routes from the stack and set new ones.
     *
     * @param array|\Traversable $routes Routes to set
     *
     * @return RouteStackInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setRoutes($routes)
    {
        throw new \Exception('setRoutes not supported');
    }
}