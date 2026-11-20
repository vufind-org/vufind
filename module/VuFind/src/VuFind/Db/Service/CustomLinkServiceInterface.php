<?php

/**
 * Database service interface for CustomLink.
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

use VuFind\Db\Entity\CustomLinkEntityInterface;

/**
 * Database service interface for CustomLink.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CustomLinkServiceInterface extends DbServiceInterface
{
    /**
     * Create a custom link entity object.
     *
     * @return CustomLinkEntityInterface
     */
    public function createEntity(): CustomLinkEntityInterface;

    /**
     * Get a custom link from the database based on id.
     *
     * @param int $id Custom link id
     *
     * @return ?CustomLinkEntityInterface
     */
    public function getById(int $id): ?CustomLinkEntityInterface;

    /**
     * Get complete list of custom links from the database.
     *
     * @return CustomLinkEntityInterface[]
     */
    public function getCustomLinks(): array;

    /**
     * Insert a new custom link into the database.
     *
     * @param array $data Custom link data
     *
     * @return CustomLinkEntityInterface
     */
    public function insert(array $data): CustomLinkEntityInterface;

    /**
     * Update an existing custom link.
     *
     * @param int   $id   Custom link id
     * @param array $data Custom link data
     *
     * @return CustomLinkEntityInterface
     */
    public function update(int $id, array $data): CustomLinkEntityInterface;

    /**
     * Delete an existing custom link.
     *
     * @param int $id Custom link id
     *
     * @return void
     */
    public function delete(int $id): void;
}
