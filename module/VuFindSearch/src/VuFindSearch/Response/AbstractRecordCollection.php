<?php

/**
 * Abstract record collection (implements some shared low-level functionality).
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Response;

use function array_slice;
use function count;
use function in_array;

/**
 * Abstract record collection (implements some shared low-level functionality).
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractRecordCollection implements RecordCollectionInterface
{
    /**
     * Response records.
     *
     * @var array
     */
    protected $records = [];

    /**
     * Source identifier
     *
     * @var string
     */
    protected $source;

    /**
     * Array pointer
     *
     * @var int
     */
    protected $pointer = 0;

    /**
     * Zero-based offset in complete search result.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * Return records.
     *
     * @return array
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Return any errors.
     *
     * Each error can be a translatable string or an array that the Flashmessages
     * view helper understands.
     *
     * @return array
     */
    public function getErrors()
    {
        return [];
    }

    /**
     * Shuffles records.
     *
     * @return bool
     */
    public function shuffle()
    {
        return shuffle($this->records);
    }

    /**
     * Slice the record list.
     *
     * @param int $offset Offset
     * @param int $limit  Limit
     *
     * @return void
     */
    public function slice(int $offset, int $limit): void
    {
        $this->records = array_slice(
            $this->records,
            $offset,
            $limit
        );
    }

    /**
     * Return first record in response.
     *
     * @return RecordInterface|null
     */
    public function first()
    {
        return $this->records[0] ?? null;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     *
     * @deprecated Use setSourceIdentifiers instead
     */
    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
        foreach ($this->records as $record) {
            $record->setSourceIdentifier($identifier);
        }
    }

    /**
     * Set the source backend identifiers.
     *
     * @param string $recordSourceId  Record source identifier
     * @param string $searchBackendId Search backend identifier (if different from
     * $recordSourceId)
     *
     * @return void
     */
    public function setSourceIdentifiers($recordSourceId, $searchBackendId = '')
    {
        $this->source = $searchBackendId ?: $recordSourceId;
        foreach ($this->records as $record) {
            $record->setSourceIdentifiers(
                $recordSourceId,
                $this->source
            );
        }
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier()
    {
        return $this->source;
    }

    /**
     * Sets the result set identifier for all records in the collection.
     *
     * This method assigns a given UUID to each record in the collection by calling
     * the `setResultSetIdentifier` method on each record.
     *
     * @param string $uuid A valid UUID to be assigned to each record in the collection.
     *
     * @return void
     */
    public function setResultSetIdentifier(string $uuid)
    {
        foreach ($this->records as $record) {
            $record->setResultSetIdentifier($uuid);
        }
    }

    /**
     * Add a record to the collection.
     *
     * @param RecordInterface $record        Record to add
     * @param bool            $checkExisting Whether to check for existing record in
     * the collection (slower, but makes sure there are no duplicates)
     *
     * @return void
     */
    public function add(RecordInterface $record, $checkExisting = true)
    {
        if (!$checkExisting || !$this->has($record)) {
            $this->records[$this->pointer] = $record;
            $this->next();
        }
    }

    /**
     * Check if the collection contains the given record
     *
     * @param RecordInterface $record Record to check
     *
     * @return bool
     */
    public function has(RecordInterface $record)
    {
        return in_array($record, $this->records, true);
    }

    /**
     * Replace a record in the collection.
     *
     * @param RecordInterface $record      Record to be replaced
     * @param RecordInterface $replacement Replacement record
     *
     * @return void
     */
    public function replace(RecordInterface $record, RecordInterface $replacement)
    {
        $key = array_search($record, $this->records, true);
        if ($key !== false) {
            $this->records[$key] = $replacement;
        }
    }

    /// Iterator interface

    /**
     * Return true if current collection index is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->records[$this->pointer]);
    }

    /**
     * Return record at current collection index.
     *
     * @return RecordInterface
     */
    public function current(): mixed
    {
        return $this->records[$this->pointer];
    }

    /**
     * Rewind collection index.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->pointer = 0;
    }

    /**
     * Move to next collection index.
     *
     * @return void
     */
    public function next(): void
    {
        $this->pointer++;
    }

    /**
     * Return current collection index.
     *
     * @return integer
     */
    public function key(): mixed
    {
        return $this->pointer + $this->getOffset();
    }

    /// Countable interface

    /**
     * Return number of records in collection.
     *
     * @return integer
     */
    public function count(): int
    {
        return count($this->records);
    }
}
