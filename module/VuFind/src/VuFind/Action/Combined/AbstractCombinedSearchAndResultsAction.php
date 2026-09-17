<?php

/**
 * Abstract base class for combined search actions.
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

namespace VuFind\Action\Combined;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Search\AbstractSearchAndResultsAction;

use function count;
use function is_array;

/**
 * Abstract base class for combined search actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCombinedSearchAndResultsAction extends AbstractSearchAndResultsAction
{
    /**
     * Adjust the query context to reflect the current settings.
     *
     * @param ServerRequestInterface $request    Request to adjust
     * @param array                  $settings   Settings
     * @param string                 $searchType Override for search handler name
     *
     * @return ServerRequestInterface
     */
    protected function adjustQueryForSettings(
        ServerRequestInterface $request,
        $settings,
        $searchType = null
    ): ServerRequestInterface {
        // Apply limit setting, if any:
        $query = $request->getQueryParams();
        $query['limit'] = $settings['limit'] ?? null;

        // Disable default filters, if requested:
        if ($settings['disable_default_filters'] ?? false) {
            $query['dfApplied'] = 1;
        }

        // Apply filters, if any:
        $query['filter'] = isset($settings['filter'])
            ? (array)$settings['filter'] : null;

        // Apply hidden filters, if any:
        $query['hiddenFilters'] = isset($settings['hiddenFilter'])
            ? (array)$settings['hiddenFilter'] : null;

        // Apply shards, if any:
        $query['shard'] = isset($settings['shard'])
            ? (array)$settings['shard'] : null;

        // Reset override to avoid bleed-over from one section to the next!
        $query['recommendOverride'] = false;

        // Always disable 'jumpto' setting, as it does not make sense to
        // load a record view in the context of combined search.
        $query['jumpto'] = false;

        // Override the search type:
        $query['type'] = $searchType;

        // Display or hide top based on include_recommendations setting.
        $recommendOverride = [];
        $noRecommend = [];
        $includeRecommendSetting = $settings['include_recommendations'] ?? false;
        if (is_array($includeRecommendSetting)) {
            $recommendOverride['top'] = $includeRecommendSetting;
        } elseif (!$includeRecommendSetting) {
            $noRecommend[] = 'top';
        }

        // Display or hide bottom based on include_recommendations_bottom setting.
        $includeRecommendBottomSetting = $settings['include_recommendations_bottom'] ?? false;
        if (is_array($includeRecommendBottomSetting)) {
            $recommendOverride['bottom'] = $includeRecommendBottomSetting;
        } elseif (!$includeRecommendBottomSetting) {
            $noRecommend[] = 'bottom';
        }

        // Display or hide side based on include_recommendations_side setting.
        if (is_array($settings['include_recommendations_side'] ?? false)) {
            $recommendOverride['side'] = $settings['include_recommendations_side'];
        } else {
            $noRecommend[] = 'side';
        }

        // Display or hide no results recommendations, based on
        // include_recommendations_noresults setting (to display them in the bento box) or
        // include_recommendations_noresults_side setting (to display them in the sidebar).
        $includeRecommendNoResultsSetting = $settings['include_recommendations_noresults'] ?? false;
        if (is_array($includeRecommendNoResultsSetting)) {
            $recommendOverride['noresults'] = $settings['include_recommendations_noresults'];
        } elseif (!$includeRecommendNoResultsSetting) {
            $noRecommend[] = 'noresults';
        }

        if (is_array($settings['include_recommendations_noresults_side'] ?? false)) {
            $recommendOverride['noresults_side'] = $settings['include_recommendations_noresults_side'];
        } else {
            $noRecommend[] = 'noresults_side';
        }

        $query['recommendOverride'] = $recommendOverride;
        $query['noRecommend'] = count($noRecommend) ? implode(',', $noRecommend) : false;

        return $request->withQueryParams($query);
    }
}
