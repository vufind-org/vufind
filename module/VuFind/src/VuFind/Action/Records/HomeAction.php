<?php

/**
 * Records home action.
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

namespace VuFind\Action\Records;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Search\AbstractSearchAndResultsAction;
use VuFind\ActionHelper\RedirectHelper;

use function count;

/**
 * Records home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractSearchAndResultsAction
{
    /**
     * Display records.
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
        // If there is exactly one record, send the user directly there:
        $ids = $this->getQueryParam('id', []);
        $print = $this->getQueryParam('print');
        if (count($ids) == 1) {
            $details = $this->recordRouter->getTabRouteDetails($ids[0]);
            $target = $this->routeHelper->getUrlFromRoute($details['route'], $details['params']);
            // forward print param, if necessary:
            $params = $print ? '?print=' . urlencode($print) : '';
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $target . $params);
        }
        // Ignore Print for Search History:
        if ($print) {
            $this->saveToHistory = false;
        }

        // Not exactly one record -- show search results:
        return $this->renderSearchResults($request, $response);
    }
}
