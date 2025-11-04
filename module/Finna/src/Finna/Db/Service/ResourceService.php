<?php

/**
 * Database service for resources.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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

use Finna\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

/**
 * Database service for resources.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends \VuFind\Db\Service\ResourceService implements ResourceServiceInterface
{
    /**
     * Get a batch of entities.
     *
     * @param ?int $lastId    ID of last retrieved entity, or null to start from beginning
     * @param int  $batchSize Batch size
     *
     * @return ResourceEntityInterface[]
     */
    public function getEntityBatch(?int $lastId, int $batchSize): array
    {
        $dql = 'SELECT r FROM ' . ResourceEntityInterface::class . ' r';
        $params = [];
        if (null !== $lastId) {
            $dql .= ' WHERE r.id > :lastId';
            $params['lastId'] = $lastId;
        }
        $dql .= ' ORDER BY r.id';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($params);
        $query->setMaxResults($batchSize);
        return $query->getResult();
    }

    /**
     * Get a set of resources from the requested favorite list.
     *
     * @param UserEntityInterface|int          $userOrId          ID of user owning favorite list
     * @param UserListEntityInterface|int|null $listOrId          ID of list to retrieve (null for all favorites)
     * @param string[]                         $tags              Tags to use for limiting results
     * @param ?string                          $sort              Resource table field to use for sorting (null for no
     * particular sort).
     * @param int                              $offset            Offset for results
     * @param ?int                             $limit             Limit for results (null for none)
     * @param bool                             $caseSensitiveTags Treat tags as case-sensitive?
     *
     * @return ResourceEntityInterface[]
     */
    public function getFavoritesX(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        array $tags = [],
        ?string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $caseSensitiveTags = false
    ): array {
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $list = $listOrId ? $this->getDoctrineReference(UserListEntityInterface::class, $listOrId) : null;
        $orderByDetails = empty($sort) ? [] : $this->getResourceOrderByClause($sort);
        $dql = 'SELECT DISTINCT r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . ResourceEntityInterface::class . ' r '
            . 'JOIN ' . UserResourceEntityInterface::class . ' ur WITH r.id = ur.resource ';
        $dqlWhere = [];
        $dqlWhere[] = 'ur.user = :user';
        $parameters = compact('user');
        if (null !== $list) {
            $dqlWhere[] = 'ur.list = :list';
            $parameters['list'] = $list;
        }

        // Adjust for tags if necessary:
        if (!empty($tags)) {
            $matches = null;
            foreach ($tags as $tag) {
                $nextTagBatch = $this->getResourceIDsForTag($tag, $user->getId(), $list?->getId(), $caseSensitiveTags);
                $matches = array_intersect(
                    $matches ?? $nextTagBatch, // first time, use whole batch
                    $nextTagBatch
                );
            }
            $dqlWhere[] = 'r.id IN (:ids)';
            $parameters['ids'] = $matches;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        if (!empty($orderByDetails['orderByClause'])) {
            $dql .= $orderByDetails['orderByClause'];
        }

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if ($offset > 0) {
            $query->setFirstResult($offset);
        }
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        $result = $query->getResult();
        return $result;
    }

    /**
     * Apply a sort parameter to a query on the resource table. Returns an
     * array with two keys: 'orderByClause' (the actual ORDER BY) and
     * 'extraSelect' (extra values to add to SELECT, if necessary)
     *
     * @param string $sort  Field to use for sorting (may include
     *                      'desc' qualifier)
     * @param string $alias Alias to the resource table (defaults to 'r')
     *
     * @return array
     */
    protected function getResourceOrderByClause(string $sort, string $alias = 'r'): array
    {
        if ('custom_order' === $sort) {
            $orderByClause = ' ORDER BY custom_order ASC';
            $extraSelect = 'ur.finnaCustomOrderIndex AS HIDDEN custom_order';
            return compact('orderByClause', 'extraSelect');
        }
        return parent::getResourceOrderByClause($sort, $alias);
    }
}
