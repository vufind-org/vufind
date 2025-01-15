<?php

/**
 * Finna resource list resource entity interface.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Db\Entity;

use DateTime;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Finna resource list resource entity interface.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @property int $id
 * @property int $resource_id
 * @property int $list_id
 * @property int $user_id
 * @property string $notes
 * @property string $saved
 */
interface FinnaResourceListResourceEntityInterface extends EntityInterface
{
    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Set resource id
     *
     * @param ResourceEntityInterface $resource Resource entity
     *
     * @return static
     */
    public function setResource(ResourceEntityInterface $resource): static;

    /**
     * Get resource id
     *
     * @return int
     */
    public function getResourceId(): int;

    /**
     * Get list id
     *
     * @return int
     */
    public function getListId(): int;

    /**
     * Set list id
     *
     * @param FinnaResourceListEntityInterface $list List entity
     *
     * @return static
     */
    public function setList(FinnaResourceListEntityInterface $list): static;

    /**
     * Set saved
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setSaved(DateTime $dateTime): static;

    /**
     * Get saved
     *
     * @return DateTime
     */
    public function getSaved(): Datetime;

    /**
     * Set notes
     *
     * @param string $note Note
     *
     * @return static
     */
    public function setNotes(string $note): static;

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes(): string;

    /**
     * Set user id from user entity
     *
     * @param UserEntityInterface $user User entity
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;
}
