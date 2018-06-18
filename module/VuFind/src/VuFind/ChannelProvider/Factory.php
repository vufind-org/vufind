<?php
/**
 * Factory for ChannelProvider plugins.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for ChannelProvider plugins.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the AlphaBrowse channel provider.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AlphaBrowse
     */
    public static function getAlphaBrowse(ServiceManager $sm)
    {
        $helper = new AlphaBrowse(
            $sm->getServiceLocator()->get('VuFind\Search'),
            $sm->getServiceLocator()->get('VuFind\Search\BackendManager')
                ->get('Solr'),
            $sm->getServiceLocator()->get('ControllerPluginManager')->get('url'),
            $sm->getServiceLocator()->get('VuFind\RecordRouter')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }

    /**
     * Construct the Facets channel provider.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Facets
     */
    public static function getFacets(ServiceManager $sm)
    {
        $helper = new Facets(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->getServiceLocator()->get('ControllerPluginManager')->get('url')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }

    /**
     * Construct the ListItems channel provider.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ListItems
     */
    public static function getListItems(ServiceManager $sm)
    {
        $helper = new ListItems(
            $sm->getServiceLocator()->get('VuFind\DbTablePluginManager')
                ->get('UserList'),
            $sm->getServiceLocator()->get('ControllerPluginManager')->get('url'),
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }

    /**
     * Construct the Random channel provider.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Random
     */
    public static function getRandom(ServiceManager $sm)
    {
        $helper = new Random(
            $sm->getServiceLocator()->get('VuFind\Search'),
            $sm->getServiceLocator()->get('VuFind\SearchParamsPluginManager')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }

    /**
     * Construct the SimilarItems channel provider.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SimilarItems
     */
    public static function getSimilarItems(ServiceManager $sm)
    {
        $helper = new SimilarItems(
            $sm->getServiceLocator()->get('VuFind\Search'),
            $sm->getServiceLocator()->get('ControllerPluginManager')->get('url'),
            $sm->getServiceLocator()->get('VuFind\RecordRouter')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }
}
