<?php
/**
 * Permission Provider Factory Class
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Role\PermissionProvider;

use Zend\ServiceManager\ServiceManager;

/**
 * Permission Provider Factory Class
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for IpRange
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return IpRange
     */
    public static function getIpRange(ServiceManager $sm)
    {
        return new IpRange(
            $sm->get('Request'),
            $sm->get('VuFind\Net\IpAddressUtils')
        );
    }

    /**
     * Factory for IpRegEx
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return IpRegEx
     */
    public static function getIpRegEx(ServiceManager $sm)
    {
        return new IpRegEx($sm->get('Request'));
    }

    /**
     * Factory for ServerParam
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ServerParam
     */
    public static function getServerParam(ServiceManager $sm)
    {
        return new ServerParam($sm->get('Request'));
    }

    /**
     * Factory for Shibboleth
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Shibboleth
     */
    public static function getShibboleth(ServiceManager $sm)
    {
        return new Shibboleth(
            $sm->get('Request'),
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Factory for Username
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Username
     */
    public static function getUsername(ServiceManager $sm)
    {
        return new Username(
            $sm->get('ZfcRbac\Service\AuthorizationService')
        );
    }

    /**
     * Factory for User
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return User
     */
    public static function getUser(ServiceManager $sm)
    {
        return new User(
            $sm->get('ZfcRbac\Service\AuthorizationService')
        );
    }
}
