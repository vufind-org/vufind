<?php
/**
 * VuFind Admin Controller Base
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
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindAdmin\Controller;
use Zend\Mvc\MvcEvent;

/**
 * VuFind Admin Controller Base
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AbstractAdmin extends \VuFind\Controller\AbstractBase
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->accessPermission = 'access.AdminModule';
    }

    /**
     * Use preDispatch event to block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function validateAccessPermission(MvcEvent $e)
    {
        // Disable search box in Admin module:
        $this->layout()->searchbox = false;

        // If we're using the "disabled" action, we don't need to do any further
        // checking to see if we are disabled!!
        $routeMatch = $e->getRouteMatch();
        if (strtolower($routeMatch->getParam('action')) == 'disabled') {
            return;
        }

        // Block access to everyone when module is disabled:
        $config = $this->getConfig();
        if (!isset($config->Site->admin_enabled) || !$config->Site->admin_enabled) {
            $pluginManager  = $this->getServiceLocator()
                ->get('Zend\Mvc\Controller\PluginManager');
            $redirectPlugin = $pluginManager->get('redirect');
            return $redirectPlugin->toRoute('admin/disabled');
        }

        // Call parent method to do permission checking:
        parent::validateAccessPermission($e);
    }

    /**
     * Display disabled message.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function disabledAction()
    {
        return $this->createViewModel();
    }
}
