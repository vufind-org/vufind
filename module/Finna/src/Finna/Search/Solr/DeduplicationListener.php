<?php

/**
 * Solr deduplication (merged records) listener.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013-2016.
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
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

/**
 * Solr merged record handling listener.
 *
 * @category VuFind
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
     * @param array $localRecordData Local record data
     * @param array $dedupRecordData Dedup record data
     * @param array $recordSources   List of active record sources, empty if all
     * @param array $sourcePriority  Array of source priorities keyed by source id
     *
     * @return array Local record data
     */
    protected function appendDedupRecordFields($localRecordData, $dedupRecordData,
        $recordSources, $sourcePriority
    ) {
        // Copy over only those local IDs that
        if (empty($recordSources)) {
            $localRecordData['local_ids_str_mv']
                = $dedupRecordData['local_ids_str_mv'];
        } else {
            $sources = array_flip($recordSources);
            $localIds = $dedupRecordData['local_ids_str_mv'];
            foreach ($localIds as $id) {
                list($idSource) = explode('.', $id, 2);
                if (isset($sources[$idSource])) {
                    $localRecordData['local_ids_str_mv'][] = $id;
                }
            }
        }

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

    /**
     * Function that determines the priority for buildings
     *
     * @param object $params Query parameters
     *
     * @return array Array keyed by building with priority as the value
     */
    protected function determineBuildingPriority($params)
    {
        $result = parent::determineBuildingPriority($params);

        if (!isset($_ENV['VUFIND_API_CALL']) || !$_ENV['VUFIND_API_CALL']) {
            return $result;
        }

        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (!isset($searchConfig->Records->apiExcludedSources)) {
            return $result;
        }
        $excluded = explode(',', $searchConfig->Records->apiExcludedSources);
        $result = array_flip($result);
        $result = array_diff($result, $excluded);

        return array_flip($result);
    }

    /**
     * Function that determines the priority for sources
     *
     * @param string $recordSources Record sources defined in searches.ini
     *
     * @return array Array keyed by source with priority as the value
     */
    protected function determineSourcePriority($recordSources)
    {
        $cookieManager = $this->serviceLocator->get('VuFind\CookieManager');
        if ($cookieManager) {
            if (!($preferred = $cookieManager->get('preferredRecordSource'))) {
                $authManager = $this->serviceLocator->get('VuFind\AuthManager');
                if ($user = $authManager->isLoggedIn()) {
                    if ($user->cat_username) {
                        list($preferred) = explode('.', $user->cat_username, 2);
                    }
                }
            }
            // array_search may return 0, but that's fine since it means the source
            // already has highest priority
            if ($preferred && $key = array_search($preferred, $recordSources)) {
                unset($recordSources[$key]);
                array_unshift($recordSources, $preferred);
            }
        }

        // If handling an API call, remove excluded sources so that they don't get
        // become preferred (they will get filtered out of the dedup data later)
        if (isset($_ENV['VUFIND_API_CALL']) && $_ENV['VUFIND_API_CALL']) {
            $config = $this->serviceLocator->get('VuFind\Config');
            $searchConfig = $config->get($this->searchConfig);
            if (isset($searchConfig->Records->apiExcludedSources)) {
                $excluded = explode(',', $searchConfig->Records->apiExcludedSources);
                $resourceSources = array_diff($recordSources, $excluded);
            }
        }

        return array_flip($recordSources);
    }

    /**
     * Fetch local records for all the found dedup records
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function fetchLocalRecords($event)
    {
        parent::fetchLocalRecords($event);

        if (!isset($_ENV['VUFIND_API_CALL']) || !$_ENV['VUFIND_API_CALL']) {
            return;
        }

        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (!isset($searchConfig->Records->apiExcludedSources)) {
            return;
        }
        $excluded = explode(',', $searchConfig->Records->apiExcludedSources);

        $result = $event->getTarget();
        foreach ($result->getRecords() as $record) {
            $fields = $record->getRawData();
            if (!isset($fields['dedup_data'])) {
                continue;
            }
            foreach ($excluded as $item) {
                unset($fields['dedup_data'][$item]);
            }
            $record->setRawData($fields);
        }
    }
}
