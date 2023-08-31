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

use VuFind\Db\Entity\Comments;

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
class CommentsService extends AbstractService implements \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Create a comments entity object.
     *
     * @return Comments
     */
    public function createEntity(): Comments
    {
        $class = $this->getEntityClass(Comments::class);
        return new $class();
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return array
     */
    public function getForResource(string $id, $source = DEFAULT_SEARCH_BACKEND)
    {
        $resource = $this->getDbService(\VuFind\Db\Service\ResourceService::class)
            ->findResource($id, $source, false);

        if (empty($resource)) {
            return [];
        }
        $dql = 'SELECT c, u.firstname, u.lastname '
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
     * @param int                        $id   ID of row to delete
     * @param int|\VuFind\Db\Entity\User $user User object or identifier
     *
     * @return bool
     */
    public function deleteIfOwnedByUser($id, $user)
    {
        if (null === $user) {
            return false;
        }
        $comment = $this->entityManager->find(
            $this->getEntityClass(\VuFind\Db\Entity\Comments::class),
            $id
        );

        $commentOwnerId = $comment->getUser()->getId();
        $userId = is_int($user) ? $user : $user->getId();
        if ($userId !== $commentOwnerId) {
            return false;
        }

        $del = 'DELETE FROM ' . $this->getEntityClass(Comments::class) . ' c '
        . 'WHERE c.id = :id AND c.user = :user';
        $parameters = compact('id', 'user');
        $query = $this->entityManager->createQuery($del);
        $query->setParameters($parameters);
        $query->execute();
        return true;
    }

    /**
     * Deletes all comments by a user.
     *
     * @param int|\VuFind\Db\Entity\User $user User object or identifier
     *
     * @return void
     */
    public function deleteByUser($user)
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Comments::class) . ' c '
        . 'WHERE c.user = :user';
        $parameters = compact('user');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
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
}
