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

use Zend\ServiceManager\Factory\InvokableFactory;

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
        'alphabrowselink' => AlphaBrowseLink::class,
        'authorfacets' => AuthorFacets::class,
        'authorinfo' => AuthorInfo::class,
        'authorityrecommend' => AuthorityRecommend::class,
        'catalogresults' => CatalogResults::class,
        'channels' => Channels::class,
        'collectionsidefacets' => CollectionSideFacets::class,
        'doi' => DOI::class,
        'dplaterms' => DPLATerms::class,
        'europeanaresults' => EuropeanaResults::class,
        'europeanaresultsdeferred' => EuropeanaResultsDeferred::class,
        'expandfacets' => ExpandFacets::class,
        'facetcloud' => FacetCloud::class,
        'favoritefacets' => FavoriteFacets::class,
        'libraryh3lp' => Libraryh3lp::class,
        'mapselection' => MapSelection::class,
        'sidefacets' => SideFacets::class,
        'sidefacetsdeferred' => SideFacetsDeferred::class,
        'openlibrarysubjects' => OpenLibrarySubjects::class,
        'openlibrarysubjectsdeferred' => OpenLibrarySubjectsDeferred::class,
        'pubdatevisajax' => PubDateVisAjax::class,
        'randomrecommend' => RandomRecommend::class,
        'removefilters' => RemoveFilters::class,
        'resultgooglemapajax' => Deprecated::class,
        'spellingsuggestions' => SpellingSuggestions::class,
        'summonbestbets' => SummonBestBets::class,
        'summonbestbetsdeferred' => SummonBestBetsDeferred::class,
        'summondatabases' => SummonDatabases::class,
        'summondatabasesdeferred' => SummonDatabasesDeferred::class,
        'summonresults' => SummonResults::class,
        'summonresultsdeferred' => SummonResultsDeferred::class,
        'summontopics' => SummonTopics::class,
        'switchquery' => SwitchQuery::class,
        'switchtype' => SwitchType::class,
        'topfacets' => TopFacets::class,
        'visualfacets' => VisualFacets::class,
        'webresults' => WebResults::class,
        'worldcatidentities' => WorldCatIdentities::class,
        'worldcatterms' => Deprecated::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AlphaBrowseLink::class => InvokableFactory::class,
        AuthorFacets::class => InjectResultsManagerFactory::class,
        AuthorInfo::class => AuthorInfoFactory::class,
        AuthorityRecommend::class => InjectResultsManagerFactory::class,
        CatalogResults::class => InjectSearchRunnerFactory::class,
        Channels::class => InvokableFactory::class,
        CollectionSideFacets::class => CollectionSideFacetsFactory::class,
        Deprecated::class => InvokableFactory::class,
        DOI::class => InvokableFactory::class,
        DPLATerms::class => DPLATermsFactory::class,
        EuropeanaResults::class => EuropeanaResultsFactory::class,
        EuropeanaResultsDeferred::class => InvokableFactory::class,
        ExpandFacets::class => ExpandFacetsFactory::class,
        FacetCloud::class => InvokableFactory::class,
        FavoriteFacets::class => FavoriteFacetsFactory::class,
        Libraryh3lp::class => InvokableFactory::class,
        MapSelection::class => MapSelectionFactory::class,
        OpenLibrarySubjects::class => InvokableFactory::class,
        OpenLibrarySubjectsDeferred::class => InvokableFactory::class,
        PubDateVisAjax::class => InvokableFactory::class,
        RandomRecommend::class => RandomRecommendFactory::class,
        RemoveFilters::class => InvokableFactory::class,
        SideFacets::class => SideFacetsFactory::class,
        SideFacetsDeferred::class => InjectConfigManagerFactory::class,
        SpellingSuggestions::class => InvokableFactory::class,
        SummonBestBets::class => InjectResultsManagerFactory::class,
        SummonBestBetsDeferred::class => InvokableFactory::class,
        SummonDatabases::class => InjectResultsManagerFactory::class,
        SummonDatabasesDeferred::class => InvokableFactory::class,
        SummonResults::class => InjectSearchRunnerFactory::class,
        SummonResultsDeferred::class => InvokableFactory::class,
        SummonTopics::class => InjectResultsManagerFactory::class,
        SwitchQuery::class => SwitchQueryFactory::class,
        SwitchType::class => InvokableFactory::class,
        TopFacets::class => InjectConfigManagerFactory::class,
        VisualFacets::class => InjectConfigManagerFactory::class,
        WebResults::class => InjectSearchRunnerFactory::class,
        WorldCatIdentities::class => WorldCatIdentitiesFactory::class,
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

        $this->addAbstractFactory(PluginFactory::class);
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
        return RecommendInterface::class;
    }
}
