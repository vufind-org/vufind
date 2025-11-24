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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for user table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'user_cat_id_idx', columns: ['cat_id'], options: ['lengths' => [190]])]
#[ORM\UniqueConstraint(name: 'user_username_idx', columns: ['username'], options: ['lengths' => [190]])]
#[ORM\Index(name: 'user_email_idx', columns: ['email'], options: ['lengths' => [190]])]
#[ORM\Index(name: 'user_verify_hash_idx', columns: ['verify_hash'])]
#[ORM\Entity]
class User implements UserEntityInterface
{
    use DateTimeTrait;
    use ExchangeArrayTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * Username
     *
     * @var string
     */
    #[ORM\Column(name: 'username', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected string $username = '';

    /**
     * Password
     *
     * @var string
     */
    #[ORM\Column(name: 'password', type: 'string', length: 32, nullable: false, options: ['default' => ''])]
    protected string $password = '';

    /**
     * Hash of the password.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'pass_hash', type: 'string', length: 60, nullable: true)]
    protected ?string $passHash = null;

    /**
     * First Name.
     *
     * @var string
     */
    #[ORM\Column(name: 'firstname', type: 'string', length: 50, nullable: false, options: ['default' => ''])]
    protected string $firstname = '';

    /**
     * Last Name.
     *
     * @var string
     */
    #[ORM\Column(name: 'lastname', type: 'string', length: 50, nullable: false, options: ['default' => ''])]
    protected string $lastname = '';

    /**
     * Email.
     *
     * @var string
     */
    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected string $email = '';

    /**
     * Date of email verification.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'email_verified', type: 'datetime', nullable: true)]
    protected ?DateTime $emailVerified = null;

    /**
     * Pending email.
     *
     * @var string
     */
    #[ORM\Column(name: 'pending_email', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected string $pendingEmail = '';

    /**
     * User provided email.
     *
     * @var bool
     */
    #[ORM\Column(name: 'user_provided_email', type: 'boolean', nullable: false, options: ['default' => false])]
    protected bool $userProvidedEmail = false;

    /**
     * Cat ID.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_id', type: 'string', length: 255, nullable: true)]
    protected ?string $catId = null;

    /**
     * Cat username.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_username', type: 'string', length: 50, nullable: true)]
    protected ?string $catUsername = null;

    /**
     * Cat password.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_password', type: 'string', length: 70, nullable: true)]
    protected ?string $catPassword = null;

    /**
     * Cat encrypted password.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_pass_enc', type: 'string', length: 255, nullable: true)]
    protected ?string $catPassEnc = null;

    /**
     * College.
     *
     * @var string
     */
    #[ORM\Column(name: 'college', type: 'string', length: 100, nullable: false, options: ['default' => ''])]
    protected string $college = '';

    /**
     * Major.
     *
     * @var string
     */
    #[ORM\Column(name: 'major', type: 'string', length: 100, nullable: false, options: ['default' => ''])]
    protected string $major = '';

    /**
     * Home library.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'home_library', type: 'string', length: 100, nullable: true, options: ['default' => ''])]
    protected ?string $homeLibrary = '';

    /**
     * Creation date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $created;

    /**
     * Verify hash.
     *
     * @var string
     */
    #[ORM\Column(name: 'verify_hash', type: 'string', length: 42, nullable: false, options: ['default' => ''])]
    protected string $verifyHash = '';

    /**
     * Time last loggedin.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'last_login', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $lastLogin;

    /**
     * Method of authentication.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'auth_method', type: 'string', length: 50, nullable: true)]
    protected ?string $authMethod = null;

    /**
     * Last known language.
     *
     * @var string
     */
    #[ORM\Column(name: 'last_language', type: 'string', length: 30, nullable: false, options: ['default' => ''])]
    protected string $lastLanguage = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default values as DateTime objects
        $this->created = $this->getUnassignedDefaultDateTime();
        $this->lastLogin = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
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
        $this->passHash = $hash;
        return $this;
    }

    /**
     * Get hashed password. This should only be used when hashing is enabled.
     *
     * @return ?string
     */
    public function getPasswordHash(): ?string
    {
        return $this->passHash;
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
     * Catalog id setter
     *
     * @param ?string $catId Catalog id
     *
     * @return static
     */
    public function setCatId(?string $catId): static
    {
        $this->catId = $catId;
        return $this;
    }

    /**
     * Get catalog id.
     *
     * @return ?string
     */
    public function getCatId(): ?string
    {
        return $this->catId;
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
     * @return static
     */
    public function setHomeLibrary(?string $homeLibrary): static
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
     * @return static
     */
    public function setRawCatPassword(?string $catPassword): static
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
     * @return static
     */
    public function setCatPassEnc(?string $passEnc): static
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
        return $this->college;
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
        return $this->major;
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
        $this->verifyHash = $hash;
        return $this;
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
     * Set active authentication method (if any).
     *
     * @param ?string $authMethod New value (null for none)
     *
     * @return static
     */
    public function setAuthMethod(?string $authMethod): static
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    /**
     * Get active authentication method (if any).
     *
     * @return ?string
     */
    public function getAuthMethod(): ?string
    {
        return $this->authMethod;
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
        $this->lastLanguage = $lang;
        return $this;
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

    /**
     * Does the user have a user-provided (true) vs. automatically looked up (false) email address?
     *
     * @return bool
     */
    public function hasUserProvidedEmail(): bool
    {
        return $this->userProvidedEmail;
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
        $this->userProvidedEmail = $userProvided;
        return $this;
    }

    /**
     * Last login setter.
     *
     * @param ?DateTime $dateTime Last login date
     *
     * @return static
     */
    public function setLastLogin(?DateTime $dateTime): static
    {
        $this->lastLogin = $this->getNonNullableDateTimeFromNullable($dateTime);
        return $this;
    }

    /**
     * Last login getter
     *
     * @return ?DateTime
     */
    public function getLastLogin(): ?DateTime
    {
        return $this->getNullableDateTimeFromNonNullable($this->lastLogin);
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Last login date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
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
        $this->emailVerified = $dateTime;
        return $this;
    }

    /**
     * Get email verification date (or null for unverified).
     *
     * @return ?DateTime
     */
    public function getEmailVerified(): ?DateTime
    {
        return $this->emailVerified;
    }

    /**
     * Get the list of roles of this identity
     *
     * @return string[]|\Laminas\Permissions\Rbac\RoleInterface[]
     */
    public function getRoles(): iterable
    {
        return ['loggedin'];
    }
}
