<?php
/**
 * Factory for various top-level VuFind services.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Service;

use Zend\ServiceManager\ServiceManager;
use Zend\Session\Container as SessionContainer;

/**
 * Factory for various top-level VuFind services.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Factory extends \VuFind\Service\Factory
{
    /**
     * Construct the feed service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Feed\Feed
     */
    public static function getFeed(ServiceManager $sm)
    {
        return new \Finna\Feed\Feed(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('rss'),
            $sm->get('VuFind\Http'),
            $sm->get('VuFind\Translator'),
            $sm->get('VuFind\CacheManager')
        );
    }

    /**
     * Construct the Organisation info Service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\OrganisationInfo\OrganisationInfo
     */
    public static function getOrganisationInfo(ServiceManager $sm)
    {
        return new \Finna\OrganisationInfo\OrganisationInfo(
            $config = $sm->get('VuFind\Config')->get('OrganisationInfo'),
            $sm->get('VuFind\CacheManager'),
            $sm->get('VuFind\Http'),
            $sm->get('ViewRenderer'),
            $sm->get('VuFind\Translator')
        );
    }

    /**
     * Construct the Location Service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\LocationService\LocationService
     */
    public static function getLocationService(ServiceManager $sm)
    {
        return new \Finna\LocationService\LocationService(
            $sm->get('VuFind\Config')->get('LocationService')
        );
    }

    /**
     * Construct the online payment manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\OnlinePayment\OnlinePayment
     */
    public static function getOnlinePaymentManager(ServiceManager $sm)
    {
        return new \Finna\OnlinePayment\OnlinePayment(
            $sm->get('VuFind\Http'),
            $sm->get('VuFind\DbTablePluginManager'),
            $sm->get('VuFind\Logger'),
            $sm->get('VuFind\Config')->get('datasources'),
            $sm->get('VuFind\Translator')
        );
    }

    /**
     * Construct the online payment session.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SessionContainer
     */
    public static function getOnlinePaymentSession(ServiceManager $sm)
    {
        return new SessionContainer(
            'OnlinePayment',
            $sm->get('VuFind\SessionManager')
        );
    }
}
