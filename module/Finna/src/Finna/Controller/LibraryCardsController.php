<?php
/**
 * LibraryCards Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Controller for the library card functionality.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class LibraryCardsController extends \VuFind\Controller\LibraryCardsController
{
    /**
     * Send user's library card to the edit view
     *
     * @return mixed
     */
    public function editCardAction()
    {
        // Check login here so that we know not to mess with AuthManager
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $view = parent::editCardAction();

        if (!($view instanceof \Zend\View\Model\ViewModel)) {
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
            $labels = [];

            foreach ($view->targets as $target) {
                $labels[$target]
                    = $manager->getSecondaryLoginFieldLabel($target);
            }
            $view->secondaryLoginFieldLabels = $labels;
        } else {
            $view->secondaryLoginFieldLabel
                = $manager->getSecondaryLoginFieldLabel();
        }
        $manager->setAuthMethod($originalMethod);


        $view->secondaryUsername = $this->params()->fromPost(
            'secondary_username', ''
        );

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
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $card = $user->getLibraryCard($id);
        if ($id == null || !$card->rowExistsInDatabase()) {
            throw new \Exception('Changing password not supported for this card');
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            if ($redirect = $this->processPasswordChange($user, $card)) {
                return $redirect;
            }
        }

        // Connect to the ILS and check if it supports changing password
        $catalog = $this->getILS();
        if (!$catalog->checkFunction('changePassword', $card->toArray())) {
            throw new \Exception('Changing password not supported for this card');
        }
        // It's not exactly correct to send a card to getPasswordPolicy, but it has
        // the required fields..
        $policy = $catalog->getPasswordPolicy($card->toArray());

        $user->updateHash();

        // Send the card to the view:
        return $this->createViewModel(
            [
                'card' => $card,
                'hash' => $user->verify_hash,
                'passwordPolicy' => $policy,
                'verifyold' => true
            ]
        );
    }

    /**
     * Process the "edit library card" submission.
     *
     * @param \VuFind\Db\Row\User $user Logged in user
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

        if (!$username || !$password) {
            $this->flashMessenger()
                ->addMessage('authentication_error_blank', 'error');
            return false;
        }

        if ($target) {
            $username = "$target.$username";
        }

        // Check for a secondary username
        $secondaryUsername = trim($this->params()->fromPost('secondary_username'));

        // Connect to the ILS and check that the credentials are correct:
        $catalog = $this->getILS();
        $patron = $catalog->patronLogin($username, $password, $secondaryUsername);
        if (!$patron) {
            $this->flashMessenger()
                ->addMessage('authentication_error_invalid', 'error');
            return false;
        }

        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        try {
            $user->saveLibraryCard(
                $id == 'NEW' ? null : $id, $cardName, $username, $password
            );
        } catch(\VuFind\Exception\LibraryCard $e) {
            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            return false;
        }

        return $this->redirect()->toRoute('librarycards-home');
    }

    /**
     * Process the "change password" submission.
     *
     * @param \VuFind\Db\Row\User     $user Logged in user
     * @param \VuFind\Db\Row\UserCard $card Library card
     *
     * @return object|bool Response object if redirect is needed, false if form
     * needs to be redisplayed.
     */
    protected function processPasswordChange($user, $card)
    {
        $post = $this->getRequest()->getPost();
        $userFromHash = isset($post->hash)
            ? $this->getTable('User')->getByVerifyHash($post->hash)
            : false;

        $oldPassword = $this->params()->fromPost('oldpwd', '');
        $password = $this->params()->fromPost('password', '');
        $password2 = $this->params()->fromPost('password2', '');

        if ($oldPassword === '' || $password === '' || $password2 === '') {
            $this->flashMessenger()
                ->addMessage('authentication_error_blank', 'error');
            return false;
        }

        // Missing or invalid hash
        if (false == $userFromHash) {
            $this->flashMessenger()->addMessage('recovery_user_not_found', 'error');
            return false;
        } elseif ($userFromHash->username !== $user->username) {
            $this->flashMessenger()
                ->addMessage('authentication_error_invalid', 'error');
            return false;
        }

        // Connect to the ILS and check that the credentials are correct:
        $catalog = $this->getILS();
        $patron = $catalog->patronLogin($card->cat_username, $oldPassword);
        if (!$patron) {
            $this->flashMessenger()
                ->addMessage('authentication_error_invalid', 'error');
            return false;
        }
        if ($password !== $password2) {
            $this->flashMessenger()->addMessage('Passwords do not match', 'error');
            return false;
        }

        $result = $catalog->changePassword(
            [
                'patron' => $patron,
                'oldPassword' => $oldPassword,
                'newPassword' => $password
            ]
        );
        if (!$result['success']) {
            $this->flashMessenger()->addMessage($result['status'], 'error');
            return false;
        }
        $user->saveLibraryCard(
            $card->id, $card->card_name, $card->cat_username, $password
        );
        if ($user->cat_username === $card->cat_username) {
            $user->saveCredentials($card->cat_username, $password);
        }
        $user->updateHash();

        $this->flashMessenger()->addMessage('new_password_success', 'info');

        return $this->redirect()->toRoute('librarycards-home');
    }
}
