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

use VuFind\Db\Entity\Ratings;

/**
 * Database service for Ratings.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RatingsService extends AbstractService implements \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Get average rating and rating count associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param ?int   $userId User ID, or null for all users
     *
     * @return array Array with keys count and rating (between 0 and 100)
     */
    public function getForResource(string $id, string $source, ?int $userId): array
    {
        $resource = $this->getDbService(ResourceService::class)
            ->findResource($id, $source, false);

        if (empty($resource)) {
            return [
                'count' => 0,
                'rating' => 0,
            ];
        }
        $dql = 'SELECT COUNT(r.id) AS count, AVG(r.rating) AS rating '
            . 'FROM ' . $this->getEntityClass(Ratings::class) . ' r ';

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
            'rating' => floor($result[0]['rating']) ?? 0,
        ];
    }

    /**
     * Get rating breakdown for the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param array  $groups Group definition (key => [min, max])
     *
     * @return array Array with keys count and rating (between 0 and 100) as well as
     * an groups array with ratings from lowest to highest
     */
    public function getCountsForResource(
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

        $resource = $this->getDbService(ResourceService::class)
            ->findResource($id, $source, false);

        if (empty($resource)) {
            return $result;
        }
        $dql = 'SELECT COUNT(r.id) AS count, r.rating AS rating '
            . 'FROM ' . $this->getEntityClass(Ratings::class) . ' r '
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
            if ($groups) {
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
        }
        $result['rating'] = $groupCount ? floor($ratingTotal / $groupCount) : 0;
        return $result;
    }

    /**
     * Deletes all ratings by a user.
     *
     * @param int|\VuFind\Db\Entity\User $user User object or identifier
     *
     * @return void
     */
    public function deleteByUser($user): void
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Ratings::class) . ' r '
            . 'WHERE r.user = :user';
        $parameters['user'] = $user;
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
            . 'FROM ' . $this->getEntityClass(Ratings::class) . ' r';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
    }
}
