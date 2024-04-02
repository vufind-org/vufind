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
     * Get identifier.
     *
     * @return int
     */
    public function getId();

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
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname(): string;

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
     * Get verification hash for recovery.
     *
     * @return string
     */
    public function getVerifyHash(): string;

    /**
     * Get last language.
     *
     * @return string
     */
    public function getLastLanguage(): string;
}
