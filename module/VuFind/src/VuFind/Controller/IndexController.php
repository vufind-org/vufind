<?php

/**
 * Default Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Config\Config;
use VuFind\Auth\Manager as AuthManager;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class IndexController extends \Laminas\Mvc\Controller\AbstractActionController
{
    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Auth manager
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Constructor
     *
     * @param Config      $config      VuFind configuration
     * @param AuthManager $authManager Auth manager
     */
    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->config = $config;
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
            $controller = $this->config->Site->defaultLoggedInModule ?? 'MyResearch';
            $actionConfig = 'defaultLoggedInAction';
        } else {
            $controller = $this->config->Site->defaultModule ?? 'Search';
            $actionConfig = 'defaultAction';
        }
        $action = $this->config->Site->$actionConfig ?? 'Home';

        // Forward to the appropriate controller and action:
        return $this->forward()->dispatch($controller, compact('action'));
    }
}
