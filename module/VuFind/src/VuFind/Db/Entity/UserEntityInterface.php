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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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

interface UserEntityInterface extends
    EntityInterface,
    ExchangeArrayInterface,
    \Lmc\Rbac\Identity\IdentityInterface
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
     * @return static
     */
    public function setUsername(string $username): static;

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
     * @return static
     */
    public function setRawPassword(string $password): static;

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
     * @return static
     */
    public function setPasswordHash(?string $hash): static;

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
     * @return static
     */
    public function setFirstname(string $firstName): static;

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
     * @return static
     */
    public function setLastname(string $lastName): static;

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
     * @return static
     */
    public function setEmail(string $email): static;

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
     * @return static
     */
    public function setPendingEmail(string $email): static;

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
     * @return static
     */
    public function setCatId(?string $catId): static;

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
     * @return static
     */
    public function setCatUsername(?string $catUsername): static;

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
     * @return static
     */
    public function setHomeLibrary(?string $homeLibrary): static;

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
     * @return static
     */
    public function setRawCatPassword(?string $catPassword): static;

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
     * @return static
     */
    public function setCatPassEnc(?string $passEnc): static;

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
     * @return static
     */
    public function setCollege(string $college): static;

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
     * @return static
     */
    public function setMajor(string $major): static;

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
     * @return static
     */
    public function setVerifyHash(string $hash): static;

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
     * @return static
     */
    public function setAuthMethod(?string $authMethod): static;

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
     * @return static
     */
    public function setLastLanguage(string $lang): static;

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
     * @return static
     */
    public function setHasUserProvidedEmail(bool $userProvided): static;

    /**
     * Last login setter.
     *
     * @param ?DateTime $dateTime Last login date
     *
     * @return static
     */
    public function setLastLogin(?DateTime $dateTime): static;

    /**
     * Last login getter
     *
     * @return ?DateTime
     */
    public function getLastLogin(): ?DateTime;

    /**
     * Created setter
     *
     * @param DateTime $dateTime Last login date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

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
     * @return static
     */
    public function setEmailVerified(?DateTime $dateTime): static;

    /**
     * Get email verification date (or null for unverified).
     *
     * @return ?DateTime
     */
    public function getEmailVerified(): ?DateTime;
}
