<?php
/**
 * MapSelection Recommendations Module
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
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * MapSelection Recommendations Module
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class MapSelection implements \VuFind\Recommend\RecommendInterface
{
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
    protected $geoField;
    
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
     * @var string
     */
    protected $searchParams = null;
 
    /**
     * Search object
     *
     * @var string
     */
    protected $searchObject;
    
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
     * Config options
     *
     * @var array
     */
    protected $configOptions = [];

    /**
     * Solr search loader
     *
     * @var \VuFind\Search\BackendManager
     */
    protected $solr;

    /**
     * Query Builder object
     *
     * @var \VuFind\Search\BackendManager
     */
    protected $queryBuilder;

    /**
     * Solr connector Object
     *
     * @var \VuFind\Search\BackendManager
     */
    protected $solrConnector;

    /**
     * Query Object
     *
     * @var \VuFind\Search\BackendManager
     */
    protected $searchQuery;

    /**
     * Backend Parameters / Search Filters
     *
     * @var \VuFind\Search\BackendManager
     */
    protected $searchFilters;
    
    /**
     * Constructor
     *
     * @param array                            $options from searches.ini
     * @param \VuFind\Search\BackendManager    $solr Search interface
     */
    public function __construct($options, $solr)
    {
        $this->configOptions = $options;
        $this->solr = $solr;
        $this->queryBuilder = $solr->getQueryBuilder();
        $this->solrConnector = $solr->getConnector();
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
        $settings = $this->configOptions;
        if (isset($settings[0])) {
            $enabled = $settings[0];
        }
        if (isset($settings[1])) {
            $this->defaultCoordinates = explode(',', $settings[1]);
        }
        if (isset($settings[2])) {
            $this->geoField = $settings[2];
        }
        if (isset($settings[3])) {
            $this->height = $settings[3];
        }
    }
    
    /**
     * Init
     *
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Solr\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
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
        $reorder_coords = [];
        $filters = $results->getParams()->getFilters();
        foreach ($filters as $key => $value) {
            if ($key == $this->geoField) {
                $match = array();
                if (preg_match('/Intersects\(ENVELOPE\((.*), (.*), (.*), (.*)\)\)/', 
                $value[0], $match)) {
                    array_push($this->bboxSearchCoords,
                    (float)$match[1], (float)$match[2], 
                    (float)$match[3], (float)$match[4]
                    );
                    // Need to reorder coords from WENS to WSEN
                    array_push($reorder_coords,
                    (float)$match[1], (float)$match[4],
                    (float)$match[2], (float)$match[3]
                    );
                    $this->selectedCoordinates = $reorder_coords;
                }
                $this->searchParams = $results->getUrlQuery()->removeFacet($this->geoField,
                $value[0], false
                );
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
     * GetSearchParams no question mark at end
     *
     * Return search params without leading question mark and colon.
     * Copied from ResultGoogleMapAjax.php and chngd name to add NoQ.LMG 
     * 
     * @return string
     */
    public function getSearchParamsNoQ()
    {
        // Get search parameters and return them minus the leading ?:
           return substr($this->searchObject->getUrlQuery()->getParams(false), 1);
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
     * Get bbox_geo field values for all search results
     *
     * @return array
     */
    public function getSearchResultCoordinates()
    {
        $result = [];
        $params = $this->searchFilters;
        $params->mergeWith($this->queryBuilder->build($this->searchQuery));
        $params->set('fl', 'id, bbox_geo');
        $params->set('wt', 'json');
        $params->set('rows', '10000000'); // set to return all results
        $response = json_decode($this->solrConnector->search($params));
        foreach ($response->response->docs as $current) {
            $result[] = [$current->id, $current->bbox_geo];
        }
        return $result;
    }

    /**
     * Process search result record coordinate values
     *
     * Return search results record coordinates and process for
     * display on search map.
     *
     * @return array
     */
    public function getMapResultCoordinates()
    {
        $centerCoords = [];
        // Both coordinate variables are in WENS order //
        $rawCoords =$this->getSearchResultCoordinates();
        $bboxCoords = $this->bboxSearchCoords;

        // Set up comparision variables //
        $bboxW = $bboxCoords[0];
        $bboxE = $bboxCoords[1];
        $bboxN = $bboxCoords[2];
        $bboxS = $bboxCoords[3];

        foreach ($rawCoords as $idCoords) {
            foreach ($idCoords[1] as $coord) {
              $match = [];
              $addCtr = false;
              if (preg_match('/ENVELOPE\((.*),(.*),(.*),(.*)\)/', $coord, $match)) {
                $coordW = (float)$match[1];
                $coordE = (float)$match[2];
                $coordN = (float)$match[3];
                $coordS = (float)$match[4];
                if ($coordE == (float)-0) { 
                    $coordE = (float)0; 
                }
            // If coordinates fall within bbox, calculate center point and add to return array
            // Have to do this because some records have multiple coordinates that
            // are geographically distributed
                if (($bboxW <= $coordE && $coordW <= $bboxE) || ($bboxS <= $coordN && $coordS <= $bboxN)) {
                    $centerWE = (($coordE - $coordW)/2) + $coordW;
                    $centerSN = (($coordN - $coordS)/2) + $coordS;
            // Now check to see if center coordinate falls within the search box.
                if (($centerWE >= $bboxW && $centerWE <= $bboxE) && ($centerSN >= $bboxS && $centerSN <=$bboxN)) {
                    $centerCoords[] = [$idCoords[0], $centerWE, $centerSN];
                    $addCtr = true;
                } else {  //recalculate the center point
                    if ($coordW < $bboxW) { $coordW = $bboxW; }
                    if ($coordE > $bboxE) { $coordE = $bboxE; }
                    if ($coordS < $bboxS) { $coordS = $bboxS; }
                    if ($coordN > $bboxN) { $coordN = $bboxN; }
                        $centerWE = (($coordE - $coordW)/2) + $coordW;
                        $centerSN = (($coordN - $coordS)/2) + $coordS;
                    if (($centerWE >= $bboxW && $centerWE <= $bboxE) && ($centerSN >= $bboxS && $centerSN <=$bboxN)) {
                        $centerCoords[] = [$idCoords[0], $centerWE, $centerSN];
                        $addCtr=true;
                    } else { // put the center in the middle of the searchbox
                        $centerWE = (($bboxE - $bboxW)/2) + $bboxW;
                        $centerSN = (($bboxN - $bboxS)/2) + $bboxS;
                        $centerCoords[] = [$idCoords[0], $centerWE, $centerSN];
                        $addCtr=true;
                    }
                }
                if ($addCtr == true) {
                    break;
                }
            }
           }
          }
        }
     return $centerCoords;
    }
}
