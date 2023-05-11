<?php

/**
 * Trait to add facet prefix and matches settings to a Params object.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\Search\Params;

use Laminas\Config\Config;

/**
 * Trait to add facet limiting settings to a Params object.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait FacetRestrictionsTrait
{
    /**
     * Per-field facet prefix
     *
     * @var array
     */
    protected $facetPrefixByField = [];

    /**
     * Per-field facet matches
     *
     * @var array
     */
    protected $facetMatchesByField = [];

    /**
     * Initialize facet prefix and matches from a Config object.
     *
     * @param Config $config Configuration
     *
     * @return void
     */
    protected function initFacetRestrictionsFromConfig(Config $config = null)
    {
        foreach ($config->facet_prefix_by_field ?? [] as $k => $v) {
            $this->facetPrefixByField[$k] = $v;
        }
        foreach ($config->facet_matches_by_field ?? [] as $k => $v) {
            $this->facetMatchesByField[$k] = $v;
        }
    }

    /**
     * Set Facet Prefix by Field
     *
     * @param array $new Associative array of $field name => $limit
     *
     * @return void
     */
    public function setFacetPrefixByField(array $new)
    {
        $this->facetPrefixByField = $new;
    }

    /**
     * Set Facet Matches by Field
     *
     * @param array $new Associative array of $field name => $limit
     *
     * @return void
     */
    public function setFacetMatchesByField(array $new)
    {
        $this->facetMatchesByField = $new;
    }

    /**
     * Get the facet prefix for the specified field.
     *
     * @param string $field Field to look up
     *
     * @return string
     */
    protected function getFacetPrefixForField($field)
    {
        $prefix = $this->facetPrefixByField[$field] ?? '';
        return $prefix;
    }

    /**
     * Get the facet matches for the specified field.
     *
     * @param string $field Field to look up
     *
     * @return string
     */
    protected function getFacetMatchesForField($field)
    {
        $matches = $this->facetMatchesByField[$field] ?? '';
        return $matches;
    }
}
