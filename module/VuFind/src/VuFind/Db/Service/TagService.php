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

use Doctrine\ORM\Query\ResultSetMapping;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
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
    use Feature\ResourceSortTrait;
    use LoggerAwareTrait;

    /**
     * Get statistics on use of tags.
     *
     * @param bool $extended          Include extended (unique/anonymous) stats.
     * @param bool $caseSensitiveTags Should we treat tags case-sensitively?
     *
     * @return array
     */
    public function getStatistics(bool $extended = false, bool $caseSensitiveTags = false): array
    {
        $dql = 'SELECT COUNT(DISTINCT(rt.user)) AS users, '
            . 'COUNT(DISTINCT(rt.resource)) AS resources, '
            . 'COUNT(rt.id) AS total '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        $resourceTagsService = $this->getDbService(ResourceTagsServiceInterface::class);
        if ($extended) {
            $stats['unique'] = count($resourceTagsService->getUniqueTags(caseSensitive: $caseSensitiveTags));
            $stats['anonymous'] = $resourceTagsService->getAnonymousCount();
        }
        return $stats;
    }

    /**
     * Get the tags that match a string
     *
     * @param string $text          Tag to look up.
     * @param string $sort          Sort type
     * @param int    $limit         Maximum results to retrieve
     * @param bool   $caseSensitive Should tags be treated as case-sensitive?
     *
     * @return array
     */
    public function getNonListTagsFuzzilyMatchingString(
        string $text,
        string $sort = 'alphabetical',
        int $limit = 100,
        bool $caseSensitive = false
    ): array {
        $where = ['LOWER(t.tag) LIKE LOWER(:text)', 'rt.resource is NOT NULL '];
        $parameters = ['text' => $text . '%'];
        return $this->getTagListWithDoctrine($sort, $limit, $where, $parameters, $caseSensitive);
    }

    /**
     * Get all matching tags by text. Normally, 0 or 1 results will be retrieved, but more
     * may be retrieved under exceptional circumstances (e.g. if retrieving case-insensitively
     * after storing data case-sensitively).
     *
     * @param string $text          Tag text to match
     * @param bool   $caseSensitive Should tags be retrieved case-sensitively?
     *
     * @return TagsEntityInterface[]
     */
    public function getTagsByText(string $text, bool $caseSensitive = false): array
    {
        $dql = 'SELECT t FROM ' . TagsEntityInterface::class . ' t '
            . ($caseSensitive ? 'WHERE t.tag=:tag' : 'WHERE LOWER(t.tag) = LOWER(:tag)');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['tag' => $text]);
        return $query->getResult();
    }

    /**
     * Get the first available matching tag by text; return null if no match is found.
     *
     * @param string $text          Tag text to match
     * @param bool   $caseSensitive Should tags be retrieved case-sensitively?
     *
     * @return TagsEntityInterface[]
     */
    public function getTagByText(string $text, bool $caseSensitive = false): ?TagsEntityInterface
    {
        $tags = $this->getTagsByText($text, $caseSensitive);
        return $tags[0] ?? null;
    }

    /**
     * Get a list of tags based on a sort method ($sort) and a where clause.
     *
     * @param string $sort          Sort/search parameter
     * @param int    $limit         Maximum number of tags (default = 100, < 1 = no limit)
     * @param array  $where         Array of where clauses
     * @param array  $parameters    Array of query parameters
     * @param bool   $caseSensitive Should tags be retrieved case-sensitively?
     *
     * @return array Tag details.
     */
    protected function getTagListWithDoctrine(
        string $sort = 'alphabetical',
        int $limit = 100,
        array $where = [],
        array $parameters = [],
        bool $caseSensitive = false
    ) {
        $tagClause = $caseSensitive ? 't.tag' : 'LOWER(t.tag)';
        $dql = 'SELECT t.id as id, COUNT(DISTINCT(rt.resource)) as cnt, MAX(rt.posted) as posted, '
            . $tagClause . ' AS tag '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
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
     * @param string  $q             Search query
     * @param ?string $source        Record source (optional limiter)
     * @param ?string $sort          Resource field to sort on (optional)
     * @param int     $offset        Offset for results
     * @param ?int    $limit         Limit for results (null for none)
     * @param bool    $fuzzy         Are we doing an exact (false) or fuzzy (true) search?
     * @param ?bool   $caseSensitive Should search be case sensitive? (Ignored when fuzzy = true)
     *
     * @return array
     */
    public function getResourcesMatchingTagQuery(
        string $q,
        ?string $source = null,
        ?string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $fuzzy = true,
        bool $caseSensitive = false
    ): array {
        $orderByDetails = empty($sort) ? [] : $this->getResourceOrderByClause($sort);
        $dql = 'SELECT DISTINCT(r.id) AS resource, r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . TagsEntityInterface::class . ' t '
            . 'JOIN ' . ResourceTagsEntityInterface::class . ' rt WITH t.id = rt.tag '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = rt.resource '
            . 'WHERE rt.resource IS NOT NULL ';
        $parameters = compact('q');
        if ($fuzzy) {
            $dql .= 'AND LOWER(t.tag) LIKE LOWER(:q) ';
        } elseif (!$caseSensitive) {
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
     * @param bool                         $caseSensitive     Should tags be treated case-sensitively?
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
        array $extraParameters = [],
        bool $caseSensitive = false
    ): array {
        $parameters = compact('id', 'source') + $extraParameters;
        $tag = $caseSensitive ? 't.tag' : 'lower(t.tag)';
        $fieldList = 't.id AS id, COUNT(DISTINCT(rt.user)) AS cnt, ' . $tag . ' AS tag';
        // If we're looking for ownership, adjust query to include an "is_me" flag value indicating
        // if the selected resource is tagged by the specified user.
        if (!empty($ownerOrId)) {
            $fieldList .= ', MAX(CASE WHEN rt.user = :userToCheck THEN 1 ELSE 0 END) AS is_me';
            $parameters['userToCheck'] = $this->getDoctrineReference(UserEntityInterface::class, $ownerOrId);
        }
        $dql = 'SELECT ' . $fieldList . ' FROM ' . TagsEntityInterface::class . ' t '
            . 'JOIN ' . ResourceTagsEntityInterface::class . ' rt WITH t.id = rt.tag '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = rt.resource '
            . 'WHERE r.recordId = :id AND r.source = :source ';

        foreach ($extraWhereClauses as $clause) {
            $dql .= "AND $clause ";
        }

        if (null !== $userOrId) {
            $dql .= 'AND rt.user = :user ';
            $parameters['user'] = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
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
     * @param string $sort          Sort/search parameter
     * @param int    $limit         Maximum number of tags (default = 100, < 1 = no limit)
     * @param bool   $caseSensitive Treat tags as case-sensitive?
     *
     * @return array
     */
    public function getTagBrowseList(string $sort, int $limit, bool $caseSensitive = false): array
    {
        // Extra where clause is to discard user list tags:
        return $this
            ->getTagListWithDoctrine($sort, $limit, ['rt.resource is NOT NULL'], caseSensitive: $caseSensitive);
    }

    /**
     * Get all tags associated with the specified record (and matching provided filters).
     *
     * @param string                           $id            Record ID to look up
     * @param string                           $source        Source of record to look up
     * @param int                              $limit         Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|null $listOrId      ID of list to load tags from (null for no restriction)
     * @param UserEntityInterface|int|null     $userOrId      ID of user to load tags from (null for all users)
     * @param string                           $sort          Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null     $ownerOrId     ID of user to check for ownership
     * @param bool                             $caseSensitive Treat tags as case-sensitive?
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
        UserEntityInterface|int|null $ownerOrId = null,
        bool $caseSensitive = false
    ): array {
        $extraClauses = $extraParams = [];
        if ($listOrId) {
            $extraClauses[] = 'rt.list = :list';
            $extraParams['list'] = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
        }
        return $this->getRecordTagsWithDoctrine(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            $extraClauses,
            $extraParams,
            $caseSensitive
        );
    }

    /**
     * Get all tags from favorite lists associated with the specified record (and matching provided filters).
     *
     * @param string                           $id            Record ID to look up
     * @param string                           $source        Source of record to look up
     * @param int                              $limit         Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|null $listOrId      ID of list to load tags from (null for tags that
     * are associated with ANY list, but excluding non-list tags)
     * @param UserEntityInterface|int|null     $userOrId      ID of user to load tags from (null for all users)
     * @param string                           $sort          Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null     $ownerOrId     ID of user to check for ownership
     * (this will not filter the result list, but rows owned by this user will have an is_me column set to 1)
     * @param bool                             $caseSensitive Treat tags as case-sensitive?
     *
     * @return array
     */
    public function getRecordTagsFromFavorites(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserListEntityInterface|int|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null,
        bool $caseSensitive = false
    ): array {
        $extraClauses = $extraParams = [];
        if ($listOrId) {
            $extraClauses[] = 'rt.list = :list';
            $extraParams['list'] = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
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
            $extraParams,
            $caseSensitive
        );
    }

    /**
     * Get all tags outside of favorite lists associated with the specified record (and matching provided filters).
     *
     * @param string                       $id            Record ID to look up
     * @param string                       $source        Source of record to look up
     * @param int                          $limit         Max. number of tags to return (0 = no limit)
     * @param UserEntityInterface|int|null $userOrId      User entity/ID to load tags from (null for all users)
     * @param string                       $sort          Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null $ownerOrId     Entity/ID representing user to check for ownership
     * (this will not filter the result list, but rows owned by this user will have an is_me column set to 1)
     * @param bool                         $caseSensitive Treat tags as case-sensitive?
     *
     * @return array
     */
    public function getRecordTagsNotInFavorites(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null,
        bool $caseSensitive = false
    ): array {
        return $this->getRecordTagsWithDoctrine(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            ['rt.list IS NULL'],
            [],
            $caseSensitive
        );
    }

    /**
     * Merge source tag into target tag.
     *
     * @param TagsEntityInterface $target Target tag
     * @param TagsEntityInterface $source Source tag
     *
     * @return void
     */
    public function mergeTags(TagsEntityInterface $target, TagsEntityInterface $source): void
    {
        // Don't merge a tag with itself!
        if ($target->getId() === $source->getId()) {
            return;
        }

        $result = $this->entityManager->getRepository(ResourceTagsEntityInterface::class)
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
     * Get a list of duplicate tags (this should never happen, but past bugs and the introduction of case-insensitive
     * tags have introduced problems).
     *
     * @param bool $caseSensitive Treat tags as case-sensitive?
     *
     * @return array
     */
    public function getDuplicateTags(bool $caseSensitive = false): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('tag', 'tag');
        $rsm->addScalarResult('cnt', 'cnt');
        $rsm->addScalarResult('id', 'id');
        $sql = 'SELECT MIN(tag) AS tag, COUNT(tag) AS cnt, MIN(id) AS id '
            . 'FROM tags t '
            . 'GROUP BY ' . ($caseSensitive ? 't.tag ' : 'LOWER(t.tag) ')
            . 'HAVING COUNT(tag) > 1';
        $statement = $this->entityManager->createNativeQuery($sql, $rsm);
        $results = $statement->getResult();
        return $results;
    }

    /**
     * Get a list of all tags generated by the user in favorites lists. Note that the returned list WILL NOT include
     * tags attached to records that are not saved in favorites lists. Returns an array of arrays with id and tag keys.
     *
     * @param UserEntityInterface|int          $userOrId      User ID to look up.
     * @param UserListEntityInterface|int|null $listOrId      Filter for tags tied to a specific list (null for no
     * filter).
     * @param ?string                          $recordId      Filter for tags tied to a specific resource (null for no
     * filter).
     * @param ?string                          $source        Filter for tags tied to a specific record source (null
     * for no filter).
     * @param bool                             $caseSensitive Treat tags as case-sensitive?
     *
     * @return array
     */
    public function getUserTagsFromFavorites(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        ?string $recordId = null,
        ?string $source = null,
        bool $caseSensitive = false
    ): array {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $tag = $caseSensitive ? 't.tag' : 'lower(t.tag)';
        $dql = 'SELECT MIN(t.id) AS id, ' . $tag . ' AS tag, COUNT(DISTINCT(rt.resource)) AS cnt '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'JOIN rt.tag t '
            . 'JOIN rt.resource r '
            . 'JOIN ' . UserResourceEntityInterface::class . ' ur '
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
     * @param UserListEntityInterface|int  $listOrId      List ID or entity
     * @param UserEntityInterface|int|null $userOrId      User ID or entity to look up (null for no filter).
     * @param bool                         $caseSensitive Treat tags as case-sensitive?
     *
     * @return array[]
     */
    public function getListTags(
        UserListEntityInterface|int $listOrId,
        UserEntityInterface|int|null $userOrId = null,
        $caseSensitive = false
    ): array {
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $tag = $caseSensitive ? 't.tag' : 'lower(t.tag)';

        $dql = 'SELECT MIN(t.id) AS id, ' . $tag . ' AS tag '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
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
        $dql = 'DELETE FROM ' . TagsEntityInterface::class . ' t '
            . 'WHERE t NOT IN (SELECT IDENTITY(rt.tag) FROM '
            . ResourceTagsEntityInterface::class . ' rt)';
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
        return $this->entityManager->find(TagsEntityInterface::class, $id);
    }

    /**
     * Create a new Tag entity.
     *
     * @return TagsEntityInterface
     */
    public function createEntity(): TagsEntityInterface
    {
        return $this->entityPluginManager->get(TagsEntityInterface::class);
    }
}
