<?php

/**
 * Solr deduplication (merged records) listener.
 *
 * See https://vufind.org/wiki/indexing:deduplication for details on how this is
 * used.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2020.
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
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Psr\Container\ContainerInterface;
use VuFind\Service\GetServiceTrait;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Service;

use function in_array;

/**
 * Solr merged record handling listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeduplicationListener
{
    use GetServiceTrait;

    /**
     * Backend.
     *
     * @var Backend
     */
    protected $backend;

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * Data source configuration file identifier.
     *
     * @var string
     */
    protected $dataSourceConfig;

    /**
     * Whether deduplication is enabled.
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor.
     *
     * @param Backend            $backend          Search backend
     * @param ContainerInterface $serviceLocator   Service locator
     * @param string             $searchConfig     Search config file id
     * @param string             $dataSourceConfig Data source file id
     * @param bool               $enabled          Whether deduplication is
     * enabled
     *
     * @return void
     */
    public function __construct(
        Backend $backend,
        ContainerInterface $serviceLocator,
        $searchConfig,
        $dataSourceConfig = 'datasources',
        $enabled = true
    ) {
        $this->backend = $backend;
        $this->serviceLocator = $serviceLocator;
        $this->searchConfig = $searchConfig;
        $this->dataSourceConfig = $dataSourceConfig;
        $this->enabled = $enabled;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
        $manager->attach(
            Service::class,
            Service::EVENT_POST,
            [$this, 'onSearchPost']
        );
    }

    /**
     * Set up filter for excluding merge children.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() === $this->backend->getIdentifier()) {
            $params = $command->getSearchParameters();
            $context = $command->getContext();
            $contexts = ['search', 'similar', 'getids', 'workExpressions'];
            if ($params && in_array($context, $contexts)) {
                // If deduplication is enabled, filter out merged child records,
                // otherwise filter out dedup records.
                if (
                    $this->enabled && 'getids' !== $context
                    && !$this->hasChildFilter($params)
                ) {
                    $fq = '-merged_child_boolean:true';
                    if ($context == 'similar' && $id = $event->getParam('id')) {
                        $fq .= ' AND -local_ids_str_mv:"'
                            . addcslashes($id, '"') . '"';
                    }
                } else {
                    $fq = '-merged_boolean:true';
                }
                $params->add('fq', $fq);
            }
        }
        return $event;
    }

    /**
     * Check search parameters for child records filter
     *
     * @param \VuFindSearch\ParamBag $params Search parameters
     *
     * @return bool
     */
    public function hasChildFilter($params)
    {
        $filters = $params->get('fq');
        return $filters != null && in_array('merged_child_boolean:true', $filters);
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
        // Inject deduplication details into record objects:
        $command = $event->getParam('command');

        if ($command->getTargetIdentifier() !== $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $command->getContext();
        $contexts = ['search', 'similar', 'workExpressions'];
        if ($this->enabled && in_array($context, $contexts)) {
            $this->fetchLocalRecords($event);
        }
        return $event;
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
        $config = $this->getService(\VuFind\Config\PluginManager::class);
        $dataSourceConfig = $config->get($this->dataSourceConfig);
        $recordSources = $this->getActiveRecordSources($event);
        $sourcePriority = $this->determineSourcePriority($recordSources);
        $command = $event->getParam('command');
        $params = $command->getSearchParameters();
        $buildingPriority = $this->determineBuildingPriority($params);

        $idList = [];
        // Find out the best records and list their IDs:
        $result = $command->getResult();
        foreach ($result->getRecords() as $record) {
            $fields = $record->getRawData();

            if (!isset($fields['merged_boolean'])) {
                continue;
            }
            $localIds = $fields['local_ids_str_mv'];
            $dedupId = $localIds[0];
            $priority = 99999;
            $undefPriority = 99999;
            // Find the document that matches the source priority best:
            $dedupData = [];
            foreach ($localIds as $localId) {
                $localPriority = null;
                [$source] = explode('.', $localId, 2);
                // Ignore ID if source is not in the list of allowed record sources:
                if ($recordSources && !in_array($source, $recordSources)) {
                    continue;
                }
                if (!empty($buildingPriority)) {
                    if (isset($buildingPriority[$source])) {
                        $localPriority = -$buildingPriority[$source];
                    } elseif (isset($dataSourceConfig[$source]['institution'])) {
                        $institution = $dataSourceConfig[$source]['institution'];
                        if (isset($buildingPriority[$institution])) {
                            $localPriority = -$buildingPriority[$institution];
                        }
                    }
                }
                if (!isset($localPriority)) {
                    if (isset($sourcePriority[$source])) {
                        $localPriority = $sourcePriority[$source];
                    } else {
                        $localPriority = ++$undefPriority;
                    }
                }
                if ($localPriority < $priority) {
                    $dedupId = $localId;
                    $priority = $localPriority;
                }
                $dedupData[$source] = [
                    'id' => $localId,
                    'priority' => $localPriority,
                ];
            }
            $fields['dedup_id'] = $dedupId;
            $idList[] = $dedupId;

            // Sort dedupData by priority:
            uasort(
                $dedupData,
                function ($a, $b) {
                    return $a['priority'] - $b['priority'];
                }
            );
            $fields['dedup_data'] = $dedupData;
            $record->setRawData($fields);
        }
        if (empty($idList)) {
            return;
        }

        // Fetch records and assign them to the result:
        $localRecords = $this->backend->retrieveBatch($idList)->getRecords();
        foreach ($result->getRecords() as $record) {
            $dedupRecordData = $record->getRawData();
            if (!isset($dedupRecordData['dedup_id'])) {
                continue;
            }
            // Find the corresponding local record in the results:
            $foundLocalRecord = null;
            foreach ($localRecords as $localRecord) {
                if ($localRecord->getUniqueID() == $dedupRecordData['dedup_id']) {
                    $foundLocalRecord = $localRecord;
                    break;
                }
            }
            if (!$foundLocalRecord) {
                continue;
            }

            $localRecordData = $foundLocalRecord->getRawData();

            // Copy dedup_data for the active data sources:
            foreach ($dedupRecordData['dedup_data'] as $dedupDataKey => $dedupData) {
                if (!$recordSources || isset($sourcePriority[$dedupDataKey])) {
                    $localRecordData['dedup_data'][$dedupDataKey] = $dedupData;
                }
            }

            // Copy fields from dedup record to local record
            $localRecordData = $this->appendDedupRecordFields(
                $localRecordData,
                $dedupRecordData,
                $recordSources,
                $sourcePriority
            );
            $foundLocalRecord->setRawData($localRecordData);
            $foundLocalRecord->setHighlightDetails($record->getHighlightDetails());
            $foundLocalRecord->setLabels($record->getLabels());
            $result->replace($record, $foundLocalRecord);
        }
    }

    /**
     * Get currently active record sources.
     *
     * @param EventInterface $event Event
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getActiveRecordSources($event): array
    {
        $config = $this->getService(\VuFind\Config\PluginManager::class);
        $searchConfig = $config->get($this->searchConfig);
        return !empty($searchConfig->Records->sources)
            ? explode(',', $searchConfig->Records->sources)
            : [];
    }

    /**
     * Append fields from dedup record to the selected local record. Note: the last
     * two parameters are unused in this default method, but they may be useful for
     * custom behavior in subclasses.
     *
     * @param array $localRecordData Local record data
     * @param array $dedupRecordData Dedup record data
     * @param array $recordSources   List of active record sources, empty if all
     * @param array $sourcePriority  Array of source priorities keyed by source id
     *
     * @return array Local record data
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function appendDedupRecordFields(
        $localRecordData,
        $dedupRecordData,
        $recordSources,
        $sourcePriority
    ) {
        $localRecordData['local_ids_str_mv'] = $dedupRecordData['local_ids_str_mv'];
        return $localRecordData;
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
        if (empty($recordSources)) {
            return [];
        }
        return array_flip($recordSources);
    }

    /**
     * Function that determines the priority for buildings
     *
     * @param \VuFindSearch\ParamBag $params Query parameters
     *
     * @return array Array keyed by building with priority as the value
     */
    protected function determineBuildingPriority($params)
    {
        $result = [];
        foreach ($params->get('fq') as $fq) {
            if (preg_match_all('/\bbuilding:"([^"]+)"/', $fq, $matches)) {
                $values = $matches[1];
                foreach ($values as $value) {
                    if (preg_match('/^\d+\/([^\/]+?)\//', $value, $matches)) {
                        // Hierarchical facets; take only first level:
                        $result[] = $matches[1];
                    } else {
                        $result[] = $value;
                    }
                }
            }
        }

        array_unshift($result, '');
        return array_flip($result);
    }
}
