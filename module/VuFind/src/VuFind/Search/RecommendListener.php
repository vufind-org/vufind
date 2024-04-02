<?php

/**
 * Recommend listener.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFind\Recommend\PluginManager;

/**
 * Recommend listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecommendListener
{
    /**
     * Recommendation configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Recommendation modules, indexed by location.
     *
     * @var array
     */
    protected $objects = [];

    /**
     * Recommendation module plugin manager.
     *
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * The ID of the search for which this listener should respond. Value is set
     * by \VuFind\Search\SearchRunner and makes sure that each search run by the
     * runner is handled by its own independent RecommendListener. Otherwise,
     * the wrong recommendations might be injected into the wrong objects!
     *
     * @var int
     */
    protected $searchId;

    /**
     * Constructor.
     *
     * @param PluginManager $pluginManager Plugin manager for recommendation
     * modules
     * @param int           $searchId      The ID of the search for which this
     * listener should respond
     */
    public function __construct(PluginManager $pluginManager, $searchId)
    {
        $this->pluginManager = $pluginManager;
        $this->searchId = $searchId;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            \VuFind\Search\SearchRunner::class,
            SearchRunner::EVENT_CONFIGURED,
            [$this, 'onSearchConfigured']
        );
        $manager->attach(
            \VuFind\Search\SearchRunner::class,
            SearchRunner::EVENT_COMPLETE,
            [$this, 'onSearchComplete']
        );
    }

    /**
     * Set configuration
     *
     * @param array $config Configuration array
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Set up recommendation modules.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchConfigured(EventInterface $event)
    {
        // Make sure we're triggering in the appropriate context:
        if ($this->searchId != $event->getParam('runningSearchId')) {
            return;
        }
        $params = $event->getParam('params');
        $request = $event->getParam('request');

        // Process recommendations for each location:
        $this->objects = [
            'top' => [], 'side' => [], 'noresults' => [],
            'bottom' => [],
        ];
        foreach ($this->config as $location => $currentSet) {
            // If the current location is disabled, skip processing!
            if (empty($currentSet)) {
                continue;
            }
            // Now loop through all recommendation settings for the location.
            foreach ((array)$currentSet as $current) {
                // Break apart the setting into module name and extra parameters:
                $current = explode(':', $current);
                $module = array_shift($current);
                if (empty($module)) {
                    continue;
                }
                $config = implode(':', $current);
                if (!$this->pluginManager->has($module)) {
                    throw new \Exception(
                        'Could not load recommendation module: ' . $module
                    );
                }

                // Build a recommendation module with the provided settings.
                $obj = $this->pluginManager->get($module);
                $obj->setConfig($config);
                $obj->init($params, $request);
                $this->objects[$location][] = $obj;
            }
        }

        return $event;
    }

    /**
     * Inject additional spelling suggestions.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchComplete(EventInterface $event)
    {
        // Make sure we're triggering in the appropriate context:
        if ($this->searchId != $event->getParam('runningSearchId')) {
            return;
        }
        $results = $event->getParam('results');
        // Process recommendations:
        foreach ($this->objects as $currentSet) {
            foreach ($currentSet as $current) {
                $current->process($results);
            }
        }
        $results->setRecommendations($this->objects);
        return $event;
    }
}
