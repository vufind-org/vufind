<?php

/**
 * Database service interface for my_data.
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

use VuFind\Db\Entity\MyDataEntityInterface;

/**
 * Database service interface for my_data.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways interface
 */
interface MyDataServiceInterface extends SessionDataServiceInterface
{
    /**
     * Create an entity.
     *
     * @return MyDataEntityInterface
     */
    public function createEntity(): MyDataEntityInterface;

    /**
     * Get an entity by ID.
     *
     * @param int $id ID
     *
     * @return ?MyDataEntityInterface
     */
    public function getById(int $id): ?MyDataEntityInterface;

    /**
     * Delete an entity by ID.
     *
     * @param int $id ID
     *
     * @return void
     */
    public function deleteById(int $id): void;
}
