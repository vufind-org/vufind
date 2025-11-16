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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrinePaginatorAdapter;
use Laminas\Paginator\Paginator;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\RatingsEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Log\LoggerAwareTrait;

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
    DbServiceAwareInterface,
    LoggerAwareInterface,
    RatingsServiceInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

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
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return [
                'count' => 0,
                'rating' => 0,
            ];
        }
        $dql = 'SELECT COUNT(r.id) AS count, AVG(r.rating) AS rating '
            . 'FROM ' . RatingsEntityInterface::class . ' r ';

        $dqlWhere[] = 'r.resource = :resource';
        $parameters['resource'] = $resource;
        if (null !== $userId) {
            $dqlWhere[] = 'r.user = :user';
            $parameters['user'] = $userId;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        return [
            'count' => $result[0]['count'],
            'rating' => floor($result[0]['rating'] ?? 0) ?? 0,
        ];
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
        $result = [
            'count' => 0,
            'rating' => 0,
            'groups' => [],
        ];
        foreach (array_keys($groups) as $key) {
            $result['groups'][$key] = 0;
        }

        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return $result;
        }
        $dql = 'SELECT COUNT(r.id) AS count, r.rating AS rating '
            . 'FROM ' . RatingsEntityInterface::class . ' r '
            . 'WHERE r.resource = :resource '
            . 'GROUP BY rating';

        $parameters['resource'] = $resource;

        $query = $this->entityManager->createQuery($dql);

        $query->setParameters($parameters);
        $queryResult = $query->getResult();

        $ratingTotal = 0;
        $groupCount = 0;
        foreach ($queryResult as $rating) {
            $result['count'] += $rating['count'];
            $ratingTotal += $rating['rating'];
            ++$groupCount;
            foreach ($groups as $key => $range) {
                if (
                    $rating['rating'] >= $range[0]
                    && $rating['rating'] <= $range[1]
                ) {
                    $result['groups'][$key] = ($result['groups'][$key] ?? 0)
                        + $rating['count'];
                }
            }
        }
        $result['rating'] = $groupCount ? floor($ratingTotal / $groupCount) : 0;
        return $result;
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
        $dql = 'DELETE FROM ' . RatingsEntityInterface::class . ' r '
            . 'WHERE r.user = :user';
        $parameters['user'] = is_int($userOrId) ? $userOrId : $userOrId->getId();
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get statistics on use of Ratings.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = 'SELECT COUNT(DISTINCT(r.user)) AS users, '
            . 'COUNT(DISTINCT(r.resource)) AS resources, '
            . 'COUNT(r.id) AS total '
            . 'FROM ' . RatingsEntityInterface::class . ' r';
        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleResult();
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
        if (null !== $rating && ($rating < 0 || $rating > 100)) {
            throw new \Exception('Rating value out of range');
        }

        $dql = 'SELECT r '
            . 'FROM ' . RatingsEntityInterface::class . ' r '
            . 'WHERE r.user = :user AND r.resource = :resource';
        $resource = $this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId);
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $parameters = compact('resource', 'user');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if ($existing = $query->getOneOrNullResult()) {
            if (null === $rating) {
                $this->entityManager->remove($existing);
            } else {
                $existing->setRating($rating);
            }
            $updatedRatingId = $existing->getId();
            try {
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logError('Rating update failed: ' . $e->getMessage());
                throw $e;
            }
            return $updatedRatingId;
        }

        if (null === $rating) {
            return 0;
        }

        $row = $this->createEntity()
            ->setResource($resource)
            ->setUser($user)
            ->setRating($rating)
            ->setCreated(new \DateTime());
        try {
            $this->persistEntity($row);
        } catch (\Exception $e) {
            $this->logError('Could not save rating: ' . $e->getMessage());
            return 0;
        }
        return $row->getId();
    }

    /**
     * Create a ratings entity.
     *
     * @return RatingsEntityInterface
     */
    public function createEntity(): RatingsEntityInterface
    {
        return $this->entityPluginManager->get(RatingsEntityInterface::class);
    }

    /**
     * Delete ratings by given user and rating ids.
     *
     * @param array $ids    Array of rating ids
     * @param int   $userId User ID
     *
     * @return void
     */
    public function deleteByIdsAndUserId(array $ids, int $userId): void
    {
        $dql = 'DELETE FROM ' . RatingsEntityInterface::class . ' ra '
         . 'WHERE ra.user = :user AND ra.id IN (:ids)';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters([
            'user' => $userId,
            'ids'  => $ids,
        ]);
        $query->execute();
    }

    /**
     * Get a paginated result of all ratings by user id.
     *
     * @param int    $userId User Id
     * @param int    $limit  Limit
     * @param int    $page   Page
     * @param string $sort   Sort
     *
     * @return Paginator
     */
    public function getRatingsPaginator(
        int $userId,
        int $limit,
        int $page,
        string $sort
    ): Paginator {
        $dql = 'SELECT r.id, r.rating, r.created AS created, '
            . 'u.id AS user_id, u.username AS username, '
            . 'res.id AS resource_id, res.recordId AS record_id, res.source AS source, res.title AS title '
            . 'FROM ' . RatingsEntityInterface::class . ' r '
            . 'LEFT JOIN r.user u '
            . 'LEFT JOIN r.resource res '
            . 'WHERE r.user = :userId';

        $parameters = ['userId' => $userId];

        $sortOrder = $sort ? $sort : 'created DESC';

        $dql .= ' ORDER BY ' . $sortOrder;

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $doctrinePaginator = new DoctrinePaginator($query);
        $doctrinePaginator->setUseOutputWalkers(false);

        $paginator = new Paginator(new DoctrinePaginatorAdapter($doctrinePaginator));
        $paginator->setItemCountPerPage($limit);
        $paginator->setCurrentPageNumber($page);
        return $paginator;
    }
}
