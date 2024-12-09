<?php

/**
 * Interface for representing a user account record.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Interface for representing a user account record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface UserEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Username setter
     *
     * @param string $username Username
     *
     * @return UserEntityInterface
     */
    public function setUsername(string $username): UserEntityInterface;

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername(): string;

    /**
     * Set raw (unhashed) password (if available). This should only be used when hashing is disabled.
     *
     * @param string $password Password
     *
     * @return UserEntityInterface
     */
    public function setRawPassword(string $password): UserEntityInterface;

    /**
     * Get raw (unhashed) password (if available). This should only be used when hashing is disabled.
     *
     * @return string
     */
    public function getRawPassword(): string;

    /**
     * Set hashed password. This should only be used when hashing is enabled.
     *
     * @param ?string $hash Password hash
     *
     * @return UserEntityInterface
     */
    public function setPasswordHash(?string $hash): UserEntityInterface;

    /**
     * Get hashed password. This should only be used when hashing is enabled.
     *
     * @return ?string
     */
    public function getPasswordHash(): ?string;

    /**
     * Set firstname.
     *
     * @param string $firstName New first name
     *
     * @return UserEntityInterface
     */
    public function setFirstname(string $firstName): UserEntityInterface;

    /**
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname(): string;

    /**
     * Set lastname.
     *
     * @param string $lastName New last name
     *
     * @return UserEntityInterface
     */
    public function setLastname(string $lastName): UserEntityInterface;

    /**
     * Get lastname.
     *
     * @return string
     */
    public function getLastname(): string;

    /**
     * Set email.
     *
     * @param string $email Email address
     *
     * @return UserEntityInterface
     */
    public function setEmail(string $email): UserEntityInterface;

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail(): string;

    /**
     * Set pending email.
     *
     * @param string $email New pending email
     *
     * @return UserEntityInterface
     */
    public function setPendingEmail(string $email): UserEntityInterface;

    /**
     * Get pending email.
     *
     * @return string
     */
    public function getPendingEmail(): string;

    /**
     * Catalog id setter
     *
     * @param ?string $catId Catalog id
     *
     * @return UserEntityInterface
     */
    public function setCatId(?string $catId): UserEntityInterface;

    /**
     * Get catalog id.
     *
     * @return ?string
     */
    public function getCatId(): ?string;

    /**
     * Catalog username setter
     *
     * @param ?string $catUsername Catalog username
     *
     * @return UserEntityInterface
     */
    public function setCatUsername(?string $catUsername): UserEntityInterface;

    /**
     * Get catalog username.
     *
     * @return ?string
     */
    public function getCatUsername(): ?string;

    /**
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return UserEntityInterface
     */
    public function setHomeLibrary(?string $homeLibrary): UserEntityInterface;

    /**
     * Get home library.
     *
     * @return ?string
     */
    public function getHomeLibrary(): ?string;

    /**
     * Raw catalog password setter
     *
     * @param ?string $catPassword Cat password
     *
     * @return UserEntityInterface
     */
    public function setRawCatPassword(?string $catPassword): UserEntityInterface;

    /**
     * Get raw catalog password.
     *
     * @return ?string
     */
    public function getRawCatPassword(): ?string;

    /**
     * Encrypted catalog password setter
     *
     * @param ?string $passEnc Encrypted password
     *
     * @return UserEntityInterface
     */
    public function setCatPassEnc(?string $passEnc): UserEntityInterface;

    /**
     * Get encrypted catalog password.
     *
     * @return ?string
     */
    public function getCatPassEnc(): ?string;

    /**
     * Set college.
     *
     * @param string $college College
     *
     * @return UserEntityInterface
     */
    public function setCollege(string $college): UserEntityInterface;

    /**
     * Get college.
     *
     * @return string
     */
    public function getCollege(): string;

    /**
     * Set major.
     *
     * @param string $major Major
     *
     * @return UserEntityInterface
     */
    public function setMajor(string $major): UserEntityInterface;

    /**
     * Get major.
     *
     * @return string
     */
    public function getMajor(): string;

    /**
     * Set verification hash for recovery.
     *
     * @param string $hash Hash value to save
     *
     * @return UserEntityInterface
     */
    public function setVerifyHash(string $hash): UserEntityInterface;

    /**
     * Get verification hash for recovery.
     *
     * @return string
     */
    public function getVerifyHash(): string;

    /**
     * Set active authentication method (if any).
     *
     * @param ?string $authMethod New value (null for none)
     *
     * @return UserEntityInterface
     */
    public function setAuthMethod(?string $authMethod): UserEntityInterface;

    /**
     * Get active authentication method (if any).
     *
     * @return ?string
     */
    public function getAuthMethod(): ?string;

    /**
     * Set last language.
     *
     * @param string $lang Last language
     *
     * @return UserEntityInterface
     */
    public function setLastLanguage(string $lang): UserEntityInterface;

    /**
     * Get last language.
     *
     * @return string
     */
    public function getLastLanguage(): string;

    /**
     * Does the user have a user-provided (true) vs. automatically looked up (false) email address?
     *
     * @return bool
     */
    public function hasUserProvidedEmail(): bool;

    /**
     * Set the flag indicating whether the email address is user-provided.
     *
     * @param bool $userProvided New value
     *
     * @return UserEntityInterface
     */
    public function setHasUserProvidedEmail(bool $userProvided): UserEntityInterface;

    /**
     * Last login setter.
     *
     * @param DateTime $dateTime Last login date
     *
     * @return UserEntityInterface
     */
    public function setLastLogin(DateTime $dateTime): UserEntityInterface;

    /**
     * Last login getter
     *
     * @return DateTime
     */
    public function getLastLogin(): DateTime;

    /**
     * Created setter
     *
     * @param DateTime $dateTime Last login date
     *
     * @return UserEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserEntityInterface;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime;

    /**
     * Set email verification date (or null for unverified).
     *
     * @param ?DateTime $dateTime Verification date (or null)
     *
     * @return UserEntityInterface
     */
    public function setEmailVerified(?DateTime $dateTime): UserEntityInterface;

    /**
     * Get email verification date (or null for unverified).
     *
     * @return ?DateTime
     */
    public function getEmailVerified(): ?DateTime;
}
