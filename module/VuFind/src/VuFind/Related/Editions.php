<?php
/**
 * Related Records: WorldCat-based editions list (Solr results)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
namespace VuFind\Related;

/**
 * Related Records: WorldCat-based editions list (Solr results)
 *
 * @category VuFind2
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
class Editions implements RelatedInterface
{
    /**
     * Related editions
     *
     * @var array
     */
    protected $results = [];

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * WorldCat utilities
     *
     * @var \VuFind\Connection\WorldCatUtils
     */
    protected $wcUtils;

    /**
     * Maximum limit
     *
     * @var int
     */
    protected $maxLimit;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results  Results plugin manager
     * @param \VuFind\Connection\WorldCatUtils     $wcUtils  WorldCat utils object
     * @param int                                  $maxLimit The maximum number of
     * results to look up in Solr; set to -1 to use the "max boolean clauses" limit.
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results,
        \VuFind\Connection\WorldCatUtils $wcUtils, $maxLimit = -1
    ) {
        $this->resultsManager = $results;
        $this->wcUtils = $wcUtils;
        $this->maxLimit = $maxLimit;
    }

    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        // If we have query parts, we should try to find related records:
        $parts = $this->getQueryParts($driver);
        if (!empty($parts)) {
            // Limit the number of parts based on the boolean clause limit:
            $result = $this->resultsManager->get('Solr');
            $params = $result->getParams();
            $params->getOptions()->spellcheckEnabled(false);
            $limit = $params->getQueryIDLimit();
            if ($this->maxLimit > 0 && $limit > $this->maxLimit) {
                $limit = $this->maxLimit;
            }
            if (count($parts) > $limit) {
                $parts = array_slice($parts, 0, $limit);
            }

            // Assemble the query parts and filter out current record if it comes
            // from the Solr index.:
            $query = '(' . implode(' OR ', $parts) . ')';
            if ($driver->getResourceSource() == 'VuFind') {
                $query .= ' NOT id:"' . addcslashes($driver->getUniqueID(), '"')
                    . '"';
            }

            // Perform the search and return either results or an error:
            $params->setLimit(5);
            $params->setOverrideQuery($query);
            $this->results = $result->getResults();
        }
    }

    /**
     * Get an array of Record Driver objects representing other editions of the one
     * passed to the constructor.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Try to build an array of OCLC Number, ISBN or ISSN-based sub-queries by
     * using OCLC X-services against a record driver object.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver object
     *
     * @return array
     */
    protected function getQueryParts($driver)
    {
        $parts = [];
        $oclcNum = $driver->tryMethod('getCleanOCLCNum');
        if (!empty($oclcNum)) {
            $oclcList = $this->wcUtils->getXOCLCNUM($oclcNum);
            foreach ($oclcList as $current) {
                $parts[] = "oclc_num:" . $current;
            }
        }
        $isbn = $driver->tryMethod('getCleanISBN');
        if (!empty($isbn)) {
            $isbnList = $this->wcUtils->getXISBN($isbn);
            foreach ($isbnList as $current) {
                $parts[] = 'isbn:' . $current;
            }
        }
        $issn = $driver->tryMethod('getCleanISSN');
        if (!empty($issn)) {
            $issnList = $this->wcUtils->getXISSN($issn);
            foreach ($issnList as $current) {
                $parts[] = 'issn:' . $current;
            }
        }
        return $parts;
    }
}