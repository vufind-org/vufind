<?php
/**
 * WorldCatTerms Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;
use VuFind\Connection\WorldCatUtils;
use VuFindSearch\Query\Query;

/**
 * WorldCatTerms Recommendations Module
 *
 * This class provides recommendations by using the WorldCat Terminologies API.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class WorldCatTerms implements RecommendInterface
{
    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $searchObject;

    /**
     * Vocabulary to use.
     *
     * @var string
     */
    protected $vocab = 'lcsh';

    /**
     * WorldCat utilities wrapper object.
     *
     * @var WorldCatUtils
     */
    protected $worldCatUtils;

    /**
     * Constructor
     *
     * @param WorldCatUtils $wcu WorldCat utilities object
     */
    public function __construct(WorldCatUtils $wcu)
    {
        $this->worldCatUtils = $wcu;
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
        // Pick a vocabulary (either user-specified, or LCSH by default):
        $params = trim($settings);
        $this->vocab = empty($params) ? 'lcsh' : $params;
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed.
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getTerms()
    {
        // Extract the first search term from the search object:
        $search = $this->searchObject->getParams()->getQuery();
        $lookfor = ($search instanceof Query) ? $search->getString() : '';

        // Get terminology information:
        $terms = $this->worldCatUtils->getRelatedTerms($lookfor, $this->vocab);

        // Wipe out any empty or unexpected sections of the related terms array;
        // this will make it easier to only display content in the template if
        // we have something worth displaying.
        if (is_array($terms)) {
            $desiredKeys = ['exact', 'broader', 'narrower'];
            foreach ($terms as $key => $value) {
                if (empty($value) || !in_array($key, $desiredKeys)) {
                    unset($terms[$key]);
                }
            }
        }
        return $terms;
    }
}