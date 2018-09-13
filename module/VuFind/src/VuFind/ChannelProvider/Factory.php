<?php
/**
 * Factory for ChannelProvider plugins.
 *
 * PHP version 7
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
        return new AlphaBrowse(
            $sm->get('VuFindSearch\Service'),
            $sm->get('VuFind\Search\BackendManager')
                ->get('Solr'),
            $sm->get('ControllerPluginManager')->get('url'),
            $sm->get('VuFind\Record\Router')
        );
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
        return new Facets(
            $sm->get('VuFind\Search\Results\PluginManager'),
            $sm->get('ControllerPluginManager')->get('url')
        );
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
        return new ListItems(
            $sm->get('VuFind\Db\Table\PluginManager')->get('UserList'),
            $sm->get('ControllerPluginManager')->get('url'),
            $sm->get('VuFind\Search\Results\PluginManager')
        );
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
        return new Random(
            $sm->get('VuFindSearch\Service'),
            $sm->get('VuFind\Search\Params\PluginManager')
        );
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
        return new SimilarItems(
            $sm->get('VuFindSearch\Service'),
            $sm->get('ControllerPluginManager')->get('url'),
            $sm->get('VuFind\Record\Router')
        );
    }
}
