<?php
/**
 * Search Results Object Factory Class
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search\Results;
use Finna\Search\UrlQueryHelper,
    \Finna\Search\Results\PluginFactory,
    Zend\ServiceManager\ServiceManager;

/**
 * Search Results Object Factory Class
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Search\Results\Factory
{
    /**
     * Factory for Favorites results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Favorites
     */
    public static function getFavorites(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $obj = $factory->createServiceWithName($sm, 'favorites', 'Favorites');
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }

    /**
     * Factory for Solr results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSolr(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $solr = $factory->createServiceWithName($sm, 'solr', 'Solr');
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $spellConfig = isset($config->Spelling)
            ? $config->Spelling : null;
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor($spellConfig)
        );

        return Factory::initUrlQueryHelper($solr, $sm->getServiceLocator());
    }

    /**
     * Factory for Primo results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Primo
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $primo = $factory->createServiceWithName($sm, 'primo', 'Primo');
        return Factory::initUrlQueryHelper($primo, $sm->getServiceLocator());
    }

    /**
     * Factory for MetaLib results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MetaLib
     */
    public static function getMetaLib(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $metalib = $factory->createServiceWithName($sm, 'metalib', 'MetaLib');
        return Factory::initUrlQueryHelper($metalib, $sm->getServiceLocator());
    }

    /**
     * Factory for Combined results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Combined
     */
    public static function getCombined(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $combined = $factory->createServiceWithName($sm, 'combined', 'Combined');
        return Factory::initUrlQueryHelper($combined, $sm->getServiceLocator());
    }

    /**
     * Internal utility function for initializing
     * UrlQueryHelper for a Results-object with search ids for all tabs.
     *
     * @param ResultsManager $results Search results.
     * @param ServiceManager $locator Service locator.
     *
     * @return Results Search results with initialized UrlQueryHelper
     */
    public static function initUrlQueryHelper(
        \VuFind\Search\Base\Results $results, $locator
    ) {
        $helper = new UrlQueryHelper($results->getParams());
        $savedSearches
            = $locator->get('Request')->getQuery('search');
        if ($savedSearches) {
            $helper->setDefaultParameter('search', $savedSearches);
        }
        $results->setHelper('urlQuery', $helper);

        return $results;
    }
}
