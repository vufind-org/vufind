<?php

/**
 * LibraryCards Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container as SessionContainer;
use VuFind\Db\Entity\UserCardEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\Auth as AuthException;

use function in_array;
use function intval;

/**
 * Controller for the library card functionality.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class LibraryCardsController extends \VuFind\Controller\LibraryCardsController
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm      Service locator
     * @param SessionContainer        $session Session container for library cards
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected SessionContainer $session,
    ) {
        parent::__construct($sm);
        $this->session->LibraryCards ??= [];
    }

    /**
     * Send user's library cards to the view
     *
     * @return mixed
     */
    public function homeAction()
    {
        $view = parent::homeAction();
        if (!empty($view->libraryCards)) {
            // Try to see if the actual barcode is different from the login ID.
            // Typical with email login.
            try {
                $cards = [];
                $patron = $this->getILSAuthenticator()->storedCatalogLogin();
                foreach ($view->libraryCards as $card) {
                    if (
                        $patron
                        && $patron['cat_username'] === $card->getCatUsername()
                    ) {
                        $profile = $this->getILS()->getMyProfile($patron);
                        if (!empty($profile['barcode'])) {
                            $card->setBarcode($profile['barcode']);
                        }
                        array_unshift($cards, $card);
                        continue;
                    }
                    $cards[] = $card;
                }
                $view->libraryCards = $cards;
            } catch (\Exception $e) {
                // No worries, this isn't critical
            }
        }

        return $view;
    }

    /**
     * Send user's library card to the edit view
     *
     * @return mixed
     */
    public function editCardAction()
    {
        // Check login here so that we know not to mess with AuthManager
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        $view = parent::editCardAction();

        if (!($view instanceof \Laminas\View\Model\ViewModel)) {
            return $view;
        }

        $manager = $this->getAuthManager();
        $options = $manager->getSelectableAuthOptions();
        $originalMethod = $manager->getAuthMethod();
        if (in_array('MultiILS', $options)) {
            $manager->setAuthMethod('MultiILS');
        } else {
            $manager->setAuthMethod('ILS');
        }
        if (!empty($view->targets)) {
            // This is set for back-compatibility:
            $view->secondaryLoginFieldLabels = [];
        } else {
            // This is set for back-compatibility:
            $view->secondaryLoginFieldLabel = false;
        }
        $manager->setAuthMethod($originalMethod);

        // This is set for back-compatibility:
        $view->secondaryUsername = '';

        return $view;
    }

    /**
     * Change library card password
     *
     * @return mixed
     */
    public function newPasswordAction()
    {
        // User must be logged in to edit library cards:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        if (null === ($id = $this->params()->fromRoute('id', $this->params()->fromQuery('id')))) {
            throw new \Exception('Changing password not supported for this card');
        }
        $cards = $this->getDbService(UserCardServiceInterface::class)->getLibraryCards($user, $id);
        $card = current($cards);
        if (!$card) {
            throw new \Exception('Changing password not supported for this card');
        }

        // Process form submission:
        if ($this->formWasSubmitted()) {
            if ($redirect = $this->processPasswordChange($user, $card)) {
                return $redirect;
            }
        }

        // Connect to the ILS and check if it supports changing password
        $catalog = $this->getILS();
        $ilsParams = ['cat_username' => $card->getCatUsername()];
        if (!$catalog->checkFunction('changePassword', $ilsParams)) {
            $this->flashMessenger()->addErrorMessage('Changing password not supported for this card');
            return $this->createViewModel();
        }
        // It's not exactly correct to send a card to getPasswordPolicy, but it has
        // the required fields..
        $policy = $catalog->getPasswordPolicy($ilsParams);
        if (isset($policy['pattern']) && empty($policy['hint'])) {
            $pattern = $policy['pattern'];
            $policy['hint'] = in_array($pattern, ['numeric', 'alphanumeric'])
                ? 'password_only_' . $pattern : null;
        }

        $this->getAuthManager()->updateUserVerifyHash($user);

        // Send the card to the view:
        return $this->createViewModel(
            [
                'card' => $card,
                'hash' => $user->getVerifyHash(),
                'passwordPolicy' => $policy,
                'verifyold' => true,
            ]
        );
    }

    /**
     * Recover a library account
     *
     * @return View object
     *
     * @deprecated Exists for back-compatibility with old implementation only
     */
    public function recoverAction()
    {
        $params = [
            'target' => $this->params()->fromQuery('target') ?? $this->params()->fromPost('target'),
            'auth_method' => $this->params()->fromQuery('auth_method')
                ?? $this->params()->fromPost('auth_method')
                ?? 'MultiILS',
        ];
        return $this->redirect()->toRoute(
            'default',
            ['controller' => 'MyResearch', 'action' => 'Recover'],
            ['query' => $params]
        );
    }

    /**
     * Self-registration action
     *
     * @return View object
     */
    public function registerAction()
    {
        // Make sure we're configured to do this
        $target = $this->params()->fromQuery(
            'target',
            $this->params()->fromPost('target', '')
        );
        $catalog = $this->getILS();
        $registerConfig = $catalog->checkFunction(
            'registerPatron',
            ['patron' => ['cat_username' => "$target.123"]]
        );
        if (!$registerConfig) {
            throw new \Exception('Self-registration disabled');
        }
        $view = $this->createViewModel(
            [
                'target' => $target,
                'introductionText' => $registerConfig['introductionText'] ?? '',
            ]
        );
        $view->useCaptcha = $this->captcha()->active('passwordRecovery');
        // If we have a submitted form
        if ($this->formWasSubmitted(null, $view->useCaptcha)) {
            $email = trim($this->params()->fromPost('email'));
            if (empty($email)) {
                $this->flashMessenger()->addErrorMessage('no_email_address');
            } else {
                $emailAuthenticator = $this->serviceLocator
                    ->get(\VuFind\Auth\EmailAuthenticator::class);

                $patron = $catalog->patronLogin("$target.$email", ' ');
                $targetName = $this->translate("source_$target", null, $target);
                $subject = $this->translate(
                    'email_registration_subject',
                    ['%%target%%' => $targetName]
                );
                try {
                    if ($patron) {
                        $patron['target'] = $target;
                        $emailAuthenticator->sendAuthenticationLink(
                            $patron['email'],
                            $patron,
                            ['auth_method' => 'MultiILS'],
                            'myresearch-home',
                            [],
                            $subject,
                            'Email/registration-login-link.phtml'
                        );
                    } else {
                        $emailAuthenticator->sendAuthenticationLink(
                            $email,
                            [
                                'email' => $email,
                                'target' => $target,
                            ],
                            [],
                            'librarycards-registrationform',
                            [],
                            $subject,
                            'Email/registration-link.phtml'
                        );
                    }
                    $this->flashMessenger()
                        ->addSuccessMessage('email_registration_link_sent');
                    $view->emailSent = true;
                } catch (AuthException $e) {
                    $this->flashMessenger()
                        ->addErrorMessage($e->getMessage());
                }
            }
        }
        return $view;
    }

    /**
     * Self-registration form action
     *
     * @return View object
     */
    public function registrationFormAction()
    {
        // Verify hash
        $sessionManager = $this->serviceLocator
            ->get(\Laminas\Session\SessionManager::class);
        $session = new \Laminas\Session\Container('registerPatron', $sessionManager);
        $hash = $this->params()->fromQuery(
            'hash',
            $this->params()->fromPost('hash', '')
        );
        if (empty($session->params[$hash])) {
            $emailAuthenticator = $this->serviceLocator
                ->get(\VuFind\Auth\EmailAuthenticator::class);
            $params = $emailAuthenticator->authenticate($hash);
            if (!isset($session->params)) {
                $session->params = [];
            }
            $params['hash'] = $hash;
            $session->params[$hash] = $params;
        } else {
            $params = $session->params[$hash];
        }

        // Make sure we're configured to do this
        $target = $params['target'];
        $catalog = $this->getILS();
        $registerConfig = $catalog->checkFunction(
            'registerPatron',
            ['patron' => ['cat_username' => "$target.123"]]
        );
        if (!$registerConfig) {
            throw new \Exception('Self-registration disabled');
        }
        if (!empty($registerConfig['fields'])) {
            $fields = $registerConfig['fields'];
        } else {
            $fields = [
                'firstname' => [
                    'label' => 'First Name',
                    'type' => 'text',
                    'required' => true,
                ],
                'lastname' => [
                    'label' => 'Last Name',
                    'type' => 'text',
                    'required' => true,
                ],
                'identitynumber' => [
                    'label' => 'Identity Number',
                    'type' => 'text',
                ],
                'email' => [
                    'label' => 'Email',
                    'type' => 'email',
                    'readonly' => true,
                ],
                'address' => [
                    'label' => 'Address',
                    'type' => 'text',
                    'required' => true,
                ],
                'zip' => [
                    'label' => 'Zip',
                    'type' => 'text',
                    'required' => true,
                ],
                'city' => [
                    'label' => 'Post Office',
                    'type' => 'text',
                    'required' => true,
                ],
                'phone' => [
                    'label' => 'Phone',
                    'type' => 'text',
                    'required' => true,
                ],
                'language' => [
                    'label' => 'Preferred Language',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        'fi' => ['name' => 'Suomi'],
                        'sv' => ['name' => 'Svenska'],
                        'en' => ['name' => 'English'],
                    ],
                ],
            ];
        }
        foreach ($fields as $id => &$fieldRef) {
            // Use the email address used for the registration message
            $fieldRef['value'] = 'email' === $id
                ? $params['email'] : $params['userdata'][$id] ?? '';
        }
        // Unset reference so that any further use doesn't access the referred
        // element
        unset($fieldRef);

        $params['fields'] = $fields;
        $view = $this->createViewModel($params);
        $view->termsUrl = $registerConfig['termsUrl'] ?? '';
        $view->registrationHelpText = $registerConfig['registrationHelpText'] ?? '';

        // If we have a submitted form
        if ($this->formWasSubmitted()) {
            $missingFields = false;
            foreach ($fields as $id => $field) {
                // Don't let the user override the email address
                $params['userdata'][$id] = 'email' === $id
                    ? $params['email'] : trim($this->params()->fromPost($id, ''));
                if (
                    ($field['required'] ?? false)
                    && '' === $params['userdata'][$id]
                ) {
                    $missingFields = true;
                }
            }
            if (!$this->params()->fromPost('acceptTerms')) {
                $missingFields = true;
            }
            $session->params[$hash] = $params;
            if ($missingFields) {
                $this->flashMessenger()->addErrorMessage('Fill mandatory fields');
            } else {
                $params['userdataok'] = true;
                $session->params[$hash] = $params;
                $result = $catalog->registerPatron(
                    [
                        'cat_username' => "$target.123",
                        'userdata' => $params['userdata'],
                    ]
                );
                if ($result['success']) {
                    $this->flashMessenger()
                        ->addSuccessMessage('new_ils_account_added');
                    return $this->redirect()->toRoute(
                        'librarycards-registrationdone',
                        [],
                        ['query' => ['hash' => $hash]]
                    );
                } else {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                }
            }
        }
        return $view;
    }

    /**
     * Self-registration confirmation action
     *
     * @return View object
     */
    public function registrationDoneAction()
    {
        // Verify hash
        $sessionManager = $this->serviceLocator
            ->get(\Laminas\Session\SessionManager::class);
        $session = new \Laminas\Session\Container('registerPatron', $sessionManager);
        $hash = $this->params()->fromQuery(
            'hash',
            $this->params()->fromPost('hash', '')
        );
        if (empty($session->params[$hash])) {
            throw new \Exception('An error has occurred');
        }
        $params = $session->params[$hash];
        if (empty($params['userdataok'])) {
            throw new \Exception('An error has occurred');
        }

        $target = $params['target'];
        $catalog = $this->getILS();
        $registerConfig = $catalog->checkFunction(
            'registerPatron',
            ['patron' => ['cat_username' => "$target.123"]]
        );
        if (!$registerConfig) {
            throw new \Exception('A error has occurred');
        }
        $organisationInfoId = $registerConfig['organisationInfoId'] ?? null;

        return $this->createViewModel(compact('target', 'organisationInfoId'));
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
                ->addMessage('authentication_error_blank', 'error');
            return false;
        }

        if ($target) {
            $username = "$target.$username";
        }

        // Connect to the ILS and check that the credentials are correct:
        $loginMethod = $this->getILSLoginMethod($target);
        $catalog = $this->getILS();
        try {
            $patron = $catalog->patronLogin($username, $password);
        } catch (\VuFind\Exception\ILS $e) {
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
            if ($patron) {
                $info = $patron;
                $info['cardID'] = $id;
                $info['cardName'] = $cardName;
                $emailAuthenticator = $this->serviceLocator
                    ->get(\VuFind\Auth\EmailAuthenticator::class);
                $emailAuthenticator->sendAuthenticationLink(
                    $info['email'],
                    $info,
                    ['auth_method' => 'Email'],
                    'editLibraryCard'
                );
                $this->getAuditEventService()->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::SendCardAuthEmail,
                    $user,
                    data: [
                        'username' => $username,
                        'card_id' => $id,
                        'email' => $info['email'],
                    ]
                );
            }
            // Don't reveal the result
            $this->flashMessenger()->addSuccessMessage('email_login_link_sent');
            return $this->redirect()->toRoute('librarycards-home');
        }

        $userCardService = $this->getDbService(UserCardServiceInterface::class);
        if (!empty($cardName)) {
            [$cardInstitution] = explode('.', $username, 2);
            foreach ($userCardService->getLibraryCards($user) as $otherCard) {
                if ($otherCard->getId() == $id) {
                    continue;
                }
                [$otherInstitution] = explode('.', $otherCard->getCatUsername(), 2);
                if (
                    $cardInstitution == $otherInstitution
                    && strcasecmp($cardName, $otherCard->getCardName()) == 0
                ) {
                    $this->flashMessenger()->addMessage(
                        'library_card_name_exists',
                        'error'
                    );
                    return false;
                }
            }
        }

        try {
            $userCardService->persistLibraryCardData(
                $user,
                $id == 'NEW' ? null : $id,
                $cardName,
                $username,
                $password
            );
        } catch (\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Process the "change password" submission.
     *
     * @param UserEntityInterface     $user Logged in user
     * @param UserCardEntityInterface $card Library card
     *
     * @return object|bool Response object if redirect is needed, false if form
     * needs to be redisplayed.
     */
    protected function processPasswordChange(UserEntityInterface $user, UserCardEntityInterface $card)
    {
        $post = $this->getRequest()->getPost();
        $userFromHash = isset($post->hash)
            ? $this->getDbService(UserServiceInterface::class)->getUserByVerifyHash($post->hash)
            : null;

        $oldPassword = $this->params()->fromPost('oldpwd', '');
        $password = $this->params()->fromPost('password', '');
        $password2 = $this->params()->fromPost('password2', '');

        // Validate new password
        try {
            $ilsAuth = $this->serviceLocator->get(\VuFind\Auth\PluginManager::class)->get('ILS');
            $ilsAuth->validatePasswordInUpdate(['password' => $password, 'password2' => $password2]);
        } catch (AuthException $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            return false;
        }

        // Missing or invalid hash
        if (null === $userFromHash) {
            $this->flashMessenger()->addMessage('recovery_user_not_found', 'error');
            return false;
        } elseif ($userFromHash->getUsername() !== $user->getUsername()) {
            $this->flashMessenger()->addMessage('authentication_error_invalid', 'error');
            return false;
        }

        // Connect to the ILS and check that the credentials are correct:
        $catalog = $this->getILS();
        $patron = $catalog->patronLogin($card->getCatUsername(), $oldPassword);
        if (!$patron) {
            $this->flashMessenger()->addMessage('authentication_error_invalid', 'error');
            return false;
        }

        $result = $catalog->changePassword(
            [
                'patron' => $patron,
                'oldPassword' => $oldPassword,
                'newPassword' => $password,
            ]
        );
        if (
            !$result['success']
            && $result['status'] == 'authentication_error_invalid'
            && !empty($oldPassword)
        ) {
            // Try again with empty old password just in case this was a user that
            // was logged in with the fallback login field
            $result = $catalog->changePassword(
                [
                    'patron' => $patron,
                    'oldPassword' => '',
                    'newPassword' => $password,
                ]
            );
        }
        if (!$result['success']) {
            $this->flashMessenger()->addMessage($result['status'], 'error');
            return false;
        }
        $userCardService = $this->getDbService(UserCardServiceInterface::class);
        $userCardService->persistLibraryCardData(
            $user,
            $card,
            $card->getCardName(),
            $card->getCatUsername(),
            $password
        );
        if (strcasecmp($user->getCatUsername(), $card->getCatUsername()) === 0) {
            $userCardService->activateLibraryCard($user, $card->getId());
        }
        $this->getAuthManager()->updateUserVerifyHash($user);

        $this->flashMessenger()->addSuccessMessage('new_password_success');

        $this->getAuditEventService()->addEvent(
            AuditEventType::User,
            AuditEventSubtype::PasswordChanged,
            $user,
        );

        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Fetch and display requested library card's barcode.
     *
     * @return mixed
     */
    public function displayBarcodeAction(): mixed
    {
        try {
            if (!($user = $this->getUser())) {
                return $this->forceLogin();
            }
            if (!($id = $this->params()->fromRoute('id', $this->params()->fromQuery('id')))) {
                return $this->redirect()->toRoute('librarycards-home');
            }
            $userCardService = $this->getDbService(UserCardServiceInterface::class);
            $card = $userCardService->getOrCreateLibraryCard($user, $id);
            $username = $card->getCatUsername();
            if (str_contains($username, '.')) {
                [, $username] = explode('.', $username, 2);
            }
            $cacheKey = $username . '|' . $id;
            if (isset($this->session->LibraryCards[$cacheKey])) {
                $barcode = $this->session->LibraryCards[$cacheKey];
                return $this->createViewModel(['code' => $barcode]);
            }
            $catalog = $this->getILS();
            $auth = $this->getILSAuthenticator();
            if ($card->getCatUsername() === $user->getCatUsername()) {
                $patron = $auth->storedCatalogLogin();
            } else {
                $loginUser = clone $user;
                $loginUser->setCatUsername($card->getCatUsername());
                $loginUser->setRawCatPassword($card->getRawCatPassword());
                $loginUser->setCatPassEnc($card->getCatPassEnc());
                $patron = $catalog->patronLogin(
                    $loginUser->getCatUsername(),
                    $auth->getCatPasswordForUser($loginUser)
                );
            }
            if ($patron['cat_username'] === $card->getCatUsername()) {
                $profile = $catalog->getMyProfile($patron);
                if (!empty($profile['barcode'])) {
                    $barcode = $profile['barcode'];
                }
            }
            $barcode ??= $username;
            $this->session->LibraryCards[$cacheKey] = $barcode;
            return $this->createViewModel(['code' => $barcode]);
        } catch (\Exception) {
            $this->flashMessenger()->addErrorMessage('An error has occurred');
            return $this->redirect()->toRoute('librarycards-home');
        }
    }

    /**
     * Helper function for verification hashes
     *
     * @param string $hash User-unique hash string from request
     *
     * @return int age in seconds
     */
    protected function getHashAge($hash)
    {
        return intval(substr($hash, -10));
    }
}
