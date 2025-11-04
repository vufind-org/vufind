<?php

/**
 * Database service interface for user lists.
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

use Finna\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for user lists.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface UserListServiceInterface extends \VuFind\Db\Service\UserListServiceInterface
{
    /**
     * Check if custom favorite order is used in a list
     *
     * @param UserListEntityInterface $list List entity.
     *
     * @return bool
     */
    public function isCustomOrderAvailable(UserListEntityInterface $list);

    /**
     * Get next available custom order index
     *
     * @param UserListEntityInterface $list List entity.
     *
     * @return int Next available index or zero if custom order is not used or list is empty
     */
    public function getNextAvailableCustomOrderIndex(UserListEntityInterface $list);

    /**
     * Update custom favorite list order
     *
     * @param UserEntityInterface $user        User id
     * @param int                 $listId      List id
     * @param array               $orderedList Ordered List of Resources
     *
     * @return void
     */
    public function saveCustomFavoriteOrder(UserEntityInterface $user, int $listId, array $orderedList): void;

    /**
     * Retrieve user's list object by title.
     *
     * @param UserEntityInterface|int $userOrId User entity or ID.
     * @param string                  $title    Title of the list to retrieve
     *
     * @return ?UserListEntityInterface
     */
    public function getListByTitle(UserEntityInterface|int $userOrId, string $title): ?UserListEntityInterface;

    /**
     * Retrieve protected lists.
     *
     * @return UserListEntityInterface[]
     */
    public function getProtectedLists(): array;
}
