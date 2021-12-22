<?php
namespace VuFind\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * User
 *
 * @ORM\Table(name="user", uniqueConstraints={@ORM\UniqueConstraint(name="cat_id", columns={"cat_id"}), @ORM\UniqueConstraint(name="username", columns={"username"})})
 * @ORM\Entity
 */
class User
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=false)
     */
    private $username = '';

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=32, nullable=false)
     */
    private $password = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="pass_hash", type="string", length=60, nullable=true)
     */
    private $passHash;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=50, nullable=false)
     */
    private $firstname = '';

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=50, nullable=false)
     */
    private $lastname = '';

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=false)
     */
    private $email = '';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="email_verified", type="datetime", nullable=true)
     */
    private $emailVerified;

    /**
     * @var string
     *
     * @ORM\Column(name="pending_email", type="string", length=255, nullable=false)
     */
    private $pendingEmail = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="user_provided_email", type="boolean", nullable=false)
     */
    private $userProvidedEmail = '0';

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_id", type="string", length=255, nullable=true)
     */
    private $catId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_username", type="string", length=50, nullable=true)
     */
    private $catUsername;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_password", type="string", length=70, nullable=true)
     */
    private $catPassword;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cat_pass_enc", type="string", length=255, nullable=true)
     */
    private $catPassEnc;

    /**
     * @var string
     *
     * @ORM\Column(name="college", type="string", length=100, nullable=false)
     */
    private $college = '';

    /**
     * @var string
     *
     * @ORM\Column(name="major", type="string", length=100, nullable=false)
     */
    private $major = '';

    /**
     * @var string
     *
     * @ORM\Column(name="home_library", type="string", length=100, nullable=false)
     */
    private $homeLibrary = '';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $created = '2000-01-01 00:00:00';

    /**
     * @var string
     *
     * @ORM\Column(name="verify_hash", type="string", length=42, nullable=false)
     */
    private $verifyHash = '';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_login", type="datetime", nullable=false, options={"default"="2000-01-01 00:00:00"})
     */
    private $lastLogin = '2000-01-01 00:00:00';

    /**
     * @var string|null
     *
     * @ORM\Column(name="auth_method", type="string", length=50, nullable=true)
     */
    private $authMethod;

    /**
     * @var string
     *
     * @ORM\Column(name="last_language", type="string", length=30, nullable=false)
     */
    private $lastLanguage = '';
}
