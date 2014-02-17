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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Recommend;
use Zend\ServiceManager\ServiceManager;

/**
 * Recommendation Module Factory Class
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
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
    public function getAuthorFacets(ServiceManager $sm)
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
    public function getAuthorInfo(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new AuthorInfo(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->getServiceLocator()->get('VuFind\Http')->createClient(),
            isset ($config->Content->authors) ? $config->Content->authors : ''
        );
    }

    /**
     * Factory for AuthorityRecommend module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorityRecommend
     */
    public function getAuthorityRecommend(ServiceManager $sm)
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
    public function getCatalogResults(ServiceManager $sm)
    {
        return new CatalogResults(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Factory for CollectionSideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionSideFacets
     */
    public function getCollectionSideFacets(ServiceManager $sm)
    {
        return new CollectionSideFacets(
            $sm->getServiceLocator()->get('VuFind\Config')
        );
    }

    /**
     * Factory for EuropeanaResults module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return EuropeanaResults
     */
    public function getEuropeanaResults(ServiceManager $sm)
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
    public function getExpandFacets(ServiceManager $sm)
    {
        return new ExpandFacets(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')->get('Solr')
        );
    }

    /**
     * Factory for FavoriteFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return FavoriteFacets
     */
    public function getFavoriteFacets(ServiceManager $sm)
    {
        return new FavoriteFacets(
            $sm->getServiceLocator()->get('VuFind\Config')
        );
    }

    /**
     * Factory for SideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SideFacets
     */
    public function getSideFacets(ServiceManager $sm)
    {
        return new SideFacets(
            $sm->getServiceLocator()->get('VuFind\Config')
        );
    }

    /**
     * Factory for SummonBestBets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonBestBets
     */
    public function getSummonBestBets(ServiceManager $sm)
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
    public function getSummonDatabases(ServiceManager $sm)
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
    public function getSummonResults(ServiceManager $sm)
    {
        return new SummonResults(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Factory for SummonTopics module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SummonTopics
     */
    public function getSummonTopics(ServiceManager $sm)
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
    public function getSwitchQuery(ServiceManager $sm)
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
    public function getTopFacets(ServiceManager $sm)
    {
        return new TopFacets(
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
    public function getWebResults(ServiceManager $sm)
    {
        return new WebResults(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }

    /**
     * Factory for WorldCatIdentities module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCatIdentities
     */
    public function getWorldCatIdentities(ServiceManager $sm)
    {
        return new WorldCatIdentities(
            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
        );
    }

    /**
     * Factory for WorldCatTerms module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCatTerms
     */
    public function getWorldCatTerms(ServiceManager $sm)
    {
        return new WorldCatTerms(
            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
        );
    }
}