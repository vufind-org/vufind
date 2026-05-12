<?php

/**
 * Database service for Comments.
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
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Log\LoggerAwareTrait;

use function is_int;

/**
 * Database service for Comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class CommentsService extends AbstractDbService implements
    CommentsServiceInterface,
    DbServiceAwareInterface,
    LoggerAwareInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Create a comments entity object.
     *
     * @return CommentsEntityInterface
     */
    public function createEntity(): CommentsEntityInterface
    {
        return $this->entityPluginManager->get(CommentsEntityInterface::class);
    }

    /**
     * Add a comment to the current resource. Returns comment ID on success, null on failure.
     *
     * @param string                      $comment      The comment to save.
     * @param UserEntityInterface|int     $userOrId     User object or identifier
     * @param ResourceEntityInterface|int $resourceOrId Resource object or identifier
     *
     * @return ?int
     */
    public function addComment(
        string $comment,
        UserEntityInterface|int $userOrId,
        ResourceEntityInterface|int $resourceOrId
    ): ?int {
        $data = $this->createEntity()
            ->setUser($this->getDoctrineReference(UserEntityInterface::class, $userOrId))
            ->setComment($comment)
            ->setCreated(new \DateTime())
            ->setResource($this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId));

        try {
            $this->persistEntity($data);
        } catch (\Exception $e) {
            $this->logError('Could not save comment: ' . $e->getMessage());
            return null;
        }

        return $data->getId();
    }

    /**
     * Get comments associated with the specified record.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return CommentsEntityInterface[]
     */
    public function getRecordComments(string $id, string $source = DEFAULT_SEARCH_BACKEND): array
    {
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return [];
        }
        $dql = 'SELECT c '
            . 'FROM ' . CommentsEntityInterface::class . ' c '
            . 'LEFT JOIN c.user u '
            . 'WHERE c.resource = :resource '
            . 'ORDER BY c.created ASC';

        $parameters = compact('resource');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Delete a comment if the owner is logged in.  Returns true on success.
     *
     * @param int                     $id       ID of row to delete
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return bool
     */
    public function deleteIfOwnedByUser(int $id, UserEntityInterface|int $userOrId): bool
    {
        if (null === $userOrId) {
            return false;
        }

        $userId = is_int($userOrId) ? $userOrId : $userOrId->getId();
        $comment = $this->getCommentById($id);
        if ($userId !== $comment->getUser()->getId()) {
            return false;
        }

        $del = 'DELETE FROM ' . CommentsEntityInterface::class . ' c '
        . 'WHERE c.id = :id AND c.user = :user';
        $query = $this->entityManager->createQuery($del);
        $query->setParameters(['id' => $id, 'user' => $userId]);
        $query->execute();
        return true;
    }

    /**
     * Deletes all comments by a user.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return void
     */
    public function deleteByUser(UserEntityInterface|int $userOrId): void
    {
        $dql = 'DELETE FROM ' . CommentsEntityInterface::class . ' c '
            . 'WHERE c.user = :user';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['user' => is_int($userOrId) ? $userOrId : $userOrId->getId()]);
        $query->execute();
    }

    /**
     * Get statistics on use of comments.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = 'SELECT COUNT(DISTINCT(c.user)) AS users, '
            . 'COUNT(DISTINCT(c.resource)) AS resources, '
            . 'COUNT(c.id) AS total '
            . 'FROM ' . CommentsEntityInterface::class . ' c';
        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleResult();
    }

    /**
     * Get a comment row by ID (or return null for no match).
     *
     * @param int $id ID of comment to retrieve.
     *
     * @return ?CommentsEntityInterface
     */
    public function getCommentById(int $id): ?CommentsEntityInterface
    {
        return $this->entityManager->find(CommentsEntityInterface::class, $id);
    }

    /**
     * Change all matching comments to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int $old Original resource ID
     * @param int $new New resource ID
     *
     * @return void
     */
    public function changeResourceId(int $old, int $new): void
    {
        $dql = 'UPDATE ' . CommentsEntityInterface::class . ' e '
            . 'SET e.resource = :new WHERE e.resource = :old';
        $parameters = compact('new', 'old');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get a paginated result of all comments made by the user.
     *
     * @param int    $userId User ID
     * @param int    $limit  Limit
     * @param int    $page   Page
     * @param string $sort   Sort
     *
     * @return Paginator
     */
    public function getCommentsPaginator(
        int $userId,
        int $limit,
        int $page,
        string $sort
    ): Paginator {
        $dql = 'SELECT c.id, c.comment, c.created AS created, '
            . 'u.id AS user_id, u.username AS username, '
            . 'r.id AS resource_id, r.recordId AS record_id, r.source AS source, r.title AS title '
            . 'FROM ' . CommentsEntityInterface::class . ' c '
            . 'LEFT JOIN c.user u '
            . 'LEFT JOIN c.resource r '
            . 'WHERE c.user = :userId';

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

    /**
     * Delete comments by given user and comment ids.
     *
     * @param array $ids    Array of comment ids
     * @param int   $userId User ID
     *
     * @return void
     */
    public function deleteByIdsAndUserId(array $ids, int $userId): void
    {
        $dql = 'DELETE FROM ' . CommentsEntityInterface::class . ' c '
            . 'WHERE c.user = :user AND c.id IN (:ids)';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters([
            'user' => $userId,
            'ids'  => $ids,
        ]);
        $query->execute();
    }
}
