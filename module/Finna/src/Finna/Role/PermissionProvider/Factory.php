<?php
/**
 * Permission Provider Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace Finna\Role\PermissionProvider;
use Zend\ServiceManager\ServiceManager;

/**
 * Permission Provider Factory Class
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Role\PermissionProvider\Factory
{
    /**
     * Factory for authentication strategy
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthencationStrategy
     */
    public static function getAuthenticationStrategy(ServiceManager $sm)
    {
        return new AuthenticationStrategy($sm->getServiceLocator());
    }

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
            $sm->getServiceLocator()->get('Request'),
            $sm->getServiceLocator()->get('VuFind\IpAddressUtils')
        );
    }
}
