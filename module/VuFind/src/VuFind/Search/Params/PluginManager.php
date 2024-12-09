<?php

/**
 * Search params plugin manager
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

namespace VuFind\Search\Params;

/**
 * Search params plugin manager
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
        'blender' => \VuFind\Search\Blender\Params::class,
        'browzine' => \VuFind\Search\BrowZine\Params::class,
        'combined' => \VuFind\Search\Combined\Params::class,
        'eds' => \VuFind\Search\EDS\Params::class,
        'eit' => \VuFind\Search\EIT\Params::class,
        'epf' => \VuFind\Search\EPF\Params::class,
        'emptyset' => \VuFind\Search\EmptySet\Params::class,
        'favorites' => \VuFind\Search\Favorites\Params::class,
        'libguides' => \VuFind\Search\LibGuides\Params::class,
        'libguidesaz' => \VuFind\Search\LibGuidesAZ\Params::class,
        'mixedlist' => \VuFind\Search\MixedList\Params::class,
        'pazpar2' => \VuFind\Search\Pazpar2\Params::class,
        'primo' => \VuFind\Search\Primo\Params::class,
        'search2' => \VuFind\Search\Search2\Params::class,
        'solr' => \VuFind\Search\Solr\Params::class,
        'solrauth' => \VuFind\Search\SolrAuth\Params::class,
        'solrauthor' => \VuFind\Search\SolrAuthor\Params::class,
        'solrauthorfacets' => \VuFind\Search\SolrAuthorFacets\Params::class,
        'solrcollection' => \VuFind\Search\SolrCollection\Params::class,
        'solrreserves' => \VuFind\Search\SolrReserves\Params::class,
        'solrweb' => \VuFind\Search\SolrWeb\Params::class,
        'summon' => \VuFind\Search\Summon\Params::class,
        'tags' => \VuFind\Search\Tags\Params::class,
        'worldcat' => \VuFind\Search\WorldCat\Params::class,
        'worldcat2' => \VuFind\Search\WorldCat2\Params::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        \VuFind\Search\Blender\Params::class
            => \VuFind\Search\Blender\ParamsFactory::class,
        \VuFind\Search\BrowZine\Params::class => ParamsFactory::class,
        \VuFind\Search\Combined\Params::class => ParamsFactory::class,
        \VuFind\Search\EDS\Params::class => ParamsFactory::class,
        \VuFind\Search\EIT\Params::class => ParamsFactory::class,
        \VuFind\Search\EPF\Params::class => ParamsFactory::class,
        \VuFind\Search\EmptySet\Params::class => ParamsFactory::class,
        \VuFind\Search\Favorites\Params::class => ParamsFactory::class,
        \VuFind\Search\LibGuides\Params::class => ParamsFactory::class,
        \VuFind\Search\LibGuidesAZ\Params::class => ParamsFactory::class,
        \VuFind\Search\MixedList\Params::class => ParamsFactory::class,
        \VuFind\Search\Pazpar2\Params::class => ParamsFactory::class,
        \VuFind\Search\Primo\Params::class => ParamsFactory::class,
        \VuFind\Search\Search2\Params::class =>
            \VuFind\Search\Solr\ParamsFactory::class,
        \VuFind\Search\Solr\Params::class =>
            \VuFind\Search\Solr\ParamsFactory::class,
        \VuFind\Search\SolrAuth\Params::class => ParamsFactory::class,
        \VuFind\Search\SolrAuthor\Params::class => ParamsFactory::class,
        \VuFind\Search\SolrAuthorFacets\Params::class =>  ParamsFactory::class,
        \VuFind\Search\SolrCollection\Params::class => ParamsFactory::class,
        \VuFind\Search\SolrReserves\Params::class => ParamsFactory::class,
        \VuFind\Search\SolrWeb\Params::class => ParamsFactory::class,
        \VuFind\Search\Summon\Params::class => ParamsFactory::class,
        \VuFind\Search\Tags\Params::class => ParamsFactory::class,
        \VuFind\Search\WorldCat\Params::class => ParamsFactory::class,
        \VuFind\Search\WorldCat2\Params::class => ParamsFactory::class,
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
        return \VuFind\Search\Base\Params::class;
    }
}
