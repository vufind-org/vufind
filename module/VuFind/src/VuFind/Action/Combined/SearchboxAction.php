<?php

/**
 * Combined search searchbox action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\RedirectHelper;

/**
 * Combined search searchbox action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchboxAction extends AbstractCombinedSearchAndResultsAction
{
    /**
     * Process the combined search box.
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
        [$type, $target] = explode(':', $this->getQueryParam('type'), 2);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        switch ($type) {
            case 'VuFind':
                [$fullSearchClassId, $type] = explode('|', $target);
                [$searchClassId] = explode(':', $fullSearchClassId);
                $params = $request->getQueryParams();
                $params['type'] = $type;

                // Disable retained filters if we are switching classes!
                $activeClass = $this->getQueryParam('activeSearchClassId');
                if ($activeClass != $searchClassId) {
                    unset($params['filter']);
                }
                // We don't need to pass activeSearchClassId forward:
                unset($params['activeSearchClassId']);

                // If we are using a filtered section, apply appropriate filters:
                if ($fullSearchClassId !== $searchClassId) {
                    // Try to find matching filter settings first in [SearchTabsFilters] in config.ini, and then
                    // in the combined.ini filters setting.
                    $config = $this->configManager->getConfigArray('config');
                    $combinedConfig = $this->configManager->getConfigArray('combined');
                    $hiddenFilters = $config['SearchTabsFilters'][$fullSearchClassId]
                        ?? $combinedConfig[$fullSearchClassId]['filter']
                        ?? [];
                    // Account for an array or a string:
                    $params['hiddenFilters'] = (array)($hiddenFilters);
                } else {
                    unset($params['hiddenFilters']);
                }
                $route = $this->searchOptionsPluginManager->get($searchClassId)->getSearchAction();
                return $redirectHelper->redirectToRoute($response, $route, queryParams: $params);
            case 'External':
                $lookfor = $this->getQueryParam('lookfor');
                $finalTarget = (!str_contains($target, '%%lookfor%%'))
                    ? $target . urlencode($lookfor)
                    : str_replace('%%lookfor%%', urlencode($lookfor), $target);
                return $redirectHelper->redirectToUrl($response, $finalTarget);
            default:
                // If parameters are completely missing, redirect to home instead
                // of throwing an error; this is possibly a misbehaving crawler that
                // followed the SearchBox URL without passing any parameters.
                if (empty($type) && empty($target)) {
                    return $redirectHelper->redirectToRoute($response, 'home');
                }
                // If we have a weird value here, report it as an Exception:
                throw new \VuFind\Exception\BadRequest(
                    'Unexpected search type: "' . $type . '".'
                );
        }
    }
}
