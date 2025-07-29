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
use VuFind\Db\Feature\DateTimeTrait;

/**
 * UserCard
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'user_card')]
#[ORM\Index(name: 'user_card_cat_username_idx', columns: ['cat_username'])]
#[ORM\Index(name: 'user_card_user_id_idx', columns: ['user_id'])]
#[ORM\Entity]
class UserCard implements UserCardEntityInterface
{
    use DateTimeTrait;

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
     * Card name.
     *
     * @var string
     */
    #[ORM\Column(name: 'card_name', type: 'string', length: 255, nullable: false, options: ['default' => ''])]
    protected string $cardName = '';

    /**
     * Cat username.
     *
     * @var string
     */
    #[ORM\Column(name: 'cat_username', type: 'string', length: 50, nullable: false, options: ['default' => ''])]
    protected string $catUsername = '';

    /**
     * Cat password.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_password', type: 'string', length: 70, nullable: true)]
    protected ?string $catPassword = null;

    /**
     * Cat password (encrypted).
     *
     * @var ?string
     */
    #[ORM\Column(name: 'cat_pass_enc', type: 'string', length: 255, nullable: true)]
    protected ?string $catPassEnc = null;

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
     * Saved timestamp.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'saved', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $saved;

    /**
     * User.
     *
     * @var UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected UserEntityInterface $user;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
        $this->created = $this->getUnassignedDefaultDateTime();
        $this->saved = new DateTime();
    }

    /**
     * ID getter (returns null if the entity has not been saved/populated yet)
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Card name setter
     *
     * @param string $cardName User card name.
     *
     * @return static
     */
    public function setCardName(string $cardName): static
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
     * @return static
     */
    public function setCatUsername(string $catUsername): static
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
     * Created date setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
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
     * @return static
     */
    public function setSaved(DateTime $dateTime): static
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
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
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
