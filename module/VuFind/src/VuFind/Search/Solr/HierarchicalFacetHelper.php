<?php
/**
 * Facet Helper
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Solr;

use VuFind\I18n\SorterAwareInterface;
use VuFind\I18n\SorterAwareTrait;
use VuFind\I18n\TranslatableString;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Search\Base\HierarchicalFacetHelperInterface;
use VuFind\Search\UrlQueryHelper;

/**
 * Functions for manipulating facets
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HierarchicalFacetHelper implements HierarchicalFacetHelperInterface,
    TranslatorAwareInterface, SorterAwareInterface
{
    use TranslatorAwareTrait;
    use SorterAwareTrait;

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
    public function sortFacetList(&$facetList, $order = null)
    {
        // We need a boolean flag indicating whether or not to sort only the top
        // level of the hierarchy. If we received a string configuration option,
        // we should set the flag accordingly (boolean values of $order are
        // supported for backward compatibility).
        $topLevel = $order ?? 'count';
        if (is_string($topLevel)) {
            switch (strtolower(trim($topLevel))) {
            case 'top':
                $topLevel = true;
                break;
            case 'all':
                $topLevel = false;
                break;
            case '':
            case 'count':
                // At present, we assume the incoming list is already sorted by
                // count, so no further action is needed. If in future we need
                // to support re-sorting an arbitrary list, rather than simply
                // operating on raw Solr values, we may need to implement logic.
                return;
            }
        }

        // Parse level from each facet value so that the sort function
        // can run faster
        foreach ($facetList as &$facetItem) {
            [$facetItem['level']] = explode('/', $facetItem['value'], 2);
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
                return $this->getSorter()->compare($aText, $bText);
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
    ) {
        // Create a keyed (for conversion to hierarchical) array of facet data
        $keyedList = [];
        foreach ($facetList as $item) {
            $keyedList[$item['value']] = $this->createFacetItem(
                $facet,
                $item,
                $urlHelper,
                $escape
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
                    $results,
                    $this->flattenFacetHierarchy($children)
                );
            }
        }
        return $results;
    }

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
    ) {
        $originalText = $displayText;
        $parts = explode('/', $displayText);
        if (count($parts) > 1 && is_numeric($parts[0])) {
            if (!$allLevels && isset($parts[$parts[0] + 1])) {
                $displayText = $parts[$parts[0] + 1];
            } else {
                array_shift($parts);
                array_pop($parts);

                if (false !== $domain) {
                    $translatedParts = [];
                    foreach ($parts as $part) {
                        $translatedParts[] = $this->translate([$domain, $part]);
                    }
                    $displayText = new TranslatableString(
                        implode($separator, $parts),
                        implode($separator, $translatedParts)
                    );
                } else {
                    $displayText = implode($separator, $parts);
                }
            }
        }
        return new TranslatableString($originalText, $displayText);
    }

    /**
     * Format a filter string in parts suitable for displaying or translation
     *
     * @param string $filter Filter value
     *
     * @return array
     */
    public function getFilterStringParts($filter)
    {
        $parts = explode('/', $filter);
        if (count($parts) <= 1 || !is_numeric($parts[0])) {
            return [new TranslatableString($filter, $filter)];
        }
        $result = [];
        for ($level = 0; $level <= $parts[0]; $level++) {
            $str = $level . '/' . implode('/', array_slice($parts, 1, $level + 1))
                . '/';
            $result[] = new TranslatableString($str, $parts[$level + 1]);
        }
        return $result;
    }

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
    public function isDeepestFacetLevel($facetList, $value)
    {
        $parts = explode('/', $value);
        $level = array_shift($parts);
        if (!is_numeric($level)) {
            // Not a properly formatted hierarchical facet value
            return true;
        }
        $path = implode('/', array_slice($parts, 0, $level + 1));
        foreach ($facetList as $current) {
            $parts = explode('/', $current);
            $currentLevel = array_shift($parts);
            if (is_numeric($currentLevel) && $currentLevel > $level) {
                // Check if parent is same
                if ($path === implode('/', array_slice($parts, 0, $level + 1))) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Create an item for the hierarchical facet array
     *
     * @param string         $facet     Facet name
     * @param array          $item      Facet item received from Solr
     * @param UrlQueryHelper $urlHelper UrlQueryHelper for creating facet URLs
     * @param bool           $escape    Whether to escape URLs
     *
     * @return array Facet item
     */
    protected function createFacetItem($facet, $item, $urlHelper, $escape = true)
    {
        $href = '';
        $exclude = '';
        // Build URLs only if we were given an URL helper
        if ($urlHelper !== false) {
            if ($item['isApplied']) {
                $href = $urlHelper->removeFacet(
                    $facet,
                    $item['value'],
                    $item['operator']
                )->getParams($escape);
            } else {
                $href = $urlHelper->addFacet(
                    $facet,
                    $item['value'],
                    $item['operator']
                )->getParams($escape);
            }
            $exclude = $urlHelper->addFacet($facet, $item['value'], 'NOT')
                ->getParams($escape);
        }

        $displayText = $item['displayText'];
        if ($displayText == $item['value']) {
            // Only show the current level part
            $displayText = $this->formatDisplayText($displayText)
                ->getDisplayString();
        }

        $parts = explode('/', $item['value'], 2);
        $level = $parts[0];
        $value = $parts[1] ?? $item['value'];
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
     * @return bool Whether any items are applied (for recursive calls)
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
