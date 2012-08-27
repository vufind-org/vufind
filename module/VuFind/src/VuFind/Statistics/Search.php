<?php
/**
 * VuFind Statistics Class for Searches
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
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Statistics;

/**
 * VuFind Statistics Class for Searches
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class Search extends AbstractBase
{
    /**
     * Saves the search to wherever the config [Statistics] says so
     *
     * @param \VuFind\Search\Base\Results  $data    Results from Search controller
     * @param Zend_Controller_Request_Http $request Request data from the controller
     *
     * @return void
     */
    public function log($data, $request)
    {
        $configs = $data->getFacetConfig();
        $stat = array(
            'phrase'       => $data->getDisplayQuery(),
            'searchSource' => $data->getSearchClassId(),
            'type'         => $data->getSearchHandler(),
            'resultCount'  => $data->getResultTotal(),
            'noresults'    => $data->getResultTotal() == 0
        );
        $this->save($stat, $request);
    }

    /**
     * Returns a set of basic statistics including total searches,
     * number of empty searches and most popular search terms.
     *
     * @param Configuration $config     Configuration object to find sources
     * @param integer       $listLength Number of top searches to return
     * @param bool          $bySource   Separate searches by search source?
     *
     * @return array
     */
    public function getStatsSummary($config, $listLength = 5, $bySource = false)
    {
        foreach ($this->drivers as $driver) {
            $summary = $driver->getFullList('phrase');
            if (!empty($summary)) {
                $summary = $summary->toArray();
                $sources = $driver->getFullList('searchSource')->toArray();
                $hashes = array();
                // Generate hashes (faster than grouping by looping)
                for ($i=0;$i<count($summary);$i++) {
                    if (!isset($sources[$i]['searchSource'])) {
                        $sources[$i]['searchSource'] = 'Search';
                    } else {
                        // Escape multivalue index
                        $sources[$i]['searchSource']
                            = implode(', ', (Array)$sources[$i]['searchSource']);
                    }
                    // Escape multivalue index
                    $summary[$i]['phrase']
                        = implode(', ', (Array)$summary[$i]['phrase']);
                    // Store everything in a string array for fast sorting later
                    $source = $sources[$i]['searchSource'];
                    $hashes[$source][$summary[$i]['phrase']]
                        = isset($hashes[$source][$summary[$i]['phrase']])
                        ? $hashes[$source][$summary[$i]['phrase']] + 1
                        : 1;
                }
                $top = array();
                // For each source
                foreach ($hashes as $source=>$records) {
                    // Using a reference to consolidate code dramatically
                    $reference =& $top;
                    if ($bySource) {
                        $top[$source] = array();
                        $reference =& $top[$source];
                    }
                    // For each record
                    foreach ($records as $phrase=>$count) {
                        $value = ($phrase == '' || $phrase == '*:*')
                            ? '(empty)'
                            : $phrase;
                        $newRecord = array(
                            'value'  => $value,
                            'count'  => $count,
                            'source' => $source
                        );
                        // Insert sort (limit to listLength)
                        for ($i=0;$i<$listLength-1 && $i<count($reference);$i++) {
                            if ($count > $reference[$i]['count']) {
                                // Insert in order
                                array_splice($reference, $i, 0, array($newRecord));
                                continue 2; // Skip the append after this loop
                            }
                        }
                        if (count($reference) < $listLength) {
                            $reference[] = $newRecord;
                        }
                    }
                    $reference = array_slice($reference, 0, $listLength);
                }
                return array(
                    'top'   => $top,
                    'total' => count($summary),
                    'empty' => count(
                        $driver->getFullList('noresults', array('value' => 'true'))
                    )
                );
            }
        }
        return array();
    }
}