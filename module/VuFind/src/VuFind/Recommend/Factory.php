<?php
/**
 * Recommendation Module Factory Class
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Recommend;

use Zend\ServiceManager\ServiceManager;

/**
 * Recommendation Module Factory Class
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for AuthorFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorFacets
     */
    public static function getAuthorFacets(ServiceManager $sm)
    {
        return new AuthorFacets(
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Factory for AuthorInfo module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorInfo
     */
    public static function getAuthorInfo(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new AuthorInfo(
            $sm->get('VuFind\Search\Results\PluginManager'),
            $sm->get('VuFindHttp\HttpService')->createClient(),
            isset($config->Content->authors) ? $config->Content->authors : ''
        );
    }

    /**
     * Factory for AuthorityRecommend module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorityRecommend
     */
    public static function getAuthorityRecommend(ServiceManager $sm)
    {
        return new AuthorityRecommend(
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Factory for CatalogResults module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CatalogResults
     */
    public static function getCatalogResults(ServiceManager $sm)
    {
        return new CatalogResults($sm->get('VuFind\Search\SearchRunner'));
    }

    /**
     * Factory for CollectionSideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionSideFacets
     */
    public static function getCollectionSideFacets(ServiceManager $sm)
    {
        return new CollectionSideFacets(
            $sm->get('VuFind\Config\PluginManager'),
            $sm->get('VuFind\Search\Solr\HierarchicalFacetHelper')
        );
    }

    /**
     * Factory for DPLA Terms module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return DPLATerms
     */
    public static function getDPLATerms(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        if (!isset($config->DPLA->apiKey)) {
            throw new \Exception('DPLA API key missing from configuration.');
        }
        return new DPLATerms(
            $config->DPLA->apiKey,
            $sm->get('VuFindHttp\HttpService')->createClient()
        );
    }

    /**
     * Factory for EuropeanaResults module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return EuropeanaResults
     */
    public static function getEuropeanaResults(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        return new EuropeanaResults(
            $config->Content->europeanaAPI
        );
    }

    /**
     * Factory for ExpandFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ExpandFacets
     */
    public static function getExpandFacets(ServiceManager $sm)
    {
        return new ExpandFacets(
            $sm->get('VuFind\Config\PluginManager'),
            $sm->get('VuFind\Search\Results\PluginManager')
                ->get('Solr')
        );
    }

    /**
     * Factory for FavoriteFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return FavoriteFacets
     */
    public static function getFavoriteFacets(ServiceManager $sm)
    {
        return new FavoriteFacets(
            $sm->get('VuFind\Config\PluginManager'),
            null,
            $sm->get('VuFind\Config\AccountCapabilities')->getTagSetting()
        );
    }

    /**
     * Factory for MapSelection module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MapSelection
     */
    public function getMapSelection(ServiceManager $sm)
    {
        $backend = $sm->get('VuFind\Search\BackendManager');
        $solr = $backend->get('Solr');

        // add basemap options
        $basemapConfig = $sm->get('VuFind\GeoFeatures\BasemapConfig');
        $basemapOptions = $basemapConfig->getBasemap('MapSelection');

        // get MapSelection options
        $mapSelectionConfig = $sm->get('VuFind\GeoFeatures\MapSelectionConfig');
        $mapSelectionOptions = $mapSelectionConfig->getMapSelectionOptions();

        return new MapSelection($solr, $basemapOptions, $mapSelectionOptions);
    }

    /**
     * Factory for Random Recommendations.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RandomRecommend
     */
    public static function getRandomRecommend(ServiceManager $sm)
    {
        return new RandomRecommend(
            $sm->get('VuFindSearch\Service'),
            $sm->get('VuFind\Search\Params\PluginManager')
        );
    }

    /**
     * Factory for SideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SideFacets
     */
    public static function getSideFacets(ServiceManager $sm)
    {
        return new SideFacets(
            $sm->get('VuFind\Config\PluginManager'),
            $sm->get('VuFind\Search\Solr\HierarchicalFacetHelper')
        );
    }

    /**
     * Factory for SummonBestBets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonBestBets
     */
    public static function getSummonBestBets(ServiceManager $sm)
    {
        return new SummonBestBets(
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Factory for SummonDatabases module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonDatabases
     */
    public static function getSummonDatabases(ServiceManager $sm)
    {
        return new SummonDatabases(
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Factory for SummonResults module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonResults
     */
    public static function getSummonResults(ServiceManager $sm)
    {
        return new SummonResults($sm->get('VuFind\Search\SearchRunner'));
    }

    /**
     * Factory for SummonTopics module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonTopics
     */
    public static function getSummonTopics(ServiceManager $sm)
    {
        return new SummonTopics(
            $sm->get('VuFind\Search\Results\PluginManager')
        );
    }

    /**
     * Factory for SwitchQuery module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SwitchQuery
     */
    public static function getSwitchQuery(ServiceManager $sm)
    {
        return new SwitchQuery(
            $sm->get('VuFind\Search\BackendManager')
        );
    }

    /**
     * Factory for TopFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return TopFacets
     */
    public static function getTopFacets(ServiceManager $sm)
    {
        return new TopFacets(
            $sm->get('VuFind\Config\PluginManager')
        );
    }

    /**
     * Factory for VisualFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return VisualFacets
     */
    public static function getVisualFacets(ServiceManager $sm)
    {
        return new VisualFacets(
            $sm->get('VuFind\Config\PluginManager')
        );
    }

    /**
     * Factory for WebResults module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WebResults
     */
    public static function getWebResults(ServiceManager $sm)
    {
        return new WebResults($sm->get('VuFind\Search\SearchRunner'));
    }

    /**
     * Factory for WorldCatIdentities module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCatIdentities
     */
    public static function getWorldCatIdentities(ServiceManager $sm)
    {
        return new WorldCatIdentities($sm->get('VuFind\Connection\WorldCatUtils'));
    }
}
