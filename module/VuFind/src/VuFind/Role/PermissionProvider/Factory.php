<?php
/**
 * Permission Provider Factory Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Role\PermissionProvider;
use Zend\ServiceManager\ServiceManager;

/**
 * Permission Provider Factory Class
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
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
        return new IpRange($sm->getServiceLocator()->get('Request'));
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
        return new IpRegEx($sm->getServiceLocator()->get('Request'));
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
        return new ServerParam($sm->getServiceLocator()->get('Request'));
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
            $sm->getServiceLocator()->get('Request'),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
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
            $sm->getServiceLocator()->get('ZfcRbac\Service\AuthorizationService')
        );
    }
}
