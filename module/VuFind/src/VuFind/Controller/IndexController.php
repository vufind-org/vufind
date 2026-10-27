<?php

/**
 * Default Controller.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Action\PluginManager as ActionPluginManager;
use VuFind\Auth\Manager as AuthManager;

use function count;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class IndexController extends AbstractBase
{
    /**
     * Auth manager.
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Constructor.
     *
     * @param ServiceLocatorInterface $sm          Service locator
     * @param array                   $config      VuFind configuration
     * @param AuthManager             $authManager Auth manager
     */
    public function __construct(ServiceLocatorInterface $sm, protected array $config, AuthManager $authManager)
    {
        parent::__construct($sm);
        $this->authManager = $authManager;
    }

    /**
     * Determines what elements are displayed on the home page based on whether
     * the user is logged in.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Load different configurations depending on whether we're logged in or not:
        if ($this->authManager->getIdentity()) {
            $controller = $this->config['Site']['defaultLoggedInModule'] ?? 'Account';
            $actionConfig = 'defaultLoggedInAction';
        } else {
            $controller = $this->config['Site']['defaultModule'] ?? 'Search';
            $actionConfig = 'defaultAction';
        }
        $action = $this->config['Site'][$actionConfig] ?? 'Home';

        // Apply any action redirect right away:
        $redirects = $this->getService('Config')['vufind']['action_redirects'] ?? [];
        $key = strtolower($controller) . '/' . strtolower($action);
        if ($routeName = $redirects[$key] ?? null) {
            // This relies on being able to convert route name to action name, so check for that and fail quietly
            // if the action is not available:
            $parts = explode('-', $routeName, 2);
            if (count($parts) === 2) {
                $actionPluginManager = $this->getService(ActionPluginManager::class);
                if ($actionPluginManager->has(implode('/', $parts))) {
                    $controller = $parts[0];
                    $action = $parts[1];
                }
            }
        }

        // Forward to the appropriate controller and action:
        return $this->forwardTo($controller, $action);
    }
}
