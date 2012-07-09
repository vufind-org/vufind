<?php
/**
 * Default Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

use VuFind\Config\Reader as ConfigReader,
    VuFind\Account\Manager as AccountManager,
    Zend\Mvc\Controller\AbstractActionController;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class IndexController extends AbstractActionController
{
    /**
     * Determines what elements are displayed on the home page based on whether
     * the user is logged in.
     *
     * @return void
     */
    public function homeAction()
    {
        $config = ConfigReader::getConfig();
        $loggedInModule = isset($config->Site->defaultLoggedInModule)
            ? $config->Site->defaultLoggedInModule : 'MyResearch';
        $loggedOutModule = isset($config->Site->defaultModule)
            ? $config->Site->defaultModule : 'Search';
        $module = AccountManager::getInstance()->isLoggedIn()
            ? $loggedInModule : $loggedOutModule;
        $options = array('action' => 'Home', 'controller' => $module);
        return $this->forward()->dispatch($module, $options);
    }
}
