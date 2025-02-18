<?php

/**
 * User account service
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024
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
 * @package  Account
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Account;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Favorites\FavoritesService;

/**
 * User account service
 *
 * @category VuFind
 * @package  Account
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserAccountService implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param FavoritesService $favoritesService Favorites service
     */
    public function __construct(protected FavoritesService $favoritesService)
    {
    }

    /**
     * Destroy the user.
     *
     * @param UserEntityInterface $user           User to delete
     * @param bool                $removeComments Whether to remove user's comments
     * @param bool                $removeRatings  Whether to remove user's ratings
     *
     * @return void
     */
    public function purgeUserData(
        UserEntityInterface $user,
        bool $removeComments = true,
        bool $removeRatings = true
    ): void {
        // Remove all lists owned by the user:
        $listService = $this->getDbService(UserListServiceInterface::class);
        foreach ($listService->getUserListsByUser($user) as $current) {
            $this->favoritesService->destroyList($current, $user, true);
        }
        $this->getDbService(ResourceTagsServiceInterface::class)->destroyResourceTagsLinksForUser(null, $user);
        if ($removeComments) {
            $this->getDbService(CommentsServiceInterface::class)->deleteByUser($user);
        }
        if ($removeRatings) {
            $this->getDbService(RatingsServiceInterface::class)->deleteByUser($user);
        }
        $this->getDbService(UserServiceInterface::class)->deleteUser($user);
    }
}
