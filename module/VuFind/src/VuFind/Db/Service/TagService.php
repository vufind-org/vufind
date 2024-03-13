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

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Database service for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class TagService extends AbstractDbService implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Add tags to the record.
     *
     * @param RecordDriver        $driver The record being modified
     * @param UserEntityInterface $user   The user adding the tag(s)
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function addTagsToRecord(RecordDriver $driver, UserEntityInterface $user, array $tags): void
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier()
        );
        foreach ($tags as $tag) {
            $resource->addTag($tag, $user);
        }
    }

    /**
     * Remove tags from the record.
     *
     * @param RecordDriver        $driver The record being modified
     * @param UserEntityInterface $user   The user deleting the tag(s)
     * @param string[]            $tags   The user-provided tag(s)
     *
     * @return void
     */
    public function deleteTagsFromRecord(RecordDriver $driver, UserEntityInterface $user, array $tags): void
    {
        $resources = $this->getDbTable('Resource');
        $resource = $resources->findResource(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier()
        );
        foreach ($tags as $tag) {
            $resource->deleteTag($tag, $user);
        }
    }
}
