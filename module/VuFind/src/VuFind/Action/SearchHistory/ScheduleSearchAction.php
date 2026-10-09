<?php

/**
 * "Schedule search" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\SearchHistory;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * "Schedule search" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ScheduleSearchAction extends AbstractSearchHistoryAction
{
    /**
     * Schedule a search.
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
        // Fail if saved searches or subscriptions are disabled.
        if ($this->accountCapabilities->getSavedSearchSetting() === 'disabled') {
            throw new ForbiddenException('Saved searches disabled.');
        }
        if (!($scheduleOptions = $this->searchHistory->getScheduleOptions())) {
            throw new ForbiddenException('Scheduled searches disabled.');
        }
        // Fail if search ID is missing.
        if (!($searchId = $this->getQueryParam('searchid'))) {
            throw new BadRequestException('searchid missing');
        }
        // Not logged in?  Force user to log in:
        if (!($user = $this->authManager->getIdentity())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Get the row, and fail if the current user doesn't own it.
        $search = $this->getSearchRowSecurely($searchId, $user);

        // If the user has just logged in, the search might be a duplicate; if so, let's switch to the pre-existing
        // version instead.
        if ($duplicateId = $search ? $this->isDuplicateOfSavedSearch($search, $user) : 0) {
            $this->searchService->deleteSearch($search);
            return $this->getHelper(RedirectHelper::class)->redirectToRoute(
                $response,
                'myresearch-schedulesearch',
                queryParams: ['searchid' => $duplicateId]
            );
        }

        // Now fetch all the results:
        if (!($results = $search->getSearchObject()?->deminify($this->searchResultsPluginManager))) {
            throw new Exception("Problem getting search object from search {$search->getId()}.");
        }

        // Render the form:
        return $this->renderTemplate($request, $response, compact('scheduleOptions', 'search', 'results'));
    }
}
