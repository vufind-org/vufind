<?php
/**
 * Recommendation Module Factory Class
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
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new AuthorInfo(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->getServiceLocator()->get('VuFind\Http')->createClient(),
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
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
        return new CatalogResults(
            $sm->getServiceLocator()->get('VuFind\SearchRunner')
        );
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
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper')
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
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        if (!isset($config->DPLA->apiKey)) {
            throw new \Exception('DPLA API key missing from configuration.');
        }
        return new DPLATerms(
            $config->DPLA->apiKey,
            $sm->getServiceLocator()->get('VuFind\Http')->createClient()
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
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
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
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
        $parentSm = $sm->getServiceLocator();
        return new FavoriteFacets(
            $parentSm->get('VuFind\Config'),
            null,
            $parentSm->get('VuFind\AccountCapabilities')->getTagSetting()
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
        $config = $sm->getServiceLocator()->get('Vufind\Config');
        $backend = $sm->getServiceLocator()->get('VuFind\Search\BackendManager');
        $solr = $backend->get('Solr');
        return new MapSelection($config, $solr);
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
            $sm->getServiceLocator()->get('VuFind\Search'),
            $sm->getServiceLocator()->get('VuFind\SearchParamsPluginManager')
        );
    }

    /**
     * Factory for ResultGoogleMapAjax Recommendations.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ResultGoogleMapAjax
     */
    public static function getResultGoogleMapAjax(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $key = isset($config->Content->googleMapApiKey)
            ? $config->Content->googleMapApiKey : null;
        return new ResultGoogleMapAjax($key);
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
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper')
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
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
        return new SummonResults(
            $sm->getServiceLocator()->get('VuFind\SearchRunner')
        );
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
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
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
            $sm->getServiceLocator()->get('VuFind\Search\BackendManager')
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
            $sm->getServiceLocator()->get('VuFind\Config')
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
            $sm->getServiceLocator()->get('VuFind\Config')
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
        return new WebResults($sm->getServiceLocator()->get('VuFind\SearchRunner'));
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
        return new WorldCatIdentities(
            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
        );
    }
}
