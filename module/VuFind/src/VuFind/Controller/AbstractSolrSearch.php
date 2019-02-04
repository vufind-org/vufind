<?php
/**
 * AbstractSearch with Solr-specific features added.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

/**
 * AbstractSearch with Solr-specific features added.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AbstractSolrSearch extends AbstractSearch
{
    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        $view = parent::advancedAction();

        // Set up facet information:
        $facets = $this->serviceLocator
            ->get(\VuFind\Search\FacetCache\PluginManager::class)
            ->get($this->searchClassId)
            ->getList('Advanced');
        $view->hierarchicalFacets
            = $this->getHierarchicalFacets($view->options->getFacetsIni());
        $view->facetList = $this->processAdvancedFacets(
            $facets, $view->saved, $view->hierarchicalFacets
        );
        $specialFacets = $this->parseSpecialFacetsSetting(
            $view->options->getSpecialAdvancedFacets()
        );
        if (isset($specialFacets['illustrated'])) {
            $view->illustratedLimit
                = $this->getIllustrationSettings($view->saved);
        }
        if (isset($specialFacets['checkboxes'])) {
            $view->checkboxFacets = $this->processAdvancedCheckboxes(
                $specialFacets['checkboxes'], $view->saved
            );
        }
        $view->ranges = $this->getAllRangeSettings($specialFacets, $view->saved);

        return $view;
    }

    /**
     * Get the possible legal values for the illustration limit radio buttons.
     *
     * @param object $savedSearch Saved search object (false if none)
     *
     * @return array              Legal options, with selected value flagged.
     */
    protected function getIllustrationSettings($savedSearch = false)
    {
        $illYes = [
            'text' => 'Has Illustrations', 'value' => 1, 'selected' => false
        ];
        $illNo = [
            'text' => 'Not Illustrated', 'value' => 0, 'selected' => false
        ];
        $illAny = [
            'text' => 'No Preference', 'value' => -1, 'selected' => false
        ];

        // Find the selected value by analyzing facets -- if we find match, remove
        // the offending facet to avoid inappropriate items appearing in the
        // "applied filters" sidebar!
        if ($savedSearch
            && $savedSearch->getParams()->hasFilter('illustrated:Illustrated')
        ) {
            $illYes['selected'] = true;
            $savedSearch->getParams()->removeFilter('illustrated:Illustrated');
        } elseif ($savedSearch
            && $savedSearch->getParams()->hasFilter('illustrated:"Not Illustrated"')
        ) {
            $illNo['selected'] = true;
            $savedSearch->getParams()->removeFilter('illustrated:"Not Illustrated"');
        } else {
            $illAny['selected'] = true;
        }
        return [$illYes, $illNo, $illAny];
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList          The advanced facet values
     * @param object $searchObject       Saved search object (false if none)
     * @param array  $hierarchicalFacets Hierarchical facet list (if any)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false,
        $hierarchicalFacets = []
    ) {
        // Process the facets
        $facetHelper = null;
        if (!empty($hierarchicalFacets)) {
            $facetHelper = $this->serviceLocator
                ->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class);
        }
        foreach ($facetList as $facet => &$list) {
            // Hierarchical facets: format display texts and sort facets
            // to a flat array according to the hierarchy
            if (in_array($facet, $hierarchicalFacets)) {
                $tmpList = $list['list'];
                $facetHelper->sortFacetList($tmpList, true);
                $tmpList = $facetHelper->buildFacetArray(
                    $facet,
                    $tmpList
                );
                $list['list'] = $facetHelper->flattenFacetHierarchy($tmpList);
            }

            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
                    . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject
                    && $searchObject->getParams()->hasFilter($fullFilter)
                ) {
                    $list['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }

    /**
     * Get an array of hierarchical facets
     *
     * @param string $config Name of facet configuration file to load.
     *
     * @return array Facets
     */
    protected function getHierarchicalFacets($config)
    {
        $facetConfig = $this->getConfig($config);
        return isset($facetConfig->SpecialFacets->hierarchical)
            ? $facetConfig->SpecialFacets->hierarchical->toArray()
            : [];
    }
}
