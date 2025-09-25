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
     * @return static
     */
    public function setCardName(string $cardName): static;

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
     * @return static
     */
    public function setCatUsername(string $catUsername): static;

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
     * Created date setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

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
     * @return static
     */
    public function setSaved(DateTime $dateTime): static;

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
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * User getter
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;
}
