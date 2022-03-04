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
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
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
class Backend extends AbstractBackend implements RetrieveBatchInterface
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

        $boostMax = isset($this->config['Blending']['initialResults'])
            ? count($this->config['Blending']['initialResults']->toArray())
            : 0;
        $this->blendLimit = max(20, $boostMax);
        $this->blockSize = $this->config['Blending']['blockSize'] ?? 10;
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
        foreach ($fq ?? [] as $key => $current) {
            if (strncmp($current, 'blender_backend:', 16) === 0) {
                $active = substr($current, 16);
                if (!isset($activeBackends[$active])) {
                    throw new \Exception("Invalid blender_backend filter: $active");
                }
                $activeBackends = [
                    $active => $activeBackends[$active]
                ];
                unset($fq[$key]);
                $params->set('fq', $fq);
            }
        }
        $facetFields = $params->get('facet.field');
        foreach ($facetFields ?? [] as $key => $current) {
            if ('{!ex=blender_backend_filter}blender_backend' === $current) {
                unset($facetFields[$key]);
                $params->set('facet.field', $facetFields);
                break;
            }
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
                    $this->translateQuery($query, $backendId),
                    0,
                    $fetchLimit,
                    $params->get("params_$backendId")[0]
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
                    $params->get("params_$backendAtPos")[0],
                    $this->translateQuery($query, $backendAtPos),
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
                            $params->get("params_$backendId")[0],
                            $this->translateQuery($query, $backendId),
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
     * Retrieve a batch of documents.
     *
     * @param array    $ids    Array of document identifiers
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieveBatch($ids, ParamBag $params = null)
    {
        // TODO: Do we actually need this? Broken at the moment!
        if ($this->primaryBackend instanceof RetrieveBatchInterface) {
            $results = $this->primaryBackend->retrieveBatch($ids, $params);
        } else {
            $results = null;
            foreach ($ids as $id) {
                $primaryResults = $this->primaryBackend->retrieve($id, $params);
                if (null === $results) {
                    $results = $primaryResults;
                } else {
                    $records = $primaryResults->getRecords();
                    if ($records) {
                        $results->add($records[0]);
                    }
                }
            }
        }
        $found = [];
        foreach ($results->getRecords() as $record) {
            $found[] = $record->getUniqueID();
        }
        $missing = array_diff($ids, $found);
        if ($missing) {
            if ($this->secondaryBackend instanceof RetrieveBatchInterface) {
                $secondResults = $this->secondaryBackend->retrieveBatch(
                    $missing,
                    $params
                );
                foreach ($secondResults->getRecords() as $record) {
                    $results->add($record);
                }
            } else {
                foreach ($missing as $id) {
                    $secondResults = $this->secondaryBackend->retrieve($id, $params);
                    $records = $secondResults->getRecords();
                    if ($records) {
                        $results->add($records[0]);
                    }
                }
            }
        }

        return $results;
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
     * @param ParamBag        $params         Search params
     * @param AbstractQuery   $query          Query
     * @param array           $backendRecords Record buffer
     * @param int             $offset         Record offset
     *
     * @return RecordInterface|null
     */
    protected function getRecord(
        AbstractBackend $backend,
        ParamBag $params,
        AbstractQuery $query,
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
     * Translate query to backend format
     *
     * @param AbstractQuery $query     Query
     * @param string        $backendId Backend identifier
     *
     * @return AbstractQuery
     */
    protected function translateQuery(
        AbstractQuery $query,
        string $backendId
    ): AbstractQuery {
        if ($query instanceof Query) {
            $handler = $query->getHandler();
            if (null !== $handler) {
                $mappings = $this->config['Search']['Fields'][$handler]['Mappings']
                    ?? [];
                if ($newHandler = $mappings[$backendId] ?? '') {
                    $query->setHandler($newHandler);
                }
            }
        }
        return $query;
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
