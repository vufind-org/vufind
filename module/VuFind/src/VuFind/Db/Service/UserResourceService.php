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

use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
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
            . 'FROM ' . UserResourceEntityInterface::class . ' ur '
            . 'GROUP BY ur.resource, ur.list, ur.user '
            . 'HAVING COUNT(ur.resource) > 1';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
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
        $dql = 'SELECT DISTINCT ur FROM ' . UserResourceEntityInterface::class . ' ur '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = ur.resource '
            . 'WHERE r.source = :source AND r.recordId = :recordId ';

        $parameters = compact('source', 'recordId');
        if (null !== $userOrId) {
            $dql .= 'AND ur.user = :user ';
            $parameters['user'] = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        }
        if (null !== $listOrId) {
            $dql .= 'AND ur.list = :list';
            $parameters['list'] = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
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
            . 'FROM ' . UserResourceEntityInterface::class . ' u';
        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleResult();
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
        $resource = $this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId);
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $list = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
        $params = compact('resource', 'list', 'user');
        $result = $this->entityManager->getRepository(UserResourceEntityInterface::class)
            ->findOneBy($params);

        if (empty($result)) {
            $result = $this->createEntity()
                ->setResource($resource)
                ->setUser($user)
                ->setUserList($list);
        }
        // Update the notes:
        $result->setNotes($notes);
        $this->persistEntity($result);
        return $result;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param int|int[]|null              $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL
     * matching resources)
     * @param UserEntityInterface|int     $userOrId   ID or entity representing user removing links
     * @param UserListEntityInterface|int $listOrId   ID or entity representing list to unlink (null for ALL
     * matching lists)
     *
     * @return void
     */
    public function unlinkFavorites(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null
    ): void {
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $dql = 'DELETE FROM ' . UserResourceEntityInterface::class . ' ur ';
        $dqlWhere = ['ur.user = :user '];
        $parameters = compact('user');
        if (null !== $resourceId) {
            $dqlWhere[] = ' ur.resource IN (:resource_id) ';
            $parameters['resource_id'] = (array)$resourceId;
        }
        if (null !== $listOrId) {
            $dqlWhere[] = ' ur.list = :list ';
            $parameters['list'] = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Create a UserResource entity object.
     *
     * @return UserResourceEntityInterface
     */
    public function createEntity(): UserResourceEntityInterface
    {
        return $this->entityPluginManager->get(UserResourceEntityInterface::class);
    }

    /**
     * Change all matching rows to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int $old Original resource ID
     * @param int $new New resource ID
     *
     * @return void
     */
    public function changeResourceId(int $old, int $new): void
    {
        $dql = 'UPDATE ' . UserResourceEntityInterface::class . ' e '
            . 'SET e.resource = :new WHERE e.resource = :old';
        $parameters = compact('new', 'old');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate(): void
    {
        $repo = $this->entityManager->getRepository(UserResourceEntityInterface::class);
        foreach ($this->getDuplicates() as $dupe) {
            // Do this as a transaction to prevent odd behavior:
            $this->beginTransaction();

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
                $userResource = $this->getDoctrineReference(UserResourceEntityInterface::class, $dupe['id']);
                $userResource->setNotes(implode(' ', $notes));
                $this->entityManager->flush();

                // Now delete extra rows...
                // match on all relevant IDs in duplicate group
                // getDuplicates returns the minimum id in the set, so we want to
                // delete all of the duplicates with a higher id value.
                $dql = 'DELETE FROM ' . UserResourceEntityInterface::class . ' ur '
                    . 'WHERE ur.resource = :resource AND ur.list = :list '
                    . 'AND ur.user = :user AND ur.id > :id';
                $mainCriteria['id'] = $dupe['id'];
                $query = $this->entityManager->createQuery($dql);
                $query->setParameters($mainCriteria);
                $query->execute();
                // Done -- commit the transaction:
                $this->commitTransaction();
            } catch (\Exception $e) {
                // If something went wrong, roll back the transaction and rethrow the error:
                $this->rollBackTransaction();
                throw $e;
            }
        }
    }
}
