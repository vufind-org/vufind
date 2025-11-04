<?php

/**
 * Interface for representing a Finna cache record.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use DateTime;
use VuFind\Db\Entity\EntityInterface;

/**
 * Interface for representing a Finna cache record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface FinnaCacheEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get resource ID.
     *
     * @return string
     */
    public function getResourceId(): string;

    /**
     * Set resource ID.
     *
     * @param string $id Resource ID
     *
     * @return static
     */
    public function setResourceId(string $id): static;

    /**
     * Get creation date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set creation date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Get modification UNIX timestamp.
     *
     * @return int
     */
    public function getModificationTimestamp(): int;

    /**
     * Set modification UNIX timestamp.
     *
     * @param int $mtime Unix timestamp of modification
     *
     * @return static
     */
    public function setModificationTimestamp(int $mtime): static;

    /**
     * Get data.
     *
     * @return string
     */
    public function getData(): string;

    /**
     * Set data.
     *
     * @param string $data Data
     *
     * @return static
     */
    public function setData(string $data): static;
}
