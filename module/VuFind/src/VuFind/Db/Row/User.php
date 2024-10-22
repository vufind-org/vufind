<?php

/**
 * Row Definition for user
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\UserCard;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Favorites\FavoritesService;

use function count;

/**
 * Row Definition for user
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property ?string $username
 * @property string  $password
 * @property ?string $pass_hash
 * @property string  $firstname
 * @property string  $lastname
 * @property string  $email
 * @property ?string $email_verified
 * @property string  $pending_email
 * @property int     $user_provided_email
 * @property ?string $cat_id
 * @property ?string $cat_username
 * @property ?string $cat_password
 * @property ?string $cat_pass_enc
 * @property string  $college
 * @property string  $major
 * @property ?string $home_library
 * @property string  $created
 * @property string  $verify_hash
 * @property string  $last_login
 * @property ?string $auth_method
 * @property string  $last_language
 */
class User extends RowGateway implements
    UserEntityInterface,
    \VuFind\Db\Service\DbServiceAwareInterface,
    \VuFind\Db\Table\DbTableAwareInterface,
    \LmcRbacMvc\Identity\IdentityInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter          Database adapter
     * @param ILSAuthenticator            $ilsAuthenticator ILS authenticator
     * @param AccountCapabilities         $capabilities     Account capabilities configuration (null for defaults)
     * @param FavoritesService            $favoritesService Favorites service
     */
    public function __construct(
        $adapter,
        protected ILSAuthenticator $ilsAuthenticator,
        protected AccountCapabilities $capabilities,
        protected FavoritesService $favoritesService,
    ) {
        parent::__construct('id', 'user', $adapter);
    }

    /**
     * Configuration setter
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return void
     *
     * @deprecated
     */
    public function setConfig(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Reset ILS login credentials.
     *
     * @return void
     *
     * @deprecated Use setCatUsername(null)->setRawCatPassword(null)->setCatPassEnc(null)
     */
    public function clearCredentials()
    {
        $this->cat_username = null;
        $this->cat_password = null;
        $this->cat_pass_enc = null;
    }

    /**
     * Save ILS ID.
     *
     * @param string $catId Catalog ID to save.
     *
     * @return mixed        The output of the save method.
     * @throws \VuFind\Exception\PasswordSecurity
     *
     * @deprecated Use UserEntityInterface::setCatId() and \VuFind\Db\Service\DbServiceInterface::persistEntity()
     */
    public function saveCatalogId($catId)
    {
        $this->cat_id = $catId;
        return $this->save();
    }

    /**
     * Set ILS login credentials without saving them.
     *
     * @param string  $username Username to save
     * @param ?string $password Password to save (null for none)
     *
     * @return void
     *
     * @deprecated Use ILSAuthenticator::setUserCatalogCredentials()
     */
    public function setCredentials($username, $password)
    {
        $this->ilsAuthenticator->setUserCatalogCredentials($this, $username, $password);
    }

    /**
     * Save ILS login credentials.
     *
     * @param string $username Username to save
     * @param string $password Password to save
     *
     * @return void
     * @throws \VuFind\Exception\PasswordSecurity
     *
     * @deprecated Use ILSAuthenticator::saveUserCatalogCredentials()
     */
    public function saveCredentials($username, $password)
    {
        $this->ilsAuthenticator->saveUserCatalogCredentials($this, $username, $password);
    }

    /**
     * Save date/time when email address has been verified.
     *
     * @param string $datetime optional date/time to save.
     *
     * @return mixed           The output of the save method.
     *
     * @deprecated Use UserEntityInterface::setEmailVerified() and
     * \VuFind\Db\Service\DbServiceInterface::persistEntity()
     */
    public function saveEmailVerified($datetime = null)
    {
        if ($datetime === null) {
            $datetime = date('Y-m-d H:i:s');
        }

        $this->email_verified = $datetime;
        return $this->save();
    }

    /**
     * This is a getter for the Catalog Password. It will return a plaintext version
     * of the password.
     *
     * @return string The Catalog password in plain text
     * @throws \VuFind\Exception\PasswordSecurity
     *
     * @deprecated Use ILSAuthenticator::getCatPasswordForUser()
     */
    public function getCatPassword()
    {
        return $this->ilsAuthenticator->getCatPasswordForUser($this);
    }

    /**
     * Is ILS password encryption enabled?
     *
     * @return bool
     *
     * @deprecated
     */
    protected function passwordEncryptionEnabled()
    {
        return $this->ilsAuthenticator->passwordEncryptionEnabled();
    }

    /**
     * This is a central function for encrypting and decrypting so that
     * logic is all in one location
     *
     * @param string $text    The text to be encrypted or decrypted
     * @param bool   $encrypt True if we wish to encrypt text, False if we wish to
     * decrypt text.
     *
     * @return string|bool    The encrypted/decrypted string
     * @throws \VuFind\Exception\PasswordSecurity
     *
     * @deprecated Use ILSAuthenticator::encrypt() or ILSAuthenticator::decrypt()
     */
    protected function encryptOrDecrypt($text, $encrypt = true)
    {
        $method = $encrypt ? 'encrypt' : 'decrypt';
        return $this->ilsAuthenticator->$method($text);
    }

    /**
     * Change home library.
     *
     * @param ?string $homeLibrary New home library to store, or null to indicate
     * that the user does not want a default. An empty string is the default for
     * backward compatibility and indicates that system's default pick up location is
     * to be used
     *
     * @return mixed               The output of the save method.
     *
     * @deprecated Use ILSAuthenticator::updateUserHomeLibrary()
     */
    public function changeHomeLibrary($homeLibrary)
    {
        return $this->ilsAuthenticator->updateUserHomeLibrary($this, $homeLibrary);
    }

    /**
     * Check whether the email address has been verified yet.
     *
     * @return bool
     *
     * @deprecated Use getEmailVerified()
     */
    public function checkEmailVerified()
    {
        return !empty($this->email_verified);
    }

    /**
     * Same as getTagString(), but operates on a list of tags.
     *
     * @param array $tags Tags
     *
     * @return string
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::formatTagStringForEditing()
     */
    public function formatTagString($tags)
    {
        $tagStr = '';
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                if (strstr($tag['tag'], ' ')) {
                    $tagStr .= "\"{$tag['tag']}\" ";
                } else {
                    $tagStr .= "{$tag['tag']} ";
                }
            }
        }
        return trim($tagStr);
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string $resourceId ID of record being checked.
     * @param int    $listId     Optional list ID (to limit results to a particular
     * list).
     * @param string $source     Source of record to look up
     *
     * @return array
     *
     * @deprecated Use UserResourceServiceInterface::getFavoritesForRecord()
     */
    public function getSavedData(
        $resourceId,
        $listId = null,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
        $table = $this->getDbTable('UserResource');
        return $table->getSavedData($resourceId, $source, $listId, $this->id);
    }

    /**
     * Whether library cards are enabled
     *
     * @return bool
     *
     * @deprecated use \VuFind\Config\AccountCapabilities::libraryCardsEnabled()
     */
    public function libraryCardsEnabled()
    {
        return $this->capabilities->libraryCardsEnabled();
    }

    /**
     * Get all library cards associated with the user.
     *
     * @return array
     * @throws \VuFind\Exception\LibraryCard
     *
     * @deprecated Use UserCardServiceInterface::getLibraryCards()
     */
    public function getLibraryCards()
    {
        if (!$this->capabilities->libraryCardsEnabled()) {
            return new \Laminas\Db\ResultSet\ResultSet();
        }
        return $this->getUserCardService()->getLibraryCards($this->id);
    }

    /**
     * Get library card data
     *
     * @param int $id Library card ID
     *
     * @return UserCard|false Card data if found, false otherwise
     * @throws \VuFind\Exception\LibraryCard
     *
     * @deprecated Use LibraryCardServiceInterface::getOrCreateLibraryCard()
     */
    public function getLibraryCard($id = null)
    {
        return $this->getUserCardService()->getOrCreateLibraryCard($this, $id);
    }

    /**
     * Delete library card
     *
     * @param int $id Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     *
     * @deprecated Use UserCardServiceInterface::deleteLibraryCard()
     */
    public function deleteLibraryCard($id)
    {
        return $this->getUserCardService()->deleteLibraryCard($this, $id);
    }

    /**
     * Activate a library card for the given username
     *
     * @param int $id Library card ID
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     *
     * @deprecated Use UserCardServiceInterface::activateLibraryCard()
     */
    public function activateLibraryCard($id)
    {
        return $this->getUserCardService()->activateLibraryCard($this, $id);
    }

    /**
     * Save library card with the given information
     *
     * @param int    $id       Card ID
     * @param string $cardName Card name
     * @param string $username Username
     * @param string $password Password
     * @param string $homeLib  Home Library
     *
     * @return int Card ID
     * @throws \VuFind\Exception\LibraryCard
     *
     * @deprecated Use UserCardServiceInterface::persistLibraryCardData()
     */
    public function saveLibraryCard(
        $id,
        $cardName,
        $username,
        $password,
        $homeLib = ''
    ) {
        return $this->getUserCardService()
            ->persistLibraryCardData($this, $id, $cardName, $username, $password, $homeLib)
            ->getId();
    }

    /**
     * Verify that the current card information exists in user's library cards
     * (if enabled) and is up to date.
     *
     * @return void
     * @throws \VuFind\Exception\PasswordSecurity
     *
     * @deprecated Use UserCardServiceInterface::synchronizeUserLibraryCardData()
     */
    protected function updateLibraryCardEntry()
    {
        $this->getUserCardService()->synchronizeUserLibraryCardData($this);
    }

    /**
     * Get a UserCard service object.
     *
     * @return UserCardServiceInterface
     */
    protected function getUserCardService()
    {
        return $this->getDbService(UserCardServiceInterface::class);
    }

    /**
     * Destroy the user.
     *
     * @param bool $removeComments Whether to remove user's comments
     * @param bool $removeRatings  Whether to remove user's ratings
     *
     * @return int The number of rows deleted.
     *
     * @deprecated Use \VuFind\Account\UserAccountService::purgeUserData()
     */
    public function delete($removeComments = true, $removeRatings = true)
    {
        // Remove all lists owned by the user:
        $listService = $this->getDbService(UserListServiceInterface::class);
        foreach ($listService->getUserListsByUser($this) as $current) {
            $this->favoritesService->destroyList($current, $this, true);
        }
        $this->getDbService(ResourceTagsServiceInterface::class)->destroyResourceTagsLinksForUser(null, $this);
        if ($removeComments) {
            $comments = $this->getDbService(
                \VuFind\Db\Service\CommentsServiceInterface::class
            );
            $comments->deleteByUser($this->getId());
        }
        if ($removeRatings) {
            $ratings = $this->getDbService(\VuFind\Db\Service\RatingsServiceInterface::class);
            $ratings->deleteByUser($this);
        }

        // Remove the user itself:
        return parent::delete();
    }

    /**
     * Update the verification hash for this user
     *
     * @return bool save success
     *
     * @deprecated Use \VuFind\Auth\Manager::updateUserVerifyHash()
     */
    public function updateHash()
    {
        $hash = md5($this->username . $this->password . $this->pass_hash . rand());
        // Make totally sure the timestamp is exactly 10 characters:
        $time = str_pad(substr((string)time(), 0, 10), 10, '0', STR_PAD_LEFT);
        $this->verify_hash = $hash . $time;
        return $this->save();
    }

    /**
     * Updated saved language
     *
     * @param string $language New language
     *
     * @return void
     *
     * @deprecated Use \VuFind\Db\Entity\UserEntityInterface::setLastLanguage()
     * and \VuFind\Db\Service\UserService::persistEntity() instead.
     */
    public function updateLastLanguage($language)
    {
        $this->last_language = $language;
        $this->save();
    }

    /**
     * Update the user's email address, if appropriate. Note that this does NOT
     * automatically save the row; it assumes a subsequent call will be made to
     * persist the data.
     *
     * @param string $email        New email address
     * @param bool   $userProvided Was this email provided by the user (true) or
     * an automated lookup (false)?
     *
     * @return void
     *
     * @deprecated Use \VuFind\Db\Service\UserServiceInterface::updateUserEmail()
     */
    public function updateEmail($email, $userProvided = false)
    {
        $this->getDbService(UserServiceInterface::class)->updateUserEmail($this, $email, $userProvided);
    }

    /**
     * Get the list of roles of this identity
     *
     * @return string[]|\Rbac\Role\RoleInterface[]
     */
    public function getRoles()
    {
        return ['loggedin'];
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Username setter
     *
     * @param string $username Username
     *
     * @return static
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set raw (unhashed) password (if available). This should only be used when hashing is disabled.
     *
     * @param string $password Password
     *
     * @return static
     */
    public function setRawPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get raw (unhashed) password (if available). This should only be used when hashing is disabled.
     *
     * @return string
     */
    public function getRawPassword(): string
    {
        return $this->password ?? '';
    }

    /**
     * Set hashed password. This should only be used when hashing is enabled.
     *
     * @param ?string $hash Password hash
     *
     * @return static
     */
    public function setPasswordHash(?string $hash): static
    {
        $this->pass_hash = $hash;
        return $this;
    }

    /**
     * Get hashed password. This should only be used when hashing is enabled.
     *
     * @return ?string
     */
    public function getPasswordHash(): ?string
    {
        return $this->pass_hash ?? null;
    }

    /**
     * Set firstname.
     *
     * @param string $firstName New first name
     *
     * @return static
     */
    public function setFirstname(string $firstName): static
    {
        $this->firstname = $firstName;
        return $this;
    }

    /**
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * Set lastname.
     *
     * @param string $lastName New last name
     *
     * @return static
     */
    public function setLastname(string $lastName): static
    {
        $this->lastname = $lastName;
        return $this;
    }

    /**
     * Get lastname.
     *
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * Set email.
     *
     * @param string $email Email address
     *
     * @return static
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set pending email.
     *
     * @param string $email New pending email
     *
     * @return static
     */
    public function setPendingEmail(string $email): static
    {
        $this->pending_email = $email;
        return $this;
    }

    /**
     * Get pending email.
     *
     * @return string
     */
    public function getPendingEmail(): string
    {
        return $this->pending_email ?? '';
    }

    /**
     * Catalog id setter
     *
     * @param ?string $catId Catalog id
     *
     * @return static
     */
    public function setCatId(?string $catId): static
    {
        $this->cat_id = $catId;
        return $this;
    }

    /**
     * Get catalog id.
     *
     * @return ?string
     */
    public function getCatId(): ?string
    {
        return $this->cat_id;
    }

    /**
     * Catalog username setter
     *
     * @param ?string $catUsername Catalog username
     *
     * @return static
     */
    public function setCatUsername(?string $catUsername): static
    {
        $this->cat_username = $catUsername;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return ?string
     */
    public function getCatUsername(): ?string
    {
        return $this->cat_username ?? '';
    }

    /**
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return static
     */
    public function setHomeLibrary(?string $homeLibrary): static
    {
        $this->home_library = $homeLibrary;
        return $this;
    }

    /**
     * Get home library.
     *
     * @return ?string
     */
    public function getHomeLibrary(): ?string
    {
        return $this->home_library;
    }

    /**
     * Raw catalog password setter
     *
     * @param ?string $catPassword Cat password
     *
     * @return static
     */
    public function setRawCatPassword(?string $catPassword): static
    {
        $this->cat_password = $catPassword;
        return $this;
    }

    /**
     * Get raw catalog password.
     *
     * @return ?string
     */
    public function getRawCatPassword(): ?string
    {
        return $this->cat_password ?? null;
    }

    /**
     * Encrypted catalog password setter
     *
     * @param ?string $passEnc Encrypted password
     *
     * @return static
     */
    public function setCatPassEnc(?string $passEnc): static
    {
        $this->cat_pass_enc = $passEnc;
        return $this;
    }

    /**
     * Get encrypted catalog password.
     *
     * @return ?string
     */
    public function getCatPassEnc(): ?string
    {
        return $this->cat_pass_enc;
    }

    /**
     * Set college.
     *
     * @param string $college College
     *
     * @return static
     */
    public function setCollege(string $college): static
    {
        $this->college = $college;
        return $this;
    }

    /**
     * Get college.
     *
     * @return string
     */
    public function getCollege(): string
    {
        return $this->college ?? '';
    }

    /**
     * Set major.
     *
     * @param string $major Major
     *
     * @return static
     */
    public function setMajor(string $major): static
    {
        $this->major = $major;
        return $this;
    }

    /**
     * Get major.
     *
     * @return string
     */
    public function getMajor(): string
    {
        return $this->major ?? '';
    }

    /**
     * Set verification hash for recovery.
     *
     * @param string $hash Hash value to save
     *
     * @return static
     */
    public function setVerifyHash(string $hash): static
    {
        $this->verify_hash = $hash;
        return $this;
    }

    /**
     * Get verification hash for recovery.
     *
     * @return string
     */
    public function getVerifyHash(): string
    {
        return $this->verify_hash ?? '';
    }

    /**
     * Set active authentication method (if any).
     *
     * @param ?string $authMethod New value (null for none)
     *
     * @return static
     */
    public function setAuthMethod(?string $authMethod): static
    {
        $this->auth_method = $authMethod;
        return $this;
    }

    /**
     * Get active authentication method (if any).
     *
     * @return ?string
     */
    public function getAuthMethod(): ?string
    {
        return $this->auth_method;
    }

    /**
     * Set last language.
     *
     * @param string $lang Last language
     *
     * @return static
     */
    public function setLastLanguage(string $lang): static
    {
        $this->last_language = $lang;
        return $this;
    }

    /**
     * Get last language.
     *
     * @return string
     */
    public function getLastLanguage(): string
    {
        return $this->last_language ?? '';
    }

    /**
     * Does the user have a user-provided (true) vs. automatically looked up (false) email address?
     *
     * @return bool
     */
    public function hasUserProvidedEmail(): bool
    {
        return (bool)($this->user_provided_email ?? false);
    }

    /**
     * Set the flag indicating whether the email address is user-provided.
     *
     * @param bool $userProvided New value
     *
     * @return static
     */
    public function setHasUserProvidedEmail(bool $userProvided): static
    {
        $this->user_provided_email = $userProvided ? 1 : 0;
        return $this;
    }

    /**
     * Last login setter.
     *
     * @param DateTime $dateTime Last login date
     *
     * @return static
     */
    public function setLastLogin(DateTime $dateTime): static
    {
        $this->last_login = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Last login getter
     *
     * @return DateTime
     */
    public function getLastLogin(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->last_login);
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Creation date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Set email verification date (or null for unverified).
     *
     * @param ?DateTime $dateTime Verification date (or null)
     *
     * @return static
     */
    public function setEmailVerified(?DateTime $dateTime): static
    {
        $this->email_verified = $dateTime?->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get email verification date (or null for unverified).
     *
     * @return ?DateTime
     */
    public function getEmailVerified(): ?DateTime
    {
        return $this->email_verified ? DateTime::createFromFormat('Y-m-d H:i:s', $this->email_verified) : null;
    }
}
