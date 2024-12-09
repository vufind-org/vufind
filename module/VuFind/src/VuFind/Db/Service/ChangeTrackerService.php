<?php

/**
 * Database service for change tracker.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\ChangeTrackerEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

/**
 * Database service for change tracker.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ChangeTrackerService extends AbstractDbService implements
    ChangeTrackerServiceInterface,
    DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Format to use when sending dates to legacy code.
     *
     * @var string
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $indexName The name of the Solr index holding the record.
     * @param string $id        The ID of the record being indexed.
     *
     * @return ?ChangeTrackerEntityInterface
     */
    public function getChangeTrackerEntity(string $indexName, string $id): ?ChangeTrackerEntityInterface
    {
        return $this->getDbTable('ChangeTracker')->retrieve($indexName, $id);
    }

    /**
     * Retrieve a count of deleted rows from the database.
     *
     * @param string   $indexName The name of the Solr index holding the record.
     * @param DateTime $from      The beginning date of the range to search.
     * @param DateTime $until     The end date of the range to search.
     *
     * @return int
     */
    public function getDeletedCount(string $indexName, DateTime $from, DateTime $until): int
    {
        return $this->getDbTable('ChangeTracker')->retrieveDeletedCount(
            $indexName,
            $from->format($this->dateFormat),
            $until->format($this->dateFormat)
        );
    }

    /**
     * Retrieve a set of deleted rows from the database.
     *
     * @param string   $indexName The name of the Solr index holding the record.
     * @param DateTime $from      The beginning date of the range to search.
     * @param DateTime $until     The end date of the range to search.
     * @param int      $offset    Record number to retrieve first.
     * @param ?int     $limit     Retrieval limit (null for no limit)
     *
     * @return ChangeTrackerEntityInterface[]
     */
    public function getDeletedEntities(
        string $indexName,
        DateTime $from,
        DateTime $until,
        int $offset = 0,
        ?int $limit = null
    ): array {
        return iterator_to_array(
            $this->getDbTable('ChangeTracker')->retrieveDeleted(
                $indexName,
                $from->format($this->dateFormat),
                $until->format($this->dateFormat),
                $offset,
                $limit
            )
        );
    }

    /**
     * Update the change tracker table to indicate that a record has been deleted.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function markDeleted(string $core, string $id): ChangeTrackerEntityInterface
    {
        return $this->getDbTable('ChangeTracker')->markDeleted($core, $id);
    }

    /**
     * Update the change_tracker table to reflect that a record has been indexed.
     * We need to know the date of the last change to the record (independent of
     * its addition to the index) in order to tell the difference between a
     * reindex of a previously-encountered record and a genuine change.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core   The Solr core holding the record.
     * @param string $id     The ID of the record being indexed.
     * @param int    $change The timestamp of the last record change.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function index(string $core, string $id, int $change): ChangeTrackerEntityInterface
    {
        return $this->getDbTable('ChangeTracker')->index($core, $id, $change);
    }
}
