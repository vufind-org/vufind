<?php
/**
 * VuFind Search Runner
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search;
use VuFind\Search\Results\PluginManager as ResultsManager;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\Stdlib\Parameters;

/**
 * VuFind Search Runner
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class SearchRunner
{
    /**
     * Event identifiers.
     *
     * @var string
     */
    const EVENT_CONFIGURED = 'configured';
    const EVENT_COMPLETE = 'complete';

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $events = null;

    /**
     * Search results object manager.
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Counter of how many searches we have run (for differentiating listeners).
     *
     * @var int
     */
    protected $searchId = 0;

    /**
     * Constructor
     *
     * @param ResultsManager $resultsManager Results manager
     */
    public function __construct(ResultsManager $resultsManager)
    {
        $this->resultsManager = $resultsManager;
    }

    /**
     * Run the search.
     *
     * @param array|Parameters $rawRequest    Incoming parameters for search
     * @param string           $searchClassId Type of search to perform
     * @param mixed            $setupCallback Optional callback for setting up params
     * and attaching listeners; if provided, will be passed three parameters:
     * this object, the search parameters object, and a unique identifier for
     * the current running search.
     *
     * @return \VuFind\Search\Base\Results
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     */
    public function run($rawRequest, $searchClassId = 'Solr', $setupCallback = null)
    {
        // Increment the ID counter, then save the current value to a variable;
        // since events within this run could theoretically trigger additional
        // runs of the SearchRunner, we can't rely on the property value past
        // this point!
        $this->searchId++;
        $runningSearchId = $this->searchId;

        // Format the request object:
        $request = $rawRequest instanceof Parameters
            ? $rawRequest
            : new Parameters(is_array($rawRequest) ? $rawRequest : []);

        // Set up the search:
        $results = $this->resultsManager->get($searchClassId);
        $params = $results->getParams();
        $params->initFromRequest($request);

        if (is_callable($setupCallback)) {
            $setupCallback($this, $params, $runningSearchId);
        }

        // Trigger the "configuration done" event.
        $this->getEventManager()->trigger(
            self::EVENT_CONFIGURED, $this,
            compact('params', 'request', 'runningSearchId')
        );

        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            // Explicitly execute search within controller -- this allows us to
            // catch exceptions more reliably:
            $results->performAndProcessSearch();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            if ($e->hasTag('VuFind\Search\ParserError')) {
                // We need to create and process an "empty results" object to
                // ensure that recommendation modules and templates behave
                // properly when displaying the error message.
                $results = $this->resultsManager->get('EmptySet');
                $results->setParams($params);
                $results->performAndProcessSearch();
            } else {
                throw $e;
            }
        }

        // Trigger the "search completed" event.
        $this->getEventManager()->trigger(
            self::EVENT_COMPLETE, $this, compact('results', 'runningSearchId')
        );

        return $results;
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
        $events->setIdentifiers([__CLASS__]);
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
}