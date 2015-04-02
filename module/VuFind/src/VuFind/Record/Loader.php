<?php
/**
 * Record loader
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Record;
use VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\RecordDriver\PluginManager as RecordFactory,
    VuFindSearch\Service as SearchService;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader
{
    /**
     * Record factory
     *
     * @var RecordFactory
     */
    protected $recordFactory;

    /**
     * Search service
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param SearchService $searchService Search service
     * @param RecordFactory $recordFactory Record loader
     */
    public function __construct(SearchService $searchService,
        RecordFactory $recordFactory
    ) {
        $this->searchService = $searchService;
        $this->recordFactory = $recordFactory;
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id              Record ID
     * @param string $source          Record source
     * @param bool   $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = 'VuFind', $tolerateMissing = false)
    {
        $results = $this->searchService->retrieve($source, $id)->getRecords();
        if (count($results) > 0) {
            return $results[0];
        }
        if ($tolerateMissing) {
            $record = $this->recordFactory->get('Missing');
            $record->setRawData(['id' => $id]);
            $record->setSourceIdentifier($source);
            return $record;
        }
        throw new RecordMissingException(
            'Record ' . $source . ':' . $id . ' does not exist.'
        );
    }

    /**
     * Given an array of IDs and a record source, load a batch of records for
     * that source.
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource($ids, $source = 'VuFind')
    {
        return $this->searchService->retrieveBatch($source, $ids)->getRecords();
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch($ids)
    {
        // Sort the IDs by source -- we'll create an associative array indexed by
        // source and record ID which points to the desired position of the indexed
        // record in the final return array:
        $idBySource = [];
        foreach ($ids as $i => $details) {
            // Convert source|id string to array if necessary:
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $ids[$i] = $details = [
                    'source' => $parts[0], 'id' => $parts[1]
                ];
            }
            $idBySource[$details['source']][$details['id']] = $i;
        }

        // Retrieve the records and put them back in order:
        $retVal = [];
        foreach ($idBySource as $source => $details) {
            $records = $this->loadBatchForSource(array_keys($details), $source);
            foreach ($records as $current) {
                $id = $current->getUniqueId();
                // In theory, we should be able to assume that $details[$id] is
                // set... but in practice, we can't make that assumption. In some
                // cases, Summon IDs will change, and requests for an old ID value
                // will return a record with a different ID.
                if (isset($details[$id])) {
                    $retVal[$details[$id]] = $current;
                }
            }
        }

        // Check for missing records and fill gaps with \VuFind\RecordDriver\Missing
        // objects:
        foreach ($ids as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $fields = isset($details['extra_fields'])
                    ? $details['extra_fields'] : [];
                $fields['id'] = $details['id'];
                $retVal[$i] = $this->recordFactory->get('Missing');
                $retVal[$i]->setRawData($fields);
                $retVal[$i]->setSourceIdentifier($details['source']);
            }
        }

        // Send back the final array, with the keys in proper order:
        ksort($retVal);
        return $retVal;
    }
}
