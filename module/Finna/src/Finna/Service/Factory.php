<?php
/**
 * Factory for various top-level VuFind services.
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
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Service;
use Zend\Console\Console,
    Zend\ServiceManager\ServiceManager;

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
     * Construct the Autocomplete Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Autocomplete\PluginManager
     */
    public static function getAutocompletePluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Autocomplete');
    }

    /**
     * Construct the cache manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Cache\Manager
     */
    public static function getCacheManager(ServiceManager $sm)
    {
        return new \Finna\Cache\Manager(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('searches')
        );
    }

    /**
     * Construct the cookie manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cookie\CookieManager
     */
    public static function getCookieManager(ServiceManager $sm)
    {
        if (Console::isConsole()) {
            return false;
        }
        return parent::getCookieManager($sm);
    }

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
     * Construct the ILS connection.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\ILS\Connection
     */
    public static function getILSConnection(ServiceManager $sm)
    {
        $catalog = new \Finna\ILS\Connection(
            $sm->get('VuFind\Config')->get('config')->Catalog,
            $sm->get('VuFind\ILSDriverPluginManager'),
            $sm->get('VuFind\Config')
        );
        return $catalog->setHoldConfig($sm->get('VuFind\ILSHoldSettings'));
    }

    /**
     * Generic plugin manager factory (support method).
     *
     * @param ServiceManager $sm Service manager.
     * @param string         $ns VuFind namespace containing plugin manager
     *
     * @return object
     */
    public static function getGenericPluginManager(ServiceManager $sm, $ns)
    {
        $className = 'Finna\\' . $ns . '\PluginManager';
        $configKey = strtolower(str_replace('\\', '_', $ns));
        $config = $sm->get('Config');
        return new $className(
            new \Zend\ServiceManager\Config(
                $config['vufind']['plugin_managers'][$configKey]
            )
        );
    }

    /**
     * Construct the Organisation info Service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\LocationService
     */
    public static function getOrganisationInfo(ServiceManager $sm)
    {
        return new \Finna\OrganisationInfo\OrganisationInfo(
            $config = $sm->get('VuFind\Config')->get('organisationInfo'),
            $sm->get('VuFind\CacheManager'),
            $sm->get('VuFind\Http'),
            $sm->get('viewmanager')->getRenderer()
        );
    }

    /**
     * Construct the Location Service.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\LocationService
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
    public static function getOnlinePayment(ServiceManager $sm)
    {
        return new \Finna\OnlinePayment\OnlinePayment(
            $sm->get('VuFind\DbTablePluginManager'),
            $sm->get('VuFind\Logger'),
            $sm->get('VuFind\Config')->get('datasources')
        );
    }

    /**
     * Construct the Search\Results Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Search\Results\PluginManager
     */
    public static function getSearchResultsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Search\Results');
    }

    /**
     * Construct the search specs reader.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Config\SearchSpecsReader
     */
    public static function getSearchSpecsReader(ServiceManager $sm)
    {
        return new \Finna\Config\SearchSpecsReader(
            $sm->get('VuFind\CacheManager')
        );
    }
}
