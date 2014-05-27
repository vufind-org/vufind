<?php
/**
 * EBSCO Search Results
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
 * @package  Search_EBSCO
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\EIT;

/**
 * EBSCO Search Parameters
 * Partially copied from WorldCat Search Parameters; partially copied from other pieces of VuFind code
 *
 * @category VuFind2
 * @package  Search_EBSCO
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends \VuFind\Search\Base\Results
{

    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger = false;    

	/**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
	$offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $collection = $this->getSearchService()
            ->search('EIT', $query, $offset, $limit, $params);

        $this->resultTotal = $collection->getTotal();
    	$records = $collection->getRecords();
	        // Construct record drivers for all the items in the response:
        $this->results = array();
        foreach ($records as $current) {
            $this->results[] = $this->initRecordDriver($current);
        }
    }

    /**
     * Method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */

    public function getRecord($id)
    {
        $collection = $this->getSearchService()->retrieve($id);
    	$records = $collection->getRecords();
	// Construct a record driver for the item:
        return $this->initRecordDriver($records[0]);
    }

    /**
     * Support method for performSearch(): given an EIT record,
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
        $driver = $factory->get('EIT');
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
        // No facets in EIT:
        return array();
    }
}
