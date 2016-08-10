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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
     * @param array                         $options from searches.ini
     * @param \VuFind\Search\BackendManager $solr    Search interface
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
                $match = [];
                if (preg_match(
                    '/Intersects\(ENVELOPE\((.*), (.*), (.*), (.*)\)\)/',
                    $value[0], $match
                )
                ) {
                    array_push(
                        $this->bboxSearchCoords,
                        (float)$match[1], (float)$match[2],
                        (float)$match[3], (float)$match[4]
                    );
                    // Need to reorder coords from WENS to WSEN
                    array_push(
                        $reorder_coords,
                        (float)$match[1], (float)$match[4],
                        (float)$match[2], (float)$match[3]
                    );
                    $this->selectedCoordinates = $reorder_coords;
                }
                $this->searchParams = $results->getUrlQuery()->removeFacet(
                    $this->geoField, $value[0], false
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
     * Convert coordinates to 360degree grid
     *
     * @param $coordinates coordinates for conversion
     * @return array
     */
    public function coordinatesToGrid($coordinates)
    {
        $gridCoords = [];
        $coordW = $coordinates[0];
        $coordE = $coordinates[1];
        $coordN = $coordinates[2];
        $coordS = $coordinates[3];
        
        if ($coordE == (float)-0) {
            $coordE = (float)0;
        }

        // Convert coordinates to 360 degree grid
        if ($coordE < $coordW && $coordE < 0) {
            $coordE = 360 + $coordE;
        }
        if ($coordW < 0 && $coordW >= -180) {
            $coordW = 360 + $coordW;
            $coordE = 360 + $coordE;
        }
        $gridCoords = [$coordW, $coordE, $coordN, $coordS];
        return $gridCoords;
    }

    /**
     * Check to see if coordinate and bbox intersect
     *
     * @param $bboxCoords searchbox coordinates
     * @param $coordinate result record coordinates
     * @return bool
     */
    public function coordBboxIntersect($bboxCoords, $coordinate)
    {
        $coordIntersect = false;
        $bboxW = $bboxCoords[0];
        $bboxE = $bboxCoords[1];
        $bboxN = $bboxCoords[2];
        $bboxS = $bboxCoords[3];

        $coordW = $coordinate[0];
        $coordE = $coordinate[1];
        $coordN = $coordinate[2];
        $coordS = $coordinate[3];

        //Does coordinate fall within search box
        if (
            (($coordW >= $bboxW && $coordW <= $bboxE)
            || ($coordE >= $bboxW && $coordE <= $bboxE))
            && (($coordS >= $bboxS && $coordS <= $bboxN)
            || ($coordN >= $bboxS && $coordN <= $bboxN))
        ) {
            $coordIntersect = true;
        }

        // Does searchbox fall within coordinate
        if (
            (($bboxW >= $coordW && $bboxW <= $coordE)
            || ($bboxE >= $coordW && $bboxE <= $coordE))
            && (($bboxS >= $coordS && $bboxS <= $coordN) 
            || ($bboxN >= $coordS && $bboxN <= $coordN))
        ) {
            $coordIntersect = true;
        }
        // Does searchbox span coordinate
        if (
            (($coordE >= $bboxW && $coordE <= $bboxE) 
            && ($coordW >= $bboxW && $coordW <= $bboxE))
            && ($coordN > $bboxN && $coordS < $bboxS)
        ) {
            $coordIntersect = true;
        }
        // Does coordinate span searchbox
        if (
            ($coordW < $bboxW && $coordE > $bboxE)
            && (($coordS >= $bboxS && $coordS <= $bboxN) 
            && ($coordN >= $bboxS && $coordN <= $bboxN))
        ) {
            $coordIntersect = true;
        }
        return $coordIntersect;
    }
    
    /**
     * Calculate center point of coordinate set
     *
     * @param $coordinate centerPoint coordinate
     * @return array
     */
    public function calculateCenterPoint($coordinate)
    {
        $centerCoord = [];
        $coordW = $coordinate[0];
        $coordE = $coordinate[1];
        $coordN = $coordinate[2];
        $coordS = $coordinate[3];

        // Calculate center point
        $centerWE = (($coordW - $coordE) / 2) + $coordE;
        $centerSN = (($coordN - $coordS) / 2) + $coordS;
        // Return WENS coordinates even though W=E and N=S
        $centerCoord = [$centerWE, $centerWE, $centerSN, $centerSN];
        return $centerCoord;
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
        $rawCoordIds = [];
        $centerCoordIds = [];
        // Both coordinate variables are in WENS order //
        $rawCoords = $this->getSearchResultCoordinates();a
        // Convert bbox coords to 360 grid  //
        $bboxCoords = $this->coordinatesToGrid($this->bboxSearchCoords);
        $bboxW = $bboxCoords[0];
        $bboxE = $bboxCoords[1];
        $bboxN = $bboxCoords[2];
        $bboxS = $bboxCoords[3];

        foreach ($rawCoords as $idCoords) {
            foreach ($idCoords[1] as $coord) {
                $rawCoordIds[] = $idCoords[0];
                $match = [];
                $addCtr = false;
                if (preg_match(
                    '/ENVELOPE\((.*),(.*),(.*),(.*)\)/', $coord, $match
                )) {
                    // Convert coordinates to 360 degree grid
                    $matchCoords = [
                        (float)$match[1], (float)$match[2],
                        (float)$match[3], (float)$match[4]
                    ];
                    $gridCoords = $this->coordinatesToGrid($matchCoords);
                    $coordW = $gridCoords[0];
                    $coordE = $gridCoords[1];
                    $coordN = $gridCoords[2];
                    $coordS = $gridCoords[3];
                    // adjust coordinates on grid if necessary based on bbox
                    if ($bboxW > 180 && ($coordW > 0 && $coordW < 180)) {
                        $coordW = 360 + $coordW;
                        $coordE = 360 + $coordE;
                    }
                    if ($bboxE > 180 && ($coordE > 0 && $coordE < 180)) {
                        $coordE = 360 + $coordE;
                    }
                    //Does coordinate fall within search box
                    $coordIntersect = $this->coordBboxIntersect(
                        $bboxCoords, [$coordW, $coordE, $coordN, $coordS]
                    );
                    if ($coordIntersect == true) {
                        // Calculate center point
                        $centerPt = $this->calculateCenterPoint(
                            [$coordW, $coordE, $coordN, $coordS]
                        );
                        //Does centerpoint intersect search box
                        $centerIntersect = $this->coordBboxIntersect($bboxCoords, $centerPt);
                        if ($centerIntersect) {
                            $centerWE = $centerPt[0];
                            $centerSN = $centerPt[2];
                            // convert coordinate to 180 degree grid
                            if ($centerWE > 180) {
                                $centerWE = $centerWE - 360;
                            }
                                $centerCoords[] = [$idCoords[0],
                                $centerWE, $centerSN
                                ];
                                $addCtr = true;
                        } else {
                            // re-calculate center point
                            $bboxLon = range($bboxW, $bboxE);
                            $bboxLat = range($bboxS, $bboxN);
                            $coordLon = range($coordW, $coordE);
                            $coordLat = range($coordS, $coordN);
                            $cbLon = array_intersect($coordLon, $bboxLon);
                            $cbLat = array_intersect($coordLat, $bboxLat);
                            // Calculate new center point
                            $newCenterPt = $this->calculateCenterPoint(
                                [min($cbLon), max($cbLon), min($cbLat), max($cbLat)]
                                );
                            //Does center point fall within search box
                            $centerIntersect = $this->coordBboxIntersect(
                                $bboxCoords, $newCenterPt
                            );
                            if ($centerIntersect) {
                                $centerWE = $newCenterPt[0];
                                $centerSN = $newCenterPt[2];
                                // convert coordinate to 180 degree grid
                                if ($centerWE > 180) {
                                    $centerWE = $centerWE - 360;
                                }
                                $centerCoords[] = [$idCoords[0],
                                $centerWE, $centerSN
                                ];
                                $addCtr = true;
                            } else {
                                // make center point center of search box
                                $centerWE = (($bboxE - $bboxW) / 2) + $bboxW;
                                $centerSN = (($bboxN - $bboxS) / 2) + $bboxS;
                                if ($centerWE > 180) {
                                    $centerWE = $centerWE - 360;
                                }
                                $centerCoords[] = [$idCoords[0],
                                $centerWE, $centerSN
                                ];
                                $addCtr = true;
                            }
                        }
                        if ($addCtr == true) {
                            $centerCoordIds[] = $idCoords[0];
                            break;
                        }
                    }
                }
            }
        }
        //Solr search includes close-by geo features
        //Check and add these if there are any
        $addIds = array_merge(
            array_diff($rawCoordIds, $centerCoordIds),
            array_diff($centerCoordIds, $rawCoordIds)
        );
        if (count($addIds) > 0) {
            $centerWE = (($bboxE - $bboxW) / 2) + $bboxW;
            $centerSN = (($bboxN - $bboxS) / 2) + $bboxS;
            if ($centerWE > 180) {
                $centerWE = $centerWE - 360;
            }
            foreach ($addIds as $coordId) {
                $centerCoords[] = [$coordId, $centerWE, $centerSN];
            }
        }
        return $centerCoords;
    }
}
