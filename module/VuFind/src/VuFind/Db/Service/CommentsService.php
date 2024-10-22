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

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\Comments;
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\User;
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
        $class = $this->getEntityClass(Comments::class);
        return new $class();
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
            ->setUser($this->getDoctrineReference(User::class, $userOrId))
            ->setComment($comment)
            ->setCreated(new \DateTime())
            ->setResource($this->getDoctrineReference(Resource::class, $resourceOrId));

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
            . 'FROM ' . $this->getEntityClass(Comments::class) . ' c '
            . 'LEFT JOIN c.user u '
            . 'WHERE c.resource = :resource '
            . 'ORDER BY c.created';

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

        $del = 'DELETE FROM ' . $this->getEntityClass(Comments::class) . ' c '
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
        $dql = 'DELETE FROM ' . $this->getEntityClass(Comments::class) . ' c '
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
            . 'FROM ' . $this->getEntityClass(Comments::class) . ' c';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
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
        return $this->entityManager->find(
            $this->getEntityClass(Comments::class),
            $id
        );
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
        $dql = 'UPDATE ' . $this->getEntityClass(Comments::class) . ' e '
            . 'SET e.resource = :new WHERE e.resource = :old';
        $parameters = compact('new', 'old');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }
}
