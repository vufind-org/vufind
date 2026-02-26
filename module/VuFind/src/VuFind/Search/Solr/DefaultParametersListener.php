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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use JsonException;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\NestingParamBag;
use VuFindSearch\Service;

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
    ): void {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
    }

    /**
     * Add default parameters
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event): EventInterface
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() === $this->backend->getIdentifier()) {
            $context = $command->getContext();
            if (empty($context)) {
                $context = null;
            }
            $context = $this->contextMap[$context] ?? $context;
            $defaultParamsText = $this->defaultParams[$context]
                ?? $this->defaultParams['*']
                ?? '';
            if ($defaultParamsText && $params = NestingParamBag::from($command->getSearchParameters())) {
                $command->setSearchParameters($params);
                try {
                    $defaultParams = json_decode($defaultParamsText, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new \Exception(
                        'Default parameters must be expressed in JSON, using Solr\'s JSON Request API '
                        . '(starting in VuFind 12)'
                    );
                }
                foreach ($defaultParams as $name => $param) {
                    $params->addMultiNested($name, $param);
                }
            }
        }
        return $event;
    }
}
