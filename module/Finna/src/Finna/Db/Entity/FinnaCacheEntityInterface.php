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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Resource ID setter
     *
     * @param string $id Resource ID
     *
     * @return static
     */
    public function setResourceId(string $id): static;

    /**
     * Resource ID getter
     *
     * @return string
     */
    public function getResourceId(): string;

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime;

    /**
     * Modification timestamp setter
     *
     * @param int $mtime Unix timestamp of modification
     *
     * @return static
     */
    public function setModificationTimestamp(int $mtime): static;

    /**
     * Modification timestamp getter
     *
     * @return int
     */
    public function getModificationTimestamp(): int;

    /**
     * Data setter
     *
     * @param string $data Data
     *
     * @return static
     */
    public function setData(string $data): static;

    /**
     * Data getter
     *
     * @return string
     */
    public function getData(): string;
}
