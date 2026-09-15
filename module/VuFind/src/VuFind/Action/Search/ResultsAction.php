<?php

/**
 * Search results action.
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
use VuFind\ActionHelper\ForwardHelper;

/**
 * Search results action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResultsAction extends AbstractSearchAndResultsAction
{
    /**
     * Display results.
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
        // Special case -- redirect tag searches.
        $queryParams = $request->getQueryParams();
        if ('' !== ($tag = $this->getQueryParam('tag', ''))) {
            $queryParams['lookfor'] = $tag;
            $queryParams['type'] = 'tag';
        }
        if ('tag' === ($queryParams['type'] ?? null)) {
            // Because we're coming in from a search, we want to do a fuzzy tag search, not an exact search like we
            // would when linking to a specific tag name.
            return $this->getHelper(ForwardHelper::class)->forwardTo(
                $request->withQueryParams($queryParams),
                $response,
                'tag/home'
            );
        }
        return $this->renderSearchResults($request, $response);
    }
}
