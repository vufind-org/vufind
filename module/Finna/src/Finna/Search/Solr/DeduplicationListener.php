<?php

/**
 * Solr deduplication (merged records) listener.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

/**
 * Solr merged record handling listener.
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class DeduplicationListener extends \VuFind\Search\Solr\DeduplicationListener
{
    /**
     * Append fields from dedup record to the selected local record
     *
     * @param array  $localRecordData Local record data
     * @param array  $dedupRecordData Dedup record data
     * @param string $recordSources   List of active record sources, empty if all
     * @param array  $sourcePriority  Array of source priorities keyed by source id
     *
     * @return array Local record data
     */
    protected function appendDedupRecordFields($localRecordData, $dedupRecordData,
        $recordSources, $sourcePriority
    ) {
        $localRecordData = parent::appendDedupRecordFields(
            $localRecordData, $dedupRecordData,
            $recordSources, $sourcePriority
        );

        if (isset($dedupRecordData['online_urls_str_mv'])) {
            $localRecordData['online_urls_str_mv'] = [];
            foreach ($dedupRecordData['online_urls_str_mv'] as $onlineURL) {
                $onlineURLArray = json_decode($onlineURL, true);
                if (!$recordSources
                    || isset($sourcePriority[$onlineURLArray['source']])
                ) {
                    $localRecordData['online_urls_str_mv'][] = $onlineURL;
                }
            }
        }
        return $localRecordData;
    }
}
