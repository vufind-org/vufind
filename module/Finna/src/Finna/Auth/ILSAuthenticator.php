<?php
/**
 * Class for managing ILS-specific authentication.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;
use VuFind\Exception\ILS as ILSException, VuFind\ILS\Connection as ILSConnection;

/**
 * Class for managing ILS-specific authentication.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ILSAuthenticator extends \VuFind\Auth\ILSAuthenticator
{
    /**
     * Attempt to log in the user to the ILS, and save credentials if it works.
     *
     * @param string $username  Catalog username
     * @param string $password  Catalog password
     * @param string $username2 Secondary user name
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function newCatalogLogin($username, $password, $secondaryUsername = '')
    {
        try {
            $result = $this->catalog->patronLogin(
                $username, $password, $secondaryUsername
            );
        } catch (ILSException $e) {
            return false;
        }
        if ($result) {
            $user = $this->auth->isLoggedIn();
            if ($user) {
                $user->saveCredentials($username, $password);
                $this->auth->updateSession($user);
                // cache for future use
                $this->ilsAccount[$username] = $result;
            }
            return $result;
        }
        return false;
    }
}
