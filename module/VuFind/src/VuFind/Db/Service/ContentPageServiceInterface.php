<?php

/**
 * Database service interface for ContentPage.
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

use VuFind\Db\Entity\ContentPageEntityInterface;

/**
 * Database service interface for ContentPage.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ContentPageServiceInterface extends DbServiceInterface
{
    /**
     * Create a content page entity object.
     *
     * @return ContentPageEntityInterface
     */
    public function createEntity(): ContentPageEntityInterface;

    /**
     * Get a content page from the database based on HRID.
     *
     * @param string $hrid HRID
     *
     * @return ?ContentPageEntityInterface
     */
    public function getByHrid(string $hrid): ?ContentPageEntityInterface;

    /**
     * Get complete list of content pages from the database.
     *
     * @return ContentPageEntityInterface[]
     */
    public function getContentPages(): array;

    /**
     * Insert a new content page into the database.
     *
     * @param array $data Content page data
     *
     * @return ContentPageEntityInterface
     */
    public function insert(array $data): ContentPageEntityInterface;

    /**
     * Update an existing content page by HRID.
     *
     * @param string $hrid Content page HRID
     * @param array  $data Content page data
     *
     * @return ContentPageEntityInterface
     */
    public function updateByHrid(string $hrid, array $data): ContentPageEntityInterface;

    /**
     * Delete an existing content page by HRID.
     *
     * @param string $hrid Content page ID
     *
     * @return void
     */
    public function deleteByHrid(string $hrid): void;
}
