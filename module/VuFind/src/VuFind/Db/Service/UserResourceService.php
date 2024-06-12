<?php

/**
 * Database service for UserResource.
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
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResource;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for UserResource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserResourceService extends AbstractDbService implements
    LoggerAwareInterface,
    DbServiceAwareInterface,
    UserResourceServiceInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Get a list of duplicate rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return array
     */
    public function getDuplicates()
    {
        $dql = 'SELECT MIN(ur.resource) as resource_id, MIN(ur.list) as list_id, '
            . 'MIN(ur.user) as user_id, COUNT(ur.resource) as cnt, MIN(ur.id) as id '
            . 'FROM ' . $this->getEntityClass(UserResource::class) . ' ur '
            . 'GROUP BY ur.resource, ur.list, ur.user '
            . 'HAVING COUNT(ur.resource) > 1';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate()
    {
        $repo = $this->entityManager->getRepository($this->getEntityClass(UserResource::class));
        foreach ($this->getDuplicates() as $dupe) {
            // Do this as a transaction to prevent odd behavior:
            $this->entityManager->getConnection()->beginTransaction();

            // Merge notes together...
            $mainCriteria = [
                'resource' => $dupe['resource_id'],
                'list' => $dupe['list_id'],
                'user' => $dupe['user_id'],
            ];
            try {
                $dupeRows = $repo->findBy($mainCriteria);
                $notes = [];
                foreach ($dupeRows as $row) {
                    if (!empty($row->getNotes())) {
                        $notes[] = $row->getNotes();
                    }
                }
                $userResource = $this->getDoctrineReference(UserResource::class, $dupe['id']);
                $userResource->setNotes(implode(' ', $notes));
                $this->entityManager->flush();

                // Now delete extra rows...
                // match on all relevant IDs in duplicate group
                // getDuplicates returns the minimum id in the set, so we want to
                // delete all of the duplicates with a higher id value.
                $dql = 'DELETE FROM ' . $this->getEntityClass(UserResource::class) . ' ur '
                    . 'WHERE ur.resource = :resource AND ur.list = :list '
                    . 'AND ur.user = :user AND ur.id > :id';
                $mainCriteria['id'] = $dupe['id'];
                $query = $this->entityManager->createQuery($dql);
                $query->setParameters($mainCriteria);
                $query->execute();
                // Done -- commit the transaction:
                $this->entityManager->getConnection()->commit();
            } catch (\Exception $e) {
                // If something went wrong, roll back the transaction and rethrow the error:
                $this->entityManager->getConnection()->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string                           $recordId ID of record being checked.
     * @param string                           $source   Source of record to look up
     * @param UserListEntityInterface|int|null $listOrId Optional list entity or ID
     * (to limit results to a particular list).
     * @param UserEntityInterface|int|null     $userOrId Optional user entity or ID
     * (to limit results to a particular user).
     *
     * @return UserResourceEntityInterface[]
     */
    public function getFavoritesForRecord(
        string $recordId,
        string $source = DEFAULT_SEARCH_BACKEND,
        UserListEntityInterface|int|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null
    ): array {
        $dql = 'SELECT DISTINCT ur FROM ' . $this->getEntityClass(UserResource::class) . ' ur '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = ur.resource '
            . 'WHERE r.source = :source AND r.recordId = :recordId ';

        $parameters = compact('source', 'recordId');
        if (null !== $userOrId) {
            $dql .= 'AND ur.user = :user ';
            $parameters['user'] = $this->getDoctrineReference(User::class, $userOrId);
        }
        if (null !== $listOrId) {
            $dql .= 'AND ur.list = :list';
            $parameters['list'] = $this->getDoctrineReference(UserList::class, $listOrId);
        }

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get statistics on use of UserResource.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = 'SELECT COUNT(DISTINCT(u.user)) AS users, '
            . 'COUNT(DISTINCT(u.list)) AS lists, '
            . 'COUNT(DISTINCT(u.resource)) AS resources, '
            . 'COUNT(u.id) AS total '
            . 'FROM ' . $this->getEntityClass(UserResource::class) . ' u';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
    }

    /**
     * Unlink rows for the specified resource. This will also automatically remove
     * any tags associated with the relationship.
     *
     * @param User|int          $userOrId    ID of user removing links
     * @param string|array|null $resource_id ID (or array of IDs) of resource(s) to unlink
     * (null for ALL matching resources)
     * @param UserList|null     $list        list to unlink (null for ALL matching lists, with the destruction
     * of all tags associated with the $resource_id value; true for ALL matching lists,
     * but retaining any tags associated with the resource_id independently of lists)
     *
     * @return void
     */
    public function destroyLinks($userOrId, $resource_id = null, $list = null)
    {
        $user = $this->getDoctrineReference(User::class, $userOrId);

        // Remove any tags associated with the links we are removing; we don't
        // want to leave orphaned tags in the resource_tags table after we have
        // cleared out favorites in user_resource!
        $this->getDbService(ResourceTagsServiceInterface::class)
            ->destroyResourceTagsLinksForUser($resource_id, $user, $list);

        $dql = 'DELETE FROM ' . $this->getEntityClass(UserResource::class) . ' ur ';
        $dqlWhere = ['ur.user = :user '];
        $parameters = compact('user');
        if (null !== $resource_id) {
            $dqlWhere[] = ' ur.resource IN (:resource_id) ';
            $parameters['resource_id'] = (array)$resource_id;
        }

        // null or true values of $list have different meanings in the
        // context of the destroyResourceTagsLinksForUser() call above, since
        // some tags have a null $list value. In the case of user_resource
        // rows, however, every row has a non-null $list value, so the
        // two cases are equivalent and may be handled identically.
        if (null !== $list && true !== $list) {
            $dqlWhere[] = ' ur.list = :list ';
            $parameters['list'] = $list;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Create user/resource/list link if one does not exist; update notes if one does.
     *
     * @param ResourceEntityInterface|int $resourceOrId Entity or ID of resource to link up
     * @param UserEntityInterface|int     $userOrId     Entity or ID of user creating link
     * @param UserListEntityInterface|int $listOrId     Entity or ID of list to link up
     * @param string                      $notes        Notes to associate with link
     *
     * @return UserResource|false
     */
    public function createOrUpdateLink(
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int $listOrId,
        string $notes = ''
    ): UserResourceEntityInterface {
        $resource = $this->getDoctrineReference(Resource::class, $resourceOrId);
        $user = $this->getDoctrineReference(User::class, $userOrId);
        $list = $this->getDoctrineReference(UserList::class, $listOrId);
        $params = compact('resource', 'list', 'user');
        $result = current($this->entityManager->getRepository($this->getEntityClass(UserResource::class))
            ->findBy($params));

        if (empty($result)) {
            $result = $this->createEntity()
                ->setResource($resource)
                ->setUser($user)
                ->setUserList($list);
        }
        // Update the notes:
        $result->setNotes($notes);
        try {
            $this->persistEntity($result);
        } catch (\Exception $e) {
            $this->logError('Could not save user resource: ' . $e->getMessage());
            return false;
        }
        return $result;
    }

    /**
     * Create a UserResource entity object.
     *
     * @return UserResourceEntityInterface
     */
    public function createEntity(): UserResourceEntityInterface
    {
        $class = $this->getEntityClass(UserResource::class);
        return new $class();
    }
}
