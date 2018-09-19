<?php
/**
 * Search options plugin manager
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
        'browzine' => 'VuFind\Search\BrowZine\Options',
        'combined' => 'VuFind\Search\Combined\Options',
        'eds' => 'VuFind\Search\EDS\Options',
        'eit' => 'VuFind\Search\EIT\Options',
        'emptyset' => 'VuFind\Search\EmptySet\Options',
        'favorites' => 'VuFind\Search\Favorites\Options',
        'libguides' => 'VuFind\Search\LibGuides\Options',
        'mixedlist' => 'VuFind\Search\MixedList\Options',
        'pazpar2' => 'VuFind\Search\Pazpar2\Options',
        'primo' => 'VuFind\Search\Primo\Options',
        'search2' => 'VuFind\Search\Search2\Options',
        'solr' => 'VuFind\Search\Solr\Options',
        'solrauth' => 'VuFind\Search\SolrAuth\Options',
        'solrauthor' => 'VuFind\Search\SolrAuthor\Options',
        'solrauthorfacets' => 'VuFind\Search\SolrAuthorFacets\Options',
        'solrcollection' => 'VuFind\Search\SolrCollection\Options',
        'solrreserves' => 'VuFind\Search\SolrReserves\Options',
        'solrweb' => 'VuFind\Search\SolrWeb\Options',
        'summon' => 'VuFind\Search\Summon\Options',
        'tags' => 'VuFind\Search\Tags\Options',
        'worldcat' => 'VuFind\Search\WorldCat\Options',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Search\BrowZine\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Combined\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\EDS\Options' => 'VuFind\Search\EDS\OptionsFactory',
        'VuFind\Search\EIT\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\EmptySet\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Favorites\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\LibGuides\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\MixedList\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Pazpar2\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Primo\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Search2\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Solr\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrAuth\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrAuthor\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrAuthorFacets\Options' =>
            'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrCollection\Options' =>
            'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrReserves\Options' =>
            'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\SolrWeb\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Summon\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\Tags\Options' => 'VuFind\Search\Options\OptionsFactory',
        'VuFind\Search\WorldCat\Options' => 'VuFind\Search\Options\OptionsFactory',
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
        $this->addAbstractFactory('VuFind\Search\Options\PluginFactory');
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
        return 'VuFind\Search\Base\Options';
    }
}
