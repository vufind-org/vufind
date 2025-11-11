<?php

/**
 * Database service for resource_tags.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrinePaginatorAdapter;
use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

use function count;
use function in_array;

/**
 * Database service for resource_tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceTagsService extends AbstractDbService implements
    DbServiceAwareInterface,
    ResourceTagsServiceInterface
{
    use DbServiceAwareTrait;

    /**
     * Given an array for sorting database results, make sure the tag field is
     * sorted in a case-insensitive fashion and that no illegal fields are
     * specified.
     *
     * @param array $order Order settings
     *
     * @return array
     */
    protected function formatTagOrder(array $order)
    {
        // This array defines legal sort fields:
        $legalSorts = ['tag', 'title', 'username', 'posted asc', 'posted desc'];
        $newOrder = [];
        foreach ($order as $next) {
            if (in_array($next, $legalSorts)) {
                $newOrder[] = 'posted asc' === $next || 'posted desc' === $next
                    ? $next
                    : $next . 'Sort ASC';
            }
        }
        return $newOrder;
    }

    /**
     * Get Resource Tags Paginator
     *
     * @param ?int    $userId            ID of user (null for any)
     * @param ?int    $resourceId        ID of the resource (null for any)
     * @param ?int    $tagId             ID of the tag (null for any)
     * @param ?string $order             The order in which to return the data
     * @param ?int    $page              The page number to select
     * @param int     $limit             The number of items to fetch
     * @param bool    $caseSensitiveTags Should we treat tags as case-sensitive?
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?int $page = null,
        int $limit = 20,
        bool $caseSensitiveTags = false
    ): Paginator {
        $tag = $caseSensitiveTags ? 't.tag' : 'lower(t.tag)';
        $dql = 'SELECT rt.id, ' . $tag . ' AS tag, rt.posted AS posted, u.username AS username, r.title AS title,'
            . ' t.id AS tag_id, r.id AS resource_id, r.source AS source, r.recordId AS record_id, u.id AS user_id,'
            . ' lower(t.tag) AS HIDDEN tagSort, lower(u.username) AS HIDDEN usernameSort,'
            . ' lower(r.title) AS HIDDEN titleSort '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'LEFT JOIN rt.resource r '
            . 'LEFT JOIN rt.tag t '
            . 'LEFT JOIN rt.user u';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = 'rt.user = :user';
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = 'r.id = :resource';
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = 'rt.tag = :tag';
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $sanitizedOrder = $this->formatTagOrder(
            (array)($order ?? ['username', 'tag', 'title'])
        );
        $dql .= ' ORDER BY ' . implode(', ', $sanitizedOrder);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if (null !== $page) {
            $query->setMaxResults($limit);
            $query->setFirstResult($limit * ($page - 1));
        }

        $doctrinePaginator = new DoctrinePaginator($query);
        $doctrinePaginator->setUseOutputWalkers(false);
        $paginator = new Paginator(new DoctrinePaginatorAdapter($doctrinePaginator));
        if (null !== $page) {
            $paginator->setCurrentPageNumber($page);
            $paginator->setItemCountPerPage($limit);
        }
        return $paginator;
    }

    /**
     * Create a ResourceTagsEntityInterface object.
     *
     * @return ResourceTagsEntityInterface
     */
    public function createEntity(): ResourceTagsEntityInterface
    {
        return $this->entityPluginManager->get(ResourceTagsEntityInterface::class);
    }

    /**
     * Create a resource_tags row linking the specified resources
     *
     * @param ResourceEntityInterface|int|null $resourceOrId Resource entity or ID to link up (optional)
     * @param TagsEntityInterface|int          $tagOrId      Tag entity or ID to link up
     * @param UserEntityInterface|int|null     $userOrId     User entity or ID creating link (optional but recommended)
     * @param UserListEntityInterface|int|null $listOrId     List entity or ID to link up (optional)
     * @param ?DateTime                        $posted       Posted date (optional -- omit for current)
     *
     * @return void
     */
    public function createLink(
        ResourceEntityInterface|int|null $resourceOrId,
        TagsEntityInterface|int $tagOrId,
        UserEntityInterface|int|null $userOrId = null,
        UserListEntityInterface|int|null $listOrId = null,
        ?DateTime $posted = null
    ) {
        $tag = $this->getDoctrineReference(TagsEntityInterface::class, $tagOrId);
        $dql = ' SELECT rt FROM ' . ResourceTagsEntityInterface::class . ' rt ';
        $dqlWhere = ['rt.tag = :tag '];
        $parameters = compact('tag');

        if (null !== $resourceOrId) {
            $resource = $this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId);
            $dqlWhere[] = 'rt.resource = :resource ';
            $parameters['resource'] = $resource;
        } else {
            $resource = null;
            $dqlWhere[] = 'rt.resource IS NULL ';
        }

        if (null !== $listOrId) {
            $list = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
            $dqlWhere[] = 'rt.list = :list ';
            $parameters['list'] = $list;
        } else {
            $list = null;
            $dqlWhere[] = 'rt.list IS NULL ';
        }

        if (null !== $userOrId) {
            $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
            $dqlWhere[] = 'rt.user = :user';
            $parameters['user'] = $user;
        } else {
            $user = null;
            $dqlWhere[] = 'rt.user IS NULL ';
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getOneOrNullResult();

        // Only create row if it does not already exist:
        if (empty($result)) {
            $row = $this->createEntity()
                ->setResource($resource)
                ->setTag($tag);
            if (null !== $list) {
                $row->setUserList($list);
            }
            if (null !== $user) {
                $row->setUser($user);
            }
            $row->setPosted($posted ?? new DateTime());
            $this->persistEntity($row);
        }
    }

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int
    {
        $dql = 'DELETE FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'WHERE rt.id IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $query->execute();
        return count($ids);
    }

    /**
     * Support method for the other destroyResourceTagsLinksForUser methods.
     *
     * @param int|int[]|null          $resourceId  ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param UserEntityInterface|int $userOrId    ID or entity representing user
     * @param int|int[]|null          $tagId       ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     * @param array                   $extraWhere  Extra where clauses for query
     * @param array                   $extraParams Extra parameters for query
     *
     * @return void
     */
    protected function destroyResourceTagsLinksForUserWithDoctrine(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null,
        $extraWhere = [],
        $extraParams = [],
    ) {
        $dql = 'DELETE FROM ' . ResourceTagsEntityInterface::class . ' rt ';

        $dqlWhere = ['rt.user = :user '];
        $parameters = ['user' => $this->getDoctrineReference(UserEntityInterface::class, $userOrId)];
        if (null !== $resourceId) {
            $dqlWhere[] = 'rt.resource IN (:resource) ';
            $parameters['resource'] = (array)$resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = 'rt.tag IN (:tag) ';
            $parameters['tag'] = (array)$tagId;
        }
        $dql .= ' WHERE ' . implode(' AND ', array_merge($dqlWhere, $extraWhere));
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters + $extraParams);
        $query->execute();
    }

    /**
     * Unlink tag rows for the specified resource and user.
     *
     * @param int|int[]|null                   $resourceId ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param UserEntityInterface|int          $userOrId   ID or entity representing user
     * @param UserListEntityInterface|int|null $listOrId   ID of list to unlink (null for ALL matching tags)
     * @param int|int[]|null                   $tagId      ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     */
    public function destroyResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        int|array|null $tagId = null
    ): void {
        $dqlWhere = $parameters = [];
        if (null !== $listOrId) {
            $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
            $dqlWhere[] = 'rt.list = :list';
            $parameters['list'] = $listId;
        }
        $this->destroyResourceTagsLinksForUserWithDoctrine($resourceId, $userOrId, $tagId, $dqlWhere, $parameters);
    }

    /**
     * Unlink tag rows that are not associated with a favorite list for the specified resource and user.
     *
     * @param int|int[]|null          $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL matching
     * resources)
     * @param UserEntityInterface|int $userOrId   ID or entity representing user
     * @param int|int[]|null          $tagId      ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyNonListResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $dqlWhere = ['rt.list IS NULL '];
        $this->destroyResourceTagsLinksForUserWithDoctrine($resourceId, $userOrId, $tagId, $dqlWhere);
    }

    /**
     * Unlink all tag rows associated with favorite lists for the specified resource and user. Tags added directly
     * to records outside of favorites will not be impacted.
     *
     * @param int|int[]|null          $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL matching
     * resources)
     * @param UserEntityInterface|int $userOrId   ID or entity representing user
     * @param int|int[]|null          $tagId      ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyAllListResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $dqlWhere = ['rt.list IS NOT NULL '];
        $this->destroyResourceTagsLinksForUserWithDoctrine($resourceId, $userOrId, $tagId, $dqlWhere);
    }

    /**
     * Unlink rows for the specified user list. This removes tags ON THE LIST ITSELF, not tags on
     * resources within the list.
     *
     * @param UserListEntityInterface|int $listOrId ID or entity representing list
     * @param UserEntityInterface|int     $userOrId ID or entity representing user
     * @param int|int[]|null              $tagId    ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyUserListLinks(
        UserListEntityInterface|int $listOrId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $list = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $dql = 'DELETE FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'WHERE rt.user = :user AND rt.resource IS NULL AND rt.list = :list';
        $parameters = compact('user', 'list');
        if (null !== $tagId) {
            $dql .= ' AND rt.tag IN (:tag) ';
            $parameters['tag'] = (array)$tagId;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Gets unique tagged resources from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueResources(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        $dql = 'SELECT r.id AS resource_id, MAX(rt.tag) AS tag_id, '
            . 'MAX(rt.list) AS list_id, MAX(rt.user) AS user_id, MAX(rt.id) AS id, '
            . 'r.title AS title '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'LEFT JOIN rt.resource r ';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = 'rt.user = :user';
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = 'r.id = :resource';
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = 'rt.tag = :tag';
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= ' GROUP BY resource_id, title'
            . ' ORDER BY title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Gets unique tags from the database.
     *
     * @param ?int $userId        ID of user (null for any)
     * @param ?int $resourceId    ID of the resource (null for any)
     * @param ?int $tagId         ID of the tag (null for any)
     * @param bool $caseSensitive Should we treat tags in a case-sensitive manner?
     *
     * @return array[]
     */
    public function getUniqueTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        bool $caseSensitive = false
    ): array {
        if ($caseSensitive) {
            $tagClause = 't.tag AS tag';
            $sort = 'LOWER(t.tag), tag';
        } else {
            $tagClause = 'LOWER(t.tag) AS tag, MAX(t.tag) AS HIDDEN tagTiebreaker';
            $sort = 'tag, tagTiebreaker';
        }
        $dql = 'SELECT MAX(r.id) AS resource_id, MAX(t.id) AS tag_id, '
            . 'MAX(l.id) AS list_id, MAX(u.id) AS user_id, MAX(rt.id) AS id, '
            . $tagClause
            . ' FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'LEFT JOIN rt.resource r '
            . 'LEFT JOIN rt.tag t '
            . 'LEFT JOIN rt.list l '
            . 'LEFT JOIN rt.user u';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = 'u.id = :user';
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = 'r.id = :resource';
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = 't.id = :tag';
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= ' GROUP BY tag'
            . ' ORDER BY ' . $sort;
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Gets unique users from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueUsers(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        $dql = 'SELECT MAX(rt.resource) AS resource_id, MAX(rt.tag) AS tag_id, '
            . 'MAX(rt.list) AS list_id, u.id AS user_id, MAX(rt.id) AS id, '
            . 'u.username AS username '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'INNER JOIN rt.user u ';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = 'rt.user = :user';
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = 'rt.resource = :resource';
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = 'rt.tag = :tag';
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= ' GROUP BY user_id, username'
            . ' ORDER BY username';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Delete resource tags rows matching specified filter(s). Return count of IDs deleted.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return int
     */
    public function deleteResourceTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): int {
        $deleted = 0;
        while (true) {
            $nextBatch = $this->getResourceTagsPaginator($userId, $resourceId, $tagId);
            if ($nextBatch->getTotalItemCount() < 1) {
                return $deleted;
            }
            $ids = [];
            foreach ($nextBatch as $row) {
                $ids[] = $row['id'];
            }
            $deleted += $this->deleteLinksByResourceTagsIdArray($ids);
        }
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public function getAnonymousCount(): int
    {
        $dql = 'SELECT COUNT(rt.id) AS total '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'WHERE rt.user IS NULL';
        $query = $this->entityManager->createQuery($dql);
        return $query->getSingleScalarResult();
    }

    /**
     * Assign anonymous tags to the specified user.
     *
     * @param UserEntityInterface|int $userOrId User entity or ID to own anonymous tags.
     *
     * @return void
     */
    public function assignAnonymousTags(UserEntityInterface|int $userOrId): void
    {
        $id = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $dql = 'UPDATE ' . ResourceTagsEntityInterface::class . ' rt '
            . 'SET rt.user = :id WHERE rt.user is NULL';
        $parameters = compact('id');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
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
        $dql = 'UPDATE ' . ResourceTagsEntityInterface::class . ' e '
            . 'SET e.resource = :new WHERE e.resource = :old';
        $parameters = compact('new', 'old');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get a list of duplicate resource_tags rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return array
     */
    protected function getDuplicateResourceLinks(): array
    {
        $dql = 'SELECT MIN(rt.resource) as resource_id, MiN(rt.tag) as tag_id, MIN(rt.list) as list_id, '
            . 'MIN(rt.user) as user_id, COUNT(rt.resource) as cnt, MIN(rt.id) as id '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'GROUP BY rt.resource, rt.tag, rt.list, rt.user '
            . 'HAVING COUNT(rt.resource) > 1';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate(): void
    {
        // match on all relevant IDs in duplicate group
        // getDuplicates returns the minimum id in the set, so we want to
        // delete all of the duplicates with a higher id value.
        foreach ($this->getDuplicateResourceLinks() as $dupe) {
            $dql = 'DELETE FROM ' . ResourceTagsEntityInterface::class . ' rt '
                . 'WHERE rt.resource = :resource AND rt.tag = :tag '
                . 'AND rt.user = :user AND rt.id > :id';
            $parameters = [
                'resource' => $dupe['resource_id'],
                'user' => $dupe['user_id'],
                'tag' => $dupe['tag_id'],
                'id' =>  $dupe['id'],
            ];
            // List ID might be null (for record-level tags); this requires special handling.
            if ($dupe['list_id'] !== null) {
                $parameters['list'] = $dupe['list_id'];
                $dql .= ' AND rt.list = :list ';
            } else {
                $dql .= ' AND rt.list IS NULL';
            }
            $query =  $this->entityManager->createQuery($dql);
            $query->setParameters($parameters);
            $query->execute();
        }
    }
}
