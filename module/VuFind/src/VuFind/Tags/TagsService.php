<?php

/**
 * Service for handling tag processing.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @package  Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\Tags;

use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\Feature\TransactionInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function is_array;

/**
 * Service for handling tag processing.
 *
 * @category VuFind
 * @package  Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class TagsService implements DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param TagServiceInterface                               $tagDbService        Tag database service
     * @param ResourceTagsServiceInterface&TransactionInterface $resourceTagsService Resource/Tags database service
     * @param UserListServiceInterface                          $userListService     User list database service
     * @param ResourcePopulator                                 $resourcePopulator   Resource populator service
     * @param int                                               $maxLength           Maximum tag length
     * @param bool                                              $caseSensitive       Are tags case sensitive?
     */
    public function __construct(
        protected TagServiceInterface $tagDbService,
        protected ResourceTagsServiceInterface&TransactionInterface $resourceTagsService,
        protected UserListServiceInterface $userListService,
        protected ResourcePopulator $resourcePopulator,
        protected int $maxLength = 64,
        protected bool $caseSensitive = false
    ) {
    }

    /**
     * Parse a user-submitted tag string into an array of separate tags.
     *
     * @param string $tags User-provided tags
     *
     * @return array
     */
    public function parse($tags)
    {
        preg_match_all('/"[^"]*"|[^ ]+/', trim($tags), $words);
        $result = [];
        foreach ($words[0] as $tag) {
            // Wipe out double-quotes and trim over-long tags:
            $result[] = substr(str_replace('"', '', $tag), 0, $this->maxLength);
        }
        return array_unique($result);
    }

    /**
     * Add tags to the record.
     *
     * @param RecordDriver        $driver Driver representing record being tagged
     * @param UserEntityInterface $user   The user adding the tag(s)
     * @param string|string[]     $tags   The user-provided tag(s), either as a string (to parse) or an
     * array (already parsed)
     *
     * @return void
     */
    public function linkTagsToRecord(RecordDriver $driver, UserEntityInterface $user, string|array $tags): void
    {
        $parsedTags = is_array($tags) ? $tags : $this->parse($tags);
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
        foreach ($parsedTags as $tag) {
            $this->linkTagToResource($tag, $resource, $user);
        }
    }

    /**
     * Get a tag entity if it exists; create it otherwise.
     *
     * @param string $tag Text of tag to fetch/create
     *
     * @return TagsEntityInterface
     */
    public function getOrCreateTagByText(string $tag): TagsEntityInterface
    {
        if ($entity = $this->getTagByText($tag)) {
            return $entity;
        }
        $newEntity = $this->tagDbService->createEntity()
            ->setTag($this->caseSensitive ? $tag : mb_strtolower($tag, 'UTF8'));
        $this->tagDbService->persistEntity($newEntity);
        return $newEntity;
    }

    /**
     * Unlink a tag from a resource object.
     *
     * @param string                           $tagText      Text of tag to link (empty strings will be ignored)
     * @param ResourceEntityInterface|int      $resourceOrId Resource entity or ID to link
     * @param UserEntityInterface|int          $userOrId     Owner of tag link
     * @param null|UserListEntityInterface|int $listOrId     Optional list (omit to tag at resource level)
     *
     * @return void
     */
    public function linkTagToResource(
        string $tagText,
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null
    ): void {
        if (($trimmedTagText = trim($tagText)) !== '') {
            $this->resourceTagsService->beginTransaction();
            $this->resourceTagsService->createLink(
                $resourceOrId,
                $this->getOrCreateTagByText($trimmedTagText),
                $userOrId,
                $listOrId
            );
            $this->resourceTagsService->commitTransaction();
        }
    }

    /**
     * Unlink a tag from a resource object.
     *
     * @param string                           $tagText      Text of tag to unlink
     * @param ResourceEntityInterface|int      $resourceOrId Resource entity or ID to unlink
     * @param UserEntityInterface|int          $userOrId     Owner of tag to unlink
     * @param null|UserListEntityInterface|int $listOrId     Optional filter (only unlink from this list if provided)
     *
     * @return void
     */
    public function unlinkTagFromResource(
        string $tagText,
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null
    ) {
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        if (($trimmedTagText = trim($tagText)) !== '') {
            $tagIds = [];
            foreach ($this->getTagsByText($trimmedTagText) as $tag) {
                $tagIds[] = $tag->getId();
            }
            if ($tagIds) {
                $this->resourceTagsService->destroyResourceTagsLinksForUser(
                    $resourceOrId instanceof ResourceEntityInterface ? $resourceOrId->getId() : $resourceOrId,
                    $userOrId,
                    $listId,
                    $tagIds
                );
            }
        }
    }

    /**
     * Remove tags from the record.
     *
     * @param RecordDriver        $driver Driver representing record being tagged
     * @param UserEntityInterface $user   The user deleting the tag(s)
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function unlinkTagsFromRecord(RecordDriver $driver, UserEntityInterface $user, array $tags): void
    {
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
        foreach ($tags as $tag) {
            $this->unlinkTagFromResource($tag, $resource, $user);
        }
    }

    /**
     * Repair duplicate tags in the database (if any).
     *
     * @return void
     */
    public function fixDuplicateTags(): void
    {
        $this->getDbTable('Tags')->fixDuplicateTags($this->caseSensitive);
    }

    /**
     * Are tags case-sensitive?
     *
     * @return bool
     */
    public function hasCaseSensitiveTags(): bool
    {
        return $this->caseSensitive;
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
        return $this->tagDbService->getStatistics($extended, $this->caseSensitive);
    }

    /**
     * Get the tags that match a string
     *
     * @param string $text  Tag to look up.
     * @param string $sort  Sort type
     * @param int    $limit Maximum results to retrieve
     *
     * @return array
     */
    public function getNonListTagsFuzzilyMatchingString(
        string $text,
        string $sort = 'alphabetical',
        int $limit = 100
    ): array {
        return $this->tagDbService->getNonListTagsFuzzilyMatchingString($text, $sort, $limit, $this->caseSensitive);
    }

    /**
     * Get all matching tags by text. Normally, 0 or 1 results will be retrieved, but more
     * may be retrieved under exceptional circumstances (e.g. if retrieving case-insensitively
     * after storing data case-sensitively).
     *
     * @param string $text Tag text to match
     *
     * @return TagsEntityInterface[]
     */
    public function getTagsByText(string $text): array
    {
        return $this->tagDbService->getTagsByText($text, $this->caseSensitive);
    }

    /**
     * Get the first available matching tag by text; return null if no match is found.
     *
     * @param string $text Tag text to match
     *
     * @return TagsEntityInterface[]
     */
    public function getTagByText(string $text): ?TagsEntityInterface
    {
        return $this->tagDbService->getTagByText($text, $this->caseSensitive);
    }

    /**
     * Get all resources associated with the provided tag query.
     *
     * @param string $q      Search query
     * @param string $source Record source (optional limiter)
     * @param string $sort   Resource field to sort on (optional)
     * @param int    $offset Offset for results
     * @param ?int   $limit  Limit for results (null for none)
     * @param bool   $fuzzy  Are we doing an exact (false) or fuzzy (true) search?
     *
     * @return array
     */
    public function getResourcesMatchingTagQuery(
        string $q,
        string $source = null,
        string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $fuzzy = true
    ): array {
        return $this->tagDbService
            ->getResourcesMatchingTagQuery($q, $source, $sort, $offset, $limit, $fuzzy, $this->caseSensitive);
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
        return $this->tagDbService->getTagBrowseList($sort, $limit, $this->caseSensitive);
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
        return $this->tagDbService
            ->getRecordTags($id, $source, $limit, $listOrId, $userOrId, $sort, $ownerOrId, $this->caseSensitive);
    }

    /**
     * Get all tags from favorite lists associated with the specified record (and matching provided filters).
     *
     * @param string                           $id        Record ID to look up
     * @param string                           $source    Source of record to look up
     * @param int                              $limit     Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|null $listOrId  ID of list to load tags from (null for tags that
     *                                                    are associated with ANY list, but excluding
     *                                                    non-list tags)
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
        UserListEntityInterface|int|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null
    ) {
        return $this->tagDbService->getRecordTagsFromFavorites(
            $id,
            $source,
            $limit,
            $listOrId,
            $userOrId,
            $sort,
            $ownerOrId,
            $this->caseSensitive
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
     * @param UserEntityInterface|int|null $ownerOrId ID of user to check for ownership
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
        return $this->tagDbService->getRecordTagsNotInFavorites(
            $id,
            $source,
            $limit,
            $userOrId,
            $sort,
            $ownerOrId,
            $this->caseSensitive
        );
    }

    /**
     * Get a list of duplicate tags (this should never happen, but past bugs and the introduction of case-insensitive
     * tags have introduced problems).
     *
     * @return array
     */
    public function getDuplicateTags(): array
    {
        return $this->tagDbService->getDuplicateTags($this->caseSensitive);
    }

    /**
     * Get a list of all tags generated by the user in favorites lists. Note that the returned list WILL NOT include
     * tags attached to records that are not saved in favorites lists. Returns an array of arrays with id and tag keys.
     *
     * @param UserEntityInterface|int          $userOrId User ID to look up.
     * @param UserListEntityInterface|int|null $listOrId Filter for tags tied to a specific list (null for no filter).
     * @param ?string                          $recordId Filter for tags tied to a specific resource (null for no
     * filter).
     * @param ?string                          $source   Filter for tags tied to a specific record source (null
     * for no filter).
     *
     * @return array
     */
    public function getUserTagsFromFavorites(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        ?string $recordId = null,
        ?string $source = null
    ): array {
        return $this->tagDbService
            ->getUserTagsFromFavorites($userOrId, $listOrId, $recordId, $source, $this->caseSensitive);
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
        UserEntityInterface|int|null $userOrId = null,
    ): array {
        return $this->tagDbService->getListTags($listOrId, $userOrId, $this->caseSensitive);
    }

    /**
     * Gets unique tags from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        return $this->resourceTagsService->getUniqueTags($userId, $resourceId, $tagId, $this->caseSensitive);
    }

    /**
     * Get Resource Tags Paginator
     *
     * @param ?int    $userId     ID of user (null for any)
     * @param ?int    $resourceId ID of the resource (null for any)
     * @param ?int    $tagId      ID of the tag (null for any)
     * @param ?string $order      The order in which to return the data
     * @param ?int    $page       The page number to select
     * @param int     $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?int $page = null,
        int $limit = 20
    ): Paginator {
        return $this->resourceTagsService
            ->getResourceTagsPaginator($userId, $resourceId, $tagId, $order, $page, $limit, $this->caseSensitive);
    }

    /**
     * Get lists associated with a particular tag and/or list of IDs. If IDs and
     * tags are both provided, only the intersection of matches will be returned.
     *
     * @param string|string[]|null $tag        Tag or tags to match (by text, not ID; null for all)
     * @param int|int[]|null       $listId     List ID or IDs to match (null for all)
     * @param bool                 $publicOnly Whether to return only public lists
     * @param bool                 $andTags    Use AND operator when filtering by tag.
     *
     * @return UserListEntityInterface[]
     */
    public function getUserListsByTagAndId(
        string|array|null $tag = null,
        int|array|null $listId = null,
        bool $publicOnly = true,
        bool $andTags = true
    ): array {
        return $this->userListService
            ->getUserListsByTagAndId($tag, $listId, $publicOnly, $andTags, $this->caseSensitive);
    }

    /**
     * Delete orphaned tags (those not present in resource_tags) from the tags table.
     *
     * @return void
     */
    public function deleteOrphanedTags(): void
    {
        $this->tagDbService->deleteOrphanedTags();
    }
}
