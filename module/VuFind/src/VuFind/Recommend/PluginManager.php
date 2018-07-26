<?php
/**
 * Recommendation module plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * Recommendation module plugin manager
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'alphabrowselink' => 'VuFind\Recommend\AlphaBrowseLink',
        'authorfacets' => 'VuFind\Recommend\AuthorFacets',
        'authorinfo' => 'VuFind\Recommend\AuthorInfo',
        'authorityrecommend' => 'VuFind\Recommend\AuthorityRecommend',
        'catalogresults' => 'VuFind\Recommend\CatalogResults',
        'channels' => 'VuFind\Recommend\Channels',
        'collectionsidefacets' => 'VuFind\Recommend\CollectionSideFacets',
        'doi' => 'VuFind\Recommend\DOI',
        'dplaterms' => 'VuFind\Recommend\DPLATerms',
        'europeanaresults' => 'VuFind\Recommend\EuropeanaResults',
        'europeanaresultsdeferred' => 'VuFind\Recommend\EuropeanaResultsDeferred',
        'expandfacets' => 'VuFind\Recommend\ExpandFacets',
        'facetcloud' => 'VuFind\Recommend\FacetCloud',
        'favoritefacets' => 'VuFind\Recommend\FavoriteFacets',
        'libraryh3lp' => 'VuFind\Recommend\Libraryh3lp',
        'mapselection' => 'VuFind\Recommend\MapSelection',
        'sidefacets' => 'VuFind\Recommend\SideFacets',
        'openlibrarysubjects' => 'VuFind\Recommend\OpenLibrarySubjects',
        'openlibrarysubjectsdeferred' =>
            'VuFind\Recommend\OpenLibrarySubjectsDeferred',
        'pubdatevisajax' => 'VuFind\Recommend\PubDateVisAjax',
        'randomrecommend' => 'VuFind\Recommend\RandomRecommend',
        'removefilters' => 'VuFind\Recommend\RemoveFilters',
        'resultgooglemapajax' => 'VuFind\Recommend\Deprecated',
        'spellingsuggestions' => 'VuFind\Recommend\SpellingSuggestions',
        'summonbestbets' => 'VuFind\Recommend\SummonBestBets',
        'summonbestbetsdeferred' => 'VuFind\Recommend\SummonBestBetsDeferred',
        'summondatabases' => 'VuFind\Recommend\SummonDatabases',
        'summondatabasesdeferred' => 'VuFind\Recommend\SummonDatabasesDeferred',
        'summonresults' => 'VuFind\Recommend\SummonResults',
        'summonresultsdeferred' => 'VuFind\Recommend\SummonResultsDeferred',
        'summontopics' => 'VuFind\Recommend\SummonTopics',
        'switchquery' => 'VuFind\Recommend\SwitchQuery',
        'switchtype' => 'VuFind\Recommend\SwitchType',
        'topfacets' => 'VuFind\Recommend\TopFacets',
        'visualfacets' => 'VuFind\Recommend\VisualFacets',
        'webresults' => 'VuFind\Recommend\WebResults',
        'worldcatidentities' => 'VuFind\Recommend\WorldCatIdentities',
        'worldcatterms' => 'VuFind\Recommend\Deprecated',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Recommend\AlphaBrowseLink' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\AuthorFacets' =>
            'VuFind\Recommend\Factory::getAuthorFacets',
        'VuFind\Recommend\AuthorInfo' => 'VuFind\Recommend\Factory::getAuthorInfo',
        'VuFind\Recommend\AuthorityRecommend' =>
            'VuFind\Recommend\Factory::getAuthorityRecommend',
        'VuFind\Recommend\CatalogResults' =>
            'VuFind\Recommend\Factory::getCatalogResults',
        'VuFind\Recommend\Channels' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\CollectionSideFacets' =>
            'VuFind\Recommend\Factory::getCollectionSideFacets',
        'VuFind\Recommend\Deprecated' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\DOI' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\DPLATerms' => 'VuFind\Recommend\Factory::getDPLATerms',
        'VuFind\Recommend\EuropeanaResults' =>
            'VuFind\Recommend\Factory::getEuropeanaResults',
        'VuFind\Recommend\EuropeanaResultsDeferred' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\ExpandFacets' =>
            'VuFind\Recommend\Factory::getExpandFacets',
        'VuFind\Recommend\FacetCloud' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\FavoriteFacets' =>
            'VuFind\Recommend\Factory::getFavoriteFacets',
        'VuFind\Recommend\Libraryh3lp' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\MapSelection' =>
            'VuFind\Recommend\Factory::getMapSelection',
        'VuFind\Recommend\SideFacets' => 'VuFind\Recommend\Factory::getSideFacets',
        'VuFind\Recommend\OpenLibrarySubjects' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\OpenLibrarySubjectsDeferred' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\PubDateVisAjax' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\RandomRecommend' =>
            'VuFind\Recommend\Factory::getRandomRecommend',
        'VuFind\Recommend\RemoveFilters' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\SpellingSuggestions' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\SummonBestBets' =>
            'VuFind\Recommend\Factory::getSummonBestBets',
        'VuFind\Recommend\SummonBestBetsDeferred' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\SummonDatabases' =>
            'VuFind\Recommend\Factory::getSummonDatabases',
        'VuFind\Recommend\SummonDatabasesDeferred' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\SummonResults' =>
            'VuFind\Recommend\Factory::getSummonResults',
        'VuFind\Recommend\SummonResultsDeferred' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\SummonTopics' =>
            'VuFind\Recommend\Factory::getSummonTopics',
        'VuFind\Recommend\SwitchQuery' => 'VuFind\Recommend\Factory::getSwitchQuery',
        'VuFind\Recommend\SwitchType' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Recommend\TopFacets' => 'VuFind\Recommend\Factory::getTopFacets',
        'VuFind\Recommend\VisualFacets' =>
            'VuFind\Recommend\Factory::getVisualFacets',
        'VuFind\Recommend\WebResults' => 'VuFind\Recommend\Factory::getWebResults',
        'VuFind\Recommend\WorldCatIdentities' =>
            'VuFind\Recommend\Factory::getWorldCatIdentities',
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;

        $this->addAbstractFactory('VuFind\Recommend\PluginFactory');
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Recommend\RecommendInterface';
    }
}
