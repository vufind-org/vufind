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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ChangeTracker;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for change tracker.
 *
 * @category VuFind
 * @package  Database
 * @author   sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ChangeTrackerService extends AbstractService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return ?ChangeTracker
     */
    public function retrieve(string $core, string $id): ?ChangeTracker
    {
        $dql = 'SELECT c '
            . 'FROM ' . $this->getEntityClass(ChangeTracker::class) . ' c '
            . 'WHERE c.core = :core AND c.id = :id';
        $parameters = compact('core', 'id');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $queryResult = $query->getResult();
        $result = current($queryResult);
        return $result ? $result : null;
    }

    /**
     * Retrieve a set of deleted rows from the database.
     *
     * @param string   $core  The Solr core holding the record.
     * @param DateTime $from  The beginning date of the range to search.
     * @param DateTime $until The end date of the range to search.
     *
     * @return int
     */
    public function retrieveDeletedCount(string $core, DateTime $from, DateTime $until): int
    {
        $dql = 'SELECT COUNT(c) as deletedcount '
            . 'FROM ' . $this->getEntityClass(ChangeTracker::class) . ' c '
            . 'WHERE c.core = :core AND c.deleted BETWEEN :from AND :until';
        $parameters = compact('core', 'from', 'until');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();
        return current($result)['deletedcount'];
    }

    /**
     * Retrieve a set of deleted rows from the database.
     *
     * @param string   $core   The Solr core holding the record.
     * @param DateTime $from   The beginning date of the range to search.
     * @param DateTime $until  The end date of the range to search.
     * @param int      $offset Record number to retrieve first.
     * @param int      $limit  Retrieval limit (null for no limit)
     *
     * @return array
     */
    public function retrieveDeleted(
        string $core,
        DateTime $from,
        DateTime $until,
        int $offset = 0,
        int $limit = null
    ): array {
        $dql = 'SELECT c '
            . 'FROM ' . $this->getEntityClass(ChangeTracker::class) . ' c '
            . 'WHERE c.core = :core AND c.deleted BETWEEN :from AND :until '
            . 'ORDER BY c.deleted';
        $parameters = compact('core', 'from', 'until');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->setFirstResult($offset);
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }
        $result = $query->getResult();
        return $result;
    }

    /**
     * Retrieve a row from the database based on primary key; create a new
     * row if no existing match is found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return ChangeTracker|false
     */
    public function retrieveOrCreate(string $core, string $id): ChangeTracker|false
    {
        $row = $this->retrieve($core, $id);
        if (empty($row)) {
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $row = $this->createEntity()
                ->setCore($core)
                ->setId($id)
                ->setFirstIndexed($now)
                ->setLastIndexed($now);
            try {
                $this->persistEntity($row);
            } catch (\Exception $e) {
                $this->logError('Could not save change tracker record: ' . $e->getMessage());
                return false;
            }
        }
        return $row;
    }

    /**
     * Update the change tracker table to indicate that a record has been deleted.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return ChangeTracker|false
     */
    public function markDeleted(string $core, string $id): ChangeTracker|false
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // If the record is already deleted, we don't need to do anything!
        if (!empty($row->getDeleted())) {
            return $row;
        }

        // Save new value to the object:
        $row->setDeleted(new \DateTime('now', new \DateTimeZone('UTC')));
        try {
            $this->persistEntity($row);
        } catch (\Exception $e) {
            $this->logError('Could not update the deleted time: ' . $e->getMessage());
            return false;
        }
        return $row;
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
     * @return ChangeTracker|false
     */
    public function index(string $core, string $id, int $change): ChangeTracker|false
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // Flag to indicate whether we need to save the contents of $row:
        $saveNeeded = false;
        $utcTime = \DateTime::createFromFormat('U', $change, new \DateTimeZone('UTC'));

        // Make sure there is a change date in the row (this will be empty
        // if we just created a new row):
        if (empty($row->getLastRecordChange())) {
            $row->setLastRecordChange($utcTime);
            $saveNeeded = true;
        }

        // Are we restoring a previously deleted record, or was the stored
        // record change date before current record change date?  Either way,
        // we need to update the table!
        if (!empty($row->getDeleted()) || $row->getLastRecordChange() < $utcTime) {
            // Save new values to the object:
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $row->setLastIndexed($now);
            $row->setLastRecordChange($utcTime);

            // If first indexed is null, we're restoring a deleted record, so
            // we need to treat it as new -- we'll use the current time.
            if (empty($row->getFirstIndexed())) {
                $row->setFirstIndexed($now);
            }

            // Make sure the record is "undeleted" if necessary:
            $row->setDeleted(null);

            $saveNeeded = true;
        }

        // Save the row if changes were made:
        if ($saveNeeded) {
            try {
                $this->persistEntity($row);
            } catch (\Exception $e) {
                $this->logError('Could not update the change tracker table: ' . $e->getMessage());
                return false;
            }
        }

        // Send back the row:
        return $row;
    }

    /**
     * Remove all or selected rows from the database.
     *
     * @param ?string $core The Solr core holding the record.
     * @param ?string $id   The ID of the record being indexed.
     *
     * @return void
     */
    public function deleteRows(?string $core = null, ?string $id = null): void
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(ChangeTracker::class) . ' c ';
        $parameters = $dqlWhere = [];
        if (null !== $core) {
            $dqlWhere[] = 'c.core = :core';
            $parameters['core'] = $core;
        }
        if (null !== $id) {
            $dqlWhere[] = 'c.id = :id';
            $parameters['id'] = $id;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Create a change tracker entity object.
     *
     * @return ChangeTracker
     */
    public function createEntity(): ChangeTracker
    {
        $class = $this->getEntityClass(ChangeTracker::class);
        return new $class();
    }
}
