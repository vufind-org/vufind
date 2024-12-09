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
     * Id getter
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Session Id setter
     *
     * @param ?string $sid Session Id.
     *
     * @return SessionEntityInterface
     */
    public function setSessionId(?string $sid): SessionEntityInterface;

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return SessionEntityInterface
     */
    public function setCreated(DateTime $dateTime): SessionEntityInterface;

    /**
     * Set time the session is last used.
     *
     * @param int $lastUsed Time last used
     *
     * @return SessionEntityInterface
     */
    public function setLastUsed(int $lastUsed): SessionEntityInterface;

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
     * @return SessionEntityInterface
     */
    public function setData(?string $data): SessionEntityInterface;

    /**
     * Get session data.
     *
     * @return ?string
     */
    public function getData(): ?string;
}
