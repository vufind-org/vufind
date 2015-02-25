<?php

/**
 * Search service.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Response\RecordCollectionInterface;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;

/**
 * Search service.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Service
{
    /**
     * Event identifiers.
     *
     * @var string
     */
    const EVENT_PRE     = 'pre';
    const EVENT_POST    = 'post';
    const EVENT_ERROR   = 'error';
    const EVENT_RESOLVE = 'resolve';

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $events;

    /**
     * Cache resolved backends.
     *
     * @var array
     */
    protected $backends;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->backends = [];
    }

    /**
     * Perform a search and return a wrapped response.
     *
     * @param string              $backend Search backend identifier
     * @param Query\AbstractQuery $query   Search query
     * @param integer             $offset  Search offset
     * @param integer             $limit   Search limit
     * @param ParamBag            $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search($backend, Query\AbstractQuery $query, $offset = 0,
        $limit = 20, ParamBag $params = null
    ) {
        $params  = $params ?: new ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'query', 'offset', 'limit', 'params', 'context');
        $backend  = $this->resolve($backend, $args);
        $args['backend_instance'] = $backend;

        $this->triggerPre($backend, $args);
        try {
            $response = $backend->search($query, $offset, $limit, $params);
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($response, $args);
        return $response;
    }

    /**
     * Retrieve a single record.
     *
     * @param string   $backend Search backend identifier
     * @param string   $id      Record identifier
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieve($backend, $id, ParamBag $params = null)
    {
        $params  = $params ?: new ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'id', 'params', 'context');
        $backend = $this->resolve($backend, $args);
        $args['backend_instance'] = $backend;

        $this->triggerPre($backend, $args);
        try {
            $response = $backend->retrieve($id, $params);
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($response, $args);
        return $response;
    }

    /**
     * Retrieve a batch of records.
     *
     * @param string   $backend Search backend identifier
     * @param array    $ids     Record identifier
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieveBatch($backend, $ids, ParamBag $params = null)
    {
        $params  = $params ?: new ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'ids', 'params', 'context');
        $backend = $this->resolve($backend, $args);
        $args['backend_instance'] = $backend;

        $this->triggerPre($backend, $args);

        // If the backend implements the RetrieveBatchInterface, we can load
        // all the records at once; otherwise, we need to load them one at a
        // time and aggregate them:
        if ($backend instanceof RetrieveBatchInterface) {
            try {
                $response = $backend->retrieveBatch($ids, $params);
            } catch (BackendException $e) {
                $this->triggerError($e, $args);
                throw $e;
            }
        } else {
            $response = false;
            foreach ($ids as $id) {
                try {
                    $next = $backend->retrieve($id, $params);
                } catch (BackendException $e) {
                    $this->triggerError($e, $args);
                    throw $e;
                }
                if (!$response) {
                    $response = $next;
                } else if ($record = $next->first()) {
                    $response->add($record);
                }
            }
        }

        $this->triggerPost($response, $args);
        return $response;
    }

    /**
     * Retrieve a random batch of records.
     *
     * @param string              $backend Search backend identifier
     * @param Query\AbstractQuery $query   Search query
     * @param integer             $limit   Search limit
     * @param ParamBag            $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function random($backend, $query, $limit = 20, $params = null)
    {
        $params  = $params ?: new ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'query', 'limit', 'params', 'context');
        $backend = $this->resolve($backend, $args);
        $args['backend_instance'] = $backend;

        $this->triggerPre($backend, $args);

        // If the backend implements the RetrieveRandomInterface, we can load
        // all the records at once; otherwise, we need to load them one at a
        // time and aggregate them:
        if ($backend instanceof RandomInterface) {
            try {
                $response = $backend->random($query, $limit, $params);
            } catch (BackendException $e) {
                $this->triggerError($e, $args);
                throw $e;
            }
        } else {
            // offset/limit of 0 - we don't need records, just count
            try {
                $results = $backend->search($query, 0, 0, $params);
            } catch (BackendException $e) {
                $this->triggerError($e, $args);
                throw $e;
            }
            $total_records = $results->getTotal();

            if (0 === $total_records) {
                // Empty result? Send back as-is:
                $response = $results;
            } elseif ($total_records < $limit) {
                // Result set smaller than limit? Get everything and shuffle:
                try {
                     $response = $backend->search($query, 0, $limit, $params);
                } catch (BackendException $e) {
                    $this->triggerError($e, $args);
                    throw $e;
                }
                $response->shuffle();
            } else {
                // Default case: retrieve n random records:
                $response = false;
                $retrievedIndexes = [];
                for ($i = 0; $i < $limit; $i++) {
                    $nextIndex = rand(0, $total_records - 1);
                    while (in_array($nextIndex, $retrievedIndexes)) {
                        // avoid duplicate records
                        $nextIndex = rand(0, $total_records - 1);
                    }
                    $retrievedIndexes[] = $nextIndex;
                    try {
                        $currentBatch = $backend->search(
                            $query, $nextIndex, 1, $params
                        );
                    } catch (BackendException $e) {
                        $this->triggerError($e, $args);
                        throw $e;
                    }
                    if (!$response) {
                        $response = $currentBatch;
                    } else if ($record = $currentBatch->first()) {
                        $response->add($record);
                    }
                }
            }
        }
        $this->triggerPost($response, $args);
        return $response;
    }

    /**
     * Return similar records.
     *
     * @param string   $backend Search backend identifier
     * @param string   $id      Id of record to compare with
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function similar($backend, $id, ParamBag $params = null)
    {
        $params  = $params ?: new ParamBag();
        $context = __FUNCTION__;
        $args = compact('backend', 'id', 'params', 'context');
        $backendInstance = $this->resolve($backend, $args);
        $args['backend_instance'] = $backendInstance;

        $this->triggerPre($backendInstance, $args);
        try {
            if (!($backendInstance instanceof Feature\SimilarInterface)) {
                throw new BackendException("$backend does not support similar()");
            }
            $response = $backendInstance->similar($id, $params);
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($response, $args);
        return $response;
    }

    /**
     * Set EventManager instance.
     *
     * @param EventManagerInterface $events Event manager
     *
     * @return void
     * @todo   Deprecate `VuFind\Search' event namespace (2.2)
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(['VuFind\Search', 'VuFindSearch']);
        $this->events = $events;
    }

    /**
     * Return EventManager instance.
     *
     * Lazy loads a new EventManager if none was set.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /// Internal API

    /**
     * Resolve a backend.
     *
     * @param string            $backend Backend name
     * @param array|ArrayAccess $args    Service function arguments
     *
     * @return BackendInterface
     *
     * @throws Exception\RuntimeException Unable to resolve backend
     */
    protected function resolve($backend, $args)
    {
        if (!isset($this->backends[$backend])) {
            $response = $this->getEventManager()->trigger(
                self::EVENT_RESOLVE,
                $this,
                $args,
                function ($o) {
                    return ($o instanceof BackendInterface);
                }
            );
            if (!$response->stopped()) {
                throw new Exception\RuntimeException(
                    sprintf(
                        'Unable to resolve backend: %s, %s', $args['context'],
                        $args['backend']
                    )
                );
            }
            $this->backends[$backend] = $response->last();
        }
        return $this->backends[$backend];
    }

    /**
     * Trigger the error event.
     *
     * @param BackendException $exception Error exception
     * @param array            $args      Event arguments
     *
     * @return void
     */
    public function triggerError(BackendException $exception, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_ERROR, $exception, $args);
    }

    /**
     * Trigger the pre event.
     *
     * @param BackendInterface $backend Selected backend
     * @param array            $args    Event arguments
     *
     * @return void
     */
    protected function triggerPre(BackendInterface $backend, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_PRE, $backend, $args);
    }

    /**
     * Trigger the post event.
     *
     * @param mixed $response Backend response
     * @param array $args     Event arguments
     *
     * @return void
     */
    protected function triggerPost($response, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_POST, $response, $args);
    }

}