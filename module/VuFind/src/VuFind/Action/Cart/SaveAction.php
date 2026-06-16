<?php

/**
 * Cart save action.
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

namespace VuFind\Action\Cart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ListItemSelectionTrait;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Cart;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Export;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

use function count;
use function is_array;

/**
 * Cart save action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SaveAction extends AbstractCartAction implements TranslatorAwareInterface
{
    use ListItemSelectionTrait;
    use TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param Export                   $export              Export handler
     * @param Cart                     $cart                Cart handler
     * @param FollowupHelper           $followupHelper      Followup helper
     * @param AccountCapabilities      $accountCapabilities Account capabilities
     * @param AuthManager              $authManager         Authentication manager
     * @param RecordLoader             $recordLoader        Record loader
     * @param UserListServiceInterface $userListService     User list database service
     * @param FavoritesService         $favoritesService    Favorites service
     */
    public function __construct(
        Export $export,
        Cart $cart,
        FollowupHelper $followupHelper,
        protected AccountCapabilities $accountCapabilities,
        protected AuthManager $authManager,
        protected RecordLoader $recordLoader,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserListServiceInterface $userListService,
        protected FavoritesService $favoritesService,
    ) {
        parent::__construct($export, $cart, $followupHelper);
    }

    /**
     * Save cart.
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
        if ($this->accountCapabilities->getListSetting() === 'disabled') {
            throw new ForbiddenException('Lists disabled');
        }

        // Load record information first (no need to prompt for login if we just
        // need to display a "no records" error message):
        $ids = $this->getSelectedIds();
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followupHelper->retrieveAndClear('cartIds') ?? [];
        }
        $bulkActionHelper = $this->getHelper(BulkActionHelper::class);
        $actionLimit = $bulkActionHelper->getBulkActionLimit('saveCart');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        // Make sure user is logged in:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin(
                $request,
                $response,
                null,
                ['cartIds' => $ids, 'cartAction' => 'Save']
            );
        }
        $formHelper = $this->getHelper(FormHelper::class);
        if (
            !($submitDisabled ?? false)
            && $formHelper->formWasSubmitted($request, 'newList')
        ) {
            // Remove submit now from parameters
            $postParams = $request->getParsedBody();
            unset($postParams['newList']);
            unset($postParams['submitButton']);
            // Set id to NEW:
            $routeMatch = $this->request->getAttribute('route-match');
            $routeMatch?->setParam('id', 'NEW');
            // Forward:
            return $this->getHelper(ForwardHelper::class)->forwardTo(
                $request->withParsedBody($postParams),
                $response,
                'MyResearch/EditList'
            );
        }
        // Process submission if necessary:
        if ($formHelper->formWasSubmitted($request)) {
            $results = $this->favoritesService->saveRecordsToFavorites($request->getParsedBody(), $user);
            $listUrl = $this->getRouteHelper()->getUrlFromRoute(
                'userList',
                ['id' => $results['listId']]
            );
            $message = [
                'html' => true,
                'msg' => $this->translate('bulk_save_success') . '. '
                . '<a href="' . $listUrl . '" class="gotolist">'
                . $this->translate('go_to_list') . '</a>.',
            ];
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage($message);
            return $this->getRedirectResponse($response, $listUrl);
        }

        // Pass record and list information to template:
        $templateParams = [
            'records' => $this->recordLoader->loadBatch($ids),
            'lists' => $this->userListService->getUserListsByUser($user),
        ];
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
