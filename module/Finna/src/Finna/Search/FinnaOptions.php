<?php

/**
 * Additional functionality for Finna options.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2017.
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
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\Search;

/**
 * Additional functionality for Finna options.
 *
 * @category VuFind
 * @package  Search
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait FinnaOptions
{
    /**
     * Get default limit setting.
     *
     * @param string $view Selected view
     *
     * @return int
     */
    public function getDefaultLimitByView($view = null)
    {
        if ($view == 'grid' && ($limit = $this->searchSettings['General']['default_limit_grid'] ?? null)) {
            $defaultLimit = $limit;
        } elseif (
            $view == 'condensed'
            && ($limit = $this->searchSettings['General']['default_limit_condensed'] ?? null)
        ) {
            $defaultLimit = $limit;
        } elseif (
            $view == 'compact'
            && ($limit = $this->searchSettings['General']['default_limit_compact'] ?? null)
        ) {
            $defaultLimit = $limit;
        } else {
            $defaultLimit = $this->getDefaultLimit();
        }
        return $defaultLimit;
    }

    /**
     * Get view option list type setting
     *
     * @return bool
     */
    public function getViewOptionListType()
    {
        $viewOptionsIcons = $this->searchSettings['General']['view_options_icons'] ?? false;
        return $viewOptionsIcons ? true : false;
    }

    /**
     * Adds support for a results_top location for recommendations.
     *
     * @param string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     */
    public function getRecommendationSettings($handler = null)
    {
        $recommend = parent::getRecommendationSettings($handler);

        if (
            null !== $handler
            && ($handler = $this->searchSettings['ResultsTopRecommendations'][$handler] ?? null)
        ) {
            $recommend['results_top'] = $handler;
        } else {
            $recommend['results_top'] = $this->searchSettings['General']['default_results_top_recommend'] ?? false;
        }

        return $recommend;
    }
}
