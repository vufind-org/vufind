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
     * @param bool $extended Include extended (unique/anonymous) stats.
     *
     * @return array
     */
    public function getStatistics(bool $extended = false): array
    {
        return $this->getDbTable('ResourceTags')->getStatistics($extended);
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
        return $this->getDbTable('Tags')->matchText($text);
    }

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id          Record ID to look up
     * @param string $source      Source of record to look up
     * @param int    $limit       Max. number of tags to return (0 = no limit)
     * @param ?int   $list        ID of list to load tags from (null for no
     * restriction,  true for on ANY list, false for on NO list)
     * @param ?int   $user        ID of user to load tags from (null for all users)
     * @param string $sort        Sort type ('count' or 'tag')
     * @param ?int   $userToCheck ID of user to check for ownership (this will
     * not filter the result list, but rows owned by this user will have an is_me
     * column set to 1)
     *
     * @return array
     */
    public function getForResource(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        ?int $list = null,
        ?int $user = null,
        string $sort = 'count',
        ?int $userToCheck = null
    ): array {
        return $this->getDbTable('Tags')
            ->getForResource($id, $source, $limit, $list, $user, $sort, $userToCheck)
            ->toArray();
    }

    /**
     * Add tags to the record.
     *
     * @param string              $id     Unique record ID
     * @param string              $source Record source
     * @param UserEntityInterface $user   The user adding the tag(s)
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function addTagsToRecord(string $id, string $source, UserEntityInterface $user, array $tags): void
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource($id, $source);
        foreach ($tags as $tag) {
            $resource->addTag($tag, $user);
        }
    }

    /**
     * Remove tags from the record.
     *
     * @param string              $id     Unique record ID
     * @param string              $source Record source
     * @param UserEntityInterface $user   The user deleting the tag(s)
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function deleteTagsFromRecord(string $id, string $source, UserEntityInterface $user, array $tags): void
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource($id, $source);
        foreach ($tags as $tag) {
            $resource->deleteTag($tag, $user);
        }
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
}
