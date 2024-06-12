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
use Doctrine\ORM\Query\ResultSetMapping;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceTags;
use VuFind\Db\Entity\Tags;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResource;
use VuFind\Log\LoggerAwareTrait;

use function count;

/**
 * Database service for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class TagService extends AbstractDbService implements TagServiceInterface, DbServiceAwareInterface, LoggerAwareInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

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
        protected bool $caseSensitive
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->caseSensitive = $caseSensitive;
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
     * @param string $tag  Tag to match
     * @param string $user ID of user owning favorite list
     * @param string $list ID of list to retrieve (null for all favorites)
     *
     * @return array
     */
    public function getResourceIDsForTag($tag, $user, $list = null)
    {
        $dql = 'SELECT DISTINCT(rt.resource) AS resource_id '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t '
            . 'WHERE ' . ($this->caseSensitive ? 't.tag = :tag' : 'LOWER(t.tag) = LOWER(:tag) ')
            . 'AND rt.user = :user ';

        $user = $this->getDoctrineReference(User::class, $user);
        $parameters = compact('tag', 'user');
        if (null !== $list) {
            $list = $this->getDoctrineReference(UserList::class, $list);
            $dql .= 'AND rt.list = :list';
            $parameters['list'] = $list;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result =  $query->getSingleColumnResult();
        return $result;
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
     * Get a list of duplicate resource_tags rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return array
     */
    public function getDuplicateResourceLinks()
    {
        $dql = 'SELECT MIN(rt.resource) as resource_id, MiN(rt.tag) as tag_id, MIN(rt.list) as list_id, '
            . 'MIN(rt.user) as user_id, COUNT(rt.resource) as cnt, MIN(rt.id) as id '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'GROUP BY rt.resource, rt.tag, rt.list, rt.user '
            . 'HAVING COUNT(rt.resource) > 1';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Deduplicate resource_tags rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicateResourceLinks()
    {
        // match on all relevant IDs in duplicate group
        // getDuplicates returns the minimum id in the set, so we want to
        // delete all of the duplicates with a higher id value.
        foreach ($this->getDuplicateResourceLinks() as $dupe) {
            $dql = 'DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
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
        $resourceTagsService = $this->getDbService(ResourceTagsServiceInterface::class);
        if ($extended) {
            $stats['unique'] = count($resourceTagsService->getUniqueTags());
            $stats['anonymous'] = $resourceTagsService->getAnonymousCount();
        }
        return $stats;
    }

    /**
     * Create a Tags entity object.
     *
     * @return Tags
     */
    public function createTags(): Tags
    {
        $class = $this->getEntityClass(Tags::class);
        return new $class();
    }

    /**
     * Get the row associated with a specific tag string.
     *
     * @param string $tag       Tag to look up.
     * @param bool   $create    Should we create the row if it does not exist?
     * @param bool   $firstOnly Should we return the first matching row (true)
     * or the entire result set (in case of multiple matches)?
     *
     * @return mixed Matching row/result set if found or created, null otherwise.
     */
    public function getByText($tag, $create = true, $firstOnly = true)
    {
        $dql = 'SELECT t '
            . 'FROM ' . $this->getEntityClass(Tags::class) . ' t '
            . 'WHERE ' . ($this->caseSensitive ? 't.tag = :tag' : 'LOWER(t.tag) = LOWER(:tag) ');
        $parameters = compact('tag');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result =  $query->getResult();
        if (count($result) == 0 && $create) {
            $row = $this->createTags()
                ->setTag($this->caseSensitive ? $tag : mb_strtolower($tag, 'UTF8'));
            try {
                $this->persistEntity($row);
            } catch (\Exception $e) {
                $this->logError('Could not save tag: ' . $e->getMessage());
                return false;
            }
            return $firstOnly ? $row : [$row];
        }
        return $firstOnly ? current($result) : $result;
    }

    /**
     * Get the tags that match a string
     *
     * @param string $text Tag to look up.
     *
     * @return array
     */
    public function matchText(string $text): array
    {
        $where = ['LOWER(t.tag) LIKE LOWER(:text)', 'rt.resource is NOT NULL '];
        $parameters = ['text' => $text . '%'];
        return $this->getTagListWithDoctrine(where: $where, parameters: $parameters);
    }

    /**
     * Get a list of tags based on a sort method ($sort) and a where clause.
     *
     * @param string $sort       Sort/search parameter
     * @param int    $limit      Maximum number of tags (default = 100,
     *                           < 1 = no limit)
     * @param array  $where      Array of where clauses
     * @param array  $parameters Array of query parameters
     *
     * @return array Tag details.
     */
    protected function getTagListWithDoctrine($sort = 'alphabetical', $limit = 100, $where = [], $parameters = [])
    {
        $tagClause = $this->caseSensitive ? 't.tag' : 'LOWER(t.tag)';
        $dql = 'SELECT t.id as id, COUNT(DISTINCT(rt.resource)) as cnt, MAX(rt.posted) as posted, '
            . $tagClause . ' AS tag '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t ';
        if (!empty($where)) {
            $dql .= ' WHERE ' . implode(' AND ', $where) . ' ';
        }

        $dql .= 'GROUP BY t.id, t.tag ';
        $dql .= match ($sort) {
            'alphabetical' => 'ORDER BY lower(t.tag), cnt DESC ',
            'popularity' => 'ORDER BY cnt DESC, lower(t.tag) ',
            'recent' => 'ORDER BY posted DESC, cnt DESC, lower(t.tag) ',
            default => '',
        };

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setMaxResults($limit);
        $results = $query->getResult();

        $tagList = [];
        foreach ($results as $result) {
            $tagList[] = [
                'tag' => $result['tag'],
                'cnt' => $result['cnt'],
            ];
        }
        return $tagList;
    }

    /**
     * Get all resources associated with the provided tag query.
     *
     * @param string $q      Search query
     * @param string $source Record source (optional limiter)
     * @param string $sort   Resource field to sort on (optional)
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     * @param bool   $fuzzy  Are we doing an exact or fuzzy search?
     *
     * @return array
     */
    public function resourceSearch(
        $q,
        $source = null,
        $sort = null,
        $offset = 0,
        $limit = null,
        $fuzzy = true
    ) {
        $orderByDetails = empty($sort) ? [] : ResourceService::getOrderByClause($sort);
        $dql = 'SELECT DISTINCT(r.id) AS resource, r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . $this->getEntityClass(Tags::class) . ' t '
            . 'JOIN ' . $this->getEntityClass(ResourceTags::class) . ' rt WITH t.id = rt.tag '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = rt.resource '
            . 'WHERE rt.resource IS NOT NULL ';
        $parameters = compact('q');
        if ($fuzzy) {
            $dql .= 'AND LOWER(t.tag) LIKE LOWER(:q) ';
        } elseif (!$this->caseSensitive) {
            $dql .= 'AND LOWER(t.tag) = LOWER(:q) ';
        } else {
            $dql .= 'AND t.tag = :q ';
        }

        if (!empty($source)) {
            $dql .= 'AND r.source = :source';
            $parameters['source'] = $source;
        }

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
        $results = $query->getResult();
        return $results;
    }

    /**
     * Support method for other getRecordTags*() methods to consolidate shared logic.
     *
     * @param string                       $id                Record ID to look up
     * @param string                       $source            Source of record to look up
     * @param int                          $limit             Max. number of tags to return (0 = no limit)
     * @param UserEntityInterface|int|null $userOrId          ID of user to load tags from (null for all users)
     * @param string                       $sort              Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null $ownerOrId         ID of user to check for ownership
     * @param array                        $extraWhereClauses Extra where clauses to apply to query
     * @param array                        $extraParameters   Extra parameters to provide with query
     *
     * @return array
     */
    protected function getRecordTagsWithDoctrine(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null,
        array $extraWhereClauses = [],
        array $extraParameters = []
    ): array {
        $parameters = compact('id', 'source') + $extraParameters;
        $tag = $this->caseSensitive ? 't.tag' : 'lower(t.tag)';
        $fieldList = 't.id AS id, COUNT(DISTINCT(rt.user)) AS cnt, ' . $tag . ' AS tag';
        // If we're looking for ownership, adjust query to include an "is_me" flag value indicating
        // if the selected resource is tagged by the specified user.
        if (!empty($ownerOrId)) {
            $fieldList .= ', MAX(CASE WHEN rt.user = :userToCheck THEN 1 ELSE 0 END) AS is_me';
            $parameters['userToCheck'] = $this->getDoctrineReference(User::class, $ownerOrId);
        }
        $dql = 'SELECT ' . $fieldList . ' FROM ' . $this->getEntityClass(Tags::class) . ' t '
            . 'JOIN ' . $this->getEntityClass(ResourceTags::class) . ' rt WITH t.id = rt.tag '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = rt.resource '
            . 'WHERE r.recordId = :id AND r.source = :source ';

        foreach ($extraWhereClauses as $clause) {
            $dql .= "AND $clause ";
        }

        if (null !== $userOrId) {
            $dql .= 'AND rt.user = :user ';
            $parameters['user'] = $this->getDoctrineReference(User::class, $userOrId);
        }

        $dql .= 'GROUP BY t.id, t.tag ';
        if ($sort == 'count') {
            $dql .= 'ORDER BY cnt DESC, LOWER(t.tag) ';
        } elseif ($sort == 'tag') {
            $dql .= 'ORDER BY LOWER(t.tag) ';
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        if ($limit > 0) {
            $query->setMaxResults($limit);
        }
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get a list of tags for the browse interface.
     *
     * @param string $sort  Sort/search parameter
     * @param int    $limit Maximum number of tags (default = 100, < 1 = no limit)
     *
     * @return array
     */
    public function getTagBrowseList(string $sort, int $limit): array
    {
        // Extra where clause is to discard user list tags:
        return $this->getTagListWithDoctrine($sort, $limit, ['rt.resource is NOT NULL']);
    }

    /**
     * Get all tags associated with the specified record (and matching provided filters).
     *
     * @param string                           $id        Record ID to look up
     * @param string                           $source    Source of record to look up
     * @param int                              $limit     Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|null $listOrId  ID of list to load tags from (null for no restriction)
     * @param UserEntityInterface|int|null     $userOrId  ID of user to load tags from (null for all users)
     * @param string                           $sort      Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null     $ownerOrId ID of user to check for ownership
     *
     * @return array
     */
    public function getRecordTags(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserListEntityInterface|int|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null
    ): array {
        $extraClauses = $extraParams = [];
        if ($listOrId) {
            $extraClauses[] = 'rt.list = :list';
            $extraParams['list'] = $this->getDoctrineReference(UserList::class, $listOrId);
        }
        return $this->getRecordTagsWithDoctrine(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            $extraClauses,
            $extraParams
        );
    }

    /**
     * Get all tags from favorite lists associated with the specified record (and matching provided filters).
     *
     * @param string                           $id        Record ID to look up
     * @param string                           $source    Source of record to look up
     * @param int                              $limit     Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|null $listOrId  ID of list to load tags from (null for tags that
     * are associated with ANY list, but excluding non-list tags)
     * @param UserEntityInterface|int|null     $userOrId  ID of user to load tags from (null for all users)
     * @param string                           $sort      Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null     $ownerOrId ID of user to check for ownership
     * (this will not filter the result list, but rows owned by this user will have an is_me column set to 1)
     *
     * @return array
     */
    public function getRecordTagsFromFavorites(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserListEntityInterface|int|bool|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null
    ): array {
        $extraClauses = $extraParams = [];
        if ($listOrId) {
            $extraClauses[] = 'rt.list = :list';
            $extraParams['list'] = $this->getDoctrineReference(UserList::class, $listOrId);
        } else {
            $extraClauses[] = 'rt.list IS NOT NULL';
        }
        return $this->getRecordTagsWithDoctrine(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            $extraClauses,
            $extraParams
        );
    }

    /**
     * Get all tags outside of favorite lists associated with the specified record (and matching provided filters).
     *
     * @param string                       $id        Record ID to look up
     * @param string                       $source    Source of record to look up
     * @param int                          $limit     Max. number of tags to return (0 = no limit)
     * @param UserEntityInterface|int|null $userOrId  User entity/ID to load tags from (null for all users)
     * @param string                       $sort      Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null $ownerOrId Entity/ID representing user to check for ownership
     * (this will not filter the result list, but rows owned by this user will have an is_me column set to 1)
     *
     * @return array
     */
    public function getRecordTagsNotInFavorites(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null
    ): array {
        return $this->getRecordTagsWithDoctrine(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            ['rt.list IS NULL'],
        );
    }

    /**
     * Support method for fixDuplicateTag() -- merge $source into $target.
     *
     * @param TagsEntityInterface $target Target ID
     * @param TagsEntityInterface $source Source ID
     *
     * @return void
     */
    protected function mergeTags($target, $source)
    {
        // Don't merge a tag with itself!
        if ($target->getId() === $source->getId()) {
            return;
        }

        $result = $this->entityManager->getRepository($this->getEntityClass(ResourceTags::class))
            ->findBy(['tag' => $source]);

        foreach ($result as $current) {
            // Move the link to the target ID:
            $this->getDbService(ResourceTagsServiceInterface::class)->createLink(
                $current->getResource(),
                $target,
                $current->getUser(),
                $current->getUserList(),
                $current->getPosted()
            );

            // Remove the duplicate link:
            $this->entityManager->remove($current);
        }
        // Remove the source tag:
        $this->entityManager->remove($source);
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logError('Clean up operation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Support method for fixDuplicateTags()
     *
     * @param string $tag Tag to deduplicate.
     *
     * @return void
     */
    protected function fixDuplicateTag($tag)
    {
        // Make sure this really is a duplicate.
        $result = $this->getByText($tag, false, false);
        if (count($result) < 2) {
            return;
        }

        $first = current($result);
        foreach ($result as $current) {
            $this->mergeTags($first, $current);
        }
    }

    /**
     * Repair duplicate tags in the database (if any).
     *
     * @return void
     */
    public function fixDuplicateTags()
    {
        foreach ($this->getDuplicateTags() as $dupe) {
            $this->fixDuplicateTag($dupe['tag']);
        }
    }

    /**
     * Get a list of duplicate tags (this should never happen, but past bugs and the introduction of case-insensitive
     * tags have introduced problems).
     *
     * @return array
     */
    public function getDuplicateTags(): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('tag', 'tag');
        $rsm->addScalarResult('cnt', 'cnt');
        $rsm->addScalarResult('id', 'id');
        $sql = 'SELECT MIN(tag) AS tag, COUNT(tag) AS cnt, MIN(id) AS id '
            . 'FROM tags t '
            . 'GROUP BY ' . ($this->caseSensitive ? 't.tag ' : 'LOWER(t.tag) ')
            . 'HAVING COUNT(tag) > 1';
        $statement = $this->entityManager->createNativeQuery($sql, $rsm);
        $results = $statement->getResult();
        return $results;
    }

    /**
     * Get a list of all tags generated by the user in favorites lists. Note that the returned list WILL NOT include
     * tags attached to records that are not saved in favorites lists. Returns an array of arrays with id and tag keys.
     *
     * @param UserEntityInterface|int          $userOrId User ID to look up.
     * @param UserListEntityInterface|int|null $listOrId Filter for tags tied to a specific list (null for no
     * filter).
     * @param ?string                          $recordId Filter for tags tied to a specific resource (null for no
     * filter).
     * @param ?string                          $source   Filter for tags tied to a specific record source (null for
     * no filter).
     *
     * @return array
     */
    public function getUserTagsFromFavorites(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        ?string $recordId = null,
        ?string $source = null
    ): array {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $tag = $this->caseSensitive ? 't.tag' : 'lower(t.tag)';
        $dql = 'SELECT MIN(t.id) AS id, ' . $tag . ' AS tag, COUNT(DISTINCT(rt.resource)) AS cnt '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t '
            . 'JOIN rt.resource r '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur '
            . 'WITH r.id = ur.resource '
            . 'WHERE ur.user = :userId AND rt.user = :userId AND ur.list = rt.list ';
        $parameters = compact('userId');
        if (null !== $source) {
            $dql .= 'AND r.source = :source ';
            $parameters['source'] = $source;
        }
        if (null !== $recordId) {
            $dql .= 'AND r.recordId = :recordId ';
            $parameters['recordId'] = $recordId;
        }
        if (null !== $listId) {
            $dql .= 'AND rt.list = :listId ';
            $parameters['listId'] = $listId;
        }
        $dql .= 'GROUP BY t.tag ORDER BY LOWER(t.tag) ';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get tags assigned to a user list. Returns an array of arrays with id and tag keys.
     *
     * @param UserListEntityInterface|int  $listOrId List ID or entity
     * @param UserEntityInterface|int|null $userOrId User ID or entity to look up (null for no filter).
     *
     * @return array[]
     */
    public function getListTags(
        UserListEntityInterface|int $listOrId,
        UserEntityInterface|int|null $userOrId = null
    ): array {
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $user = $this->getDoctrineReference(User::class, $userOrId);
        $tag = $this->caseSensitive ? 't.tag' : 'lower(t.tag)';

        $dql = 'SELECT MIN(t.id) AS id, ' . $tag . ' AS tag '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'JOIN rt.tag t '
            . 'WHERE rt.list = :listId AND rt.resource IS NULL ';
        $parameters  = compact('listId');
        if ($user) {
            $dql .= 'AND rt.user = :userId ';
            $parameters['userId'] = $user;
        }

        $dql .= 'GROUP BY t.tag ORDER BY LOWER(t.tag) ';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Delete orphaned tags (those not present in resource_tags) from the tags table.
     *
     * @return void
     */
    public function deleteOrphanedTags(): void
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Tags::class) . ' t '
            . 'WHERE t NOT IN (SELECT IDENTITY(rt.tag) FROM '
            . $this->getEntityClass(ResourceTags::class) . ' rt)';
        $query = $this->entityManager->createQuery($dql);
        $query->execute();
    }

    /**
     * Retrieve a tag by ID.
     *
     * @param int $id Tag ID
     *
     * @return ?TagsEntityInterface
     */
    public function getTagById(int $id): ?TagsEntityInterface
    {
        return $this->entityManager->find($this->getEntityClass(Tags::class), $id);
    }
}
