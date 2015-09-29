<?php
/**
 * Facet Helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use VuFind\I18n\TranslatableString;

/**
 * Functions for manipulating facets
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class HierarchicalFacetHelper
{
    /**
     * Helper method for building hierarchical facets:
     * Sort a facet list according to the given sort order
     *
     * @param array $facetList Facet list returned from Solr
     * @param bool  $topLevel  Whether to sort only top level
     *
     * @return void
     */
    public function sortFacetList(&$facetList, $topLevel)
    {
        // Parse level from each facet value so that the sort function
        // can run faster
        foreach ($facetList as &$facetItem) {
            list($facetItem['level']) = explode('/', $facetItem['value'], 2);
            if (!is_numeric($facetItem['level'])) {
                $facetItem['level'] = 0;
            }
        }
        // Avoid problems having the reference set further below
        unset($facetItem);
        $sortFunc = function ($a, $b) use ($topLevel) {
            if ($a['level'] == $b['level'] && (!$topLevel || $a['level'] == 0)) {
                $aText = $a['displayText'] == $a['value']
                    ? $this->formatDisplayText($a['displayText'])
                    : $a['displayText'];
                $bText = $b['displayText'] == $b['value']
                    ? $this->formatDisplayText($b['displayText'])
                    : $b['displayText'];
                return strcasecmp($aText, $bText);
            }
            return $a['level'] == $b['level']
                ? $b['count'] - $a['count']
                : $a['level'] - $b['level'];
        };
        usort($facetList, $sortFunc);
    }

    /**
     * Helper method for building hierarchical facets:
     * Convert facet list to a hierarchical array
     *
     * @param string    $facet     Facet name
     * @param array     $facetList Facet list
     * @param UrlHelper $urlHelper Query URL helper for building facet URLs
     *
     * @return array Facet hierarchy
     *
     * @see http://blog.tekerson.com/2009/03/03/
     * converting-a-flat-array-with-parent-ids-to-a-nested-tree/
     * Based on this example
     */
    public function buildFacetArray($facet, $facetList, $urlHelper = false)
    {
        // getParamArray() is expensive, so call it just once and pass it on
        $paramArray = $urlHelper !== false ? $urlHelper->getParamArray() : null;
        // Create a keyed (for conversion to hierarchical) array of facet data
        $keyedList = [];
        foreach ($facetList as $item) {
            $keyedList[$item['value']] = $this->createFacetItem(
                $facet, $item, $urlHelper, $paramArray
            );
        }

        // Convert the keyed array to a hierarchical array
        $result = [];
        foreach ($keyedList as &$item) {
            if ($item['level'] > 0) {
                $keyedList[$item['parent']]['children'][] = &$item;
            } else {
                $result[] = &$item;
            }
        }

        // Update information on whether items have applied children
        $this->updateAppliedChildrenStatus($result);

        return $result;
    }

    /**
     * Flatten a hierarchical facet list to a simple array
     *
     * @param array $facetList Facet list
     *
     * @return array Simple array of facets
     */
    public function flattenFacetHierarchy($facetList)
    {
        $results = [];
        foreach ($facetList as $facetItem) {
            $children = !empty($facetItem['children'])
                ? $facetItem['children']
                : [];
            unset($facetItem['children']);
            $results[] = $facetItem;
            if ($children) {
                $results = array_merge(
                    $results, $this->flattenFacetHierarchy($children)
                );
            }
        }
        return $results;
    }

    /**
     * Format a facet display text for displaying
     *
     * @param string $displayText Display text
     * @param bool   $allLevels   Whether to display all levels or only
     * the current one
     * @param string $separator   Separator string displayed between levels
     *
     * @return TranslatableString Formatted text
     */
    public function formatDisplayText(
        $displayText, $allLevels = false, $separator = '/'
    ) {
        $originalText = $displayText;
        $parts = explode('/', $displayText);
        if (count($parts) > 1 && is_numeric($parts[0])) {
            if (!$allLevels && isset($parts[$parts[0] + 1])) {
                $displayText = $parts[$parts[0] + 1];
            } else {
                array_shift($parts);
                array_pop($parts);
                $displayText = implode($separator, $parts);
            }
        }
        return new TranslatableString($originalText, $displayText);
    }

    /**
     * Create an item for the hierarchical facet array
     *
     * @param string         $facet      Facet name
     * @param array          $item       Facet item received from Solr
     * @param UrlQueryHelper $urlHelper  UrlQueryHelper for creating facet
     * url's
     * @param array          $paramArray URL parameters
     * active children
     *
     * @return array Facet item
     */
    protected function createFacetItem($facet, $item, $urlHelper, $paramArray)
    {
        $href = '';
        $exclude = '';
        // Build URLs only if we were given an URL helper
        if ($urlHelper !== false) {
            if ($item['isApplied']) {
                $href = $urlHelper->removeFacet(
                    $facet, $item['value'], true, $item['operator'], $paramArray
                );
            } else {
                $href = $urlHelper->addFacet(
                    $facet, $item['value'], $item['operator'], $paramArray
                );
            }
            $exclude = $urlHelper->addFacet(
                $facet, $item['value'], 'NOT', $paramArray
            );
        }

        $displayText = $item['displayText'];
        if ($displayText == $item['value']) {
            // Only show the current level part
            $displayText = $this->formatDisplayText($displayText)
                ->getDisplayString();
        }

        list($level, $value) = explode('/', $item['value'], 2);
        if (!is_numeric($level)) {
            $level = 0;
        }
        $parent = null;
        if ($level > 0) {
            $parent = ($level - 1) . '/' . implode(
                '/',
                array_slice(
                    explode('/', $value),
                    0,
                    $level
                )
            ) . '/';
        }

        $item['level'] = $level;
        $item['parent'] = $parent;
        $item['displayText'] = $displayText;
        // hasAppliedChildren is updated in updateAppliedChildrenStatus
        $item['hasAppliedChildren'] = false;
        $item['href'] = $href;
        $item['exclude'] = $exclude;
        $item['children'] = [];

        return $item;
    }

    /**
     * Update 'opened' of all facet items
     *
     * @param array $list Facet list
     *
     * @return boolean Whether any items are applied (for recursive calls)
     */
    protected function updateAppliedChildrenStatus($list)
    {
        $result = false;
        foreach ($list as &$item) {
            $item['hasAppliedChildren'] = !empty($item['children'])
                && $this->updateAppliedChildrenStatus($item['children']);
            if ($item['isApplied'] || $item['hasAppliedChildren']) {
                $result = true;
            }
        }
        return $result;
    }
}
