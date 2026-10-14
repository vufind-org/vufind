<?php

/**
 * "Select library card" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2026.
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

namespace VuFind\Action\LibraryCards;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Select library card" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SelectCardAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager              $authManager      Authentication manager
     * @param UserCardServiceInterface $userCardService  User card database service
     * @param Connection               $ilsConnection    ILS connection
     * @param ILSAuthenticator         $ilsAuthenticator ILS authenticator
     *                                                   ´
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserCardServiceInterface $userCardService,
        protected Connection $ilsConnection,
        protected ILSAuthenticator $ilsAuthenticator,
    ) {
        parent::__construct();
    }

    /**
     * Activate a library card.
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

        $redirectHelper = $this->getHelper(RedirectHelper::class);

        // Get requested library card ID:
        if (!($cardID = $this->getPostOrQueryParam('cardID'))) {
            return $redirectHelper->redirectToRoute($response, 'myresearch-home');
        }

        $this->userCardService->activateLibraryCard($user, $cardID);

        // Connect to the ILS and check that the credentials are correct:
        try {
            $patron = $this->ilsConnection->patronLogin(
                $user->getCatUsername(),
                $this->ilsAuthenticator->getCatPasswordForUser($user)
            );
            if (!$patron) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('authentication_error_invalid');
            }
        } catch (ILSException $e) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('authentication_error_technical');
        }

        if ($url = $this->getHelper(ContextHelper::class)->getReferrer($request, true)) {
            return $redirectHelper->redirectToUrl($response, $this->adjustCardRedirectUrl($url));
        }
        return $redirectHelper->redirectToUrl($response, 'myresearch-home');
    }

    /**
     * When redirecting after selecting a library card, adjust the URL to make
     * sure it will work correctly.
     *
     * @param string $url URL to adjust
     *
     * @return string
     */
    protected function adjustCardRedirectUrl(string $url): string
    {
        // If there is pagination in the URL, reset it to page 1, since the new card may have a different number of
        // pages of data:
        return preg_replace('/([&?]page)=[0-9]+/', '$1=1', $url);
    }
}
