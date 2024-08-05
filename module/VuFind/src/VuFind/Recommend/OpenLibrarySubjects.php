<?php

/**
 * OpenLibrarySubjects Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use VuFind\Connection\OpenLibrary;
use VuFind\Solr\Utils as SolrUtils;

use function intval;
use function is_object;

/**
 * OpenLibrarySubjects Recommendations Module
 *
 * This class provides recommendations by doing a search of the catalog; useful
 * for displaying catalog recommendations in other modules (i.e. Summon, Web, etc.)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class OpenLibrarySubjects implements
    RecommendInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Parameter to use for search terms
     *
     * @var string
     */
    protected $requestParam;

    /**
     * Search limit
     *
     * @var int
     */
    protected $limit;

    /**
     * Field to use for date filtering
     *
     * @var string
     */
    protected $pubFilter;

    /**
     * Date filter to apply
     *
     * @var string
     */
    protected $publishedIn = '';

    /**
     * Subject to search for
     *
     * @var string
     */
    protected $subject;

    /**
     * Subject types to use
     *
     * @var array
     */
    protected $subjectTypes;

    /**
     * Result of search (false if none)
     *
     * @var array|bool
     */
    protected $result = false;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Parse out parameters:
        $params = explode(':', $settings);
        $this->requestParam = empty($params[0]) ? 'lookfor' : $params[0];
        $this->limit = isset($params[1]) && is_numeric($params[1]) && $params[1] > 0
            ? intval($params[1]) : 5;
        $this->pubFilter = (!isset($params[2]) || empty($params[2])) ?
            'publishDate' : $params[2];
        if (strtolower(trim($this->pubFilter)) == 'false') {
            $this->pubFilter = false;
        }

        if (isset($params[3])) {
            $this->subjectTypes = explode(',', $params[3]);
        } else {
            $this->subjectTypes = ['topic'];
        }

        // A 4th parameter is not specified in searches.ini, if it exists
        //     it has been passed in by an AJAX call and carries the
        //     publication date range in the form YYYY-YYYY
        if (isset($params[4]) && strstr($params[4], '-') != false) {
            $this->publishedIn = $params[4];
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
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // Get and normalise $requestParam
        $this->subject = $request->get($this->requestParam);

        // Set up the published date range if it has not already been provided:
        if (empty($this->publishedIn) && $this->pubFilter) {
            $this->publishedIn = $this->getPublishedDates(
                $this->pubFilter,
                $params,
                $request
            );
        }
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
        // Only proceed if we have a request parameter value
        if (!empty($this->subject)) {
            $ol = new OpenLibrary($this->httpService->createClient());
            $result = $ol->getSubjects(
                $this->subject,
                $this->publishedIn,
                $this->subjectTypes,
                true,
                false,
                $this->limit,
                null,
                true
            );

            if (!empty($result)) {
                $this->result = [
                    'worksArray' => $result, 'subject' => $this->subject,
                ];
            }
        }
    }

    /**
     * Support function to get publication date range. Return string in the form
     * "YYYY-YYYY"
     *
     * @param string                     $field   Name of filter field to check for
     * date limits
     * @param \VuFind\Search\Params\Base $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     *                                            request.
     *
     * @return string
     */
    protected function getPublishedDates($field, $params, $request)
    {
        $range = null;
        // Try to extract range details from request parameters or SearchObject:
        $from = $request->get($field . 'from');
        $to = $request->get($field . 'to');
        if (null !== $from && null !== $to) {
            $range = ['from' => $from, 'to' => $to];
        } elseif (is_object($params)) {
            $currentFilters = $params->getRawFilters();
            if (isset($currentFilters[$field][0])) {
                $range = SolrUtils::parseRange($currentFilters[$field][0]);
            }
        }

        // Normalize range if we found one:
        if (isset($range)) {
            if (empty($range['from']) || $range['from'] == '*') {
                $range['from'] = 0;
            }
            if (empty($range['to']) || $range['to'] == '*') {
                $range['to'] = date('Y') + 1;
            }
            return $range['from'] . '-' . $range['to'];
        }

        // No range found?  Return empty string:
        return '';
    }

    /**
     * Get the results of the subject query -- false if none, otherwise an array
     * with 'worksArray' and 'subject' keys.
     *
     * @return bool|array
     */
    public function getResult()
    {
        return $this->result;
    }
}
