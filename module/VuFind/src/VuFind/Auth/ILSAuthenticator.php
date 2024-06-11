<?php

/**
 * Class for managing ILS-specific authentication.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use Laminas\Config\Config;
use Laminas\Crypt\BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection as ILSConnection;

/**
 * Class for managing ILS-specific authentication.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ILSAuthenticator implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Callback for retrieving the authentication manager
     *
     * @var callable
     */
    protected $authManagerCallback;

    /**
     * Authentication manager
     *
     * @var Manager
     */
    protected $authManager = null;

    /**
     * Cache for ILS account information (keyed by username)
     *
     * @var array
     */
    protected $ilsAccount = [];

    /**
     * Is encryption enabled?
     *
     * @var bool
     */
    protected $encryptionEnabled = null;

    /**
     * Encryption key used for catalog passwords (null if encryption disabled):
     *
     * @var string
     */
    protected $encryptionKey = null;

    /**
     * Constructor
     *
     * @param callable            $authCB             Auth manager callback
     * @param ILSConnection       $catalog            ILS connection
     * @param ?EmailAuthenticator $emailAuthenticator Email authenticator
     * @param ?Config             $config             Configuration from config.ini
     */
    public function __construct(
        callable $authCB,
        protected ILSConnection $catalog,
        protected ?EmailAuthenticator $emailAuthenticator = null,
        protected ?Config $config = null
    ) {
        $this->authManagerCallback = $authCB;
    }

    /**
     * Is ILS password encryption enabled?
     *
     * @return bool
     */
    public function passwordEncryptionEnabled()
    {
        if (null === $this->encryptionEnabled) {
            $this->encryptionEnabled
                = $this->config->Authentication->encrypt_ils_password ?? false;
        }
        return $this->encryptionEnabled;
    }

    /**
     * Decrypt text.
     *
     * @param ?string $text The text to decrypt (null values will be returned as null)
     *
     * @return ?string|bool The decrypted string (null if empty or false if invalid)
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function decrypt(?string $text)
    {
        return $this->encryptOrDecrypt($text, false);
    }

    /**
     * Encrypt text.
     *
     * @param ?string $text The text to encrypt (null values will be returned as null)
     *
     * @return ?string|bool The encrypted string (null if empty or false if invalid)
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function encrypt(?string $text)
    {
        return $this->encryptOrDecrypt($text, true);
    }

    /**
     * This is a central function for encrypting and decrypting so that
     * logic is all in one location
     *
     * @param ?string $text    The text to be encrypted or decrypted
     * @param bool    $encrypt True if we wish to encrypt text, False if we wish to
     * decrypt text.
     *
     * @return ?string|bool    The encrypted/decrypted string (null = empty input; false = error)
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function encryptOrDecrypt(?string $text, bool $encrypt = true)
    {
        // Ignore empty text:
        if ($text === null || $text === '') {
            return null;
        }

        $configAuth = $this->config->Authentication ?? new \Laminas\Config\Config([]);

        // Load encryption key from configuration if not already present:
        if ($this->encryptionKey === null) {
            if (empty($configAuth->ils_encryption_key)) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'ILS password encryption on, but no key set.'
                );
            }

            $this->encryptionKey = $configAuth->ils_encryption_key;
        }

        // Perform encryption:
        $algo = $configAuth->ils_encryption_algo ?? 'blowfish';

        // Check if OpenSSL error is caused by blowfish support
        try {
            $cipher = new BlockCipher(new Openssl(['algorithm' => $algo]));
            if ($algo == 'blowfish') {
                trigger_error(
                    'Deprecated encryption algorithm (blowfish) detected',
                    E_USER_DEPRECATED
                );
            }
        } catch (\InvalidArgumentException $e) {
            if ($algo == 'blowfish') {
                throw new \VuFind\Exception\PasswordSecurity(
                    'The blowfish encryption algorithm ' .
                    'is not supported by your version of OpenSSL. ' .
                    'Please visit /Upgrade/CriticalFixBlowfish for further details.'
                );
            } else {
                throw $e;
            }
        }
        $cipher->setKey($this->encryptionKey);
        return $encrypt ? $cipher->encrypt($text) : $cipher->decrypt($text);
    }

    /**
     * Given a user object, retrieve the decrypted password (or null if unset/invalid).
     *
     * @param UserEntityInterface $user User
     *
     * @return ?string
     */
    public function getCatPasswordForUser(UserEntityInterface $user)
    {
        if ($this->passwordEncryptionEnabled()) {
            $encrypted = $user->getCatPassEnc();
            $decrypted = !empty($encrypted) ? $this->decrypt($encrypted) : null;
            if ($decrypted === false) {
                // Unexpected error decrypting password; let's treat it as unset for now:
                return null;
            }
            return $decrypted;
        }
        return $user->getRawCatPassword();
    }

    /**
     * Set ILS login credentials for a user without saving them.
     *
     * @param UserEntityInterface $user     User to update
     * @param string              $username Username to save
     * @param ?string             $password Password to save (null for none)
     *
     * @return void
     */
    public function setUserCatalogCredentials(UserEntityInterface $user, string $username, ?string $password): void
    {
        $user->setCatUsername($username);
        if ($this->passwordEncryptionEnabled()) {
            $user->setRawCatPassword(null);
            $user->setCatPassEnc($this->encrypt($password));
        } else {
            $user->setRawCatPassword($password);
            $user->setCatPassEnc(null);
        }
    }

    /**
     * Save ILS login credentials.
     *
     * @param UserEntityInterface $user     User to update
     * @param string              $username Username to save
     * @param ?string             $password Password to save
     *
     * @return void
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function saveUserCatalogCredentials(UserEntityInterface $user, string $username, ?string $password): void
    {
        $this->setUserCatalogCredentials($user, $username, $password);
        $this->getDbService(UserServiceInterface::class)->persistEntity($user);

        // Update library card entry after saving the user so that we always have a
        // user id:
        $this->getDbService(UserCardServiceInterface::class)->synchronizeUserLibraryCardData($user);
    }

    /**
     * Change and persist the user's home library.
     *
     * @param UserEntityInterface $user        User to update
     * @param ?string             $homeLibrary New home library value (or null to clear)
     *
     * @return void
     */
    public function updateUserHomeLibrary(UserEntityInterface $user, ?string $homeLibrary): void
    {
        // Update the home library and make sure library cards are kept in sync:
        $user->setHomeLibrary($homeLibrary);
        $this->getDbService(UserCardServiceInterface::class)->synchronizeUserLibraryCardData($user);
        $this->getDbService(UserServiceInterface::class)->persistEntity($user);
        $this->getAuthManager()->updateSession($user);
    }

    /**
     * Get stored catalog credentials for the current user.
     *
     * Returns associative array of cat_username and cat_password if they are
     * available, false otherwise. This method does not verify that the credentials
     * are valid.
     *
     * @return array|bool
     */
    public function getStoredCatalogCredentials()
    {
        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if (($user = $this->getAuthManager()->getUserObject()) && ($username = $user->getCatUsername())) {
            return [
                'cat_username' => $username,
                'cat_password' => $this->getCatPasswordForUser($user),
            ];
        }
        return false;
    }

    /**
     * Log the current user into the catalog using stored credentials; if this
     * fails, clear the user's stored credentials so they can enter new, corrected
     * ones.
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function storedCatalogLogin()
    {
        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if (($user = $this->getAuthManager()->getUserObject()) && ($username = $user->getCatUsername())) {
            // Do we have a previously cached ILS account?
            if (isset($this->ilsAccount[$username])) {
                return $this->ilsAccount[$username];
            }
            $patron = $this->catalog->patronLogin(
                $username,
                $this->getCatPasswordForUser($user)
            );
            if (empty($patron)) {
                // Problem logging in -- clear user credentials so they can be
                // prompted again; perhaps their password has changed in the
                // system!
                $user->setCatUsername(null)->setRawCatPassword(null)->setCatPassEnc(null);
            } else {
                // cache for future use
                $this->ilsAccount[$username] = $patron;
                return $patron;
            }
        }

        return false;
    }

    /**
     * Attempt to log in the user to the ILS, and save credentials if it works.
     *
     * @param string $username Catalog username
     * @param string $password Catalog password
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     * @throws ILSException
     */
    public function newCatalogLogin($username, $password)
    {
        $result = $this->catalog->patronLogin($username, $password);
        if ($result) {
            $this->updateUser($username, $password, $result);
            return $result;
        }
        return false;
    }

    /**
     * Send email authentication link
     *
     * @param string $email       Email address
     * @param string $route       Route for the login link
     * @param array  $routeParams Route parameters
     * @param array  $urlParams   URL parameters
     *
     * @return void
     */
    public function sendEmailLoginLink($email, $route, $routeParams = [], $urlParams = [])
    {
        if (null === $this->emailAuthenticator) {
            throw new \Exception('Email authenticator not set');
        }

        $userData = $this->catalog->patronLogin($email, '');
        if ($userData) {
            $this->emailAuthenticator->sendAuthenticationLink(
                $userData['email'],
                compact('userData'),
                ['auth_method' => 'ILS'] + $urlParams,
                $route,
                $routeParams
            );
        }
    }

    /**
     * Process email login
     *
     * @param string $hash Login hash
     *
     * @return array|bool
     * @throws ILSException
     */
    public function processEmailLoginHash($hash)
    {
        if (null === $this->emailAuthenticator) {
            throw new \Exception('Email authenticator not set');
        }

        try {
            $loginData = $this->emailAuthenticator->authenticate($hash);
            // Check if we have more granular data available:
            $patron = $loginData['userData'] ?? $loginData;
        } catch (\VuFind\Exception\Auth $e) {
            return false;
        }
        $this->updateUser($patron['cat_username'], '', $patron);
        return $patron;
    }

    /**
     * Update current user account with the patron information
     *
     * @param string $catUsername Catalog username
     * @param string $catPassword Catalog password
     * @param array  $patron      Patron
     *
     * @return void
     */
    protected function updateUser($catUsername, $catPassword, $patron)
    {
        $user = $this->getAuthManager()->getUserObject();
        if ($user) {
            $this->saveUserCatalogCredentials($user, $catUsername, $catPassword);
            $this->getAuthManager()->updateSession($user);
            // cache for future use
            $this->ilsAccount[$catUsername] = $patron;
        }
    }

    /**
     * Get authentication manager
     *
     * @return Manager
     */
    protected function getAuthManager(): Manager
    {
        if (null === $this->authManager) {
            $this->authManager = ($this->authManagerCallback)();
        }
        return $this->authManager;
    }
}
