<?php

/**
 * Database service for Ratings.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function is_int;

/**
 * Database service for Ratings.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RatingsService extends AbstractDbService implements
    DbTableAwareInterface,
    RatingsServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Get average rating and rating count associated with the specified record.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param ?int   $userId User ID, or null for all users
     *
     * @return array Array with keys count and rating (between 0 and 100)
     */
    public function getRecordRatings(string $id, string $source, ?int $userId): array
    {
        return $this->getDbTable('ratings')->getForResource($id, $source, $userId);
    }

    /**
     * Get rating breakdown for the specified record.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param array  $groups Group definition (key => [min, max])
     *
     * @return array Array with keys count and rating (between 0 and 100) as well as
     * an groups array with ratings from lowest to highest
     */
    public function getCountsForRecord(
        string $id,
        string $source,
        array $groups
    ): array {
        return $this->getDbTable('ratings')->getCountsForResource($id, $source, $groups);
    }

    /**
     * Deletes all ratings by a user.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return void
     */
    public function deleteByUser(UserEntityInterface|int $userOrId): void
    {
        $this->getDbTable('ratings')->deleteByUser(
            is_int($userOrId) ? $this->getDbTable('user')->getById($userOrId) : $userOrId
        );
    }

    /**
     * Get statistics on use of Ratings.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->getDbTable('ratings')->getStatistics();
    }

    /**
     * Add or update user's rating for a resource.
     *
     * @param ResourceEntityInterface|int $resourceOrId Resource to add or update rating.
     * @param UserEntityInterface|int     $userOrId     User
     * @param ?int                        $rating       Rating (null to delete)
     *
     * @throws \Exception
     * @return int ID of rating added, deleted or updated
     */
    public function addOrUpdateRating(
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        ?int $rating
    ): int {
        $resource = is_int($resourceOrId)
            ? $this->getDbTable('resource')->select(['id' => $resourceOrId])->current() : $resourceOrId;
        return $resource->addOrUpdateRating(is_int($userOrId) ? $userOrId : $userOrId->getId(), $rating);
    }
}
