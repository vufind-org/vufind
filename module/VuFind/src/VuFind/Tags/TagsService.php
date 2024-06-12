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

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceTagsServiceInterface;
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
class TagsService implements DbServiceAwareInterface, DbTableAwareInterface
{
    use DbServiceAwareTrait;
    use DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param ResourcePopulator $resourcePopulator Resource populator service
     * @param int               $maxLength         Maximum tag length
     * @param bool              $caseSensitive     Are tags case sensitive?
     */
    public function __construct(
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
            $tags = $this->getDbTable('Tags');
            $tag = $tags->getByText($trimmedTagText);

            $this->getDbService(ResourceTagsServiceInterface::class)->createLink(
                $resourceOrId,
                $tag,
                $userOrId,
                $listOrId
            );
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
            $tags = $this->getDbTable('Tags');
            $tagIds = [];
            foreach ($tags->getByText($trimmedTagText, false, false) as $tag) {
                $tagIds[] = $tag->getId();
            }
            if ($tagIds) {
                $this->getDbService(ResourceTagsServiceInterface::class)->destroyResourceTagsLinksForUser(
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
        $this->getDbTable('Tags')->fixDuplicateTags($this->hasCaseSensitiveTags());
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
}
