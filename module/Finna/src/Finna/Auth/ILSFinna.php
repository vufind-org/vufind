<?php

/**
 * Additional functionality for ILS/MultiILS authentication.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace Finna\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * Additional functionality for ILS/MultiILS authentication.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
trait ILSFinna
{
    /**
     * Check if ILS supports self-registration
     *
     * @param string $target Login target (MultiILS)
     *
     * @return string
     */
    public function ilsSupportsSelfRegistration($target)
    {
        $catalog = $this->getCatalog();
        $config = $catalog->checkFunction(
            'registerPatron',
            ['cat_username' => "$target.123"]
        );
        return !empty($config);
    }

    /**
     * Make sure passwords match and fulfill ILS policy
     *
     * @param array $params request parameters
     *
     * @return void
     */
    public function validatePasswordInUpdate($params)
    {
        $this->validatePasswordUpdate($params);
    }

    /**
     * Handle the actual login with the ILS.
     *
     * @param string $username    User name
     * @param string $password    Password
     * @param string $loginMethod Login method
     * @param bool   $rememberMe  Whether to remember the login
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function handleLogin($username, $password, $loginMethod, $rememberMe)
    {
        $username = str_replace(' ', '', $username);
        return parent::handleLogin($username, $password, $loginMethod, $rememberMe);
    }

    /**
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function processILSUser($info)
    {
        if (empty($info['email'])) {
            // Try to fetch patron's profile to get the email address
            $profile = $this->getCatalog()->getMyProfile($info);
            if (!empty($profile['email'])) {
                $info['email'] = $profile['email'];
            }
        }

        // Add institution prefix to id
        if (isset($info['id'])) {
            $config = $this->getConfig()->toArray();
            if ($institution = $config['Site']['institution'] ?? null) {
                $info['id'] = "$institution:" . $info['id'];
            }
        }

        $user = parent::processILSUser($info);

        // Set home library if not already set
        if (!empty($info['home_library']) && empty($user->getHomeLibrary())) {
            $this->authenticator->updateUserHomeLibrary($user, $info['home_library']);
        }

        return $user;
    }
}
