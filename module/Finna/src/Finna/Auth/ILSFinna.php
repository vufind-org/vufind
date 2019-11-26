<?php
/**
 * Additional functionality for ILS/MultiILS authentication.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace Finna\Auth;

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
     * Get secondary login field label (if any)
     *
     * @param string $target Login target (MultiILS)
     *
     * @return string
     */
    public function getSecondaryLoginFieldLabel($target)
    {
        $catalog = $this->getCatalog();
        $check = $catalog->checkCapability(
            'getConfig', ['cat_username' => "$target.login"]
        );
        if (!$check) {
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
     * Check if ILS supports password recovery
     *
     * @param string $target Login target (MultiILS)
     *
     * @return string
     */
    public function ilsSupportsPasswordRecovery($target)
    {
        $catalog = $this->getCatalog();
        $recoveryConfig = $catalog->checkFunction(
            'recoverPassword', ['cat_username' => "$target.123"]
        );
        return $recoveryConfig ? true : false;
    }

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
            'registerPatron', ['cat_username' => "$target.123"]
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
     * @param string $username          User name
     * @param string $password          Password
     * @param string $loginMethod       Login method
     * @param string $secondaryUsername Secondary user name
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function handleLogin($username, $password, $loginMethod,
        $secondaryUsername = ''
    ) {
        $username = str_replace(' ', '', $username);
        if ($username == '' || ('password' === $loginMethod && $password == '')) {
            throw new AuthException('authentication_error_blank');
        }

        // Connect to catalog:
        try {
            $patron = $this->getCatalog()->patronLogin(
                $username, $password, $secondaryUsername
            );
        } catch (AuthException $e) {
            // Pass Auth exceptions through
            throw $e;
        } catch (\Exception $e) {
            throw new AuthException('authentication_error_technical');
        }

        // Did the patron successfully log in?
        if ('email' === $loginMethod) {
            if (null === $this->emailAuthenticator) {
                throw new \Exception('Email authenticator not set');
            }
            if ($patron) {
                $class = get_class($this);
                if ($p = strrpos($class, '\\')) {
                    $class = substr($class, $p + 1);
                }
                $this->emailAuthenticator->sendAuthenticationLink(
                    $patron['email'],
                    $patron,
                    ['auth_method' => $class]
                );
            }
            // Don't reveal the result
            throw new \VuFind\Exception\AuthInProgress('email_login_link_sent');
        }
        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
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
            if (!empty($profile['email'])) {
                $info['email'] = $profile['email'];
            }
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
        $userTable = $this->getUserTable();
        if (!empty($info['id'])) {
            $user = $userTable->getByCatalogId($info['id'], false);
            if (empty($user)) {
                $user = $userTable->getByUsername($info[$usernameField]);
                $user->saveCatalogId($info['id']);
            }
        } else {
            $user = $userTable->getByUsername($info[$usernameField]);
        }

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
            $user->$field = $info[$field] ?? ' ';
        }

        // Set home library if not already set
        if (!empty($info['home_library']) && empty($user->home_library)) {
            $user->home_library = $info['home_library'];
        }

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            $info['cat_username'] ?? ' ',
            $info['cat_password'] ?? ' '
        );

        return $user;
    }
}
