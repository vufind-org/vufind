<?php

/**
 * Database service interface for Notice.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\NoticeEntityInterface;

/**
 * Database service interface for Notice.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface NoticeServiceInterface extends DbServiceInterface
{
    /**
     * Create a notice entity object.
     *
     * @return NoticeEntityInterface
     */
    public function createEntity(): NoticeEntityInterface;

    /**
     * Get a notice from the database based on id.
     *
     * @param int $id Notice id
     *
     * @return ?NoticeEntityInterface
     */
    public function getById(int $id): ?NoticeEntityInterface;

    /**
     * Get complete list of notices from the database.
     *
     * @return NoticeEntityInterface[]
     */
    public function getNotices(): array;

    /**
     * Insert a new notice into the database.
     *
     * @param array $data Notice data
     *
     * @return NoticeEntityInterface
     */
    public function insert(array $data): NoticeEntityInterface;

    /**
     * Update an existing notice.
     *
     * @param int   $id   Notice id
     * @param array $data Notice data
     *
     * @return NoticeEntityInterface
     */
    public function update(int $id, array $data): NoticeEntityInterface;

    /**
     * Delete an existing notice.
     *
     * @param int $id Notice id
     *
     * @return void
     */
    public function delete(int $id): void;
}
