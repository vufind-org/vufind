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
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
namespace VuFind\RecordTab;

/**
 * Collection list tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
class CollectionList extends AbstractBase
{
    /**
     * Search results object
     *
     * @var \VuFind\Search\SolrCollection\Results
     */
    protected $results;

    /**
     * Flag indicating whether results have been processed yet
     *
     * @var bool
     */
    protected $processed = false;

    /**
     * Constructor
     *
     * @param \VuFind\Search\SolrCollection\Results $results Search object
     */
    public function __construct(\VuFind\Search\SolrCollection\Results $results)
    {
        $this->results = $results;
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
     * Get the processed search results.
     *
     * @return \VuFind\Search\SolrCollection\Results
     */
    public function getResults()
    {
        if (!$this->processed) {
            $params = new \Zend\Stdlib\Parameters(
                $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray()
            );
            $this->results->getParams()->initFromRecordDriver(
                $this->getRecordDriver(), $params
            );
            $this->processed = true;
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
}