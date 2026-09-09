<?php

/**
 * Record save action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UrlHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;

use function in_array;

/**
 * Record save action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SaveAction extends AbstractRecordAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param SearchMemory                 $searchMemory        Search memory
     * @param TabManager                   $tabManager          Tab manager
     * @param AuthManager                  $authManager         Authentication manager
     * @param RecordLoader                 $recordLoader        Record loader
     * @param RecordRouter                 $recordRouter        Record router
     * @param ResultScroller               $resultScroller      Result scroller
     * @param array                        $config              VuFind configuration
     * @param UserResourceServiceInterface $userResourceService User resource database service
     * @param UserListServiceInterface     $userListService     User list database service
     * @param TagsService                  $tagsService         Tags service
     * @param FavoritesService             $favoritesService    Favorites service
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserResourceServiceInterface $userResourceService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserListServiceInterface $userListService,
        protected TagsService $tagsService,
        protected FavoritesService $favoritesService,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Save record to a list.
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
        // Make sure lists are enabled:
        if (!$this->getHelper(UserContentHelper::class)->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Check permission:
        $permissionResponse
            = $this->getHelper(PermissionHelper::class)->check($request, $response, 'feature.Favorites', false);
        if ($permissionResponse) {
            return $permissionResponse;
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Process form submission:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            return $this->processSave($request, $response, $user);
        }

        // If we got this far, we should save the referer for later use by the
        // ProcessSave action (to get back to where we came from after saving).
        // We shouldn't save follow-up information if it points to the Save
        // screen or the "create list" screen, as this causes confusing workflows;
        // in these cases, we will simply push the user to record view
        // by unsetting the followup and relying on default behavior in processSave.
        $referer = $request->getHeader('Referer')[0] ?? '';
        $loginHelper = $this->getHelper(LoginHelper::class);
        if (
            !empty($referer)
            && !str_ends_with($referer, '/Save')
            && stripos($referer, 'MyResearch/EditList/NEW') === false
            && $this->getHelper(UrlHelper::class)->isLocalUrl($referer)
        ) {
            $loginHelper->setFollowupUrlToReferer($request);
        } else {
            $loginHelper->clearFollowupUrl();
        }

        // Retrieve the record driver:
        $driver = $this->loadRecord();

        // Find out if the item is already part of any lists; save list info/IDs
        $listIds = [];
        $resources = $this->userResourceService->getFavoritesForRecord(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier(),
            null,
            $user
        );
        foreach ($resources as $userResource) {
            if ($currentList = $userResource->getUserList()) {
                $listIds[] = $currentList->getId();
            }
        }

        // Loop through all user lists and sort out containing/non-containing lists
        $containingLists = $nonContainingLists = [];
        foreach ($this->userListService->getUserListsByUser($user) as $list) {
            // Assign list to appropriate array based on whether or not we found
            // it earlier in the list of lists containing the selected record.
            if (in_array($list->getId(), $listIds)) {
                $containingLists[] = $list;
            } else {
                $nonContainingLists[] = $list;
            }
        }

        $templateParams = $this->getTemplateParams(compact('containingLists', 'nonContainingLists'));
        return $this->renderTemplate($request, $response, $templateParams, 'record/save');
    }

    /**
     * Store the results of the Save action.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param UserEntityInterface    $user     User
     *
     * @return mixed
     */
    protected function processSave(
        ServerRequestInterface $request,
        ResponseInterface $response,
        UserEntityInterface $user
    ): ResponseInterface {
        // Perform the save operation:
        $driver = $this->loadRecord();
        $post = $request->getParsedBody();
        $post['mytags'] = $this->tagsService->parse($post['mytags'] ?? '');
        $results = $this->favoritesService->saveRecordToFavorites($post, $user, $driver);

        // Display a success status message:
        $listUrl = $this->getRouteHelper()->getUrlFromRoute('userList', ['id' => $results['listId']]);
        $message = [
            'html' => true,
            'msg' => $this->translate('bulk_save_success') . '. '
                . '<a href="' . htmlspecialchars($listUrl) . '" class="gotolist">'
                . $this->translate('go_to_list') . '</a>.',
        ];
        $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage($message);

        // redirect to followup url saved in saveAction
        if ($url = $this->getHelper(LoginHelper::class)->getAndClearFollowupUrl($request)) {
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
        }

        // No followup info found?  Send back to record view:
        return $this->redirectToRecord();
    }
}
