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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Related;

/**
 * Related Records: WorldCat-based editions list (Solr results)
 *
 * @category VuFind2
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class Editions extends AbstractServiceLocator
{
    protected $results = array();

    /**
     * init
     *
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
            $sm = $this->getSearchManager();
            $params = $sm->setSearchClassId('Solr')->getParams();
            $limit = $params->getQueryIDLimit();
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
            $result = $sm->setSearchClassId('Solr')->getResults($params);
            $this->results = $result->getResults();
        }
    }

    /**
     * getResults
     *
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
        $wc = $this->getServiceLocator()->get('WorldCatUtils');
        $parts = array();
        if (method_exists($driver, 'getCleanOCLCNum')) {
            $oclcNum = $driver->getCleanOCLCNum();
            if (!empty($oclcNum)) {
                $oclcList = $wc->getXOCLCNUM($oclcNum);
                foreach ($oclcList as $current) {
                    $parts[] = "oclc_num:" . $current;
                }
            }
        }
        if (method_exists($driver, 'getCleanISBN')) {
            $isbn = $driver->getCleanISBN();
            if (!empty($isbn)) {
                $isbnList = $wc->getXISBN($isbn);
                foreach ($isbnList as $current) {
                    $parts[] = 'isbn:' . $current;
                }
            }
        }
        if (method_exists($driver, 'getCleanISSN')) {
            $issn = $driver->getCleanISSN();
            if (!empty($issn)) {
                $issnList = $wc->getXISSN($issn);
                foreach ($issnList as $current) {
                    $parts[] = 'issn:' . $current;
                }
            }
        }
        return $parts;
    }
}
