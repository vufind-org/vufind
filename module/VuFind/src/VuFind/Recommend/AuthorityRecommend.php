<?php

/**
 * AuthorityRecommend Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012.
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
 * @package  Recommendations
 * @author   Lutz Biedinger <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Recommend;

use Laminas\Stdlib\Parameters;
use VuFindSearch\Backend\Exception\RequestErrorException;

use function count;
use function intval;

/**
 * AuthorityRecommend Module
 *
 * This class provides recommendations based on Authority records.
 * i.e. searches for a pseudonym will provide the user with a link
 * to the official name (according to the Authority index)
 *
 * Originally developed at the National Library of Ireland by Lutz
 * Biedinger and Ronan McHugh.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorityRecommend implements RecommendInterface
{
    /**
     * User search query
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Configured filters for authority searches
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Maximum number of results that will be accompanied by recommendations (set
     * to 0 for no limit).
     *
     * @var int
     */
    protected $resultLimit = 0;

    /**
     * Current user search
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Generated recommendations
     *
     * @var array
     */
    protected $recommendations = [];

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Which lookup mode(s) to use.
     *
     * @var string
     */
    protected $mode = '*';

    /**
     * Header to use in the user interface.
     *
     * @var string
     */
    protected $header = 'See also';

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results)
    {
        $this->resultsManager = $results;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $params = explode(':', $settings);
        for ($i = 0; $i < count($params); $i += 2) {
            if (isset($params[$i + 1])) {
                if ($params[$i] == '__resultlimit__') {
                    $this->resultLimit = intval($params[$i + 1]);
                } elseif ($params[$i] == '__mode__') {
                    $this->mode = strtolower($params[$i + 1]);
                } elseif ($params[$i] == '__header__') {
                    $this->header = $params[$i + 1];
                } else {
                    $this->filters[] = $params[$i] . ':' . $params[$i + 1];
                }
            }
        }
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param Parameters                 $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        // Save user search query:
        $this->lookfor = $request->get('lookfor');
    }

    /**
     * Perform a search of the authority index.
     *
     * @param array $params Array of request parameters.
     *
     * @return array
     */
    protected function performSearch($params)
    {
        // Initialise and process search (ignore Solr errors -- no reason to fail
        // just because search syntax is not compatible with Authority core):
        try {
            $authResults = $this->resultsManager->get('SolrAuth');
            $authParams = $authResults->getParams();
            $authParams->initFromRequest(new Parameters($params));
            foreach ($this->filters as $filter) {
                $authParams->addHiddenFilter($filter);
            }
            return $authResults->getResults();
        } catch (RequestErrorException $e) {
            return [];
        }
    }

    /**
     * Return true if $a and $b are similar enough to represent the same heading.
     *
     * @param string $a First string to compare
     * @param string $b Second string to compare
     *
     * @return bool
     */
    protected function fuzzyCompare($a, $b)
    {
        $normalize = function ($str) {
            return trim(strtolower(preg_replace('/\W/', '', $str)));
        };
        return $normalize($a) == $normalize($b);
    }

    /**
     * Add main headings from records that match search terms on use_for/see_also.
     *
     * @return void
     */
    protected function addUseForHeadings()
    {
        // Build an advanced search request that prevents Solr from retrieving
        // records that would already have been retrieved by a search of the biblio
        // core, i.e. it only returns results where $lookfor IS found in in the
        // "Heading" search and IS NOT found in the "MainHeading" search defined
        // in authsearchspecs.yaml.
        $params = [
            'join' => 'AND',
            'bool0' => ['AND'],
            'lookfor0' => [$this->lookfor],
            'type0' => ['Heading'],
            'bool1' => ['NOT'],
            'lookfor1' => [$this->lookfor],
            'type1' => ['MainHeading'],
        ];

        // loop through records and assign id and headings to separate arrays defined
        // above
        foreach ($this->performSearch($params) as $result) {
            $this->recommendations[] = $result->getBreadcrumb();
        }
    }

    /**
     * Add "see also" headings from records that match search terms on main heading.
     *
     * @return void
     */
    protected function addSeeAlsoReferences()
    {
        // Build a simple "MainHeading" search.
        $params = [
            'lookfor' => [$this->lookfor],
            'type' => ['MainHeading'],
        ];

        // loop through records and assign id and headings to separate arrays defined
        // above
        foreach ($this->performSearch($params) as $result) {
            foreach ($result->getSeeAlso() as $seeAlso) {
                // check for duplicates before adding record to recordSet
                if (!$this->fuzzyCompare($seeAlso, $this->lookfor)) {
                    $this->recommendations[] = $seeAlso;
                }
            }
        }
    }

    /**
     * Is the specified mode configured to be active?
     *
     * @param string $mode Mode to check
     *
     * @return bool
     */
    protected function isModeActive($mode)
    {
        return $this->mode === '*' || str_contains($this->mode, $mode);
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->results = $results;

        // empty searches such as New Items will return blank
        if ($this->lookfor == null) {
            return;
        }

        // function will return blank on Advanced Search
        if ($results->getParams()->getSearchType() == 'advanced') {
            return;
        }

        // check result limit before proceeding...
        if (
            $this->resultLimit > 0
            && $this->resultLimit < $results->getResultTotal()
        ) {
            return;
        }

        // see if we can add main headings matching use_for/see_also fields...
        if ($this->isModeActive('usefor')) {
            $this->addUseForHeadings();
        }

        // see if we can add see-also references associated with main headings...
        if ($this->isModeActive('seealso')) {
            $this->addSeeAlsoReferences();
        }
    }

    /**
     * Get the header to display in the user interface.
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        return array_unique($this->recommendations);
    }

    /**
     * Get results stored in the object.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }
}
