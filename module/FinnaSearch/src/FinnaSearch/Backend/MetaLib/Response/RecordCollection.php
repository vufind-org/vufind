<?php
/**
 * MetaLib record collection.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\MetaLib\Response;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * Primo Central record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
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
        return isset($this->response['totalRecords'])
            ? $this->response['totalRecords'] : 0;
    }

    /**
     * Return facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        return isset($this->response['facets'])
            ? $this->response['facets'] : [];
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
     * Return response for a IRD info request.
     *
     * @return int
     */
    public function getIRDInfo()
    {
        $result = [];
        if (isset($this->response['name'])) {
            $result['name'] = $this->response['name'];
        }
        if (isset($this->response['searchable'])) {
            $result['searchable'] = $this->response['searchable'];
        }

        if (isset($this->response['access'])) {
            $result['access'] = $this->response['access'];
        }
        return $result;
    }

    /**
     * Return failed and disallowed databases for a search request.
     *
     * @return array
     */
    public function getFailedDatabases()
    {
        $failed = [];
        $failed['failed']
            = isset($this->response['failedDatabases'])
            && !empty($this->response['failedDatabases'])
            ? $this->response['failedDatabases'] : [];

        $failed['disallowed']
            = isset($this->response['disallowedDatabases'])
            && !empty($this->response['disallowedDatabases'])
            ? $this->response['disallowedDatabases'] : [];

        return $failed;
    }
}
