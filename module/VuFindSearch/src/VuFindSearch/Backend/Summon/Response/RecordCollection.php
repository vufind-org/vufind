<?php

/**
 * WorldCat record collection.
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

namespace VuFindSearch\Backend\Summon\Response;

use VuFindSearch\Response\AbstractRecordCollection;

use function is_array;

/**
 * WorldCat record collection.
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
     * @param array $response Summon response
     *
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = $response;

        // Determine the offset:
        $page = isset($this->response['query']['pageNumber'])
            ? $this->response['query']['pageNumber'] - 1 : 0;
        $size = $this->response['query']['pageSize'] ?? 0;
        $this->offset = $page * $size;

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
     * Return facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        return $this->response['facetFields'] ?? [];
    }

    /**
     * Get spelling suggestions.
     *
     * @return array
     */
    public function getSpellcheck()
    {
        return is_array($this->response['didYouMeanSuggestions'] ?? null)
            ? $this->response['didYouMeanSuggestions'] : [];
    }

    /**
     * Get best bets from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getBestBets()
    {
        return $this->response['recommendationLists']['bestBet'] ?? false;
    }

    /**
     * Get database recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getDatabaseRecommendations()
    {
        return $this->response['recommendationLists']['database'] ?? false;
    }

    /**
     * Get topic recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getTopicRecommendations()
    {
        return $this->response['topicRecommendations'] ?? false;
    }
}
