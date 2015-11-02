<?php
/**
 * Additional functionality for ILS/MultiILS authentication.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015.
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
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\Auth;

/**
 * Additional functionality for ILS/MultiILS authentication.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
trait ILSFinna
{
    /**
     * Get secondary login field label (if any)
     *
     * @param string $target Login target (MultiILS)
     *
     * @return string
     */
    public function getSecondaryLoginFieldLabel($target)
    {
        $catalog = $this->getCatalog();
        if (!$catalog->checkCapability(
            'getConfig', ['cat_username' => "$target.login"]
        )) {
            return '';
        }
        $config = $this->getCatalog()->getConfig(
            'patronLogin', ['cat_username' => "$target.login"]
        );
        if (!empty($config['secondary_login_field_label'])) {
            return $config['secondary_login_field_label'];
        }
        return '';
    }

    /**
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function processILSUser($info)
    {
        if (empty($info['email'])) {
            // Try to fetch patron's profile to get the email address
            $profile = $this->getCatalog()->getMyProfile($info);
            $info['email'] = $profile['email'];
        }

        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $config = $this->getConfig();
        $usernameField = isset($config->Authentication->ILS_username_field)
            ? $config->Authentication->ILS_username_field : 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $user = $this->getUserTable()->getByUsername($info[$usernameField]);

        // No need to store the ILS password in VuFind's main password field:
        $user->password = '';

        // Update user information based on ILS data:
        $fields = ['firstname', 'lastname', 'email', 'major', 'college'];
        foreach ($fields as $field) {
            // Special case: don't override existing email address:
            if ($field == 'email') {
                if (isset($user->email) && trim($user->email) != '') {
                    continue;
                }
            }
            $user->$field = isset($info[$field]) ? $info[$field] : ' ';
        }

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            isset($info['cat_username']) ? $info['cat_username'] : ' ',
            isset($info['cat_password']) ? $info['cat_password'] : ' '
        );

        return $user;
    }

}
