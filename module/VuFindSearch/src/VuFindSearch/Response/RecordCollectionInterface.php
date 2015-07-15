<?php

/**
 * Search backend search response interface file.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-search-subsystem
 */
namespace VuFindSearch\Response;

/**
 * Interface for backend responses to a search() operation.
 *
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-search-subsystem
 */
interface RecordCollectionInterface extends \Countable, \Iterator
{
    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal();

    /**
     * Return available facets.
     *
     * Returns an associative array with the internal field name as key. The
     * value is an associative array of the available facets for the field,
     * indexed by facet value.
     *
     * @return array
     */
    public function getFacets();

    /**
     * Return records.
     *
     * @return array
     */
    public function getRecords();

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset();

    /**
     * Return first record in collection.
     *
     * @return RecordInterface|null
     */
    public function first();

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier($identifier);

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier();

    /**
     * Add a record to the collection.
     *
     * @param RecordInterface $record Record to add
     *
     * @return void
     */
    public function add(RecordInterface $record);

}