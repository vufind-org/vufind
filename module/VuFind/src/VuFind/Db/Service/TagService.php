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

use Laminas\Db\Sql\Select;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

/**
 * Database service for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class TagService extends AbstractDbService implements TagServiceInterface, \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

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
        return $this->getDbTable('ResourceTags')->getStatistics($extended, $caseSensitiveTags);
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
        return $this->getDbTable('Tags')->matchText($text, $sort, $limit, $caseSensitive);
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
        $callback = function ($select) use ($text, $caseSensitive) {
            if ($caseSensitive) {
                $select->where->equalTo('tag', $text);
            } else {
                $select->where->literal('lower(tag) = lower(?)', [$text]);
            }
        };
        return iterator_to_array($this->getDbTable('Tags')->select($callback));
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
     * Get all resources associated with the provided tag query.
     *
     * @param string $q             Search query
     * @param string $source        Record source (optional limiter)
     * @param string $sort          Resource field to sort on (optional)
     * @param int    $offset        Offset for results
     * @param ?int   $limit         Limit for results (null for none)
     * @param bool   $fuzzy         Are we doing an exact (false) or fuzzy (true) search?
     * @param ?bool  $caseSensitive Should search be case sensitive? (Ignored when fuzzy = true)
     *
     * @return array
     */
    public function getResourcesMatchingTagQuery(
        string $q,
        string $source = null,
        string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $fuzzy = true,
        bool $caseSensitive = false
    ): array {
        return iterator_to_array(
            $this->getDbTable('Tags')->resourceSearch(
                $q,
                $source,
                $sort,
                $offset,
                $limit,
                $fuzzy,
                $caseSensitive
            )
        );
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
        $callback = function ($select) {
            // Discard user list tags
            $select->where->isNotNull('resource_tags.resource_id');
        };
        return $this->getDbTable('Tags')->getTagList($sort, $limit, $callback, $caseSensitive);
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
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $userToCheck = $ownerOrId instanceof UserEntityInterface ? $ownerOrId->getId() : $ownerOrId;
        return $this->getDbTable('Tags')
            ->getForResource($id, $source, $limit, $listId, $userId, $sort, $userToCheck, $caseSensitive)
            ->toArray();
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
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $userToCheck = $ownerOrId instanceof UserEntityInterface ? $ownerOrId->getId() : $ownerOrId;
        return $this->getDbTable('Tags')
            ->getForResource($id, $source, $limit, $listId ?? true, $userId, $sort, $userToCheck, $caseSensitive)
            ->toArray();
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
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $userToCheck = $ownerOrId instanceof UserEntityInterface ? $ownerOrId->getId() : $ownerOrId;
        return $this->getDbTable('Tags')
            ->getForResource($id, $source, $limit, false, $userId, $sort, $userToCheck, $caseSensitive)
            ->toArray();
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
        return $this->getDbTable('Tags')->getDuplicates($caseSensitive)->toArray();
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
        return $this->getDbTable('Tags')->getListTagsForUser($userId, $recordId, $listId, $source, $caseSensitive)
            ->toArray();
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
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        return $this->getDbTable('Tags')->getForList($listId, $userId, $caseSensitive)->toArray();
    }

    /**
     * Delete orphaned tags (those not present in resource_tags) from the tags table.
     *
     * @return void
     */
    public function deleteOrphanedTags(): void
    {
        $callback = function ($select) {
            $subQuery = $this->getDbTable('ResourceTags')
                ->getSql()
                ->select()
                ->quantifier(Select::QUANTIFIER_DISTINCT)
                ->columns(['tag_id']);
            $select->where->notIn('id', $subQuery);
        };
        $this->getDbTable('Tags')->delete($callback);
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
        return $this->getDbTable('Tags')->select(['id' => $id])->current();
    }

    /**
     * Create a new Tag entity.
     *
     * @return TagsEntityInterface
     */
    public function createEntity(): TagsEntityInterface
    {
        return $this->getDbTable('Tags')->createRow();
    }
}
