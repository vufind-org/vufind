<?php

/**
 * Autocomplete handler plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace VuFind\Autocomplete;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Autocomplete handler plugin manager
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'none' => None::class,
        'eds' => Eds::class,
        'oclcidentities' => None::class,
        'search2' => Search2::class,
        'search2cn' => Search2CN::class,
        'solr' => Solr::class,
        'solrauth' => SolrAuth::class,
        'solrcn' => SolrCN::class,
        'solrreserves' => SolrReserves::class,
        'tag' => Tag::class,
        'solrprefix' => SolrPrefix::class,
        // for legacy 1.x compatibility
        'noautocomplete' => 'None',
        'oclcidentitiesautocomplete' => 'None',
        'solrautocomplete' => 'Solr',
        'solrauthautocomplete' => 'SolrAuth',
        'solrcnautocomplete' => 'SolrCN',
        'solrreservesautocomplete' => 'SolrReserves',
        'tagautocomplete' => 'Tag',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        None::class => InvokableFactory::class,
        Eds::class => EdsFactory::class,
        Search2::class => SolrFactory::class,
        Search2CN::class => SolrFactory::class,
        Solr::class => SolrFactory::class,
        SolrAuth::class => SolrFactory::class,
        SolrCN::class => SolrFactory::class,
        SolrReserves::class => SolrFactory::class,
        Tag::class => TagFactory::class,
        SolrPrefix::class => SolrFactory::class,
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
        return AutocompleteInterface::class;
    }
}
