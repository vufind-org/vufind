<?php

/**
 * VuFind tag processing logic
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * VuFind tag processing logic
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
     * @param ResourcePopulator $resourcePopulator Resource populator service
     * @param int               $maxLength         Maximum tag length
     */
    public function __construct(
        protected ResourcePopulator $resourcePopulator,
        protected int $maxLength = 64
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
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function addTagsToRecord(RecordDriver $driver, UserEntityInterface $user, array $tags): void
    {
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
        foreach ($tags as $tag) {
            $resource->addTag($tag, $user);
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
    public function deleteTagsFromRecord(RecordDriver $driver, UserEntityInterface $user, array $tags): void
    {
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
        foreach ($tags as $tag) {
            $resource->deleteTag($tag, $user);
        }
    }

    /**
     * Repair duplicate tags in the database (if any).
     *
     * @return void
     */
    public function fixDuplicateTags(): void
    {
        $this->getDbTable('Tags')->fixDuplicateTags();
    }
}
