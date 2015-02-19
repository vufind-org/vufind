<?php

/**
 * Abstract record collection (implements some shared low-level functionality).
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Response;

/**
 * Abstract record collection (implements some shared low-level functionality).
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
     * Shuffles records.
     *
     * @return bool
     */
    public function shuffle()
    {
        return shuffle($this->records);
    }

    /**
     * Return first record in response.
     *
     * @return RecordInterface|null
     */
    public function first()
    {
        return isset($this->records[0]) ? $this->records[0] : null;
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
     */
    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
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
     * Add a record to the collection.
     *
     * @param RecordInterface $record Record to add
     *
     * @return void
     */
    public function add(RecordInterface $record)
    {
        if (!in_array($record, $this->records, true)) {
            $this->records[$this->pointer] = $record;
            $this->next();
        }
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
     * @return boolean
     */
    public function valid()
    {
        return isset($this->records[$this->pointer]);
    }

    /**
     * Return record at current collection index.
     *
     * @return RecordInterface
     */
    public function current()
    {
        return $this->records[$this->pointer];
    }

    /**
     * Rewind collection index.
     *
     * @return void
     */
    public function rewind()
    {
        $this->pointer = 0;
    }

    /**
     * Move to next collection index.
     *
     * @return void
     */
    public function next()
    {
        $this->pointer++;
    }

    /**
     * Return current collection index.
     *
     * @return integer
     */
    public function key()
    {
        return $this->pointer + $this->getOffset();
    }

    /// Countable interface

    /**
     * Return number of records in collection.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->records);
    }

}