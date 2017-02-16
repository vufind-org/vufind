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
    protected $geoField = 'location_geo';

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
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

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
     * @param \VuFind\Config\PluginManager  $configLoader Configuration loader
     * @param \VuFind\Search\BackendManager $solr         Search interface
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader, $solr)
    {
        $this->configLoader = $configLoader;
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
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'MapSelection' : $settings[0];
        $iniFile = empty($settings[1]) ? 'searches' : $settings[1];
        $config = $this->configLoader->get($iniFile);
        if (isset($config->$mainSection)) {
            $entries = $config->$mainSection;
            if (isset($entries->default_coordinates)) {
                $this->defaultCoordinates = explode(
                    ',', $entries->default_coordinates
                );
            }
            if (isset($entries->height)) {
                $this->height = $entries->height;
            }
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
     * Get geo field values for all search results
     *
     * @return array
     */
    public function getSearchResultCoordinates()
    {
        $result = [];
        $params = $this->searchFilters;
        // Check to makes sure we have a geographic search
        if (strpos($params->get('fq')[0], $this->geoField) !== false) {
            $params->mergeWith($this->queryBuilder->build($this->searchQuery));
            $params->set('fl', 'id, ' . $this->geoField . ', title');
            $params->set('wt', 'json');
            $params->set('rows', '10000000'); // set to return all results
            $response = json_decode($this->solrConnector->search($params));
            foreach ($response->response->docs as $current) {
                $result[] = [
                    $current->id, $current->{$this->geoField}, $current->title
                ];
            }
        }
        return $result;
    }

    /**
     * Convert coordinates to 360 degree grid
     *
     * @param array $coordinates coordinates for conversion
     *
     * @return array
     */
    public function coordinatesToGrid($coordinates)
    {
        $gridCoords = [];
        list($coordW, $coordE, $coordN, $coordS) = $coordinates;
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
     * Convert coordinates to longitude latitude grid
     *
     * @param array $centerPt coordinates for conversion
     *
     * @return array
     */
    public function centerToLongLat($centerPt)
    {
        $LongLatCoords = [];
        list($coordWE, $coordSN) = $centerPt;
        // convert coordinate to 180 degree grid
        if ($coordWE > 180) {
            $coordWE = $coordWE - 360;
        }
        $LongLatCoords = [$coordWE, $coordSN];
        return $LongLatCoords;
    }

    /**
     * Calculated the center of search box and coordinate overlap
     *
     * @param array $bboxCoords  search box coordinates
     * @param array $coordinates coordinates for conversion
     *
     * @return array
     */
    public function getCenterFromBboxCoordIntersect($bboxCoords, $coordinates)
    {
        $centerCoordBbox = [];
        list($bboxW, $bboxE, $bboxN, $bboxS) = $bboxCoords;
        list($coordW, $coordE, $coordN, $coordS) = $coordinates;
        $bboxLon = range(floor($bboxW), ceil($bboxE));
        $bboxLat = range(floor($bboxS), ceil($bboxN));
        $coordLon = range(floor($coordW), ceil($coordE));
        $coordLat = range(floor($coordS), ceil($coordN));
        $cbLon = array_intersect($coordLon, $bboxLon);
        $cbLat = array_intersect($coordLat, $bboxLat);
        $centerCoordBbox = [
            min($cbLon), max($cbLon), min($cbLat), max($cbLat)
        ];
        return $centerCoordBbox;
    }

    /**
     * Check to see if coordinate and bbox intersect
     *
     * @param array $bboxCoords searchbox coordinates
     * @param array $coordinate result record coordinates
     *
     * @return bool
     */
    public function coordBboxIntersect($bboxCoords, $coordinate)
    {
        $coordIntersect = false;
        list($bboxW, $bboxE, $bboxN, $bboxS) = $bboxCoords;
        list($coordW, $coordE, $coordN, $coordS) = $coordinate;
        //Does coordinate fall within search box
        if ((($coordW >= $bboxW && $coordW <= $bboxE)
            || ($coordE >= $bboxW && $coordE <= $bboxE))
            && (($coordS >= $bboxS && $coordS <= $bboxN)
            || ($coordN >= $bboxS && $coordN <= $bboxN))
        ) {
            $coordIntersect = true;
        }
        // Does searchbox fall within coordinate
        if ((($bboxW >= $coordW && $bboxW <= $coordE)
            || ($bboxE >= $coordW && $bboxE <= $coordE))
            && (($bboxS >= $coordS && $bboxS <= $coordN)
            || ($bboxN >= $coordS && $bboxN <= $coordN))
        ) {
            $coordIntersect = true;
        }
        // Does searchbox span coordinate
        if ((($coordE >= $bboxW && $coordE <= $bboxE)
            && ($coordW >= $bboxW && $coordW <= $bboxE))
            && ($coordN > $bboxN && $coordS < $bboxS)
        ) {
            $coordIntersect = true;
        }
        // Does coordinate span searchbox
        if (($coordW < $bboxW && $coordE > $bboxE)
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
     * @param array $coordinate centerPoint coordinate
     *
     * @return array
     */
    public function calculateCenterPoint($coordinate)
    {
        $centerCoord = [];
        list($coordW, $coordE, $coordN, $coordS) = $coordinate;
        // Calculate center point
        $centerWE = (($coordW - $coordE) / 2) + $coordE;
        $centerSN = (($coordN - $coordS) / 2) + $coordS;
        // Return WENS coordinates even though W=E and N=S
        $centerCoord = [$centerWE, $centerWE, $centerSN, $centerSN];
        return $centerCoord;
    }

    /**
     * Create array of geo features that are in search box
     *
     * Return search results record data
     *
     * @param string $recordId    record ID
     * @param string $recordCoord record coordinates
     * @param string $title       record title
     * @param array  $bboxCoords  search box coordinates
     *
     * @return array
     */
    public function createGeoFeature($recordId, $recordCoord, $title, $bboxCoords)
    {
        $recId = $recordId;
        $recCoord = $recordCoord;
        $recTitle = $title;
        list($bboxW, $bboxE, $bboxN, $bboxS) = $bboxCoords;
        $centerData = [];
        $match = [];
        if (preg_match('/ENVELOPE\((.*),(.*),(.*),(.*)\)/', $recCoord, $match)) {
            // Convert coordinates to 360 degree grid
            $floats = array_map('floatval', $match);
            $matchCoords = [$floats[1], $floats[2], $floats[3], $floats[4]];
            $gridCoords = $this->coordinatesToGrid($matchCoords);
            list($coordW, $coordE, $coordN, $coordS) = $gridCoords;

            // Adjust coordinates on grid if necessary based on search box
            if ($bboxW > 180 && ($coordW > 0 && $coordW < 180)) {
                $coordW = 360 + $coordW;
                $coordE = 360 + $coordE;
            }
            if ($bboxE > 180 && ($coordE > 0 && $coordE < 180)) {
                $coordE = 360 + $coordE;
            }
            //Does coordinate fall within search box
            if ($this->coordBboxIntersect(
                $bboxCoords, [$coordW, $coordE, $coordN, $coordS]
            )
            ) {
                // Calculate center point
                $centerPt = $this->calculateCenterPoint(
                    [$coordW, $coordE, $coordN, $coordS]
                );
                // Does center point intersect search box?
                if ($this->coordBboxIntersect($bboxCoords, $centerPt)) {
                    // Convert center point to long lat cooridnate
                    $ctrLongLat = $this->centerToLongLat(
                        [$centerPt[0], $centerPt[2]]
                    );
                    $centerData = [
                        $recId, $ctrLongLat[0], $ctrLongLat[1], $recTitle
                    ];
                } else {
                    // Recalculate center point
                    $centerCoordBbox = $this->getCenterFromBboxCoordIntersect(
                        [$bboxW, $bboxE, $bboxN, $bboxS],
                        [$coordW, $coordE, $coordN, $coordS]
                    );
                    // Calculate new center point
                    $newCtr = $this->calculateCenterPoint($centerCoordBbox);
                    // Does new center point intersect search box?
                    if ($this->coordBboxIntersect($bboxCoords, $newCtr)) {
                        // Convert new center point to long lat cooridnate
                        $ctrLongLat = $this->centerToLongLat(
                            [$newCtr[0], $newCtr[2]]
                        );
                        $centerData = [
                            $recId, $ctrLongLat[0], $ctrLongLat[1], $recTitle
                        ];
                    } else {
                        // Make center point center of search box
                        $bboxCtr = $this->calculateCenterPoint(
                            [$bboxW, $bboxE, $bboxN, $bboxS]
                        );
                        $ctrLongLat = $this->centerToLongLat(
                            [$bboxCtr[0],$bboxCtr[2]]
                        );
                        $centerData = [
                            $recId, $ctrLongLat[0], $ctrLongLat[1], $recTitle
                        ];
                    }

                }
            }
        }
        return $centerData;
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
        $rawCoords = $this->getSearchResultCoordinates();
        // Convert bbox coords to 360 grid  //
        $bboxCoords = $this->coordinatesToGrid($this->bboxSearchCoords);
        list($bboxW, $bboxE, $bboxN, $bboxS) = $bboxCoords;
        foreach ($rawCoords as $idCoords) {
            foreach ($idCoords[1] as $coord) {
                $recId = $idCoords[0];
                $rawCoordIds[] = $recId;
                $title = $idCoords[2];
                $centerPoint = $this->createGeoFeature(
                    $recId, $coord, $title, $bboxCoords
                );
                if ($centerPoint) {
                    $centerCoordIds[] = $centerPoint[0];
                    $centerCoords[] = $centerPoint;
                    break;
                }
            }
        }
        //Solr search includes close-by geo features
        //Check and add these if there are any
        $addIds = array_merge(
            array_diff($rawCoordIds, $centerCoordIds),
            array_diff($centerCoordIds, $rawCoordIds)
        );
        //Remove duplicate ids
        $addIds = array_unique($addIds);
        if (count($addIds) > 0) {
            $bboxCenter = $this->calculateCenterPoint(
                [$bboxW, $bboxE, $bboxN, $bboxS]
            );
            $centerLongLat = $this->centerToLongLat([$bboxCenter[0],$bboxCenter[2]]);
            foreach ($addIds as $coordId) {
                foreach ($rawCoords as $idCoords) {
                    if ($coordId == $idCoords[0]) {
                        $title = $idCoords[2];
                        $centerCoords[] = [$coordId,
                            $centerLongLat[0],
                            $centerLongLat[1],
                            $title
                        ];
                    }
                }
            }
        }
        return $centerCoords;
    }
}
