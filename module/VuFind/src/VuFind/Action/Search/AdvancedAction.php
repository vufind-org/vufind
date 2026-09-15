<?php

/**
 * Advanced search action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Search;

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Search\Base\Results;

use function in_array;

/**
 * Advanced search action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdvancedAction extends AbstractSearchAndResultsFacetingAction
{
    /**
     * Display advanced search form.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $this->renderAdvancedSearch();
    }

    /**
     * Create an array of template parameters.
     *
     * @param array $params Parameters to pass to template renderer.
     *
     * @return array
     */
    protected function createTemplateParams(array $params = []): array
    {
        $templateParams = parent::createTemplateParams($params);

        // Set up facet information:
        $templateParams = $this->addFacetDetails($templateParams);
        $specialFacets = $this->parseSpecialFacetsSetting($templateParams['options']->getSpecialAdvancedFacets());
        if (isset($specialFacets['illustrated'])) {
            $templateParams['illustratedLimit'] = $this->getIllustrationSettings($templateParams['saved'] ?: null);
        }
        if (isset($specialFacets['checkboxes'])) {
            $templateParams['checkboxFacets'] = $this->processAdvancedCheckboxes(
                $specialFacets['checkboxes'],
                ($templateParams['saved'] ?? null) ?: null
            );
        }
        $templateParams['ranges'] = $this->getAllRangeSettings($specialFacets, $templateParams['saved'] ?: null);

        return $templateParams;
    }

    /**
     * Get an array of hierarchical facets.
     *
     * @param string $config Name of facet configuration file to load.
     *
     * @return array Facets
     */
    protected function getHierarchicalFacets($config)
    {
        $facetConfig = $this->configManager->getConfigArray($config);
        return $facetConfig['SpecialFacets']['hierarchical'] ?? [];
    }

    /**
     * Get an array of hierarchical facet sort options for Advanced search.
     *
     * @param string $config Name of facet configuration file to load.
     *
     * @return array
     */
    protected function getAdvancedHierarchicalFacetsSortOptions($config)
    {
        $facetConfig = $this->configManager->getConfigArray($config);
        $baseConfig = $facetConfig['SpecialFacets']['hierarchicalFacetSortOptions'] ?? [];
        $advancedConfig = $facetConfig['Advanced_Settings']['hierarchicalFacetSortOptions'] ?? [];
        return array_merge($baseConfig, $advancedConfig);
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array    $facetList                     The advanced facet values
     * @param ?Results $searchObject                  Saved search object, or null if none
     * @param array    $hierarchicalFacets            Hierarchical facet list (if any)
     * @param array    $hierarchicalFacetsSortOptions Hierarchical facet sort options
     *                                                (if any)
     *
     * @return array Sorted facets, with selected values flagged.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function processAdvancedFacets(
        array $facetList,
        ?Results $searchObject = null,
        array $hierarchicalFacets = [],
        array $hierarchicalFacetsSortOptions = []
    ): array {
        $options = null;
        foreach ($facetList as $facet => &$list) {
            // Hierarchical facets: format display texts and sort facets
            // to a flat array according to the hierarchy
            if (in_array($facet, $hierarchicalFacets)) {
                // Process the facets
                if (!$options) {
                    $options = $this->getOptionsForClass();
                }

                $tmpList = $list['list'];
                if ($options->getFilterHierarchicalFacetsInAdvanced()) {
                    $tmpList = $this->hierarchicalFacetHelper->filterFacets(
                        $facet,
                        $tmpList,
                        $options
                    );
                }
                $list['list'] = $this->hierarchicalFacetHelper->flattenFacetHierarchy($tmpList);
            }

            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
                    . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject?->getParams()->hasFilter($fullFilter)) {
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
     * Get the possible legal values for the illustration limit radio buttons.
     *
     * @param ?Results $savedSearch Saved search object, or null if none
     *
     * @return array              Legal options, with selected value flagged.
     */
    protected function getIllustrationSettings(?Results $savedSearch = null): array
    {
        $illYes = [
            'text' => 'Has Illustrations', 'value' => 1, 'selected' => false,
        ];
        $illNo = [
            'text' => 'Not Illustrated', 'value' => 0, 'selected' => false,
        ];
        $illAny = [
            'text' => 'No Preference', 'value' => -1, 'selected' => false,
        ];

        // Find the selected value by analyzing facets -- if we find match, remove
        // the offending facet to avoid inappropriate items appearing in the
        // "applied filters" sidebar!
        $params = $savedSearch?->getParams();
        if ($params?->hasFilter('illustrated:Illustrated')) {
            $illYes['selected'] = true;
            $params->removeFilter('illustrated:Illustrated');
        } elseif ($params?->hasFilter('illustrated:"Not Illustrated"')) {
            $illNo['selected'] = true;
            $params->removeFilter('illustrated:"Not Illustrated"');
        } else {
            $illAny['selected'] = true;
        }
        return [$illYes, $illNo, $illAny];
    }
}
