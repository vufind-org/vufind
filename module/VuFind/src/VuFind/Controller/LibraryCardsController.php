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
namespace VuFind\Controller;

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
class LibraryCardsController extends AbstractBase
{
    /**
     * Send user's library cards to the view
     *
     * @return mixed
     */
    public function homeAction()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Check for "delete card" request; parameter may be in GET or POST depending
        // on calling context.
        $deleteId = $this->params()->fromPost(
            'delete', $this->params()->fromQuery('delete')
        );
        if ($deleteId) {
            // If the user already confirmed the operation, perform the delete now;
            // otherwise prompt for confirmation:
            $confirm = $this->params()->fromPost(
                'confirm', $this->params()->fromQuery('confirm')
            );
            if ($confirm) {
                $success = $this->performDeleteLibraryCard($deleteId);
                if ($success !== true) {
                    return $success;
                }
            } else {
                return $this->confirmDeleteLibraryCard($deleteId);
            }
        }

        // Connect to the ILS for login drivers:
        $catalog = $this->getILS();

        return $this->createViewModel(
            [
                'libraryCards' => $user->getLibraryCards(),
                'multipleTargets' => $catalog->checkCapability('getLoginDrivers')
            ]
        );
    }

    /**
     * Send user's library card to the edit view
     *
     * @return mixed
     */
    public function editCardAction()
    {
        // User must be logged in to edit library cards:
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            if ($redirect = $this->processEditLibraryCard($user)) {
                return $redirect;
            }
        }

        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $card = $user->getLibraryCard($id == 'NEW' ? null : $id);

        $target = null;
        $username = $card->cat_username;
        $targets = null;
        $defaultTarget = null;
        // Connect to the ILS and check if multiple target support is available:
        $catalog = $this->getILS();
        if ($catalog->checkCapability('getLoginDrivers')) {
            $targets = $catalog->getLoginDrivers();
            $defaultTarget = $catalog->getDefaultLoginDriver();
            if (strstr($username, '.')) {
                list($target, $username) = explode('.', $username, 2);
            }
        }
        $cardName = $this->params()->fromPost('card_name', $card->card_name);
        $username = $this->params()->fromPost('username', $username);
        $target = $this->params()->fromPost('target', $target);

        // Send the card to the view:
        return $this->createViewModel(
            [
                'card' => $card,
                'cardName' => $cardName,
                'target' => $target ? $target : $defaultTarget,
                'username' => $username,
                'password' => $card->cat_password,
                'targets' => $targets,
                'defaultTarget' => $defaultTarget
            ]
        );
    }

    /**
     * Creates a confirmation box to delete or not delete the current list
     *
     * @return mixed
     */
    public function deleteCardAction()
    {
        // User must be logged in to edit library cards:
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        // Get requested library card ID:
        $cardID = $this->params()
            ->fromPost('cardID', $this->params()->fromQuery('cardID'));

        // Have we confirmed this?
        $confirm = $this->params()->fromPost(
            'confirm', $this->params()->fromQuery('confirm')
        );
        if ($confirm) {
            $user->deleteLibraryCard($cardID);

            // Success Message
            $this->flashMessenger()->addMessage('Library Card Deleted', 'success');
            // Redirect to MyResearch library cards
            return $this->redirect()->toRoute('librarycards-home');
        }

        // If we got this far, we must display a confirmation message:
        return $this->confirm(
            'confirm_delete_library_card_brief',
            $this->url()->fromRoute('librarycards-deletecard'),
            $this->url()->fromRoute('librarycards-home'),
            'confirm_delete_library_card_text', ['cardID' => $cardID]
        );
    }

    /**
     * Activates a library card
     *
     * @return \Zend\Http\Response
     */
    public function selectCardAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $cardID = $this->params()->fromQuery('cardID');
        $user->activateLibraryCard($cardID);

        $this->setFollowupUrlToReferer();
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();
            return $this->redirect()->toUrl($url);
        }
        return $this->redirect()->toRoute('myresearch-home');
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

        // Connect to the ILS and check that the credentials are correct:
        $catalog = $this->getILS();
        $patron = $catalog->patronLogin($username, $password);
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
}
