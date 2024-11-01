<?php

/**
 * Search results plugin manager
 *
 * PHP version 8
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\Search\Results;

/**
 * Search results plugin manager
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'blender' => \VuFind\Search\Blender\Results::class,
        'browzine' => \VuFind\Search\BrowZine\Results::class,
        'combined' => \VuFind\Search\Combined\Results::class,
        'eds' => \VuFind\Search\EDS\Results::class,
        'eit' => \VuFind\Search\EIT\Results::class,
        'epf' => \VuFind\Search\EPF\Results::class,
        'emptyset' => \VuFind\Search\EmptySet\Results::class,
        'favorites' => \VuFind\Search\Favorites\Results::class,
        'libguides' => \VuFind\Search\LibGuides\Results::class,
        'libguidesaz' => \VuFind\Search\LibGuidesAZ\Results::class,
        'mixedlist' => \VuFind\Search\MixedList\Results::class,
        'pazpar2' => \VuFind\Search\Pazpar2\Results::class,
        'primo' => \VuFind\Search\Primo\Results::class,
        'search2' => \VuFind\Search\Search2\Results::class,
        'search2collection' => \VuFind\Search\Search2Collection\Results::class,
        'solr' => \VuFind\Search\Solr\Results::class,
        'solrauth' => \VuFind\Search\SolrAuth\Results::class,
        'solrauthor' => \VuFind\Search\SolrAuthor\Results::class,
        'solrauthorfacets' => \VuFind\Search\SolrAuthorFacets\Results::class,
        'solrcollection' => \VuFind\Search\SolrCollection\Results::class,
        'solrreserves' => \VuFind\Search\SolrReserves\Results::class,
        'solrweb' => \VuFind\Search\SolrWeb\Results::class,
        'summon' => \VuFind\Search\Summon\Results::class,
        'tags' => \VuFind\Search\Tags\Results::class,
        'worldcat' => \VuFind\Search\WorldCat\Results::class,
        'worldcat2' => \VuFind\Search\WorldCat2\Results::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        \VuFind\Search\Blender\Results::class
            => \VuFind\Search\Solr\ResultsFactory::class,
        \VuFind\Search\BrowZine\Results::class => ResultsFactory::class,
        \VuFind\Search\Combined\Results::class => ResultsFactory::class,
        \VuFind\Search\EDS\Results::class => ResultsFactory::class,
        \VuFind\Search\EIT\Results::class => ResultsFactory::class,
        \VuFind\Search\EPF\Results::class => ResultsFactory::class,
        \VuFind\Search\EmptySet\Results::class => ResultsFactory::class,
        \VuFind\Search\Favorites\Results::class =>
            \VuFind\Search\Favorites\ResultsFactory::class,
        \VuFind\Search\LibGuides\Results::class => ResultsFactory::class,
        \VuFind\Search\LibGuidesAZ\Results::class => ResultsFactory::class,
        \VuFind\Search\MixedList\Results::class => ResultsFactory::class,
        \VuFind\Search\Pazpar2\Results::class => ResultsFactory::class,
        \VuFind\Search\Primo\Results::class => ResultsFactory::class,
        \VuFind\Search\Search2\Results::class =>
            \VuFind\Search\Search2\ResultsFactory::class,
        \VuFind\Search\Search2Collection\Results::class => ResultsFactory::class,
        \VuFind\Search\Solr\Results::class =>
            \VuFind\Search\Solr\ResultsFactory::class,
        \VuFind\Search\SolrAuth\Results::class => ResultsFactory::class,
        \VuFind\Search\SolrAuthor\Results::class =>
            \VuFind\Search\Solr\ResultsFactory::class,
        \VuFind\Search\SolrAuthorFacets\Results::class => ResultsFactory::class,
        \VuFind\Search\SolrCollection\Results::class => ResultsFactory::class,
        \VuFind\Search\SolrReserves\Results::class => ResultsFactory::class,
        \VuFind\Search\SolrWeb\Results::class => ResultsFactory::class,
        \VuFind\Search\Summon\Results::class => ResultsFactory::class,
        \VuFind\Search\Tags\Results::class =>
            \VuFind\Search\Tags\ResultsFactory::class,
        \VuFind\Search\WorldCat\Results::class => ResultsFactory::class,
        \VuFind\Search\WorldCat2\Results::class => ResultsFactory::class,
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
    public function __construct(
        $configOrContainerInstance = null,
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
        return \VuFind\Search\Base\Results::class;
    }
}
