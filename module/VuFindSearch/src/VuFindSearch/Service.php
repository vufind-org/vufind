<?php

/**
 * Search service.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Command\GetIdsCommand;
use VuFindSearch\Command\RandomCommand;
use VuFindSearch\Command\RetrieveBatchCommand;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Command\SimilarCommand;
use VuFindSearch\Command\WorkExpressionsCommand;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * Search service.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Service
{
    /**
     * Event identifiers.
     *
     * @var string
     */
    public const EVENT_PRE     = 'pre';
    public const EVENT_POST    = 'post';
    public const EVENT_ERROR   = 'error';
    public const EVENT_RESOLVE = 'resolve';

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
     * @param EventManagerInterface $events Event manager (optional)
     *
     * @return void
     */
    public function __construct(EventManagerInterface $events = null)
    {
        if (null !== $events) {
            $this->setEventManager($events);
        }
        $this->backends = [];
    }

    /**
     * Invoke a command.
     *
     * @param CommandInterface $command Command
     *
     * @return CommandInterface
     */
    public function invoke(CommandInterface $command)
    {
        // The backend instance is no longer added as an event parameter.
        // All other legacy event parameters are accessible via the command object.
        $args = ['command' => $command];

        $backendInstance = $this->resolve($command->getTargetBackendName(), $args);

        $this->triggerPre($command, $args);
        try {
            $command->execute($backendInstance);
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($command, $args);

        return $command;
    }

    /**
     * Perform a search and return a wrapped response.
     *
     * @param string              $backend Search backend identifier
     * @param Query\AbstractQuery $query   Search query
     * @param int                 $offset  Search offset
     * @param int                 $limit   Search limit
     * @param ParamBag            $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(SearchCommand $command) instead
     */
    public function search($backend, Query\AbstractQuery $query, $offset = 0,
        $limit = 20, ParamBag $params = null
    ) {
        $command = new SearchCommand($backend, $query, $offset, $limit, $params);
        return $this->legacyInvoke(
            $command, ['query' => $query, 'offset' => $offset, 'limit' => $limit]
        );
    }

    /**
     * Perform a search that returns record IDs and return a wrapped response.
     *
     * @param string              $backend Search backend identifier
     * @param Query\AbstractQuery $query   Search query
     * @param int                 $offset  Search offset
     * @param int                 $limit   Search limit
     * @param ParamBag            $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(GetIdsCommand $command) instead
     */
    public function getIds($backend, Query\AbstractQuery $query, $offset = 0,
        $limit = 20, ParamBag $params = null
    ) {
        $command = new GetIdsCommand($backend, $query, $offset, $limit, $params);
        return $this->legacyInvoke(
            $command, ['query' => $query, 'offset' => $offset, 'limit' => $limit]
        );
    }

    /**
     * Retrieve a single record.
     *
     * @param string   $backend Search backend identifier
     * @param string   $id      Record identifier
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(RetrieveCommand $command) instead
     */
    public function retrieve($backend, $id, ParamBag $params = null)
    {
        $command = new RetrieveCommand($backend, $id, $params);
        return $this->legacyInvoke($command, ['id' => $id]);
    }

    /**
     * Retrieve a batch of records.
     *
     * @param string   $backend Search backend identifier
     * @param array    $ids     Record identifier
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(RetrieveBatchCommand $command) instead
     */
    public function retrieveBatch($backend, $ids, ParamBag $params = null)
    {
        $command = new RetrieveBatchCommand($backend, $ids, $params);
        return $this->legacyInvoke($command, ['ids' => $ids]);
    }

    /**
     * Retrieve a random batch of records.
     *
     * @param string              $backend Search backend identifier
     * @param Query\AbstractQuery $query   Search query
     * @param int                 $limit   Search limit
     * @param ParamBag            $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(RandomCommand $command) instead
     */
    public function random($backend, $query, $limit = 20, $params = null)
    {
        $command = new RandomCommand($backend, $query, $limit, $params);
        return $this->legacyInvoke($command, ['query' => $query, 'limit' => $limit]);
    }

    /**
     * Return similar records.
     *
     * @param string   $backend Search backend identifier
     * @param string   $id      Id of record to compare with
     * @param ParamBag $params  Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(SimilarCommand $command) instead
     */
    public function similar($backend, $id, ParamBag $params = null)
    {
        $command = new SimilarCommand($backend, $id, $params);
        return $this->legacyInvoke($command, ['id' => $id]);
    }

    /**
     * Return records for work expressions.
     *
     * @param string   $backend  Search backend identifier
     * @param string   $id       Id of record to compare with
     * @param array    $workKeys Work identification keys (optional; retrieved from
     * the record to compare with if not specified)
     * @param ParamBag $params   Search backend parameters
     *
     * @return RecordCollectionInterface
     *
     * @deprecated Use Service::invoke(WorkExpressionsCommand $command) instead
     */
    public function workExpressions($backend, $id, $workKeys = null,
        ParamBag $params = null
    ) {
        $command = new WorkExpressionsCommand($backend, $id, $workKeys, $params);
        return $this->legacyInvoke($command, ['id' => $id, 'workKeys' => $workKeys]);
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
     * Invoke a command triggering deprecated legacy events and return the result.
     *
     * @param CommandInterface $command Command
     * @param array            $args    Additional event parameters
     *
     * @return mixed
     */
    protected function legacyInvoke(CommandInterface $command, array $args = [])
    {
        $backend = $command->getTargetBackendName();
        $params = $command->getSearchParameters();
        $context = $command->getContext();
        $args = array_merge(
            compact('backend', 'params', 'context', 'command'), $args
        );

        $backendInstance = $this->resolve($backend, $args);
        $args['backend_instance'] = $backendInstance;

        $this->triggerPre($backendInstance, $args);
        try {
            $response = $command->execute($backendInstance)->getResult();
        } catch (BackendException $e) {
            $this->triggerError($e, $args);
            throw $e;
        }
        $this->triggerPost($response, $args);

        return $response;
    }

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
            $response = $this->getEventManager()->triggerUntil(
                function ($o) {
                    return $o instanceof BackendInterface;
                },
                self::EVENT_RESOLVE,
                $this,
                $args
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
     * @param mixed $target Command object, or backend instance for deprecated
     *                      legacy events
     * @param array $args   Event arguments
     *
     * @return void
     */
    protected function triggerPre($target, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_PRE, $target, $args);
    }

    /**
     * Trigger the post event.
     *
     * @param mixed $target Command object, or backend response for deprecated
     *                      legacy events
     * @param array $args   Event arguments
     *
     * @return void
     */
    protected function triggerPost($target, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_POST, $target, $args);
    }
}
