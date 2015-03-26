<?php
/**
 * Test results search model.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Search\TestHarness;
use VuFindTest\RecordDriver\TestHarness as RecordDriver;

/**
 * Test results search model.
 *
 * This abstract class defines the results methods for modeling a search in VuFind.
 *
 * @category VuFind2
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Fake expected total
     *
     * @var int
     */
    protected $fakeExpectedTotal;

    /**
     * Cache for fake drivers
     *
     * @var array
     */
    protected $driverCache = [];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params Object representing user search
     * parameters.
     * @param int                        $total  Total result set size to simulate
     */
    public function __construct(Params $params, $total = 100)
    {
        parent::__construct($params);
        $this->fakeExpectedTotal = $total;
        $this->searchId = 'fake';   // fill a fake value here so we don't hit the DB
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
        // not supported
        return [];
    }

    /**
     * Abstract support method for performAndProcessSearch -- perform a search based
     * on the parameters passed to the object.  This method is responsible for
     * filling in all of the key class properties: results, resultTotal, etc.
     *
     * @return void
     */
    protected function performSearch()
    {
        $this->resultTotal = $this->fakeExpectedTotal;
        $this->results = [];
        $limit  = $this->getParams()->getLimit();
        $start = $this->getStartRecord();
        for ($i = $start; $i < $start + $limit; $i++) {
            if ($i > $this->resultTotal) {
                break;
            }
            $this->results[] = $this->getMockRecordDriver($i);
        }
    }

    /**
     * Get a fake record driver
     *
     * @param string $id ID to use
     *
     * @return RecordDriver
     */
    public function getMockRecordDriver($id)
    {
        if (!isset($this->driverCache[$id])) {
            $this->driverCache[$id] = new RecordDriver();
            $this->driverCache[$id]->setRawData(['UniqueID' => $id]);
        }
        return $this->driverCache[$id];
    }
}
