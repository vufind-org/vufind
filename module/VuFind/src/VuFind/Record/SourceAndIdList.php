<?php

/**
 * Record ID list (support class for Loader)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record;

use VuFind\RecordDriver\AbstractBase as Record;

use function is_array;

/**
 * Record ID list (support class for Loader)
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SourceAndIdList
{
    /**
     * Processed ID data.
     *
     * @var array
     */
    protected $ids = [];

    /**
     * Record positions in the original list, indexed by source and ID.
     *
     * @var array
     */
    protected $bySource = [];

    /**
     * Constructor
     *
     * @param array $ids Array of associative arrays with id/source keys or strings
     * in source|id format. In associative array formats, there is also an optional
     * "extra_fields" key which can be used to pass in data formatted as if it
     * belongs to the Solr schema; this is used to create a mock driver object if
     * the real data source is unavailable.
     */
    public function __construct($ids)
    {
        // Sort the IDs by source -- we'll create an associative array indexed by
        // source and record ID which points to the desired position of the indexed
        // record in the final return array:
        foreach ($ids as $i => $details) {
            // Convert source|id string to array if necessary:
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $ids[$i] = $details = [
                    'source' => $parts[0], 'id' => $parts[1],
                ];
            }
            $this->bySource[$details['source']][$details['id']][] = $i;
        }
        $this->ids = $ids;
    }

    /**
     * Get the full list of IDs sent to the constructor, normalized to array
     * format.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->ids;
    }

    /**
     * Get an associative source => id list array.
     *
     * @return array
     */
    public function getIdsBySource()
    {
        return array_map('array_keys', $this->bySource);
    }

    /**
     * If the provided record driver corresponds with an ID in the list, return
     * the associated positions in the list. Otherwise, return an empty array.
     *
     * @param Record $record Record
     *
     * @return int[]
     */
    public function getRecordPositions(Record $record)
    {
        $id = $record->getUniqueId();
        $source = $record->getSourceIdentifier();

        // In some cases (e.g. Summon), the ID may have changed, so also check the
        // prior ID if available. We should do this BEFORE checking the primary ID
        // to ensure that we match the correct record in the edge case where a list
        // contains both an OLD record ID and the NEW record ID that it has been
        // replaced with. Checking the old ID first ensures that we don't match the
        // same position twice for two different records.
        $oldId = $record->tryMethod('getPreviousUniqueId');
        if ($oldId !== null && isset($this->bySource[$source][$oldId])) {
            return $this->bySource[$source][$oldId];
        }
        if (isset($this->bySource[$source][$id])) {
            return $this->bySource[$source][$id];
        }
        return [];
    }
}
