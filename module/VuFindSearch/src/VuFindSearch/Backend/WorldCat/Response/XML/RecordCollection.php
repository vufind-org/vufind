<?php

/**
 * WorldCat record collection.
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

namespace VuFindSearch\Backend\WorldCat\Response\XML;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordInterface;

use VuFindSearch\Exception\RuntimeException;

/**
 * WorldCat record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection implements RecordCollectionInterface
{
    /**
     * Raw response.
     *
     * @var array
     */
    protected $response;

    /**
     * Response records.
     *
     * @var array
     */
    protected $records;

    /**
     * Constructor.
     *
     * @param array $response WorldCat response
     * @param int   $offset   Starting offset
     * @param int   $time     Search execution time (in MS)
     * @param int   $total    Total record count (optional)
     *
     * @return void
     */
    public function __construct (array $response)
    {
        $this->response = $response;
        $this->records  = array();
        $this->offset = $response['offset'];
        $this->rewind();
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal ()
    {
        return $this->response['total'];
    }

    /**
     * Return query time in milli-seconds.
     *
     * @return float
     */
    public function getQueryTime ()
    {
        return $this->response['time'];
    }

    /**
     * Return facet information.
     *
     * @return array
     */
    public function getFacets ()
    {
        return array(); // not supported by WorldCat
    }

    /**
     * Return records.
     *
     * @return array
     */
    public function getRecords ()
    {
        return $this->records;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset ()
    {
        return $this->offset;
    }

    /**
     * Return first record in response.
     *
     * @return RecordInterface|null
     */
    public function first ()
    {
        return isset($this->records[$this->offset]) ? $this->records[$this->offset] : null;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier ($identifier)
    {
        $this->source = $identifier;
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier ()
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
    public function add (RecordInterface $record)
    {
        if (!in_array($record, $this->records, true)) {
            $this->records[$this->pointer] = $record;
            $this->next();
        }
    }

    /// Iterator interface

    /**
     * Return true if current collection index is valid.
     *
     * @return boolean
     */
    public function valid ()
    {
        return isset($this->records[$this->pointer]);
    }

    /**
     * Return record at current collection index.
     *
     * @return RecordInterface
     */
    public function current ()
    {
        return $this->records[$this->pointer];
    }

    /**
     * Rewind collection index.
     *
     * @return void
     */
    public function rewind ()
    {
        $this->pointer = $this->offset;
    }

    /**
     * Move to next collection index.
     *
     * @return void
     */
    public function next ()
    {
        $this->pointer++;
    }

    /**
     * Return current collection index.
     *
     * @return integer
     */
    public function key ()
    {
        return $this->pointer;
    }

    /// Countable interface

    /**
     * Return number of records in collection.
     *
     * @return integer
     */
    public function count ()
    {
        return count($this->records);
    }

}