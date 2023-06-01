<?php

/**
 * Hierarchical facet helper interface
 *
 * Copyright (C) The National Library of Finland 2022.
 *
 * PHP version 8
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
 * @package  Search_Base
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

/**
 * Hierarchical facet helper interface
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface HierarchicalFacetHelperInterface
{
    /**
     * Helper method for building hierarchical facets:
     * Sort a facet list according to the given sort order
     *
     * @param array          $facetList Facet list returned from Solr
     * @param boolean|string $order     Sort order:
     * - true|top  sort top level alphabetically and the rest by count
     * - false|all sort all levels alphabetically
     * - count     sort all levels by count
     *
     * @return void
     */
    public function sortFacetList(&$facetList, $order = null);

    /**
     * Helper method for building hierarchical facets:
     * Convert facet list to a hierarchical array
     *
     * @param string    $facet     Facet name
     * @param array     $facetList Facet list
     * @param UrlHelper $urlHelper Query URL helper for building facet URLs
     * @param bool      $escape    Whether to escape URLs
     *
     * @return array Facet hierarchy
     *
     * @see http://blog.tekerson.com/2009/03/03/
     * converting-a-flat-array-with-parent-ids-to-a-nested-tree/
     * Based on this example
     */
    public function buildFacetArray(
        $facet,
        $facetList,
        $urlHelper = false,
        $escape = true
    );

    /**
     * Flatten a hierarchical facet list to a simple array
     *
     * @param array $facetList Facet list
     *
     * @return array Simple array of facets
     */
    public function flattenFacetHierarchy($facetList);

    /**
     * Format a facet display text for displaying
     *
     * @param string       $displayText Display text
     * @param bool         $allLevels   Whether to display all levels or only the
     * current one
     * @param string       $separator   Separator string displayed between levels
     * @param string|false $domain      Translation domain for default translations
     * of a multilevel string or false to omit translation
     *
     * @return TranslatableString Formatted text
     */
    public function formatDisplayText(
        $displayText,
        $allLevels = false,
        $separator = '/',
        $domain = false
    );

    /**
     * Format a filter string in parts suitable for displaying or translation
     *
     * @param string $filter Filter value
     *
     * @return array
     */
    public function getFilterStringParts($filter);

    /**
     * Check if the given value is the deepest level in the facet list.
     *
     * Takes into account lists with multiple top levels.
     *
     * @param array  $facetList Facet list
     * @param string $value     Facet value
     *
     * @return bool
     */
    public function isDeepestFacetLevel($facetList, $value);
}
