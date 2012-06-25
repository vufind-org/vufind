<?php
/**
 * WorldCat Search Results
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * WorldCat Search Parameters
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Search_WorldCat_Results extends VF_Search_Base_Results
{
    // Raw search response:
    protected $rawResponse = null;

    /**
     * Get a connection to the WorldCat API.
     *
     * @return VF_Connection_WorldCat
     */
    public static function getWorldCatConnection()
    {
        static $wc = false;
        if (!$wc) {
            $wc = new VF_Connection_WorldCat();
        }
        return $wc;
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        // Collect the search parameters:
        $config = VF_Config_Reader::getConfig();
        $wc = static::getWorldCatConnection();
        $overrideQuery = $this->getOverrideQuery();
        $query = empty($overrideQuery)
            ? $wc->buildQuery($this->getSearchTerms()) : $overrideQuery;

        // Perform the search:
        $this->rawResponse  = $wc->search(
            $query, $config->WorldCat->OCLCCode, $this->getPage(), $this->getLimit(),
            $this->getSort()
        );

        // How many results were there?
        $this->resultTotal = isset($this->rawResponse->numberOfRecords)
            ? intval($this->rawResponse->numberOfRecords) : 0;

        // Construct record drivers for all the items in the response:
        $this->results = array();
        if (isset($this->rawResponse->records->record)
            && count($this->rawResponse->records->record) > 0
        ) {
            foreach ($this->rawResponse->records->record as $current) {
                $this->results[] = static::initRecordDriver(
                    $current->recordData->record->asXML()
                );
            }
        }
    }

    /**
     * Static method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws VF_Exception_RecordMissing
     * @return VF_RecordDriver_Base
     */
    public static function getRecord($id)
    {
        $wc = static::getWorldCatConnection();
        $record = $wc->getRecord($id);
        if (empty($record)) {
            throw new VF_Exception_RecordMissing(
                'Record ' . $id . ' does not exist.'
            );
        }
        return static::initRecordDriver($record);
    }

    /**
     * Support method for _performSearch(): given an array of Solr response data,
     * construct an appropriate record driver object.
     *
     * @param array $data Raw record data
     *
     * @return VF_RecordDriver_Base
     */
    protected static function initRecordDriver($data)
    {
        return new VF_RecordDriver_WorldCat($data);
    }

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
        // No facets in WorldCat:
        return array();
    }
}