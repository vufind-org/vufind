<?php

/**
 * "Edit a list item" action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\RecordDriver\AbstractBase as AbstractDriver;

use function in_array;

/**
 * "Edit a list item" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditItemAction extends AbstractListsAction
{
    /**
     * Edit a list item.
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
        // Force login:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Get current record (and, if applicable, selected list ID) for convenience:
        if (null === ($id = $this->getPostOrQueryParam('id'))) {
            throw new \Exception('Missing parameter: id');
        }
        $source = $this->getPostOrQueryParam('source', DEFAULT_SEARCH_BACKEND);
        $driver = $this->recordLoader->load($id, $source, true);
        $listID = $this->getPostOrQueryParam('list_id');

        // Process save action if necessary:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $this->processEditSubmit($user, $driver, $listID);
            $redirectHelper = $this->getHelper(RedirectHelper::class);
            return null === $listID
                ? $redirectHelper->redirectToRoute($response, 'lists-allitems')
                : $redirectHelper->redirectToRoute($response, 'userList', ['id' => $listID]);
        }

        // Get saved favorites for selected list (or all lists if $listID is null)
        $userResources = $this->userResourceService->getFavoritesForRecord($id, $source, $listID, $user);
        $savedData = [];
        foreach ($userResources as $current) {
            // There should always be list data based on the way we retrieve this result, but
            // check just to be on the safe side.
            if ($currentList = $current->getUserList()) {
                $savedData[] = [
                    'listId' => $currentList->getId(),
                    'listTitle' => $currentList->getTitle(),
                    'notes' => $current->getNotes(),
                    'tags' => $this->favoritesService->getTagStringForEditing($user, $currentList, $id, $source),
                ];
            }
        }

        // In order to determine which lists contain the requested item, we may
        // need to do an extra database lookup if the previous lookup was limited
        // to a particular list ID:
        $containingLists = [];
        if (!empty($listID)) {
            $userResources = $this->userResourceService->getFavoritesForRecord($id, $source, null, $user);
        }
        foreach ($userResources as $current) {
            if ($currentList = $current->getUserList()) {
                $containingLists[] = $currentList->getId();
            }
        }

        // Send non-containing lists to the view for user selection:
        $userLists = $this->userListService->getUserListsByUser($user);
        $lists = [];
        foreach ($userLists as $userList) {
            if (!in_array($userList->getId(), $containingLists)) {
                $lists[$userList->getId()] = $userList->getTitle();
            }
        }

        return $this->renderTemplate($request, $response, compact('driver', 'lists', 'savedData', 'listID'));
    }

    /**
     * Process the submission of the edit favorite form.
     *
     * @param UserEntityInterface $user   Logged-in user
     * @param AbstractDriver      $driver Record driver for favorite
     * @param ?int                $listID List being edited (null if editing all favorites)
     *
     * @return void
     */
    protected function processEditSubmit(UserEntityInterface $user, AbstractDriver $driver, ?int $listID): void
    {
        $lists = (array)$this->getPostParam('lists', []);
        $didSomething = false;
        foreach ($lists as $list) {
            $tags = $this->getPostParam('tags' . $list, '');
            $this->favoritesService->save(
                [
                    'list'  => $list,
                    'mytags'  => $this->tagsService->parse($tags),
                    'notes' => $this->getPostParam('notes' . $list),
                ],
                $user,
                $driver
            );
            $didSomething = true;
        }
        // add to a new list?
        $addToList = $this->getPostParam('addToList');
        if ($addToList > -1) {
            $didSomething = true;
            $this->favoritesService->saveRecordToFavorites(['list' => $addToList], $user, $driver);
        }
        if ($didSomething) {
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('edit_list_success');
        }
    }
}
