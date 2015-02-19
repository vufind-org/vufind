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
use Zend\Mvc\Router\Http\RouteMatch,
    Zend\Stdlib\RequestInterface as Request;
use Zend\Mvc\Router\Console\SimpleRouteStack;

/**
 * VuFind Console Router
 *
 * @category VuFind2
 * @package  Mvc_Router
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ConsoleRouter extends SimpleRouteStack
{
    /**
     * Present working directory
     *
     * @var string
     */
    protected $pwd = '';

    /**
     * Extract controller and action from command line when user directly accesses
     * the index.php file.
     *
     * @return array Array with 0 => controller, 1 => action
     */
    protected function extractFromCommandLine()
    {
        // We need to modify the $_SERVER superglobals so that \Zend\Console\GetOpt
        // will behave correctly after we've manipulated the CLI parameters. Let's
        // use references for convenience.
        $argv = & $_SERVER['argv'];
        $argc = & $_SERVER['argc'];

        // Pull the script name off the front of the argument array:
        $script = array_shift($argv);

        // Pull off the controller and action; if they are missing, we'll fill
        // in dummy values for consistency's sake, which also requires us to
        // adjust the argument count.
        if ($argc > 0) {
            $controller = array_shift($argv);
        } else {
            $controller = "undefined";
            $argc++;
        }
        if ($argc > 1) {
            $action = array_shift($argv);
        } else {
            $action = "undefined";
            $argc++;
        }

        // In case later scripts are displaying $argv[0] for the script name,
        // let's push the full invocation into that position. We want to eliminate
        // the $controller and $action values as separate parts of the array since
        // they'll confuse subsequent command line processing logic.
        array_unshift($argv, "$script $controller $action");
        $argc -= 2;

        return [$controller, $action];
    }

    /**
     * Check CLIDIR
     *
     * @return string
     */
    public function getCliDir()
    {
        if ($this->pwd == '' && defined('CLI_DIR')) {
            $this->pwd = CLI_DIR;
        }
        return $this->pwd;
    }

    /**
     * Set CLIDIR (used primarily for testing)
     *
     * @param string $pwd Present directory
     *
     * @return void
     */
    public function setCliDir($pwd)
    {
        $this->pwd = $pwd;
    }

    /**
     * Legacy handling for scripts: Match a given request.
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

        // WARNING: cwd is $VUFIND_HOME, so that throws off realpath!
        //
        // Convert base filename (minus .php extension and underscores) and
        // containing directory name into action and controller, respectively:
        $base = basename($filename, ".php");
        $actionName = str_replace('_', '', $base);      // action is the easy part

        $dir = dirname($filename);
        if ($dir == false || $dir == '' || $dir == '.' || basename($dir) == '.') {
            // legacy style: cd to subdir, but set CLI_DIR
            $dir  = $this->getCliDir();
            $path = $dir . '/' . $filename;
        } else {
            // modern style: invoked as, e.g. $base=util/ping.php, already has path
            $level1 = basename(dirname($filename));
            // but we need to re-orient relative to VUFIND_HOME
            $path   = $level1 . '/' . basename($filename);
        }
        $controller = basename($dir);       // the last directory part

        // Special case: if user is accessing index.php directly, we expect
        // controller and action as first two parameters.
        if ($controller == 'public' && $actionName == 'index') {
            list($controller, $actionName) = $this->extractFromCommandLine();
        }

        $routeMatch = new RouteMatch(
            ['controller' => $controller, 'action' => $actionName], 1
        );

        // Override standard routing:
        $routeMatch->setMatchedRouteName('default');
        return $routeMatch;
    }

}
