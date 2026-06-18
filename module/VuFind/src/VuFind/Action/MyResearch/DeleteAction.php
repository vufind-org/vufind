<?php

/**
 * Delete favorites action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Favorites\FavoritesService;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;

use function count;
use function is_array;

/**
 * Delete favorites action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager              $authManager      Authentication manager
     * @param FavoritesService         $favoritesService Favorites service
     * @param UserListServiceInterface $userListService  User list database service
     * @param RecordLoader             $recordLoader     Record loader
     */
    public function __construct(
        protected AuthManager $authManager,
        protected FavoritesService $favoritesService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserListServiceInterface $userListService,
        protected RecordLoader $recordLoader,
    ) {
        parent::__construct();
    }

    /**
     * Delete favorites.
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

        // Get target URL for after deletion:
        $listID = $this->getPostParam('listID');
        $newUrl = empty($listID)
            ? $this->getUrlFromRoute('myresearch-favorites')
            : $this->getUrlFromRoute('userList', ['id' => $listID]);

        // Fail if we have nothing to delete:
        $bulkActionHelper = $this->getHelper(BulkActionHelper::class);
        $ids = $bulkActionHelper->getSelectedIds($request);

        $actionLimit = $bulkActionHelper->getBulkActionLimit('delete');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_noitems_advice')) {
                return $redirect;
            }
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = [
                'msg' => 'bulk_limit_exceeded',
                'tokens' => ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            ];
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', $errorMsg)) {
                return $redirect;
            }
        } elseif ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $this->favoritesService->deleteFavorites($ids, $listID === null ? null : (int)$listID, $user);
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('fav_delete_success');
            return $this->getRedirectResponse($response, $newUrl);
        }

        // If we got this far, the operation has not been confirmed yet; show the necessary dialog box:
        $list = empty($listID)
            ? false
            : $this->userListService->getUserListById($listID);
        return $this->renderTemplate(
            $request,
            $response,
            [
                'list' => $list,
                'deleteIDS' => $ids,
                'records' => $this->recordLoader->loadBatch($ids),
            ]
        );
    }
}
