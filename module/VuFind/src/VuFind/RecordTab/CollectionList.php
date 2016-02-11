<?php
/**
 * Collection list tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;
use VuFind\Recommend\PluginManager as RecommendManager,
    VuFind\Search\RecommendListener, VuFind\Search\SearchRunner;

/**
 * Collection list tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
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
     * Constructor
     *
     * @param SearchRunner     $runner Search runner
     * @param RecommendManager $recMan Recommendation manager
     */
    public function __construct(SearchRunner $runner, RecommendManager $recMan)
    {
        $this->runner = $runner;
        $this->recommendManager = $recMan;
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
            $cb = function ($runner, $params, $searchId) use ($driver, $rManager) {
                $params->initFromRecordDriver($driver);
                $listener = new RecommendListener($rManager, $searchId);
                $listener->setConfig(
                    $params->getOptions()->getRecommendationSettings()
                );
                $listener->attach($runner->getEventManager()->getSharedManager());
            };
            $this->results
                = $this->runner->run($request, 'SolrCollection', $cb);
        }
        return $this->results;
    }

    /**
     * Get side recommendations.
     *
     * @return array
     */
    public function getSideRecommendations()
    {
        return $this->getResults()->getRecommendations('side');
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // No, special sidebar needed.
        return false;
    }
}