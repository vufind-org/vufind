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
 * @package  Search_WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\WorldCat;
use VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\Search\Base\Results as BaseResults;

/**
 * WorldCat Search Parameters
 *
 * @category VuFind2
 * @package  Search_WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends BaseResults
{
    /**
     * Raw search response:
     */
    protected $rawResponse = null;

    /**
     * Get a connection to the WorldCat API.
     *
     * @return \VuFind\Connection\WorldCat
     */
    public function getWorldCatConnection()
    {
        return $this->getServiceLocator()->get('VuFind\WorldCatConnection');
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $wc = $this->getWorldCatConnection();
        $queryBuilder = new \VuFindSearch\Backend\WorldCat\QueryBuilder();
        $query = $queryBuilder->build($this->getParams()->getQuery())->get('query');
        $query = $query[0];

        // Perform the search:
        $this->rawResponse  = $wc->search(
            $query, $config->WorldCat->OCLCCode, $this->getParams()->getPage(),
            $this->getParams()->getLimit(), $this->getParams()->getSort()
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
                $this->results[] = $this->initRecordDriver(
                    $current->recordData->record->asXML()
                );
            }
        }
    }

    /**
     * Method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws RecordMissingException
     * @return \VuFind\RecordDriver\Base
     */
    public function getRecord($id)
    {
        $wc = $this->getWorldCatConnection();
        $record = $wc->getRecord($id);
        if (empty($record)) {
            throw new RecordMissingException(
                'Record ' . $id . ' does not exist.'
            );
        }
        return $this->initRecordDriver($record);
    }

    /**
     * Support method for performSearch(): given a WorldCat MARC record,
     * construct an appropriate record driver object.
     *
     * @param string $data Raw record data
     *
     * @return \VuFind\RecordDriver\Base
     */
    protected function initRecordDriver($data)
    {
        $factory = $this->getServiceLocator()
            ->get('VuFind\RecordDriverPluginManager');
        $driver = $factory->get('WorldCat');
        $driver->setRawData($data);
        return $driver;
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