<?php

/**
 * Primo Central record collection.
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

namespace VuFindSearch\Backend\Primo\Response;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * Primo Central record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollection extends AbstractRecordCollection
{
    /**
     * Raw response.
     *
     * @var array
     */
    protected $response;

    /**
     * Constructor.
     *
     * @param array $response Primo response
     *
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = $response;
        $this->rewind();
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->response['recordCount'] ?? 0;
    }

    /**
     * Return available facets.
     *
     * Returns an associative array with the field name as key. The value is an
     * associative array of available facets for the field, indexed by facet value.
     *
     * @return array
     */
    public function getFacets()
    {
        return $this->response['facets'] ?? [];
    }

    /**
     * Set facets.
     *
     * @param array $facets Facet fields
     *
     * @return void
     */
    public function setFacets(array $facets): void
    {
        $this->response['facets'] = $facets;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset()
    {
        $page = isset($this->response['query']['pageNumber'])
            ? $this->response['query']['pageNumber'] - 1 : 0;
        $size = $this->response['query']['pageSize'] ?? 0;
        return $page * $size;
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
        return (array)($this->response['error'] ?? []);
    }
}
