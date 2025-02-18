<?php

/**
 * List view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Favorites\FavoritesService;

/**
 * List view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class UserList extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param FavoritesService         $favoritesService Favorites service
     * @param UserListServiceInterface $userListService  List database service
     * @param string                   $mode             List mode (enabled or disabled)
     */
    public function __construct(
        protected FavoritesService $favoritesService,
        protected UserListServiceInterface $userListService,
        protected string $mode = 'enabled'
    ) {
    }

    /**
     * Get lists with counts for the provided user.
     *
     * @param UserEntityInterface $user User owning lists
     *
     * @return array
     */
    public function getUserListsAndCountsByUser(UserEntityInterface $user): array
    {
        return $this->userListService->getUserListsAndCountsByUser($user);
    }

    /**
     * Get mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Retrieve the ID of the last list that was accessed, if any.
     *
     * @return mixed User_list ID (if set) or null (if not available).
     */
    public function lastUsed()
    {
        return $this->favoritesService->getLastUsedList();
    }

    /**
     * Is the provided user allowed to edit the provided list?
     *
     * @param ?UserEntityInterface    $user Logged-in user (null if none)
     * @param UserListEntityInterface $list List to check
     *
     * @return bool
     */
    public function userCanEditList(?UserEntityInterface $user, UserListEntityInterface $list): bool
    {
        return $this->favoritesService->userCanEditList($user, $list);
    }
}
