<?php
/**
 * MapSelection Recommendations Module
 *
 * Adapted to fit GeoRef database needs - LMG. 
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
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;
    
    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
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
        $iniName = isset($settings[1]) ? $settings[1] : 'searches';
        $config = $this->configLoader->get($iniName);
        if (isset($config->$mainSection)) {
            $entries = $config->$mainSection;
            if (isset($entries->default_coordinates)) {
                $this->defaultCoordinates = explode(
                    ',', $entries->default_coordinates
                );
            }
            if (isset($entries->geo_field)) {
                $this->geoField = $entries->geo_field;
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
        $coords = [];
        $filters = $params->getFilters();
        foreach ($filters as $key => $value) {
            if ($key == $this->geoField) {
                $match = [];
                $pattern = '/Intersects\(ENVELOPE\((.*), (.*), (.*), (.*)\)\)/';
                if (preg_match($pattern, $value[0], $match)) {
                    // Need to reorder coords from WENS to WSEN
                    array_push(
                        $coords, (float)$match[1],
                        (float)$match[4], (float)$match[2],
                        (float)$match[3]
                    );
                }
            }
        }
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
                $pattern = '/Intersects\(ENVELOPE\((.*), (.*), (.*), (.*)\)\)/';
                if (preg_match($pattern, $value[0], $match)) {
                    // Need to reorder coords from WENS to WSEN
                    array_push(
                        $reorder_coords, (float)$match[1],
                        (float)$match[4], (float)$match[2],
                        (float)$match[3]
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

}
