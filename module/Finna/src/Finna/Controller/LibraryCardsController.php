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

        if (!$oldPassword || !$password || !$password2) {
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
        if ($user->cat_username == $card->cat_username) {
            $user->saveCredentials($card->cat_username, $password);
        }
        $user->updateHash();

        $this->flashMessenger()->addMessage('new_password_success', 'info');

        return $this->redirect()->toRoute('librarycards-home');
    }
}
