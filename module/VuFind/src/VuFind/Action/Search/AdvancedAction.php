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
        $saved = $templateParams['saved'];
        $specialFacets = $this->parseSpecialFacetsSetting($templateParams['options']->getSpecialAdvancedFacets());
        if (isset($specialFacets['illustrated'])) {
            $templateParams['illustratedLimit'] = $this->getIllustrationSettings($saved);
        }
        if (isset($specialFacets['checkboxes'])) {
            $templateParams['checkboxFacets'] = $this->processAdvancedCheckboxes($specialFacets['checkboxes'], $saved);
        }
        $templateParams['ranges'] = $this->getAllRangeSettings($specialFacets, $saved);

        return $templateParams;
    }

    /**
     * Get the possible legal values for the illustration limit radio buttons.
     *
     * @param ?Results $savedSearch Saved search object, or null if none
     *
     * @return array Legal options, with selected value flagged.
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

        // Find the selected value by analyzing facets -- if we find match, remove the offending facet to avoid
        // inappropriate items appearing in the "applied filters" sidebar!
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
