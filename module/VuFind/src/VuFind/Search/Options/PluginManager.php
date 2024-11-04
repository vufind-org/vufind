<?php

/**
 * Search options plugin manager
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

namespace VuFind\Search\Options;

/**
 * Search options plugin manager
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
        'blender' => \VuFind\Search\Blender\Options::class,
        'browzine' => \VuFind\Search\BrowZine\Options::class,
        'combined' => \VuFind\Search\Combined\Options::class,
        'eds' => \VuFind\Search\EDS\Options::class,
        'eit' => \VuFind\Search\EIT\Options::class,
        'epf' => \VuFind\Search\EPF\Options::class,
        'emptyset' => \VuFind\Search\EmptySet\Options::class,
        'favorites' => \VuFind\Search\Favorites\Options::class,
        'libguides' => \VuFind\Search\LibGuides\Options::class,
        'libguidesaz' => \VuFind\Search\LibGuidesAZ\Options::class,
        'mixedlist' => \VuFind\Search\MixedList\Options::class,
        'pazpar2' => \VuFind\Search\Pazpar2\Options::class,
        'primo' => \VuFind\Search\Primo\Options::class,
        'search2' => \VuFind\Search\Search2\Options::class,
        'search2collection' => \VuFind\Search\Search2\Options::class,
        'solr' => \VuFind\Search\Solr\Options::class,
        'solrauth' => \VuFind\Search\SolrAuth\Options::class,
        'solrauthor' => \VuFind\Search\SolrAuthor\Options::class,
        'solrauthorfacets' => \VuFind\Search\SolrAuthorFacets\Options::class,
        'solrcollection' => \VuFind\Search\SolrCollection\Options::class,
        'solrreserves' => \VuFind\Search\SolrReserves\Options::class,
        'solrweb' => \VuFind\Search\SolrWeb\Options::class,
        'summon' => \VuFind\Search\Summon\Options::class,
        'tags' => \VuFind\Search\Tags\Options::class,
        'worldcat' => \VuFind\Search\WorldCat\Options::class,
        'worldcat2' => \VuFind\Search\WorldCat2\Options::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        \VuFind\Search\Blender\Options::class => OptionsFactory::class,
        \VuFind\Search\BrowZine\Options::class => OptionsFactory::class,
        \VuFind\Search\Combined\Options::class => \VuFind\Search\Combined\OptionsFactory::class,
        \VuFind\Search\EDS\Options::class =>
            \VuFind\Search\EDS\OptionsFactory::class,
        \VuFind\Search\EIT\Options::class => OptionsFactory::class,
        \VuFind\Search\EPF\Options::class => OptionsFactory::class,
        \VuFind\Search\EmptySet\Options::class => OptionsFactory::class,
        \VuFind\Search\Favorites\Options::class => OptionsFactory::class,
        \VuFind\Search\LibGuides\Options::class => OptionsFactory::class,
        \VuFind\Search\LibGuidesAZ\Options::class => OptionsFactory::class,
        \VuFind\Search\MixedList\Options::class => OptionsFactory::class,
        \VuFind\Search\Pazpar2\Options::class => OptionsFactory::class,
        \VuFind\Search\Primo\Options::class => OptionsFactory::class,
        \VuFind\Search\Search2\Options::class => OptionsFactory::class,
        \VuFind\Search\Search2Collection\Options::class => OptionsFactory::class,
        \VuFind\Search\Solr\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrAuth\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrAuthor\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrAuthorFacets\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrCollection\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrReserves\Options::class => OptionsFactory::class,
        \VuFind\Search\SolrWeb\Options::class => OptionsFactory::class,
        \VuFind\Search\Summon\Options::class => OptionsFactory::class,
        \VuFind\Search\Tags\Options::class => OptionsFactory::class,
        \VuFind\Search\WorldCat\Options::class => OptionsFactory::class,
        \VuFind\Search\WorldCat2\Options::class => OptionsFactory::class,
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
        return \VuFind\Search\Base\Options::class;
    }
}
