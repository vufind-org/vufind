<?php

/**
 * MyResearch "save/unsave search" action.
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

namespace VuFind\Action\MyResearch;

use DateTime;
use Exception;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\Plugin\Redirect;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * MyResearch "save/unsave search" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SaveSearchAction extends AbstractSearchHistoryAction
{
    /**
     * Save or unsave a search.
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
        // Fail if saved searches are disabled.
        if ($this->accountCapabilities->getSavedSearchSetting() === 'disabled') {
            throw new ForbiddenException('Saved searches disabled.');
        }
        // Not logged in?  Force user to log in:
        if (!($user = $this->authManager->getIdentity())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Check for schedule-related parameters and process them first:
        $schedule = $this->getQueryParam('schedule');
        $sid = $this->getQueryParam('searchid');
        if (null !== $schedule && null !== $sid) {
            $this->scheduleSearch($user, $schedule, $sid);
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'search-history');
        }

        // Check for the save / delete parameters and process them appropriately:
        if (null !== ($id = $this->getQueryParam('save'))) {
            // If the row the user is trying to save is a duplicate of an already-
            // saved row, we should just delete the duplicate. (This can happen if
            // the user clicks "save" before logging in, then logs in during the
            // save process, but has the same search already saved in their account).
            $sessionId = $this->sessionManager->getId();
            $rowToCheck = $this->searchService->getSearchByIdAndOwner($id, $sessionId, $user);
            $duplicateId = $rowToCheck ? $this->isDuplicateOfSavedSearch($rowToCheck, $user) : 0;
            if ($duplicateId) {
                $this->searchService->deleteSearch($rowToCheck);
                $id = $duplicateId;
            } else {
                $this->setSavedFlagSecurely($id, true, $user);
            }
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('search_save_success');
        } elseif (null !== ($id = $this->getQueryParam('delete'))) {
            $this->setSavedFlagSecurely($id, false, $user);
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('search_unsave_success');
        } else {
            throw new \Exception('Missing save and delete parameters.');
        }

        // Forward to the appropriate place:
        if ($this->getQueryParam('mode') === 'history') {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'search-history');
        }

        // Forward to the Search/Results action with the "saved" parameter set; this will in turn redirect the user to
        // the appropriate results screen.
        $queryParams = ['saved' => $id] + $request->getQueryParams();
        return $this->getHelper(ForwardHelper::class)->forwardTo(
            $request->withQueryParams($queryParams),
            $response,
            'search/results'
        );
    }

    /**
     * Support method -- schedule a search.
     *
     * @param UserEntityInterface $user     Logged-in user object
     * @param int                 $schedule Requested schedule setting
     * @param int                 $sid      Search ID to schedule
     *
     * @return void
     */
    protected function scheduleSearch(UserEntityInterface $user, int $schedule, int $sid): void
    {
        // Fail if scheduled searches are disabled.
        $scheduleOptions = $this->searchHistory->getScheduleOptions();
        if (!isset($scheduleOptions[$schedule])) {
            throw new ForbiddenException('Illegal schedule option: ' . $schedule);
        }
        $baseurl =  rtrim($this->serverUrlHelper->getUrlForPath($this->getRouteHelper()->getUrlFromRoute('home')), '/');
        $savedRow = $this->getSearchRowSecurely($sid, $user);

        // In case the user has just logged in, let's deduplicate...
        $duplicateId = $this->isDuplicateOfSavedSearch($savedRow, $user);
        if ($duplicateId) {
            $this->searchService->deleteSearch($savedRow);
            $sid = $duplicateId;
            $savedRow = $this->getSearchRowSecurely($sid, $user);
        }

        // If we didn't find an already-saved row, let's save and retry:
        if (!($savedRow->getSaved() ?? false)) {
            $this->setSavedFlagSecurely($sid, true, $user);
            $savedRow = $this->getSearchRowSecurely($sid, $user);
        }
        if (!($this->config['Account']['force_first_scheduled_email'] ?? false)) {
            // By default, a first scheduled email will be sent because the database last notification date will be
            // initialized to a past date. If we don't want that to happen, we need to set it to a more appropriate
            // date:
            $savedRow->setLastNotificationSent(new DateTime());
        }
        $savedRow->setNotificationFrequency($schedule);
        $savedRow->setNotificationBaseUrl($baseurl);
        $this->searchService->persistEntity($savedRow);

        $this->auditEventService->addEvent(
            AuditEventType::User,
            AuditEventSubtype::ScheduleSearch,
            $user,
            data: [
                'search_id' => $sid,
                'notification_frequency' => $schedule,
                'base_url' => $baseurl,
            ]
        );
    }

    /**
     * Support method for savesearchAction(): set the saved flag in a secure
     * fashion, throwing an exception if somebody attempts something invalid.
     *
     * @param int                 $searchId The search ID to save/unsave
     * @param bool                $saved    The new desired state of the saved flag
     * @param UserEntityInterface $user     The user requesting the change
     *
     * @throws \Exception
     * @return void
     */
    protected function setSavedFlagSecurely(int $searchId, bool $saved, UserEntityInterface $user): void
    {
        $row = $this->getSearchRowSecurely($searchId, $user);
        $row->setSaved($saved ? 1 : 0);
        if (!$saved) {
            $row->setNotificationFrequency(0);
        }
        $row->setUser($user);
        $this->searchService->persistEntity($row);
        $this->auditEventService->addEvent(
            AuditEventType::User,
            $saved ? AuditEventSubtype::SaveSearch : AuditEventSubtype::UnSaveSearch,
            $user,
            data: [
                'search_id' => $searchId,
            ]
        );
    }
}
