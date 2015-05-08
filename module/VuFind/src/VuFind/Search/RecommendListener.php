<?php

/**
 * Recommend listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search;

use VuFind\Recommend\PluginManager;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

/**
 * Recommend listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
     * Constructor.
     *
     * @param PluginManager $pluginManager Plugin manager for recommendation
     * modules
     */
    public function __construct(PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
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
            'Zend\Mvc\Controller\AbstractController',
            'vufind.searchParamsSet', [$this, 'onSearchParamsSet']
        );
        $manager->attach(
            'Zend\Mvc\Controller\AbstractController',
            'vufind.searchComplete', [$this, 'onSearchComplete']
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
    public function onSearchParamsSet(EventInterface $event)
    {
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
