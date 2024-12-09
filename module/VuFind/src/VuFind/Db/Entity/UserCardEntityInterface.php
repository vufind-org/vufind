<?php

/**
 * Entity model interface for user_card table
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
 * Entity model interface for user_card table
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface UserCardEntityInterface extends EntityInterface
{
    /**
     * ID getter (returns null if the entity has not been saved/populated yet)
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Card name setter
     *
     * @param string $cardName User card name.
     *
     * @return UserCardEntityInterface
     */
    public function setCardName(string $cardName): UserCardEntityInterface;

    /**
     * Get user card name.
     *
     * @return string
     */
    public function getCardName(): string;

    /**
     * Catalog username setter
     *
     * @param string $catUsername Catalog username
     *
     * @return UserCardEntityInterface
     */
    public function setCatUsername(string $catUsername): UserCardEntityInterface;

    /**
     * Get catalog username.
     *
     * @return string
     */
    public function getCatUsername(): string;

    /**
     * Raw catalog password setter
     *
     * @param ?string $catPassword Cat password
     *
     * @return UserCardEntityInterface
     */
    public function setRawCatPassword(?string $catPassword): UserCardEntityInterface;

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
     * @return UserCardEntityInterface
     */
    public function setCatPassEnc(?string $passEnc): UserCardEntityInterface;

    /**
     * Get encrypted catalog password.
     *
     * @return ?string
     */
    public function getCatPassEnc(): ?string;

    /**
     * Home library setter
     *
     * @param ?string $homeLibrary Home library
     *
     * @return UserCardEntityInterface
     */
    public function setHomeLibrary(?string $homeLibrary): UserCardEntityInterface;

    /**
     * Get home library.
     *
     * @return ?string
     */
    public function getHomeLibrary(): ?string;

    /**
     * Created date setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserCardEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserCardEntityInterface;

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set time the card is saved.
     *
     * @param DateTime $dateTime Saved date and time
     *
     * @return UserCardEntityInterface
     */
    public function setSaved(DateTime $dateTime): UserCardEntityInterface;

    /**
     * Get saved time.
     *
     * @return DateTime
     */
    public function getSaved(): DateTime;

    /**
     * User setter.
     *
     * @param UserEntityInterface $user User that owns card
     *
     * @return UserCardEntityInterface
     */
    public function setUser(UserEntityInterface $user): UserCardEntityInterface;

    /**
     * User getter
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;
}
