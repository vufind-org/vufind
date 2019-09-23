<?php
/**
 * CatalogLogin trait.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use VuFind\Exception\ILS as ILSException;

/**
 * CatalogLogin trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait CatalogLoginTrait
{
    /**
     * Does the user have catalog credentials available?  Returns associative array
     * of patron data if so, otherwise forwards to appropriate login prompt and
     * returns false. If there is an ILS exception, a flash message is added and
     * a newly created ViewModel is returned.
     *
     * Finna version adds handling of secondary user name
     *
     * @return bool|array|ViewModel
     */
    protected function catalogLogin()
    {
        // First make sure user is logged in to VuFind:
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            return $this->forceLogin();
        }

        // Now check if the user has provided credentials with which to log in:
        $ilsAuth = $this->getILSAuthenticator();
        $patron = null;
        if (($username = $this->params()->fromPost('cat_username', false))
            && ($password = $this->params()->fromPost('cat_password', false))
        ) {
            // Check for multiple ILS target selection
            $target = $this->params()->fromPost('target', false);
            if ($target) {
                $username = "$target.$username";
            }
            $secondaryUsername = $this->params()->fromPost(
                'cat_secondary_username', ''
            );
            try {
                if ('email' === $this->getILSLoginMethod($target)) {
                    $routeMatch = $this->getEvent()->getRouteMatch();
                    $routeName = $routeMatch ? $routeMatch->getMatchedRouteName()
                        : 'myresearch-profile';
                    $ilsAuth->sendEmailLoginLink($username, $routeName);
                    $this->flashMessenger()
                        ->addSuccessMessage('email_login_link_sent');
                } else {
                    $patron = $ilsAuth->newCatalogLogin(
                        $username,
                        $password,
                        $secondaryUsername
                    );

                    // If login failed, store a warning message:
                    if (!$patron) {
                        $this->flashMessenger()
                            ->addErrorMessage('Invalid Patron Login');
                    }
                }
            } catch (ILSException $e) {
                $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            }
        } elseif ('ILS' === $this->params()->fromQuery('auth_method', false)
            && ($hash = $this->params()->fromQuery('hash', false))
        ) {
            try {
                $patron = $ilsAuth->processEmailLoginHash($hash);
            } catch (AuthException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }
        } else {
            try {
                // If no credentials were provided, try the stored values:
                $patron = $ilsAuth->storedCatalogLogin();
            } catch (ILSException $e) {
                $this->flashMessenger()->addErrorMessage('ils_connection_failed');
                return $this->createViewModel();
            }
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            return $this->forwardTo('MyResearch', 'CatalogLogin');
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
    }
}
