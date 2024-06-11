<?php

/**
 * Trait to purge user data from the database.
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Favorites\FavoritesService;

use function is_callable;

/**
 * Trait to purge user data from the database.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait PurgeUserFeature
{
    /**
     * Destroy the user.
     *
     * @param UserEntityInterface $user           User to delete
     * @param bool                $removeComments Whether to remove user's comments
     * @param bool                $removeRatings  Whether to remove user's ratings
     *
     * @return void
     */
    protected function purgeUserData(
        UserEntityInterface $user,
        bool $removeComments = true,
        bool $removeRatings = true
    ): void {
        if (!is_callable([$this, 'getDbService'])) {
            throw new \Exception('purgeUserData requires getDbService method!');
        }
        if (!is_callable([$this, 'getFavoritesService'])) {
            if (!($favoritesService = $this->serviceLocator?->get(FavoritesService::class))) {
                throw new \Exception('purgeUserData could not find FavoritesService!');
            }
        } else {
            $favoritesService = $this->getFavoritesService();
        }
        // Remove all lists owned by the user:
        $listService = $this->getDbService(UserListServiceInterface::class);
        foreach ($listService->getUserListsByUser($user) as $current) {
            $favoritesService->destroyList($current, $user, true);
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
