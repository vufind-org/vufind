<?php

/**
 * Registry for search backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search;

/**
 * Registry for search backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class BackendRegistry extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        // Allow Solr core names to be used as aliases for services:
        'authority' => 'SolrAuth',
        'biblio' => 'Solr',
        'reserves' => 'SolrReserves',
        // Legacy:
        'VuFind' => 'Solr',
        'WorldCat' => 'WorldCat2',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'Blender' => Factory\BlenderBackendFactory::class,
        'BrowZine' => Factory\BrowZineBackendFactory::class,
        'EDS' => Factory\EdsBackendFactory::class,
        'EIT' => Factory\EITBackendFactory::class,
        'EPF' => Factory\EPFBackendFactory::class,
        'LibGuides' => Factory\LibGuidesBackendFactory::class,
        'LibGuidesAZ' => Factory\LibGuidesAZBackendFactory::class,
        'Pazpar2' => Factory\Pazpar2BackendFactory::class,
        'Primo' => Factory\PrimoBackendFactory::class,
        'Search2' => Factory\Search2BackendFactory::class,
        'Search2Collection' => Factory\Search2BackendFactory::class,
        'Solr' => Factory\SolrDefaultBackendFactory::class,
        'SolrAuth' => Factory\SolrAuthBackendFactory::class,
        'SolrReserves' => Factory\SolrReservesBackendFactory::class,
        'SolrWeb' => Factory\SolrWebBackendFactory::class,
        'Summon' => Factory\SummonBackendFactory::class,
        'WorldCat2' => Factory\WorldCat2BackendFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFindSearch\Backend\BackendInterface::class;
    }
}
