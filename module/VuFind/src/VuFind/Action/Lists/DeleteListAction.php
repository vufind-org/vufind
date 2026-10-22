<?php

/**
 * "Delete list" action.
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

namespace VuFind\Action\Lists;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;

/**
 * "Delete list" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteListAction extends AbstractListsAction
{
    /**
     * Delete a list.
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
        // Fail if lists are disabled:
        if (!$this->getHelper(UserContentHelper::class)->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Get requested list ID:
        if (null === ($listID = $this->getPostOrQueryParam('listID'))) {
            throw new Exception('List ID missing');
        }

        // Have we confirmed this?
        $confirm = $this->getPostOrQueryParam('confirm');
        if ($confirm) {
            $user = $this->authManager->getUserObject();
            try {
                $list = $this->userListService->getUserListById($listID);
                $this->favoritesService->destroyList($list, $user);
                $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('fav_list_delete');
            } catch (LoginRequiredException | ListPermissionException $e) {
                if (!$user) {
                    return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
                }
                // Logged in? Then we have to rethrow the exception!
                throw $e;
            }
            // Redirect to Lists home
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'lists-allitems');
        }

        // If we got this far, we must display a confirmation message:
        return $this->getHelper(ForwardHelper::class)->forwardToConfirm(
            $request,
            $response,
            'confirm_delete_list_brief',
            $this->getRouteHelper()->getUrlFromRoute('lists-deletelist'),
            $this->getRouteHelper()->getUrlFromRoute('userList', ['id' => $listID]),
            'confirm_delete_list_text',
            ['listID' => $listID]
        );
    }
}
