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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

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
     * Determines what elements are displayed on the home page based on whether
     * the user is logged in.
     *
     * @return mixed
     */
    public function homeAction()
    {
        $config = $this->getConfig();
        $loggedInModule = isset($config->Site->defaultLoggedInModule)
            ? $config->Site->defaultLoggedInModule : 'MyResearch';
        $loggedOutModule = isset($config->Site->defaultModule)
            ? $config->Site->defaultModule : 'Search';
        $module = $this->getUser() ? $loggedInModule : $loggedOutModule;
        return $this->forwardTo($module, 'Home');
    }
}
