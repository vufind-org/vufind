<?php

/**
 * Interface for representing a session row.
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
 * Interface for representing a session row.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface SessionEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Session Id setter
     *
     * @param ?string $sid Session Id.
     *
     * @return static
     */
    public function setSessionId(?string $sid): static;

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Set time the session is last used.
     *
     * @param int $lastUsed Time last used
     *
     * @return static
     */
    public function setLastUsed(int $lastUsed): static;

    /**
     * Get time when the session was last used.
     *
     * @return int
     */
    public function getLastUsed(): int;

    /**
     * Session data setter.
     *
     * @param ?string $data Session data.
     *
     * @return static
     */
    public function setData(?string $data): static;

    /**
     * Get session data.
     *
     * @return ?string
     */
    public function getData(): ?string;
}
