<?php

/**
 * Database service interface for shortlinks.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\ShortlinksEntityInterface;

/**
 * Database service interface for shortlinks.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways interface
 */
interface ShortlinksServiceInterface extends DbServiceInterface
{
    /**
     * Create a short link entity.
     *
     * @return ShortlinksEntityInterface
     */
    public function createEntity(): ShortlinksEntityInterface;

    /**
     * Create and persist an entity for the provided path.
     *
     * @param string $path Path part of URL being shortened.
     *
     * @return ShortlinksEntityInterface
     */
    public function createAndPersistEntityForPath(string $path): ShortlinksEntityInterface;

    /**
     * Look up a short link by hash value.
     *
     * @param string $hash Hash value.
     *
     * @return ?ShortlinksEntityInterface
     */
    public function getShortLinkByHash(string $hash): ?ShortlinksEntityInterface;

    /**
     * Get rows with missing hashes (for legacy upgrading).
     *
     * @return ShortlinksEntityInterface[]
     */
    public function getShortLinksWithMissingHashes(): array;
}
