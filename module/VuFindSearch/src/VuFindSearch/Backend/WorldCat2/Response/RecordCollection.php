<?php

/**
 * WorldCat v2 record collection.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\WorldCat2\Response;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * WorldCat v2 record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollection extends AbstractRecordCollection
{
    /**
     * Constructor.
     *
     * @param array $response Raw WorldCat v2 response
     *
     * @return void
     */
    public function __construct(protected array $response)
    {
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
        $result = [];
        foreach ($this->response['facets'] ?? [] as $field) {
            if (!isset($field['facetType'])) {
                continue;
            }
            $result[$field['facetType']] = [];
            foreach ($field['values'] as $value) {
                $result[$field['facetType']][$value['value']] = $value['count'];
            }
        }
        return $result;
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
        return $this->response['errors'] ?? [];
    }
}
