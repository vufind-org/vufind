<?php

/**
 * Entity model for user_card table
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
 * UserCard
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="user_card",
 * indexes={@ORM\Index(name="user_card_cat_username", columns={"cat_username"}),
 * @ORM\Index(name="user_id",   columns={"user_id"})})
 * @ORM\Entity
 */
class UserCard implements UserCardEntityInterface
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
     * Card name.
     *
     * @var string
     *
     * @ORM\Column(name="card_name", type="string", length=255, nullable=false)
     */
    protected $cardName = '';

    /**
     * Cat username.
     *
     * @var string
     *
     * @ORM\Column(name="cat_username", type="string", length=50, nullable=false)
     */
    protected $catUsername = '';

    /**
     * Cat password.
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_password", type="string", length=70, nullable=true)
     */
    protected $catPassword;

    /**
     * Cat password (encrypted).
     *
     * @var ?string
     *
     * @ORM\Column(name="cat_pass_enc", type="string", length=255, nullable=true)
     */
    protected $catPassEnc;

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
    protected $created;

    /**
     * Saved timestamp.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="saved",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"}
     * )
     */
    protected $saved;

    /**
     * User.
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
        $this->created = new \DateTime('2000-01-01 00:00:00');
        $this->saved = new \DateTime();
    }

    /**
     * ID getter (returns null if the entity has not been saved/populated yet)
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Card name setter
     *
     * @param string $cardName User card name.
     *
     * @return UserCardEntityInterface
     */
    public function setCardName(string $cardName): UserCardEntityInterface
    {
        $this->cardName = $cardName;
        return $this;
    }

    /**
     * Get user card name.
     *
     * @return string
     */
    public function getCardName(): string
    {
        return $this->cardName;
    }

    /**
     * Catalog username setter
     *
     * @param string $catUsername Catalog username
     *
     * @return UserCardEntityInterface
     */
    public function setCatUsername(string $catUsername): UserCardEntityInterface
    {
        $this->catUsername = $catUsername;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string
    {
        return $this->catUsername;
    }

    /**
     * Raw catalog password setter
     *
     * @param ?string $catPassword Cat password
     *
     * @return UserCardEntityInterface
     */
    public function setRawCatPassword(?string $catPassword): UserCardEntityInterface
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
     * @return UserCardEntityInterface
     */
    public function setCatPassEnc(?string $passEnc): UserCardEntityInterface
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
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return UserCardEntityInterface
     */
    public function setHomeLibrary(?string $homeLibrary): UserCardEntityInterface
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
     * Created date setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserCardEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserCardEntityInterface
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set time the card is saved.
     *
     * @param DateTime $dateTime Saved date and time
     *
     * @return UserCardEntityInterface
     */
    public function setSaved(DateTime $dateTime): UserCardEntityInterface
    {
        $this->saved = $dateTime;
        return $this;
    }

    /**
     * Get saved time.
     *
     * @return DateTime
     */
    public function getSaved(): DateTime
    {
        return $this->saved;
    }

    /**
     * User setter.
     *
     * @param UserEntityInterface $user User that owns card
     *
     * @return UserCardEntityInterface
     */
    public function setUser(UserEntityInterface $user): UserCardEntityInterface
    {
        $this->user = $user;
        return $this;
    }

    /**
     * User getter
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->user;
    }
}
