<?php

/**
 * Collection list tab
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\Recommend\PluginManager as RecommendManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;

/**
 * Collection list tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class CollectionList extends AbstractBase
{
    /**
     * Search results object (null prior to processing)
     *
     * @var \VuFind\Search\SolrCollection\Results
     */
    protected $results = null;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $runner;

    /**
     * Recommendation manager
     *
     * @var RecommendManager
     */
    protected $recommendManager;

    /**
     * Search memory
     *
     * @var SearchMemory
     */
    protected $searchMemory;

    /**
     * Search class id
     *
     * @var string
     */
    protected $searchClassId = 'SolrCollection';

    /**
     * Constructor
     *
     * @param SearchRunner     $runner Search runner
     * @param RecommendManager $recMan Recommendation manager
     * @param SearchMemory     $sm     Search memory
     */
    public function __construct(
        SearchRunner $runner,
        RecommendManager $recMan,
        SearchMemory $sm
    ) {
        $this->runner = $runner;
        $this->recommendManager = $recMan;
        $this->searchMemory = $sm;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Collection Items';
    }

    /**
     * Get the current search parameters.
     *
     * @return \VuFind\Search\SolrCollection\Params
     */
    public function getParams()
    {
        return $this->getResults()->getParams();
    }

    /**
     * Get the processed search results.
     *
     * @return \VuFind\Search\SolrCollection\Results
     */
    public function getResults()
    {
        if (null === $this->results) {
            $driver = $this->getRecordDriver();
            $request = $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray();
            $rManager = $this->recommendManager;
            $cb = function ($runner, $params, $searchId) use ($driver, $rManager, $request) {
                $params->initFromRecordDriver($driver, '' !== ($request['lookfor'] ?? ''));
                $listener = new RecommendListener($rManager, $searchId);
                $listener->setConfig(
                    $params->getOptions()->getRecommendationSettings()
                );
                $listener->attach($runner->getEventManager()->getSharedManager());
            };
            $this->results
                = $this->runner->run($request, $this->searchClassId, $cb);
            // Add search id from the originating search for paginator:
            $this->results->getUrlQuery()->setDefaultParameter(
                'sid',
                $this->searchMemory->getCurrentSearchId(),
                true
            );
        }
        return $this->results;
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // No, search parameters from the URL are needed.
        return false;
    }
}
