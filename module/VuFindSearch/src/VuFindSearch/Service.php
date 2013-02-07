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

use Zend\Log\LoggerInterface;
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
     * Event manager.
     *
     * @var EventManager
     */
    protected $events;

    /**
     * Logger, if any.
     *
     * @var LoggerInterface
     */
    protected $logger;

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
    public function __construct ()
    {
        $this->backends = array();
    }

    /**
     * Perform a search and return a wrapped response.
     *
     * @param string        $backend Search backend identifier
     * @param AbstractQuery $query   Search query
     * @param Params        $params  Search parameters
     *
     * @return ResponseInterface
     *
     */
    public function search ($backend, Query\AbstractQuery $query, Query\Params $params)
    {
        $context = __FUNCTION__;
        $args = compact('backend', 'query', 'params', 'context');
        $backend  = $this->resolve($backend, $args);

        $this->triggerPre($context, $backend, $args);
        $response = $backend->search($query, $params);
        $this->triggerPost($context, $response, $args);
        return $response;
    }

    /**
     * Retrieve a single record.
     *
     * @param string $backend Search backend identifier
     * @param string $id      Record identifier
     *
     * @return ResponseInterface
     */
    public function retrieve ($backend, $id)
    {
        $context = __FUNCTION__;
        $args = compact('backend', 'id', 'context');
        $backend = $this->resolve($backend, $args);

        $this->triggerPre($context, $backend, $args);
        $response = $backend->retrieve($id);
        $this->triggerPost($context, $response, $args);
        return $response;
    }

    /**
     * Delete a single record.
     *
     * @param string $backend Search backend identifier
     * @param string $id      Record identifier
     *
     * @return null
     */
    public function delete ($backend, $id)
    {
        $context = __FUNCTION__;
        $args = compact('backend', 'id', 'context');
        $backend = $this->resolve($backend, $args);

        $this->triggerPre($context, $backend, $args);
        $response = $backend->delete($id);
        $this->triggerPost($context, $response, $args);
    }

    /**
     * Delete all records.
     *
     * @param string $backend Search backend identifier
     *
     * @return null
     */
    public function deleteAll ($backend)
    {
        $context = __FUNCTION__;
        $args = compact('backend', 'context');
        $backend = $this->resolve($backend, $args);

        $this->triggerPre($context, $backend, $args);
        $response = $backend->deleteAll();
        $this->triggerPost($context, $response, $args);
        return $response;
    }

    /**
     * Update a record.
     *
     * @param string          $backend Search backend identifier
     * @param RecordInterface $record  The record to update
     *
     * @return void
     */
    public function update ($backend, RecordInterface $record)
    {
        $context = __FUNCTION__;
        $args = compact('backend', 'record', 'context');
        $backend = $this->resolve($backend, $args);

        $this->triggerPre($context, $backend, $args);
        $response = $backend->update($record);
        $this->triggerPost($context, $response, $args);
    }

    /**
     * Set application logger.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger (LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Set EventManager instance.
     *
     * @param EventManagerInterface $events Event manager
     *
     * @return void
     */
    public function setEventManager (EventManagerInterface $events)
    {
        $events->setIdentifiers('VuFind\Search');
        $this->events = $events;
    }

    /**
     * Return EventManager instance.
     *
     * Lazy loads a new EventManager if none was set.
     *
     * @return EventManagerInterface
     */
    public function getEventManager ()
    {
        if (!$this->events) {
            $this->events = new EventManager('VuFind\Search');
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
    protected function resolve ($backend, $args)
    {
        if (!isset($this->backends[$backend])) {
            $response = $this->getEventManager()->trigger(
                "resolve",
                $this,
                $args,
                function ($o) {
                    return ($o instanceOf BackendInterface);
                }
            );
            if (!$response->stopped()) {
                throw new Exception\RuntimeException(
                    sprintf('Unable to resolve backend: %s, %s', $args['context'], $args['backend'])
                );
            }
            $this->backends[$backend] = $response->last();
        }
        return $this->backends[$backend];
    }

    /**
     * Trigger the pre event.
     *
     * @param string           $context Service context
     * @param BackendInterface $backend Selected backend
     * @param array            $args    Event arguments
     *
     * @return void
     */
    protected function triggerPre ($context, BackendInterface $backend, $args)
    {
        $this->getEventManager()->trigger($context . '.pre', $backend, $args);
    }

    /**
     * Trigger the post event.
     *
     * @param string $context  Service context
     * @param mixed  $response Backend response
     * @param array  $args     Event arguments
     *
     * @return void
     */
    protected function triggerPost ($context, $response, $args)
    {
        $this->getEventManager()->trigger($context . '.post', $response, $args);
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Log context
     *
     * @return void
     */
    protected function log ($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
    }
}