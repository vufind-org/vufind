<?php

/**
 * Database service interface for Finna cache.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaCacheEntityInterface;
use VuFind\Db\Service\DbServiceInterface;

/**
 * Database service interface for Finna cache.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FinnaCacheServiceInterface extends DbServiceInterface
{
    /**
     * Create a FinnaCache entity object.
     *
     * @return FinnaCacheEntityInterface
     */
    public function createEntity(): FinnaCacheEntityInterface;

    /**
     * Delete an entity.
     *
     * @param FinnaCacheEntityInterface $entity Entity
     *
     * @return void
     */
    public function deleteCacheEntry(FinnaCacheEntityInterface $entity): void;

    /**
     * Get cache item from database by resource id.
     *
     * @param string $resourceId Resource id
     *
     * @return ?FinnaCacheEntityInterface
     */
    public function getByResourceId(string $resourceId): ?FinnaCacheEntityInterface;
}
