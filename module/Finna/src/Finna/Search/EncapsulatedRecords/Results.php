<?php

/**
 * Encapsulated Records aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @package  Search_EncapsulatedRecords
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\EncapsulatedRecords;

use Finna\RecordDriver\Feature\ContainerFormatInterface;
use VuFind\RecordDriver\AbstractBase;

/**
 * Encapsulated Records Search Results
 *
 * @category VuFind
 * @package  Search_EncapsulatedRecords
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Active container record (false if none).
     *
     * @var AbstractBase|bool
     */
    protected AbstractBase|bool $containerRecord = false;

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        // Facets are not supported for encapsulated records.
        return [];
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based
     * on the parameters passed to the object. This method is responsible for
     * filling in all of the key class properties: results, resultTotal, etc.
     *
     * @return void
     */
    protected function performSearch()
    {
        $containerRecord = $this->getContainerRecord();
        if ($containerRecord instanceof ContainerFormatInterface) {
            $this->results = $containerRecord->getEncapsulatedRecords(
                $this->getStartRecord() - 1,
                $this->getParams()->getLimit()
            );
            $this->resultTotal = $containerRecord->getEncapsulatedRecordTotal();
        } else {
            $this->results = [];
            $this->resultTotal = 0;
        }
    }

    /**
     * Set the active container record.
     *
     * @param AbstractBase $containerRecord Container record
     *
     * @return void
     */
    public function setContainerRecord(AbstractBase $containerRecord): void
    {
        $this->containerRecord = $containerRecord;
    }

    /**
     * Get the container record associated with the current search (null if no record
     * selected).
     *
     * @return AbstractBase|bool
     */
    public function getContainerRecord(): AbstractBase|bool
    {
        $filters = $this->getParams()->getRawFilters();
        $id = $filters['ids'][0] ?? null;

        // Load a container record
        //   a. if we haven't previously tried to load a container record
        //      ($this->containerRecord = false)
        //   b. if the requested container record is not the same as previously
        //      loaded container record
        if (
            $this->containerRecord === false
            || $this->containerRecord->getUniqueID() !== $id
        ) {
            // Check the filters for a record ID, and load the corresponding object
            // if one is found:
            if (null === $id) {
                $this->containerRecord = false;
            } else {
                $this->containerRecord = $this->recordLoader->load($id);
            }
        }
        return $this->containerRecord;
    }
}
