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
     * Did we encounter a parse error?
     *
     * @var bool
     */
    protected $parseError = false;

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
     * Search type.
     *
     * @var string
     */
    protected $searchClassId;

    /**
     * Active recommendation areas.
     *
     * @var array
     */
    protected $activeRecommendations;

    /**
     * Constructor
     *
     * @param ResultsManager   $resultsManager        Results manager
     * @param RecommendManager $recommendManager      Recommendation module manager
     * @param string           $searchClassId         Type of search to perform
     * @param array            $activeRecommendations Array of active recommendation
     * areas.
     */
    public function __construct(ResultsManager $resultsManager,
        RecommendManager $recommendManager, $searchClassId = 'Solr',
        array $activeRecommendations = []
    ) {
        $this->resultsManager = $resultsManager;
        $this->recommendManager = $recommendManager;
        $this->searchClassId = $searchClassId;
        $this->activeRecommendations = $activeRecommendations;
    }

    /**
     * Did we encounter a parse error during the last run?
     *
     * @return bool
     */
    public function encounteredParseError()
    {
        return $this->parseError;
    }

    /**
     * Run the search.
     *
     * @param Parameters $request Incoming parameters for search.
     *
     * @return \VuFind\Search\Base\Results
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     */
    public function run(Parameters $request)
    {
        $results = $this->resultsManager->get($this->searchClassId);
        $params = $results->getParams();
        $params->initFromRequest($request);

        // Hook up listener for recommendations.
        if ($recommendListener = $this->getRecommendListener($params)) {
            $recommendListener->attach($this->getEventManager()->getSharedManager());
        }

        $this->getEventManager()
            ->trigger('vufind.searchParamsSet', $this, compact('params', 'request'));

        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            // Explicitly execute search within controller -- this allows us to
            // catch exceptions more reliably:
            $results->performAndProcessSearch();
            $this->parseError = false;
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            if ($e->hasTag('VuFind\Search\ParserError')) {
                // If it's a parse error or the user specified an invalid field, we
                // should display an appropriate message:
                $this->parseError = true;

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
     * Create a recommendation listener based on the provided search params.
     * Return null if no recommendations are active (so we can avoid attaching
     * a useless listener).
     *
     * @param \VuFind\Search\Base\Params $params Search parameters
     *
     * @return \VuFind\Search\RecommendListener
     */
    protected function getRecommendListener($params)
    {
        if (empty($this->activeRecommendations)) {
            return null;
        }
        $listener = new \VuFind\Search\RecommendListener($this->recommendManager);
        $listener->setConfig(
            $params->getRecommendationSettings($this->activeRecommendations)
        );
        return $listener;
    }

    /**
     * Build a runner object.
     *
     * @param ServiceManager $sm                    Service manager
     * @param string         $searchClassId         Search type to run
     * @param array          $activeRecommendations Active recommendation areas
     *
     * @return SearchRunner
     */
    public static function factory(ServiceManager $sm, $searchClassId = 'Solr',
        array $activeRecommendations = []
    ) {
        return new SearchRunner(
            $sm->get('VuFind\SearchResultsPluginManager'),
            $sm->get('VuFind\RecommendPluginManager'),
            $searchClassId,
            $activeRecommendations
        );
    }
}