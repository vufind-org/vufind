<?php
/**
 * Search results plugin manager
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
        'browzine' => 'VuFind\Search\BrowZine\Results',
        'combined' => 'VuFind\Search\Combined\Results',
        'eds' => 'VuFind\Search\EDS\Results',
        'eit' => 'VuFind\Search\EIT\Results',
        'emptyset' => 'VuFind\Search\EmptySet\Results',
        'favorites' => 'VuFind\Search\Favorites\Results',
        'libguides' => 'VuFind\Search\LibGuides\Results',
        'mixedlist' => 'VuFind\Search\MixedList\Results',
        'pazpar2' => 'VuFind\Search\Pazpar2\Results',
        'primo' => 'VuFind\Search\Primo\Results',
        'search2' => 'VuFind\Search\Search2\Results',
        'solr' => 'VuFind\Search\Solr\Results',
        'solrauth' => 'VuFind\Search\SolrAuth\Results',
        'solrauthor' => 'VuFind\Search\SolrAuthor\Results',
        'solrauthorfacets' => 'VuFind\Search\SolrAuthorFacets\Results',
        'solrcollection' => 'VuFind\Search\SolrCollection\Results',
        'solrreserves' => 'VuFind\Search\SolrReserves\Results',
        'solrweb' => 'VuFind\Search\SolrWeb\Results',
        'summon' => 'VuFind\Search\Summon\Results',
        'tags' => 'VuFind\Search\Tags\Results',
        'worldcat' => 'VuFind\Search\WorldCat\Results',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Search\BrowZine\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Combined\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\EDS\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\EIT\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\EmptySet\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Favorites\Results' =>
            'VuFind\Search\Favorites\ResultsFactory',
        'VuFind\Search\LibGuides\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\MixedList\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Pazpar2\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Primo\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Search2\Results' => 'VuFind\Search\Search2\ResultsFactory',
        'VuFind\Search\Solr\Results' => 'VuFind\Search\Solr\ResultsFactory',
        'VuFind\Search\SolrAuth\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\SolrAuthor\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\SolrAuthorFacets\Results' =>
            'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\SolrCollection\Results' =>
            'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\SolrReserves\Results' =>
            'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\SolrWeb\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Summon\Results' => 'VuFind\Search\Results\ResultsFactory',
        'VuFind\Search\Tags\Results' => 'VuFind\Search\Tags\ResultsFactory',
        'VuFind\Search\WorldCat\Results' => 'VuFind\Search\Results\ResultsFactory',
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

        $this->addAbstractFactory('VuFind\Search\Results\PluginFactory');
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
        return 'VuFind\Search\Base\Results';
    }
}
