<?php
/**
 * Factory for Root view helpers.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the SystemEmail helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SystemEmail
     */
    public static function getSystemEmail(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new SystemEmail($config->Site->email ?? '');
    }

    /**
     * Construct the UserList helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserList
     */
    public static function getUserList(ServiceManager $sm)
    {
        $sessionManager = $sm->get('Zend\Session\SessionManager');
        $session = new \Zend\Session\Container('List', $sessionManager);
        $capabilities = $sm->get('VuFind\Config\AccountCapabilities');
        return new UserList($session, $capabilities->getListSetting());
    }

    /**
     * Construct the UserTags helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UserTags
     */
    public static function getUserTags(ServiceManager $sm)
    {
        $capabilities = $sm->get('VuFind\Config\AccountCapabilities');
        return new UserTags($capabilities->getTagSetting());
    }
}
