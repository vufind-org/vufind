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

namespace VuFindSearch\Backend\Summon\Response;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * WorldCat record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
     * @param array $response WorldCat response
     * @param int   $offset   Starting offset
     * @param int   $time     Search execution time (in MS)
     * @param int   $total    Total record count (optional)
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
        return $this->response['recordCount'];
    }

    /**
     * Return facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        return $this->response['facetFields'];
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
        $size = isset($this->response['query']['pageSize'])
            ? $this->response['query']['pageSize'] : 0;
        return $page * $size;
    }

    /**
     * Get the raw Summon response.
     *
     * @return array
     *
     * @todo Remove once we don't need it anymore (02/2013)
     */
    public function getRawResponse()
    {
        return $this->response;
    }

    /**
     * Get spelling suggestions.
     *
     * @return array
     */
    public function getSpellcheck()
    {
        if (isset($this->response['didYouMeanSuggestions'])
            && is_array($this->response['didYouMeanSuggestions'])
        ) {
            return $this->response['didYouMeanSuggestions'];
        }
        return array();
    }

    /**
     * Get database recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getDatabaseRecommendations()
    {
        return isset($this->response['recommendationLists']['database']) ?
            $this->response['recommendationLists']['database'] : false;
    }
}