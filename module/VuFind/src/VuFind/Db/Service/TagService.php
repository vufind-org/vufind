<?php

/**
 * Database service for tags.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceTags;
use VuFind\Db\Entity\Tags;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserList;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class TagService extends AbstractService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Are tags case sensitive?
     *
     * @var bool
     */
    protected $caseSensitive;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param bool                $caseSensitive       Are tags case sensitive?
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        bool $caseSensitive
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->caseSensitive = $caseSensitive;
    }

    /**
     * Create a resourceTags entity object.
     *
     * @return ResourceTags
     */
    public function createResourceTags(): ResourceTags
    {
        $class = $this->getEntityClass(ResourceTags::class);
        return new $class();
    }

    /**
     * Look up a row for the specified resource.
     *
     * @param int|Resource $resource ID of resource to link up
     * @param int|Tags     $tag      ID of tag to link up
     * @param int|User     $user     ID of user creating link (optional but recommended)
     * @param int|UserList $list     ID of list to link up (optional)
     * @param \DateTime    $posted   Posted date (optional -- omit for current)
     *
     * @return void
     */
    public function createLink(
        $resource,
        $tag,
        $user = null,
        $list = null,
        $posted = null
    ) {
        $resource = is_object($resource) ? $resource : $this->entityManager->getReference(Resource::class, $resource);
        $tag = is_object($tag) ? $tag : $this->entityManager->getReference(Tags::class, $tag);

        $dql = ' DELETE rt FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt ';

        $dqlWhere = [];
        $dqlWhere[] = 'rt.resource = :resource ';
        $dqlWhere[] = 'rt.tag = :tag ';
        $parameters = compact('resource', 'tag');

        if (null !== $list) {
            $list = is_object($list) ? $list : $this->entityManager->getReference(UserList::class, $list);
            $dql .= 'rt.list = :list ';
            $parameters['list'] = $list;
        } else {
            $dql .= 'rt.list IS NULL ';
        }

        if (null !== $user) {
            $user = is_object($user) ? $user : $this->entityManager->getReference(User::class, $user);
            $dql .= 'rt.user = :user';
            $parameters['user'] = $user;
        } else {
            $dql .= 'rt.user IS NULL ';
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = current($query->getResult());

        // Only create row if it does not already exist:
        if (empty($result)) {
            $row = $this->createResourceTags()
                ->setResource($resource)
                ->setTag($tag);
            if (null !== $list) {
                $row->setList($list);
            }
            if (null !== $user) {
                $row->setUser($user);
            }
            if (null !== $posted) {
                $row->setPosted($posted);
            }
            try {
                $this->persistEntity($row);
            } catch (\Exception $e) {
                $this->logError('Could not save resource tag: ' . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Check whether or not the specified tags are present in the table.
     *
     * @param array $ids IDs to check.
     *
     * @return array     Associative array with two keys: present and missing
     */
    public function checkForTags($ids)
    {
        // Set up return arrays:
        $retVal = ['present' => [], 'missing' => []];

        // Look up IDs in the table:
        $dql = 'SELECT IDENTITY(rt.tag) FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'WHERE rt.tag IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $results = $query->getSingleColumnResult();

        // Record all IDs that are present:
        $retVal['present'] = array_unique($results);

        // Detect missing IDs:
        $retVal['missing'] = array_diff($ids, $retVal['present']);

        // Send back the results:
        return $retVal;
    }

    /**
     * Get resources associated with a particular tag.
     *
     * @param string $tag    Tag to match
     * @param string $userId ID of user owning favorite list
     * @param string $listId ID of list to retrieve (null for all favorites)
     *
     * @return array
     */
    public function getResourcesForTag($tag, $user, $list = null)
    {
        $dql = 'SELECT DISTINCT(rt.resource) AS resource_id '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t '
            . 'WHERE ' . ($this->caseSensitive ? 't.tag = :tag' : 'LOWER(t.tag) = LOWER(:tag) ')
            . 'AND rt.user = :user ';

        $user = is_object($user) ? $user : $this->entityManager->getReference(User::class, $user);
        $parameters = compact('tag', 'user');
        if (null !== $list) {
            $list = is_object($list) ? $list : $this->entityManager->getReference(UserList::class, $list);
            $dql .= 'AND rt.list = :list';
            $parameters['list'] = $list;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result =  $query->getSingleColumnResult();
        return $result;
    }

    /**
     * Get lists associated with a particular tag.
     *
     * @param string|array      $tag        Tag to match
     * @param null|string|array $listId     List ID to retrieve (null for all)
     * @param bool              $publicOnly Whether to return only public lists
     * @param bool              $andTags    Use AND operator when filtering by tag.
     *
     * @return array
     */
    public function getListsForTag(
        $tag,
        $listId = null,
        $publicOnly = true,
        $andTags = true
    ) {
        $tag = (array)$tag;
        $listId = $listId ? (array)$listId : null;
        $dql = 'SELECT IDENTITY(rt.list) as list, COUNT(DISTINCT(rt.tag)) AS tagcnt '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t '
            . 'JOIN rt.list l '
            . 'WHERE rt.resource IS NULL ';
        $parameters = [];
        if (null !== $listId) {
            $dql .= 'AND rt.list IN (:listId) ';
            $parameters['listId'] = $listId;
        }
        if ($publicOnly) {
            $dql .= 'AND l.public = :public ';
            $parameters['public'] = 1;
        }
        if ($tag) {
            if ($this->caseSensitive) {
                $dql .= 'AND t.tag IN (:tag) ';
                $parameters['tag'] = $tag;
            } else {
                $dql .= 'AND LOWER(t.tag) IN (:tag) ';
                $parameters['tag'] = array_map('strtolower', $tag);
            }
        }
        $dql .= 'GROUP BY rt.list ';
        if ($tag && $andTags) {
            $dql .= 'HAVING tagcnt = :cnt ';
            $parameters['cnt'] = count(array_unique($tag));
        }
        $dql .= 'ORDER BY rt.list';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param string|array $resource ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string|User  $user     ID of user removing links
     * @param mixed        $list     ID of list to unlink (null for ALL matching
     *                               tags, 'none' for tags not in a list, true
     *                               for tags only found in a list)
     * @param string|array $tag      ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     */
    public function destroyResourceLinks($resource, $user, $list = null, $tag = null)
    {
        $dql = 'SELECT rt FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt ';

        $parameters = $dqlWhere = [];
        $dqlWhere[] = ' rt.user = :user ';
        $parameters['user'] = $user;
        if (null !== $resource) {
            $dqlWhere[] = 'rt.resource IN (:resource) ';
            $parameters['resource'] = (array)$resource;
        }
        if (null !== $list) {
            if (true === $list) {
                // special case -- if $list is set to boolean true, we
                // want to only delete tags that are associated with lists.
                $dqlWhere[] = 'rt.list IS NOT NULL ';
            } elseif ('none' === $list) {
                // special case -- if $list is set to the string "none", we
                // want to delete tags that are not associated with lists.
                $dqlWhere[] = 'rt.list IS NULL ';
            } else {
                $dqlWhere[] = 'rt.list = :list';
                $parameters['list'] = $list;
            }
        }
        if (null !== $tag) {
            $dqlWhere[] = 'rt.tag IN (:tag) ';
            $parameters['tag'] = (array)$tag;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        $this->processDestroyLinks($result);
    }

    /**
     * Unlink rows for the specified user list.
     *
     * @param int|UserList $list ID of list to unlink
     * @param int|User     $user ID of user removing links
     * @param string|array $tag  ID or array of IDs of tag(s) to unlink (null
     *                           for ALL matching tags)
     *
     * @return void
     */
    public function destroyListLinks($list, $user, $tag = null)
    {
        $dql = 'SELECT rt FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'WHERE rt.user = :user AND rt.resource IS NULL AND rt.list = :list ';
        $parameters = compact('user', 'list');
        if (null !== $tag) {
            $dqlWhere[] = 'AND rt.tag IN (:tag) ';
            $parameters['tag'] = (array)$tag;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        $this->processDestroyLinks($result);
    }

    /**
     * Process link rows marked to be destroyed.
     *
     * @param array $potentialOrphans List of resource tags being destroyed.
     *
     * @return void
     */
    protected function processDestroyLinks($potentialOrphans)
    {
        if (count($potentialOrphans) > 0) {
            $ids = [];
            // Now delete the unwanted rows:
            foreach ($potentialOrphans as $current) {
                $ids[] = $current->getTag()->getId();
                $this->entityManager->remove($current);
            }
            try {
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logError('Could not delete resourceTags: ' . $e->getMessage());
                throw $e;
            }

        // Check for orphans:
            $checkResults = $this->checkForTags(array_unique($ids));
            if (count($checkResults['missing']) > 0) {
                $this->deleteByIdArray($checkResults['missing']);
            }
        }
    }

    /**
     * Delete a group of entity objects.
     *
     * @param array $ids Tags to delete.
     *
     * @return void
     */
    public function deleteByIdArray($ids)
    {
        // Do nothing if we have no IDs to delete!
        if (empty($ids)) {
            return;
        }
        $dql = 'DELETE FROM ' . $this->getEntityClass(Tags::class) . ' t '
            . 'WHERE t.id in (:ids)';

        $parameters = compact('ids');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Assign anonymous tags to the specified user ID.
     *
     * @param int|User $id User ID to own anonymous tags.
     *
     * @return void
     */
    public function assignAnonymousTags($id)
    {
        $dql = 'UPDATE ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'SET rt.user = :id WHERE rt.user is NULL';
        $parameters = compact('id');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get a list of duplicate rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return array
     */
    public function getDuplicates()
    {
        $dql = 'SELECT MIN(rt.resource) as resource_id, MiN(rt.tag) as tag_id, MIN(rt.list) as list_id '
            . 'MIN(rt.user) as user_id, COUNT(rt.resource) as cnt, MIN(rt.id) as id '
            . 'FROM' . $this->getEntityClass(ResourceTags::class) . 'rt '
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
    public function deduplicate()
    {
        foreach ($this->getDuplicates() as $dupe) {
            $dql = 'DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
                . 'WHERE rt.resource = :resource_id AND rt.list = :list_id AND rt.tag = :tag_id '
                . 'AND rt.user = :user_id AND rt.id > :id';
            $query = $this->entityManager->createQuery($dql);
            $query->setParameters($dupe);
            $query->execute();
        }
    }

    /**
     * Update reosurce.
     *
     * @param int|Resource $newResource New resourceid.
     * @param int|User     $oldResource Old resourceid.
     *
     * @return void
     */
    public function updateResource($newResource, $oldResource)
    {
        $dql = 'UPDATE ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'SET rt.resource = :newResource WHERE rt.resource = :oldResource';
        $parameters = compact('newResource', 'oldResource');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
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
        $dql = 'DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'WHERE rt.id IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $query->execute();
        return count($ids);
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public function getAnonymousCount(): int
    {
        $dql = 'SELECT COUNT(rt.id) AS total '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'WHERE rt.user IS NULL';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats['total'];
    }

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
        $legalSorts = ['tag', 'title', 'username'];
        $newOrder = [];
        foreach ($order as $next) {
            if (in_array($next, $legalSorts)) {
                $newOrder[] = $next . ' ASC';
            }
        }
        return $newOrder;
    }

    /**
     * Delete resource tags rows matching specified filter(s).
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return void
     */
    public function deleteResourceTags(
        $userId = null,
        $resourceId = null,
        $tagId = null
    ): void {
        $dql = ' DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt ';
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
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get Resource Tags
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     * @param string $order      The order in which to return the data
     * @param string $page       The page number to select
     * @param string $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTags(
        $userId = null,
        $resourceId = null,
        $tagId = null,
        $order = null,
        $page = null,
        $limit = 20
    ): Paginator {
        $tag = $this->caseSensitive ? 't.tag' : 'lower(t.tag)';
        $dql = 'SELECT rt.id, $tag AS tag, u.username AS username, r.title AS title,'
            . ' t.id AS tag_id, r.id AS resource_id, u.id AS user_id '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
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

        $paginator = new Paginator($query);
        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }

    /**
     * Gets unique tagged resources from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueResources(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $dql = 'SELECT r.id AS resource_id, MAX(rt.tag) AS tag_id, '
            . 'MAX(rt.list) AS list_id, MAX(rt.user) AS user_id, MAX(rt.id) AS id, '
            . 'r.title AS title '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
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
     * Gets unique tags from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueTags(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $tagClause = $this->caseSensitive ? 't.tag' : 'LOWER(t.tag)';
        $dql = 'SELECT MAX(r.id) AS resource_id, MAX(t.id) AS tag_id, '
            . 'MAX(l.id) AS list_id, MAX(u.id) AS user_id, MAX(rt.id) AS id, '
            . $tagClause . ' AS tag '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
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
            . ' ORDER BY tag';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Gets unique users from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueUsers(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $dql = 'SELECT MAX(rt.resource) AS resource_id, MAX(rt.tag) AS tag_id, '
            . 'MAX(rt.list) AS list_id, u.id AS user_id, MAX(rt.id) AS id, '
            . 'u.username AS username '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
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
     * Get statistics on use of tags.
     *
     * @param bool $extended Include extended (unique/anonymous) stats.
     *
     * @return array
     */
    public function getStatistics(bool $extended = false): array
    {
        $dql = 'SELECT COUNT(DISTINCT(rt.user)) AS users, '
            . 'COUNT(DISTINCT(rt.resource)) AS resources, '
            . 'COUNT(rt.id) AS total '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        if ($extended) {
            $stats['unique'] = count($this->getUniqueTags());
            $stats['anonymous'] = $this->getAnonymousCount();
        }
        return $stats;
    }
}
