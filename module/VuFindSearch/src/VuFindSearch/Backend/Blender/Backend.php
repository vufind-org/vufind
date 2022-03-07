<?php
/**
 * Blender backend.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019-2022.
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
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\Blender;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordInterface;

/**
 * Blender backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend
{
    /**
     * Actual backends
     *
     * @var array
     */
    protected $backends;

    /**
     * Limit for number of records to blend
     *
     * @var int
     */
    protected $blendLimit;

    /**
     * Block size for interleaved records
     *
     * @var int
     */
    protected $blockSize;

    /**
     * Blender configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Mappings configuration
     *
     * @var array
     */
    protected $mappings;

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $events;

    /**
     * Constructor.
     *
     * @param array                  $backends Actual backends
     * @param \Laminas\Config\Config $config   Blender configuration
     * @param array                  $mappings Mappings configuration
     * @param EventManager           $events   Event manager
     *
     * @return void
     */
    public function __construct(
        array $backends,
        \Laminas\Config\Config $config,
        $mappings,
        EventManager $events
    ) {
        $this->backends = $backends;
        $this->config = $config;
        $this->mappings = $mappings;
        $this->setEventManager($events);

        $boostMax = isset($this->config->Blending->initialResults)
            ? count($this->config->Blending->initialResults->toArray())
            : 0;
        $this->blendLimit = max(20, $boostMax);
        $this->blockSize = $this->config->Blending->blockSize ?? 10;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        $mergedCollection = new Response\Json\RecordCollection(
            $this->config,
            $this->mappings
        );

        $activeBackends = $this->backends;

        // Handle the blender_backend pseudo-facet
        $fq = $params->get('fq');
        $filteredActiveBackends = [];
        foreach ($fq ?? [] as $key => $current) {
            if (strncmp($current, 'blender_backend:', 16) === 0) {
                $active = trim(substr($current, 16), '"');
                if (!isset($activeBackends[$active])) {
                    throw new \Exception(
                        "Invalid blender_backend filter: Backend $active not enabled"
                    );
                }
                $filteredActiveBackends[$active] = $activeBackends[$active];
                unset($fq[$key]);
                $params->set('fq', $fq);
            }
        }
        if ($filteredActiveBackends) {
            $activeBackends = $filteredActiveBackends;
        }
        $facetFields = $params->get('facet.field');
        foreach ($facetFields ?? [] as $key => $current) {
            if ('{!ex=blender_backend_filter}blender_backend' === $current) {
                unset($facetFields[$key]);
                $params->set('facet.field', $facetFields);
                break;
            }
        }

        $translatedQueries = [];
        $translatedParams = [];
        foreach (array_keys($activeBackends) as $backendId) {
            $translatedQueries[$backendId] = $params->get("query_$backendId")[0];
            $translatedParams[$backendId] = $params->get("params_$backendId")[0];
        }

        $collections = [];

        $blendLimit = $this->blendLimit;
        if ($limit === 0) {
            $blendLimit = 0;
        }
        $exception = null;
        // If offset is less than the limit, fetch from backends up to the limit
        // first:
        $fetchLimit = $offset <= $this->blendLimit ? $blendLimit : 0;
        foreach ($activeBackends as $backendId => $backend) {
            try {
                $collections[$backendId] = $backend->search(
                    $translatedQueries[$backendId],
                    0,
                    $fetchLimit,
                    $translatedParams[$backendId]
                );
            } catch (\Exception $e) {
                $exception = $e;
            }
        }

        if ($exception) {
            if (!$collections) {
                // No results and an exception previously encountered, raise it now:
                throw $exception;
            }
            $mergedCollection->addError('search_backend_partial_failure');
        }

        $backendRecords = $mergedCollection->initBlended(
            $collections,
            $offset + $limit,
            $this->blockSize
        );

        if (!$collections) {
            return $mergedCollection;
        }

        // Fill up to the required records in a round-robin fashion
        if ($offset + $limit > $mergedCollection->count()) {
            $backendOffsets = [];
            $collectionOffsets = [];
            $backendTotals = [];
            $availableBackendIds = array_keys($collections);
            foreach ($availableBackendIds as $backendId) {
                $backendOffsets[$backendId] = 0;
                $collectionOffsets[$backendId] = 0;
                $backendTotals[$backendId] = $collections[$backendId]->getTotal();
            }
            // First iterate through the merged records before the offset to
            // calculate proper backend offsets for further records:
            $records = $mergedCollection->getRecords();
            $pos = 0;
            foreach ($records as $record) {
                ++$pos;
                ++$backendOffsets[$record->getSearchBackendIdentifier()];
            }

            // Fetch records
            $backendCount = count($availableBackendIds);
            for (; $pos < $limit + $offset; $pos++) {
                $currentBlock = floor($pos / $this->blockSize);
                $backendAtPos = $availableBackendIds[$currentBlock % $backendCount];

                $offsetOk = $backendOffsets[$backendAtPos]
                    < $backendTotals[$backendAtPos];
                $record = $offsetOk ? $this->getRecord(
                    $activeBackends[$backendAtPos],
                    $translatedQueries[$backendAtPos],
                    $translatedParams[$backendAtPos],
                    $backendRecords[$backendAtPos],
                    $backendOffsets[$backendAtPos]++
                ) : null;

                if (null === $record) {
                    // Try other backends:
                    foreach ($availableBackendIds as $backendId) {
                        if ($backendId === $backendAtPos) {
                            continue;
                        }
                        $offsetOk = $backendOffsets[$backendId]
                            < $backendTotals[$backendId];
                        $record = $offsetOk ? $this->getRecord(
                            $activeBackends[$backendId],
                            $translatedQueries[$backendAtPos],
                            $translatedParams[$backendAtPos],
                            $backendRecords[$backendId],
                            $backendOffsets[$backendId]++
                        ) : null;

                        if (null !== $record) {
                            break;
                        }
                    }
                }

                if (null === $record) {
                    break;
                }
                $mergedCollection->add($record, false);
            }
        }

        $mergedCollection->slice($offset, $limit);
        $mergedCollection->setSourceIdentifier($this->identifier);

        return $mergedCollection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function retrieve($id, ParamBag $params = null)
    {
        foreach ($this->backends as $backend) {
            $result = $backend->retrieve($id, $params);
            if ($result->count() > 0) {
                break;
            }
        }
        return $result;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory()
    {
        return null;
    }

    /**
     * Get a record from the given backend by offset
     *
     * @param AbstractBackend $backend        Backend
     * @param AbstractQuery   $query          Query
     * @param ParamBag        $params         Search params
     * @param array           $backendRecords Record buffer
     * @param int             $offset         Record offset
     *
     * @return RecordInterface|null
     */
    protected function getRecord(
        AbstractBackend $backend,
        AbstractQuery $query,
        ParamBag $params,
        array &$backendRecords,
        $offset
    ): RecordInterface {
        if (!$backendRecords) {
            $collection
                = $backend->search($query, $offset, $this->blockSize, $params);
            $backendRecords = $collection->getRecords();
        }
        return $backendRecords ? array_shift($backendRecords) : null;
    }

    /**
     * Set EventManager instance.
     *
     * @param EventManagerInterface $events Event manager
     *
     * @return void
     * @todo   Deprecate `VuFind\Search' event namespace (2.2)
     */
    protected function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(['VuFind\Search', 'VuFindSearch']);
        $this->events = $events;
    }

    /**
     * Trigger pre-search events for both backends.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() !== $this->getIdentifier()) {
            return $event;
        }

        // Trigger the event for all backends:
        foreach ($this->backends as $backend) {
            $this->convertSearchEvent($event, $backend);
            $this->events->triggerEvent($event);
        }

        // Restore the event and return it:
        return $this->convertSearchEvent($event, $this);
    }

    /**
     * Trigger post-search events for both backends.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() !== $this->getIdentifier()) {
            return $event;
        }

        // Trigger the event for all backends:
        foreach ($this->backends as $backend) {
            $this->convertSearchEvent($event, $backend);
            $this->events->triggerEvent($event);
        }

        // Restore the event and return it:
        return $this->convertSearchEvent($event, $this);
    }

    /**
     * Convert a search event to another backend
     *
     * @param EventInterface   $event   Event
     * @param BackendInterface $backend Target backend
     *
     * @return EventInterface
     */
    protected function convertSearchEvent(
        EventInterface $event,
        BackendInterface $backend
    ): EventInterface {
        $command = $event->getParam('command');
        if (!($command instanceof SearchCommand)) {
            throw new \Exception('Invalid command class');
        }
        $command->setTargetIdentifier($backend->getIdentifier());
        $event->setParam('backend', $backend->getIdentifier());
        $event->setTarget($backend);
        return $event;
    }
}
