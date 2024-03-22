<?php

/**
 * Entity model for user table
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="`user`",
 *          uniqueConstraints={@ORM\UniqueConstraint(name="cat_id",
 *                          columns={"cat_id"}),
 * @ORM\UniqueConstraint(name="username", columns={"username"})})
 * @ORM\Entity
 */
class User implements UserEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Username
     *
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=false)
     */
    protected $username = '';

    /**
     * Password
     *
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=32, nullable=false)
     */
    protected $password = '';

    /**
     * Hash of the password.
     *
     * @var ?string
     *
     * @ORM\Column(name="pass_hash", type="string", length=60, nullable=true)
     */
    protected $passHash;

    /**
     * First Name.
     *
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=50, nullable=false)
     */
    protected $firstname = '';

    /**
     * Last Name.
     *
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=50, nullable=false)
     */
    protected $lastname = '';

    /**
     * Email.
     *
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=false)
     */
    protected $email = '';

    /**
     * Date of email verification.
     *
     * @var ?DateTime
     *
     * @ORM\Column(name="email_verified", type="datetime", nullable=true)
     */
    protected $emailVerified;

    /**
     * Pending email.
     *
     * @var string
     *
     * @ORM\Column(name="pending_email", type="string", length=255, nullable=false)
     */
    protected $pendingEmail = '';

    /**
     * User provided email.
     *
     * @var bool
     *
     * @ORM\Column(name="user_provided_email", type="boolean", nullable=false)
     */
    protected $userProvidedEmail = '0';

    /**
     * Cat ID.
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_id", type="string", length=255, nullable=true)
     */
    protected $catId;

    /**
     * Cat username.
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_username", type="string", length=50, nullable=true)
     */
    protected $catUsername;

    /**
     * Cat password.
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_password", type="string", length=70, nullable=true)
     */
    protected $catPassword;

    /**
     * Cat encrypted password.
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_pass_enc", type="string", length=255, nullable=true)
     */
    protected $catPassEnc;

    /**
     * College.
     *
     * @var string
     *
     * @ORM\Column(name="college", type="string", length=100, nullable=false)
     */
    protected $college = '';

    /**
     * Major.
     *
     * @var string
     *
     * @ORM\Column(name="major", type="string", length=100, nullable=false)
     */
    protected $major = '';

    /**
     * Home library.
     *
     * @var string
     *
     * @ORM\Column(name="home_library", type="string", length=100, nullable=true)
     */
    protected $homeLibrary = '';

    /**
     * Creation date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Verify hash.
     *
     * @var string
     *
     * @ORM\Column(name="verify_hash", type="string", length=42, nullable=false)
     */
    protected $verifyHash = '';

    /**
     * Time last loggedin.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="last_login",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $lastLogin = '2000-01-01 00:00:00';

    /**
     * Method of authentication.
     *
     * @var ?string
     *
     * @ORM\Column(name="auth_method", type="string", length=50, nullable=true)
     */
    protected $authMethod;

    /**
     * Last known language.
     *
     * @var string
     *
     * @ORM\Column(name="last_language", type="string", length=30, nullable=false)
     */
    protected $lastLanguage = '';

    /**
     * Get identifier.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Username setter
     *
     * @param string $username Username
     *
     * @return UserEntityInterface
     */
    public function setUsername(string $username): UserEntityInterface
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
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
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
     * @return UserEntityInterface
     */
    public function setEmail(string $email): UserEntityInterface
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
     * @return UserEntityInterface
     */
    public function setPendingEmail(string $email): UserEntityInterface
    {
        $this->pendingEmail = $email;
        return $this;
    }

    /**
     * Get pending email.
     *
     * @return string
     */
    public function getPendingEmail(): string
    {
        return $this->pendingEmail;
    }

    /**
     * Catalog username setter
     *
     * @param ?string $catUsername Catalog username
     *
     * @return UserEntityInterface
     */
    public function setCatUsername(?string $catUsername): UserEntityInterface
    {
        $this->catUsername = $catUsername;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return ?string
     */
    public function getCatUsername(): ?string
    {
        return $this->catUsername;
    }

    /**
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return UserEntityInterface
     */
    public function setHomeLibrary(?string $homeLibrary): UserEntityInterface
    {
        $this->homeLibrary = $homeLibrary;
        return $this;
    }

    /**
     * Get home library.
     *
     * @return ?string
     */
    public function getHomeLibrary(): ?string
    {
        return $this->homeLibrary;
    }

    /**
     * Raw catalog password setter
     *
     * @param ?string $catPassword Cat password
     *
     * @return UserEntityInterface
     */
    public function setRawCatPassword(?string $catPassword): UserEntityInterface
    {
        $this->catPassword = $catPassword;
        return $this;
    }

    /**
     * Get raw catalog password.
     *
     * @return ?string
     */
    public function getRawCatPassword(): ?string
    {
        return $this->catPassword;
    }

    /**
     * Encrypted catalog password setter
     *
     * @param ?string $passEnc Encrypted password
     *
     * @return UserEntityInterface
     */
    public function setCatPassEnc(?string $passEnc): UserEntityInterface
    {
        $this->catPassEnc = $passEnc;
        return $this;
    }

    /**
     * Get encrypted catalog password.
     *
     * @return ?string
     */
    public function getCatPassEnc(): ?string
    {
        return $this->catPassEnc;
    }

    /**
     * Get verification hash for recovery.
     *
     * @return string
     */
    public function getVerifyHash(): string
    {
        return $this->verifyHash;
    }

    /**
     * Get last language.
     *
     * @return string
     */
    public function getLastLanguage(): string
    {
        return $this->lastLanguage;
    }
}
