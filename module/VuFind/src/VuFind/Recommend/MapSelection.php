<?php

/**
 * MapSelection Recommendations Module
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
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use VuFindSearch\Backend\Solr\Command\RawJsonSearchCommand;
use VuFindSearch\Service;

/**
 * MapSelection Recommendations Module
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class MapSelection implements
    \VuFind\Recommend\RecommendInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Basemap configuration parameters
     *
     * @var array
     */
    protected $basemapOptions = [];

    /**
     * Default coordinates. Order is WENS
     *
     * @var array
     */
    protected $defaultCoordinates = [];

    /**
     * The geoField variable name
     *
     * @var string
     */
    protected $geoField = 'long_lat';

    /**
     * Height of search map pane
     *
     * @var string
     */
    protected $height;

    /**
     * Selected coordinates
     *
     * @var string
     */
    protected $selectedCoordinates = null;

    /**
     * Search parameters
     *
     * @var object
     */
    protected $searchParams = null;

    /**
     * Search Results coordinates
     *
     * @var array
     */
    protected $searchResultCoords = [];

    /**
     * Bbox search box coordinates
     *
     * @var array
     */
    protected $bboxSearchCoords = [];

    /**
     * Search service
     *
     * @var Service
     */
    protected $searchService;

    /**
     * Query Object
     *
     * @var \VuFindSearch\Query\QueryInterface
     */
    protected $searchQuery;

    /**
     * Backend Parameters / Search Filters
     *
     * @var \VuFindSearch\ParamBag
     */
    protected $searchFilters;

    /**
     * Constructor
     *
     * @param Service $ss                  Search service
     * @param array   $basemapOptions      Basemap Options
     * @param array   $mapSelectionOptions Map Options
     */
    public function __construct($ss, $basemapOptions, $mapSelectionOptions)
    {
        $this->searchService = $ss;
        $this->basemapOptions = $basemapOptions;
        $this->defaultCoordinates = explode(
            ',',
            $mapSelectionOptions['default_coordinates']
        );
        $this->height = $mapSelectionOptions['height'];
    }

    /**
     * SetConfig
     *
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Solr\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
    }

    /**
     * Process
     *
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
        $reorder_coords = [];
        $filters = $results->getParams()->getRawFilters();
        foreach ($filters as $key => $value) {
            if ($key == $this->geoField) {
                $match = [];
                if (
                    preg_match(
                        '/Intersects\(ENVELOPE\((.*), (.*), (.*), (.*)\)\)/',
                        $value[0],
                        $match
                    )
                ) {
                    array_push(
                        $this->bboxSearchCoords,
                        (float)$match[1],
                        (float)$match[2],
                        (float)$match[3],
                        (float)$match[4]
                    );
                    // Need to reorder coords from WENS to WSEN
                    array_push(
                        $reorder_coords,
                        (float)$match[1],
                        (float)$match[4],
                        (float)$match[2],
                        (float)$match[3]
                    );
                    $this->selectedCoordinates = $reorder_coords;
                }
                $this->searchParams = $results->getUrlQuery()
                    ->removeFacet($this->geoField, $value[0])->getParams(false);
            }
        }
        if ($this->searchParams == null) {
            $this->searchParams = $results->getUrlQuery()->getParams(false);
        }
        $this->searchFilters = $results->getParams()->getBackendParameters();
        $this->searchQuery = $results->getParams()->getQuery();
    }

    /**
     * GetSelectedCoordinates
     *
     * Return coordinates selected by user
     *
     * @return array of floats
     */
    public function getSelectedCoordinates()
    {
        return $this->selectedCoordinates;
    }

    /**
     * GetDefaultCoordinates
     *
     * Return default coordinates from configuration
     *
     * @return array of floats
     */
    public function getDefaultCoordinates()
    {
        return $this->defaultCoordinates;
    }

    /**
     * Get the basemap configuration settings.
     *
     * @return array
     */
    public function getBasemap()
    {
        return [
            $this->basemapOptions['basemap_url'],
            $this->basemapOptions['basemap_attribution'],
        ];
    }

    /**
     * GetHeight
     *
     * Return height of map in pixels
     *
     * @return number
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * GetSearchParams
     *
     * Return search params without filter for geographic search
     *
     * @return string
     */
    public function getSearchParams()
    {
        return $this->searchParams;
    }

    /**
     * GetGeoField
     *
     * Return Solr field to use for geographic search
     *
     * @return string
     */
    public function getGeoField()
    {
        return $this->geoField;
    }

    /**
     * Fetch details from search service
     *
     * @return array
     */
    public function fetchDataFromSearchService()
    {
        $params = $this->searchFilters;
        $params->set('fl', 'id, ' . $this->geoField . ', title');
        $command = new RawJsonSearchCommand(
            'Solr',
            $this->searchQuery,
            0,
            10000000,   // set to return all results
            $params
        );
        $response = $this->searchService->invoke($command)->getResult();
        $defaultTitle = $this->translate('Title not available');
        $callback = function ($current) use ($defaultTitle) {
            return [
                $current->id,
                $current->{$this->geoField},
                $current->title ?? $defaultTitle,
            ];
        };
        return array_map($callback, $response->response->docs);
    }

    /**
     * Get geo field values for all search results
     *
     * @return array
     */
    public function getSearchResultCoordinates()
    {
        $params = $this->searchFilters;
        // Check to makes sure we have a geographic search
        $filters = $params->get('fq');
        return (!empty($filters) && str_contains($filters[0], $this->geoField))
            ? $this->fetchDataFromSearchService() : [];
    }

    /**
     * Process search result record coordinate values
     * for Leaflet mapping platform.
     *
     * @return array
     */
    public function getMapResultCoordinates()
    {
        $results = [];
        $rawCoords = $this->getSearchResultCoordinates();
        foreach ($rawCoords as $idCoords) {
            foreach ($idCoords[1] as $coord) {
                $recCoords = [];
                $recId = $idCoords[0];
                $title = $idCoords[2];
                // convert title to UTF-8
                $title = mb_convert_encoding($title, 'UTF-8');
                $patternStr = '/ENVELOPE\((.*),(.*),(.*),(.*)\)/';
                if (preg_match($patternStr, $coord, $match)) {
                    $floats = array_map('floatval', $match);
                    $recCoords = [$floats[1], $floats[2], $floats[3], $floats[4]];
                }
                $results[] = [$recId, $title, $recCoords[0],
                    $recCoords[1], $recCoords[2], $recCoords[3],
                ];
            }
        }
        return $results;
    }
}
