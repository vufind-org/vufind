<?php

/**
 * Blender backend.
 *
 * PHP version 8
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
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Blender\Response\Json\RecordCollection;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordInterface;

use function count;
use function intval;

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
    use \VuFindSearch\Feature\SearchBackendEventManagerTrait;

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
     * Adaptive block sizes for interleaved records
     *
     * @var array
     */
    protected $adaptiveBlockSizes;

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
        $this->blockSize = intval($this->config->Blending->blockSize ?? 10);
        $this->adaptiveBlockSizes
            = isset($this->config->Blending->adaptiveBlockSizes)
            ? $this->config->Blending->adaptiveBlockSizes->toArray()
            : [];
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
        $mergedCollection = $this->createRecordCollection();

        $backendDetails = [];
        $activeBackends = $this->getActiveBackends(
            $params,
            $mergedCollection->getFacetDelimiter('blender_backend')
        );
        foreach ($activeBackends as $backendId => $backend) {
            $backendDetails[$backendId] = [
                'backend' => $backend,
                'query' => $params->get("query_$backendId")[0],
                'params' => $params->get("params_$backendId")[0],
            ];
        }
        if (!$backendDetails) {
            return $mergedCollection;
        }

        $blendLimit = $limit === 0 ? 0 : $this->blendLimit;
        // Fetch records from backends up to the number of initially boosted records:
        $collections = [];
        $exceptions = [];
        foreach ($backendDetails as $backendId => $details) {
            try {
                $collections[$backendId] = $details['backend']->search(
                    $details['query'],
                    0,
                    $blendLimit,
                    $details['params']
                );
            } catch (\Exception $e) {
                $exceptions[$backendId] = $e;
            }
        }

        $this->processBackendFailures(
            $mergedCollection,
            $exceptions,
            !empty($collections)
        );

        $totalCount = 0;
        foreach ($collections as $collection) {
            $totalCount += $collection->getTotal();
        }
        $blockSize = $this->getBlockSize($totalCount);

        $backendRecords = $mergedCollection->initBlended(
            $collections,
            $blendLimit,
            $blockSize,
            $totalCount
        );

        if ($limit) {
            $this->fillMergedCollection(
                $mergedCollection,
                $collections,
                $backendDetails,
                $backendRecords,
                $offset + $limit,
                $blockSize
            );
        }

        $mergedCollection->slice($offset, $limit);

        return $mergedCollection;
    }

    /**
     * Process any backend exceptions and throw an exception if all failed or add an
     * error message if some of them failed.
     *
     * @param RecordCollection $mergedCollection Result collection
     * @param array            $exceptions       Exceptions
     * @param bool             $haveResults      Whether any results are available
     *
     * @return void
     * @throws \Exception
     */
    protected function processBackendFailures(
        RecordCollection $mergedCollection,
        array $exceptions,
        bool $haveResults
    ): void {
        $failedBackends = [];
        foreach ($exceptions as $backendId => $exception) {
            // Throw exception right away if we didn't get any results or the query
            // is invalid for a backend:
            if (!$haveResults) {
                // No results and an exception previously encountered, raise it now:
                throw $exception;
            }
            // Log the errors and collect a list to display to the user:
            $this->logError("Search in $backendId failed: " . (string)$exception);
            $failedBackends[] = $this->config->Backends[$backendId];
        }
        if ($failedBackends) {
            $mergedCollection->addError(
                [
                    'msg' => 'search_backend_partial_failure',
                    'tokens' => [
                        '%%sources%%' => implode(', ', $failedBackends),
                    ],
                ]
            );
        }
    }

    /**
     * Create record collection.
     *
     * @return Response\Json\RecordCollection
     */
    protected function createRecordCollection(): Response\Json\RecordCollection
    {
        $collection = new Response\Json\RecordCollection(
            $this->config,
            $this->mappings
        );
        $collection->setSourceIdentifier($this->identifier);
        return $collection;
    }

    /**
     * Add records to the merged collection in a round-robin fashion up to the
     * specified limit
     *
     * @param RecordCollectionInterface $mergedCollection Merged collection
     * @param array                     $collections      Source collections
     * @param array                     $backendDetails   Active backend details
     * @param array                     $backendRecords   Backend record buffers
     * @param int                       $limit            Record limit
     * @param int                       $blockSize        Block size
     *
     * @return void
     */
    protected function fillMergedCollection(
        RecordCollectionInterface $mergedCollection,
        array $collections,
        array $backendDetails,
        array $backendRecords,
        int $limit,
        int $blockSize
    ): void {
        // Fill up to the required records in a round-robin fashion
        if ($limit <= $mergedCollection->count()) {
            return;
        }

        $backendOffsets = [];
        $backendTotals = [];
        $availableBackendIds = array_keys($collections);
        foreach ($availableBackendIds as $backendId) {
            $backendOffsets[$backendId] = 0;
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
        for (; $pos < $limit; $pos++) {
            $currentBlock = floor($pos / $blockSize);
            $backendAtPos = $availableBackendIds[$currentBlock % $backendCount];

            $offsetOk = $backendOffsets[$backendAtPos]
                < $backendTotals[$backendAtPos];
            $record = $offsetOk ? $this->getRecord(
                $backendDetails[$backendAtPos],
                $backendRecords[$backendAtPos],
                $backendOffsets[$backendAtPos]++,
                $blockSize
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
                        $backendDetails[$backendId],
                        $backendRecords[$backendId],
                        $backendOffsets[$backendId]++,
                        $blockSize
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
        throw new \Exception('Blender does not support retrieve');
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
        throw new \Exception('getRecordCollectionFactory not supported in Blender');
    }

    /**
     * Get active backends for a search
     *
     * @param ?ParamBag $params    Search backend parameters
     * @param string    $delimiter Delimiter for the blender_backend facet
     *
     * @return array
     */
    protected function getActiveBackends(?ParamBag $params, string $delimiter): array
    {
        if (null === $params) {
            // Can't do anything without backend params..
            return [];
        }

        $activeBackends = $this->backends;

        // Handle the blender_backend pseudo-filter
        $fq = $params->get('fq') ?? [];
        $filteredBackends = [];
        // Handle AND and OR filters first:
        foreach ($fq as $filter) {
            $advancedOr = preg_match(
                '/\{!tag=blender_backend_filter}blender_backend:\((.+)\)/',
                $filter,
                $matches
            );
            if ($advancedOr) {
                $filter = explode(' OR ', $matches[1]);
            }
            foreach ((array)$filter as $current) {
                if (strncmp($current, 'blender_backend:', 16) === 0) {
                    $active = trim(substr($current, 16), '"');
                    if ($delimiter) {
                        [$active] = explode($delimiter, $active, 2);
                    }
                    if (!isset($activeBackends[$active])) {
                        $this->logWarning(
                            "Invalid blender_backend filter: Backend $active not"
                            . ' enabled'
                        );
                    } else {
                        $filteredBackends[$active] = $activeBackends[$active];
                    }
                }
            }
        }
        if ($filteredBackends) {
            $activeBackends = $filteredBackends;
        }
        // Handle NOT filters last:
        foreach ($fq as $current) {
            if (strncmp($current, '-blender_backend:', 17) === 0) {
                $disabled = trim(substr($current, 17), '"');
                if ($delimiter) {
                    [$disabled] = explode($delimiter, $disabled, 2);
                }
                if (isset($activeBackends[$disabled])) {
                    unset($activeBackends[$disabled]);
                }
            }
        }

        return $activeBackends;
    }

    /**
     * Get next record from the given backend.
     *
     * Gets next records from the previously retrieved array of records or retrieves
     * a new batch of records from the backend.
     *
     * @param array $backendDetails Details for the backend
     * @param array $backendRecords Record buffer
     * @param int   $offset         Record offset
     * @param int   $blockSize      Blending block size
     *
     * @return RecordInterface|null
     */
    protected function getRecord(
        array $backendDetails,
        array &$backendRecords,
        int $offset,
        int $blockSize
    ): ?RecordInterface {
        if (!$backendRecords) {
            $collection = $backendDetails['backend']->search(
                $backendDetails['query'],
                $offset,
                max($blockSize, 20),
                $backendDetails['params']
            );
            $backendRecords = $collection->getRecords();
        }
        return $backendRecords ? array_shift($backendRecords) : null;
    }

    /**
     * Get the block size for the given result count
     *
     * @param int $resultCount Result count
     *
     * @return int
     */
    protected function getBlockSize(int $resultCount): int
    {
        foreach ($this->adaptiveBlockSizes as $size) {
            $parts = explode(':', $size, 2);
            $blockSize = intval($parts[1] ?? 0);
            if ($blockSize === 0) {
                throw new \Exception("Invalid adaptive block size: $size");
            }
            $rangeParts = explode('-', $parts[0]);
            $from = intval($rangeParts[0]);
            $to = intval($rangeParts[1] ?? 0);
            if ($from > $to) {
                throw new \Exception("Invalid adaptive block size: $size");
            }
            if ($from <= $resultCount && $resultCount <= $to) {
                return $blockSize;
            }
        }
        return $this->blockSize;
    }

    /**
     * Trigger pre-search events for all backends.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event): EventInterface
    {
        return $this->triggerSearchEvent($event);
    }

    /**
     * Trigger post-search events for all backends.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event): EventInterface
    {
        return $this->triggerSearchEvent($event);
    }

    /**
     * Trigger pre-search events for all backends.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    protected function triggerSearchEvent(EventInterface $event)
    {
        $command = $event->getParam('command');
        if (
            $command->getTargetIdentifier() !== $this->getIdentifier()
            || !($command instanceof SearchCommand)
        ) {
            return $event;
        }

        // Trigger the event for all backends:
        foreach ($this->backends as $id => $backend) {
            $this->convertSearchEvent($event, $command, $backend);
            $this->events->triggerEvent($event);
            $this->collectEventResults($command, $event->getParam('command'), $id);
        }

        // Restore the event and return it:
        $event->setParam('command', $command);
        $event->setParam('backend', $this->getIdentifier());
        $event->setTarget($this);
        return $event;
    }

    /**
     * Collect results back into the Command after an event has been processed
     *
     * @param SearchCommand $command        Search command
     * @param SearchCommand $backendCommand Backend-specific command
     * @param string        $backendId      Backend identifier
     *
     * @return void
     */
    protected function collectEventResults(
        SearchCommand $command,
        SearchCommand $backendCommand,
        string $backendId
    ): void {
        $command->getSearchParameters()->set(
            "query_$backendId",
            $backendCommand->getQuery()
        );
        $command->getSearchParameters()->set(
            "params_$backendId",
            $backendCommand->getSearchParameters()
        );
    }

    /**
     * Convert a search event to another backend
     *
     * @param EventInterface   $event   Event
     * @param SearchCommand    $command Search command
     * @param BackendInterface $backend Target backend
     *
     * @return EventInterface
     */
    protected function convertSearchEvent(
        EventInterface $event,
        SearchCommand $command,
        BackendInterface $backend
    ): EventInterface {
        $backendId = $backend->getIdentifier();

        $newCommand = clone $command;
        $newCommand->setTargetIdentifier($backendId);
        $params = $command->getSearchParameters();
        $newCommand->setQuery($params->get("query_$backendId")[0]);
        $newCommand->setSearchParameters($params->get("params_$backendId")[0]);

        $event->setParam('command', $newCommand);
        $event->setParam('backend', $backendId);
        $event->setTarget($backend);
        return $event;
    }
}
