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
use VuFind\Recommend\PluginManager as RecommendManager;
use VuFind\Search\RecommendListener;
use VuFind\Search\Results\PluginManager as ResultsManager;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\ServiceManager\ServiceManager;
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
     * Event manager.
     *
     * @var EventManager
     */
    protected $events = null;

    /**
     * Recommendation module manager.
     *
     * @var RecommendManager
     */
    protected $recommendManager;

    /**
     * Search results object manager.
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param ResultsManager   $resultsManager        Results manager
     * @param RecommendManager $recommendManager      Recommendation module manager
     */
    public function __construct(ResultsManager $resultsManager,
        RecommendManager $recommendManager
    ) {
        $this->resultsManager = $resultsManager;
        $this->recommendManager = $recommendManager;
    }

    /**
     * Run the search.
     *
     * @param Parameters $request               Incoming parameters for search
     * @param string     $searchClassId         Type of search to perform
     * @param array      $activeRecommendations Array of active recommendation
     * areas.
     *
     * @return \VuFind\Search\Base\Results
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     */
    public function run(Parameters $request, $searchClassId = 'Solr',
        array $activeRecommendations = []
    ) {
        $results = $this->resultsManager->get($searchClassId);
        $params = $results->getParams();
        $params->initFromRequest($request);

        // Hook up listener for recommendations.
        $this->configureRecommendListener($params, $activeRecommendations);

        // Trigger the "configuration done" event.
        $this->getEventManager()
            ->trigger('vufind.searchParamsSet', $this, compact('params', 'request'));

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
        $this->getEventManager()
            ->trigger('vufind.searchComplete', $this, compact('results'));

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
        $events->setIdentifiers(['VuFind\Search\SearchRunner']);
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

    /**
     * Create and attach a recommendation listener based on the provided search
     * params.
     *
     * @param \VuFind\Search\Base\Params $params Search parameters
     * @param array                      $active Active recommendation areas
     *
     * @return void
     */
    protected function configureRecommendListener($params, $active)
    {
        // Don't bother attaching a listener if no areas are active.
        if (!empty($active)) {
            $listener = new RecommendListener($this->recommendManager);
            $listener->setConfig($params->getRecommendationSettings($active));
            $listener->attach($this->getEventManager()->getSharedManager());
        }
    }
}