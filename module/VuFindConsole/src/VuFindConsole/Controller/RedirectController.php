<?php
/**
 * Redirect Controller
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;
use Zend\Console\Console;
use Zend\Mvc\Application;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class RedirectController extends \Zend\Mvc\Controller\AbstractActionController
{
    /**
     * Get a usage message with the help of the RouteNotFoundStrategy.
     *
     * @return mixed
     */
    protected function getUsage()
    {
        $strategy = $this->getServiceLocator()->get('ViewManager')
            ->getRouteNotFoundStrategy();
        $event = $this->getEvent();
        $event->setError(Application::ERROR_ROUTER_NO_MATCH);
        $strategy->handleRouteNotFoundError($event);
        return $event->getResult();
    }

    /**
     * Use the first two command line parameters to redirect the user to an
     * appropriate controller.
     *
     * @return mixed
     */
    public function consoledefaultAction()
    {
        // We need to modify the $_SERVER superglobals so that \Zend\Console\GetOpt
        // will behave correctly after we've manipulated the CLI parameters. Let's
        // use references for convenience.
        $argv = & $_SERVER['argv'];
        $argc = & $_SERVER['argc'];

        // Pull the script name off the front of the argument array:
        $script = array_shift($argv);

        // Fail if we don't have at least two arguments (controller/action):
        if ($argc < 2) {
            return $this->getUsage();
        }

        // Pull off the controller and action.
        $controller = array_shift($argv);
        $action = array_shift($argv);

        // In case later scripts are displaying $argv[0] for the script name,
        // let's push the full invocation into that position when index.php is
        // used. We want to eliminate the $controller and $action values as separate
        // parts of the array since they'll confuse subsequent parameter processing.
        if (substr($script, -9) === 'index.php') {
            $script .= " $controller $action";
        }
        array_unshift($argv, $script);
        $argc -= 2;

        try {
            return $this->forward()->dispatch($controller, compact('action'));
        } catch (\Exception $e) {
            Console::writeLine('ERROR: ' . $e->getMessage());
            return $this->getUsage();
        }
    }
}
