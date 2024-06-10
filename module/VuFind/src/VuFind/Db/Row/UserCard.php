<?php

/**
 * Row Definition for user_card
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\UserCardEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;

/**
 * Row Definition for user_card
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property int     $user_id
 * @property string  $card_name
 * @property string  $cat_username
 * @property ?string $cat_password
 * @property ?string $cat_pass_enc
 * @property ?string $home_library
 * @property string  $created
 * @property string  $saved
 */
class UserCard extends RowGateway implements DbServiceAwareInterface, UserCardEntityInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'user_card', $adapter);
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
     * @return UserCardEntityInterface
     */
    public function setCardName(string $cardName): UserCardEntityInterface
    {
        $this->card_name = $cardName;
        return $this;
    }

    /**
     * Get user card name.
     *
     * @return string
     */
    public function getCardName(): string
    {
        return $this->card_name;
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
        $this->cat_username = $catUsername;
        return $this;
    }

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string
    {
        return $this->cat_username;
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
        return $this->cat_password;
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
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return UserCardEntityInterface
     */
    public function setHomeLibrary(?string $homeLibrary): UserCardEntityInterface
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
     * Created date setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserCardEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserCardEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
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
        $this->saved = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get saved time.
     *
     * @return DateTime
     */
    public function getSaved(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->saved);
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
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * User getter
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->getDbService(\VuFind\Db\Service\UserServiceInterface::class)->getUserById($this->user_id);
    }
}
