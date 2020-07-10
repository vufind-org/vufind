<?php
/**
 * Solr deduplication (merged records) listener.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

use Laminas\EventManager\EventInterface;

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
     * Set up filter for excluding merge children.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $saveEnabled = $this->enabled;
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            // Check that we're not doing a known record search
            $query = $event->getParam('query');
            if ($query && $query->getHandler() === 'id') {
                return $event;
            }
            $params = $event->getParam('params');
            $context = $event->getParam('context');
            $allowedContexts = ['search', 'similar', 'workExpressions', 'getids'];
            if ($params && in_array($context, $allowedContexts)) {
                // Check for a special filter that enables deduplication
                $fq = $params->get('fq');
                if ($fq) {
                    $key = array_search('finna.deduplication:"1"', $fq);
                    if (false === $key) {
                        $key = array_search('(finna.deduplication:"1")', $fq);
                    }
                    if (false !== $key) {
                        $this->enabled = true;
                        $params->set('finna.deduplication', '1');
                        unset($fq[$key]);
                    } else {
                        $key = array_search('finna.deduplication:"0"', $fq);
                        if (false === $key) {
                            $key = array_search('(finna.deduplication:"0")', $fq);
                        }
                        if (false !== $key) {
                            $this->enabled = false;
                            $params->set('finna.deduplication', '0');
                        }
                    }
                    if (false !== $key) {
                        unset($fq[$key]);
                        $params->set('fq', $fq);
                    }
                }
            }
        }
        if ($event->getParam('context') === 'workExpressions') {
            // Handle workExpressions like similar records in the upstream code
            $event->setParam('context', 'similar');
            $result = parent::onSearchPre($event);
            $event->setParam('context', 'workExpressions');
        } else {
            $result = parent::onSearchPre($event);
        }
        $this->enabled = $saveEnabled;
        return $result;
    }

    /**
     * Fetch appropriate dedup child
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $saveEnabled = $this->enabled;

        $backend = $event->getParam('backend');
        if ($backend != $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $event->getParam('context');
        $params = $event->getParam('params');
        if ($params && in_array($context, ['search', 'similar', 'workExpression'])) {
            if ($params->contains('finna.deduplication', '1')) {
                $this->enabled = true;
            } elseif ($params->contains('finna.deduplication', '0')) {
                $this->enabled = false;
            }
        }

        if ($event->getParam('context') === 'workExpressions') {
            // Handle workExpressions like similar records in the upstream code
            $event->setParam('context', 'similar');
            $result = parent::onSearchPost($event);
            $event->setParam('context', 'workExpressions');
        } else {
            $result = parent::onSearchPost($event);
        }
        $this->enabled = $saveEnabled;
        return $result;
    }

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

        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
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
     * @param array $recordSources Record sources defined in searches.ini
     *
     * @return array Array keyed by source with priority as the value
     */
    protected function determineSourcePriority($recordSources)
    {
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
        $mainConfig = $config->get('config');
        // Sort sources alphabetically if necessary
        if (!empty($mainConfig->Record->sort_sources)) {
            $translator
                = $this->serviceLocator->get(\Laminas\Mvc\I18n\Translator::class);
            usort(
                $recordSources,
                function ($a, $b) use ($translator) {
                    $ta = $translator->translate("source_$a");
                    if ("source_$a" === $ta) {
                        $ta = $a;
                    }
                    $tb = $translator->translate("source_$b");
                    if ("source_$b" === $tb) {
                        $tb = $b;
                    }
                    return strcasecmp($ta, $tb);
                }
            );
        }

        // Secondary priority to selected library card
        $authManager = $this->serviceLocator->get(\VuFind\Auth\Manager::class);
        if ($user = $authManager->isLoggedIn()) {
            if ($user->cat_username) {
                list($preferred) = explode('.', $user->cat_username, 2);
                // array_search may return 0, but that's fine since it means the
                // source already has highest priority
                if ($preferred && $key = array_search($preferred, $recordSources)) {
                    unset($recordSources[$key]);
                    array_unshift($recordSources, $preferred);
                }
            }
        }

        // Primary priority to cookie
        $cookieManager
            = $this->serviceLocator->get(\VuFind\Cookie\CookieManager::class);
        if ($cookieManager) {
            $preferred = $cookieManager->get('preferredRecordSource');
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
            $searchConfig = $config->get($this->searchConfig);
            if (isset($searchConfig->Records->apiExcludedSources)) {
                $excluded = explode(',', $searchConfig->Records->apiExcludedSources);
                $recordSources = array_diff($recordSources, $excluded);
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

        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
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
