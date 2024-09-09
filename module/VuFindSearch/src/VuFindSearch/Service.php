<?php

/**
 * Search service.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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

use Laminas\EventManager\EventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Command\CommandInterface;

use function sprintf;

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
    use \VuFindSearch\Feature\SearchBackendEventManagerTrait;

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
     * Cache resolved backends.
     *
     * @var array
     */
    protected $backends;

    /**
     * Constructor.
     *
     * @param ?EventManagerInterface $events Event manager (optional)
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

        $backend = $this->resolve($command->getTargetIdentifier(), $args);

        $this->triggerPre($this, $args);
        try {
            $command->execute($backend);
        } catch (BackendException $e) {
            $args['error'] = $e;
            $this->triggerError($this, $args);
            throw $e;
        }
        $this->triggerPost($this, $args);

        return $command;
    }

    /**
     * Resolve a backend.
     *
     * @param string            $backendId Backend name
     * @param array|ArrayAccess $args      Service function arguments
     *
     * @return BackendInterface
     *
     * @throws Exception\RuntimeException Unable to resolve backend
     */
    protected function resolve($backendId, $args)
    {
        if (!isset($this->backends[$backendId])) {
            $response = $this->getEventManager()->triggerUntil(
                function ($o) {
                    return $o instanceof BackendInterface;
                },
                self::EVENT_RESOLVE,
                $this,
                $args
            );
            if (!$response->stopped()) {
                // We need to construct our error message differently depending
                // on whether or not we have a command object...
                $context = isset($args['command'])
                    ? $args['command']->getContext()
                    : ($args['context'] ?? 'null');
                $backendId = isset($args['command'])
                    ? $args['command']->getTargetIdentifier()
                    : ($args['backend'] ?? $backendId);
                throw new Exception\RuntimeException(
                    sprintf(
                        'Unable to resolve backend: %s, %s',
                        $context,
                        $backendId
                    )
                );
            }
            $this->backends[$backendId] = $response->last();
        }
        return $this->backends[$backendId];
    }

    /**
     * Trigger the error event.
     *
     * @param mixed $target Service instance, or error exception for deprecated
     *                      legacy events
     * @param array $args   Event arguments
     *
     * @return void
     */
    public function triggerError($target, $args)
    {
        $this->getEventManager()->trigger(self::EVENT_ERROR, $target, $args);
    }

    /**
     * Trigger the pre event.
     *
     * @param mixed $target Service instance, or backend instance for deprecated
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
     * @param mixed $target Service instance, or backend response for deprecated
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
