<?php

/**
 * Search history action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Search\History;
use VuFind\Search\Memory;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Search history action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HistoryAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager $authManager Authentication manager
     * @param History     $history     Search history
     * @param Memory      $memory      Search memory
     */
    #[Autowire]
    public function __construct(
        protected AuthManager $authManager,
        protected History $history,
        protected Memory $memory,
    ) {
        parent::__construct();
    }

    /**
     * Handle search history display and purge.
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
        // Force login if necessary
        $user = $this->authManager->getUserObject();
        if ($this->getQueryParam('require_login', 'no') !== 'no') {
            // If user is already logged in, drop the require_login parameter to allow for a cleaner log-out experience:
            return $user
                ? $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'search-history')
                : $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }
        $userId = $user?->getId();

        if ($this->getQueryParam('purge')) {
            $this->history->purgeSearchHistory($userId);

            // We don't want to remember the last search after a purge:
            $this->memory->forgetSearch();
        }
        $templateParams = $this->history->getSearchHistory($userId);
        // Eliminate schedule settings if scheduled searches are disabled; add
        // user email data if scheduled searches are enabled.
        $scheduleOptions = $this->history->getScheduleOptions();
        if (!$scheduleOptions) {
            unset($templateParams['schedule']);
        } else {
            $templateParams['scheduleOptions'] = $scheduleOptions;
            $templateParams['alertemail'] = $user?->getEmail();
        }
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
