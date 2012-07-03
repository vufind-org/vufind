<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Controller;
use Zend\Mvc\Controller\ActionController;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class AbstractBase extends ActionController
{
    /**
     * Redirect the user to the login screen.
     *
     * @param string $msg    Flash message to display on login screen
     * @param array  $extras Associative array of extra fields to store
     *
     * @return void
     */
    protected function forceLogin($msg = null, $extras = array())
    {
        /* TODO:
        // Set default message if necessary.
        if (is_null($msg)) {
            $msg = 'You must be logged in first';
        }

        // Store the current URL as a login followup action unless we are in a
        // lightbox (since lightboxes use a different followup mechanism).
        if ($this->_helper->layout->getLayout() != 'lightbox') {
            $this->_helper->followup->store($extras);
        }
        if (!empty($msg)) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage($msg);
        }

        // Set a flag indicating that we are forcing login:
        $this->_request->setParam('forcingLogin', true);

        $this->_forward('Login', 'MyResearch');
         */
    }

    /**
     * Does the user have catalog credentials available?  Returns associative array
     * of patron data if so, otherwise forwards to appropriate login prompt and
     * returns false.
     *
     * @return array|bool
     */
    protected function catalogLogin()
    {
        /* TODO:
        // First make sure user is logged in to VuFind:
        $account = VF_Account_Manager::getInstance();
        $user = $account->isLoggedIn();
        if ($user == false) {
            $this->forceLogin();
            return false;
        }

        // Now check if the user has provided credentials with which to log in:
        if (($username = $this->getRequest()->post()->get('cat_username', false))
            && ($password = $this->getRequest()->post()->get('cat_password', false))
        ) {
            $patron = $account->newCatalogLogin($username, $password);

            // If login failed, store a warning message:
            if (!$patron) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage('Invalid Patron Login');
            }
        } else {
            // If no credentials were provided, try the stored values:
            $patron = $account->storedCatalogLogin();
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            $this->_forward('CatalogLogin', 'MyResearch');
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
         */
    }
}