<?php

/**
 * Solr default parameters listener.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\Solr\Backend;

/**
 * Solr default parameters listener.
 *
 * Allows injecting of default parameters depending on request type.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DefaultParametersListener
{
    /**
     * Backend.
     *
     * @var Backend
     */
    protected $backend;

    /**
     * Default parameters
     *
     * @var array
     */
    protected $defaultParams;

    /**
     * Mapping from search methods to contexts
     *
     * @var array
     */
    protected $contextMap = [
        'getIds' => 'search',
        'random' => 'retrieve',
        'retrieveBatch' => 'retrieve',
    ];

    /**
     * Constructor.
     *
     * @param Backend $backend Search backend
     * @param array   $params  Default parameters
     *
     * @return void
     */
    public function __construct(Backend $backend, array $params)
    {
        $this->backend = $backend;
        $this->defaultParams = $params;
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
        $manager->attach(\VuFindSearch\Service::class, 'pre', [$this, 'onSearchPre']);
    }

    /**
     * Add default parameters
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $context = $event->getParam('context');
            $context = $this->contextMap[$context] ?? $context;
            $defaultParams = $this->defaultParams[$context]
                ?? $this->defaultParams['*']
                ?? '';
            if ($defaultParams && $params = $event->getParam('params')) {
                foreach (explode('&', $defaultParams) as $keyVal) {
                    $parts = explode('=', $keyVal, 2);
                    if (!isset($parts[1])) {
                        continue;
                    }
                    $params->add(urldecode($parts[0]), urldecode($parts[1]));
                }
            }
        }
        return $event;
    }
}
