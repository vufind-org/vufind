<?php

/**
 * Manager for search backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use SplObjectStorage;
use UnexpectedValueException;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

use function gettype;
use function is_object;
use function sprintf;

/**
 * Manager for search backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class BackendManager
{
    /**
     * Backend registry.
     *
     * @var ServiceLocatorInterface
     */
    protected $registry;

    /**
     * Attached listeners.
     *
     * @var SplObjectStorage
     */
    protected $listeners;

    /**
     * Constructor.
     *
     * @param ServiceLocatorInterface $registry Backend registry
     *
     * @return void
     */
    public function __construct(ServiceLocatorInterface $registry)
    {
        $this->registry  = $registry;
        $this->listeners = new SplObjectStorage();
    }

    /**
     * Return backend registry.
     *
     * @return ServiceLocatorInterface
     */
    public function getBackendRegistry()
    {
        return $this->registry;
    }

    /**
     * Return named backend.
     *
     * @param string $name Backend name
     *
     * @return BackendInterface
     *
     * @throws UnexpectedValueException Retrieved backend is not an object
     * @throws UnexpectedValueException Retrieved backend does not implement
     * BackendInterface
     */
    public function get($name)
    {
        $backend = $this->registry->get($name);
        if (!is_object($backend)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Expected backend registry to return object, got %s',
                    gettype($backend)
                )
            );
        }
        if (!$backend instanceof BackendInterface) {
            throw new UnexpectedValueException(
                sprintf(
                    'Object of class %s does not implement the expected interface',
                    $backend::class
                )
            );
        }
        $backend->setIdentifier($name);
        return $backend;
    }

    /**
     * Return true if named backend is available.
     *
     * @param string $name Backend name
     *
     * @return bool
     */
    public function has($name)
    {
        return $this->registry->has($name);
    }

    /**
     * Listener for search system event `resolve`.
     *
     * @param EventInterface $e Event object
     *
     * @return BackendInterface|null
     */
    public function onResolve(EventInterface $e)
    {
        $name = $e->getParam('command')->getTargetIdentifier();
        if ($name && $this->has($name)) {
            return $this->get($name);
        }
        return null;
    }

    /**
     * Attach to shared event manager.
     *
     * @param SharedEventManagerInterface $events Shared event manager
     *
     * @return void
     */
    public function attachShared(SharedEventManagerInterface $events)
    {
        if (!$this->listeners->offsetExists($events)) {
            $listener = [$this, 'onResolve'];
            $events->attach(Service::class, Service::EVENT_RESOLVE, $listener);
            $this->listeners->attach($events, $listener);
        }
    }

    /**
     * Detach from shared event manager.
     *
     * @param SharedEventManagerInterface $events Shared event manager
     *
     * @return void
     */
    public function detachShared(SharedEventManagerInterface $events)
    {
        if ($this->listeners->offsetExists($events)) {
            $listener = $this->listeners->offsetGet($events);
            $events->detach($listener, Service::class);
            $this->listeners->detach($events);
        }
    }
}
