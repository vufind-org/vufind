<?php

/**
 * LibraryCards Controller.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\ILS as ILSException;
use VuFind\Validator\CsrfInterface;

/**
 * Controller for the library card functionality.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LibraryCardsController extends AbstractBase
{
    /**
     * Send user's library cards to the view.
     *
     * @return mixed
     */
    public function homeAction()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Connect to the ILS for login drivers:
        $catalog = $this->getILS();
        $cardService = $this->getDbService(UserCardServiceInterface::class);

        return $this->createViewModel(
            [
                'libraryCards' => $cardService->getLibraryCards($user),
                'multipleTargets' => $catalog->checkCapability('getLoginDrivers'),
                'allowConnectingCards' => $this->getAuthManager()
                    ->supportsConnectingLibraryCard(),
            ]
        );
    }

    /**
     * Send user's library card to the edit view.
     *
     * @return mixed
     */
    public function editCardAction()
    {
        // User must be logged in to edit library cards:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Process form submission:
        if ($this->formWasSubmitted()) {
            if ($redirect = $this->processEditLibraryCard($user)) {
                return $redirect;
            }
        }

        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $cardService = $this->getDbService(UserCardServiceInterface::class);
        $card = $cardService->getOrCreateLibraryCard($user, $id == 'NEW' ? null : $id);

        $target = null;
        $username = $card->getCatUsername();

        $loginSettings = $this->getILSLoginSettings();
        // Split target and username if multiple login targets are available:
        if ($loginSettings['targets'] && strstr($username, '.')) {
            [$target, $username] = explode('.', $username, 2);
        }

        $cardName = $this->params()->fromPost('card_name', $card->getCardName());
        $username = $this->params()->fromPost('username', $username);
        $target = $this->params()->fromPost('target', $target);

        // Send the card to the view:
        return $this->createViewModel(
            [
                'card' => $card,
                'cardName' => $cardName,
                'target' => $target ?: $loginSettings['defaultTarget'],
                'username' => $username,
                'targets' => $loginSettings['targets'],
                'defaultTarget' => $loginSettings['defaultTarget'],
                'loginMethod' => $loginSettings['loginMethod'],
                'loginMethods' => $loginSettings['loginMethods'],
            ]
        );
    }

    /**
     * Verify new library card using a one-time password.
     *
     * @return mixed
     */
    public function verifyOtpAction()
    {
        // User must be logged in to edit library cards:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        $userSessionService = $this->getDbService(UserSessionPersistenceInterface::class);
        if (!($authData = $userSessionService->getLibraryCardAuthenticationData())) {
            return $this->redirect()->toRoute('librarycards-home');
        }

        // Process form submission:
        if ($this->formWasSubmitted()) {
            $csrf = $this->getService(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            } else {
                // After successful token verification, clear list to shrink session:
                $csrf->trimTokenList(0);
            }

            $password = $this->getRequest()->getPost()->get('password', '');
            $emailAuthenticator = $this->getService(\VuFind\Auth\EmailAuthenticator::class);
            if (
                ($authId = $authData['authId'] ?? null)
                && ($cardData = $emailAuthenticator->verifyAuthenticationCode($authId, $password))
                && ($cardId = $cardData['cardID'] ?? null)
            ) {
                $cardService = $this->getDbService(UserCardServiceInterface::class);
                $cardService->persistLibraryCardData(
                    $user,
                    'NEW' === $cardId ? null : $cardId,
                    $cardData['cardName'],
                    $cardData['cat_username'],
                    ' '
                );
                $this->getAuditEventService()->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::ConnectCardByEmail,
                    $user,
                    data: $cardData
                );
                $userSessionService->setLibraryCardAuthenticationData(null);
                return $this->redirect()->toRoute('librarycards-home');
            }
            $this->flashMessenger()->addErrorMessage('authentication_error_invalid');
        }

        return $this->createViewModel(compact('authData'));
    }

    /**
     * Creates a confirmation box to delete or not delete the current list.
     *
     * @return mixed
     */
    public function deleteCardAction()
    {
        // User must be logged in to edit library cards:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Get requested library card ID:
        $cardID = $this->params()
            ->fromPost('cardID', $this->params()->fromQuery('cardID'));

        // Have we confirmed this?
        $confirm = $this->params()->fromPost(
            'confirm',
            $this->params()->fromQuery('confirm')
        );
        if ($confirm) {
            $this->getDbService(UserCardServiceInterface::class)->deleteLibraryCard($user, $cardID);

            $this->getAuditEventService()->addEvent(
                AuditEventType::User,
                AuditEventSubtype::DeleteCard,
                $user,
                data: [
                    'card_id' => $cardID,
                ]
            );

            // Success Message
            $this->flashMessenger()->addSuccessMessage('Library Card Deleted');
            // Redirect to MyResearch library cards
            return $this->redirect()->toRoute('librarycards-home');
        }

        // If we got this far, we must display a confirmation message:
        return $this->confirm(
            'confirm_delete_library_card_brief',
            $this->url()->fromRoute('librarycards-deletecard'),
            $this->url()->fromRoute('librarycards-home'),
            'confirm_delete_library_card_text',
            ['cardID' => $cardID]
        );
    }

    /**
     * When redirecting after selecting a library card, adjust the URL to make
     * sure it will work correctly.
     *
     * @param string $url URL to adjust
     *
     * @return string
     */
    protected function adjustCardRedirectUrl($url)
    {
        // If there is pagination in the URL, reset it to page 1, since the
        // new card may have a different number of pages of data:
        return preg_replace('/([&?]page)=[0-9]+/', '$1=1', $url);
    }

    /**
     * Activates a library card.
     *
     * @return \Laminas\Http\Response
     */
    public function selectCardAction()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        $cardID = $this->params()->fromQuery('cardID');
        if (null === $cardID) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        $cardService = $this->getDbService(UserCardServiceInterface::class);
        $cardService->activateLibraryCard($user, $cardID);

        // Connect to the ILS and check that the credentials are correct:
        try {
            $catalog = $this->getILS();
            $patron = $catalog->patronLogin(
                $user->getCatUsername(),
                $this->getILSAuthenticator()->getCatPasswordForUser($user)
            );
            if (!$patron) {
                $this->flashMessenger()
                    ->addErrorMessage('authentication_error_invalid');
            }
        } catch (ILSException $e) {
            $this->flashMessenger()
                ->addErrorMessage('authentication_error_technical');
        }

        $this->setFollowupUrlToReferer(false);
        if ($url = $this->getAndClearFollowupUrl()) {
            return $this->redirect()->toUrl($this->adjustCardRedirectUrl($url));
        }
        return $this->redirect()->toRoute('myresearch-home');
    }

    /**
     * Redirects to authentication to connect a new library card.
     *
     * @return \Laminas\Http\Response
     */
    public function connectCardLoginAction()
    {
        if (!$this->getUser()) {
            return $this->forceLogin();
        }
        $url = $this->getServerUrl('librarycards-connectcard');
        $redirectUrl = $this->getAuthManager()->getSessionInitiator($url);
        if (!$redirectUrl) {
            $this->flashMessenger()
                ->addErrorMessage('authentication_error_technical');
            return $this->redirect()->toRoute('librarycards-home');
        }
        return $this->redirect()->toUrl($redirectUrl);
    }

    /**
     * Connects a new library card for authenticated user.
     *
     * @return \Laminas\Http\Response
     */
    public function connectCardAction()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        try {
            $this->getAuthManager()->connectLibraryCard($this->getRequest(), $user);
        } catch (\Exception $ex) {
            $this->flashMessenger()->addErrorMessage($ex->getMessage());
        }
        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Process the "edit library card" submission.
     *
     * @param UserEntityInterface $user Logged in user
     *
     * @return object|bool        Response object if redirect is
     * needed, false if form needs to be redisplayed.
     */
    protected function processEditLibraryCard($user)
    {
        $cardName = $this->params()->fromPost('card_name', '');
        $target = $this->params()->fromPost('target', '');
        $username = $this->params()->fromPost('username', '');
        $password = $this->params()->fromPost('password', '');
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));

        if (!$username) {
            $this->flashMessenger()
                ->addErrorMessage('authentication_error_blank');
            return false;
        }

        $rawUsername = $username;
        if ($target) {
            $username = "$target.$username";
        }

        // Check the credentials if the username is changed or a new password is
        // entered:
        $cardService = $this->getDbService(UserCardServiceInterface::class);
        $card = $cardService->getOrCreateLibraryCard($user, $id == 'NEW' ? null : $id);
        if ($card->getCatUsername() !== $username || trim($password)) {
            // Connect to the ILS and check that the credentials are correct:
            $loginMethod = $this->getILSLoginMethod($target);
            if (
                'password' === $loginMethod
                && !$this->getAuthManager()->allowsUserIlsLogin()
            ) {
                throw new \Exception(
                    'Illegal configuration: '
                    . 'password-based library cards and disabled user login'
                );
            }
            $catalog = $this->getILS();
            try {
                $patron = $catalog->patronLogin($username, $password);
            } catch (ILSException $e) {
                $this->flashMessenger()->addErrorMessage('ils_connection_failed');
                return false;
            }
            if ($patron) {
                $this->getAuditEventService()->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::EditCard,
                    $user,
                    data: [
                        'username' => $username,
                        'card_id' => $id,
                    ]
                );
            } else {
                if ('password' === $loginMethod) {
                    $this->flashMessenger()->addErrorMessage('authentication_error_invalid');
                }
                $this->getAuditEventService()->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::ILSLoginFailure,
                    $user,
                    data: [
                        'username' => $username,
                        'card_id' => $id,
                    ]
                );
                return false;
            }
            if ('email' === $loginMethod) {
                // Use raw (non-prefixed) username as email to display so that we don't accidentally reveal if a
                // patron was found:
                $authData = [
                    'email' => $rawUsername,
                    'authId' => null,
                ];
                if ($patron) {
                    $cardData = [
                        'cat_username' => $patron['cat_username'],
                        'email' => $patron['email'],
                        'cardID' => $id,
                        'cardName' => $cardName,
                    ];
                    $emailAuthenticator = $this->getService(\VuFind\Auth\EmailAuthenticator::class);
                    $authData['authId'] = $emailAuthenticator->sendAuthenticationCode($cardData['email'], $cardData);
                    $this->getAuditEventService()->addEvent(
                        AuditEventType::User,
                        AuditEventSubtype::SendCardAuthEmail,
                        $user,
                        data: $cardData
                    );
                }
                // Don't reveal the result
                $this->getDbService(UserSessionPersistenceInterface::class)
                    ->setLibraryCardAuthenticationData($authData);
                return $this->redirect()->toRoute('librarycards-verifyotp');
            }
        }

        try {
            $cardService->persistLibraryCardData(
                $user,
                $id == 'NEW' ? null : $id,
                $cardName,
                $username,
                $password
            );
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }
}
