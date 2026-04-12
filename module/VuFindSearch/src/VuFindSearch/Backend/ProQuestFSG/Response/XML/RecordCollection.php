<?php

/**
 * ProQuest Federated Search Gateway record collection.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\ProQuestFSG\Response\XML;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * ProQuest Federated Search Gateway record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
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
     * Facet fields.
     *
     * @var array
     */
    protected $facetFields = null;

    /**
     * Constructor.
     *
     * @param array $response ProQuestFSG response
     *
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = $response;
        $this->offset = $this->response['offset'];
        $this->rewind();
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->response['total'];
    }

    /**
     * Return facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        if ($this->facetFields === null) {
            $this->facetFields = [];
            $facets = $this->response['facets'] ?? [];
            foreach ($facets as $facetName => $facetValues) {
                usort($facetValues, fn ($a, $b) => ($a['count'] ?? 0) < ($b['count'] ?? 0));
                $values = [];
                foreach ($facetValues as $facetValue) {
                    $facetValueName = "{$facetValue['code']}|{$facetValue['name']}";
                    $values[$facetValueName] = $facetValue['count'];
                }
                $this->facetFields[$facetName] = $values;
            }
        }
        return $this->facetFields;
    }
}
