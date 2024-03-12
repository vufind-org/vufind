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
     * Get ID.
     *
     * @return int
     */
    public function getId(): int;

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
     * Get last language.
     *
     * @return string
     */
    public function getLastLanguage(): string;

    /**
     * Get catalog username.
     *
     * @return ?string
     */
    public function getCatUsername(): ?string;

    /**
     * This is a getter for the Catalog Password. It will return a plaintext version
     * of the password.
     *
     * @return string The Catalog password in plain text
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function getCatPassword();
}
