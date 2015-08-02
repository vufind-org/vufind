<?php
// Copyright 2014 OCLC
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
// http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

namespace WorldCat\Discovery;

use \EasyRdf_Graph;
use \EasyRdf_Resource;
use \EasyRdf_TypeMapper;

/**
 * A class that represents a Bibliographic Resource in WorldCat
 *
 */
class Bib extends EasyRdf_Resource
{
    use Helpers;
    
    public static $serviceUrl = 'https://beta.worldcat.org/discovery';
    public static $testServer = FALSE;
    public static $userAgent = 'WorldCat Discovery API PHP Client';
    private $bib;
    protected $creativeWork;
   
    /**
     * Construct the Bib object and set the creativeWork property
     * 
     * @param string $uri
     * @param string $graph
     */
    public function __construct($uri, $graph = null){
        parent::__construct($uri, $graph);
        $this->creativeWork = $this->getResource('schema:about');
    }
    
    /**
     * Get the Creative Work associated with this Bib
     * @return WorldCat\Discovery\CreativeWork or WorldCat\Discovery\Article or WorldCat\Discovery\Book or WorldCat\Discovery\Image WorldCat\Discovery\MusicAlbum or WorldCat\Discovery\Periodical or WorldCat\Discovery\Thesis
     */
    public function getCreativeWork()
    {
		if (!$this->creativeWork->types()){
			$this->graph->addType($this->creativeWork->getUri(), 'schema:CreativeWork');
		}
		
		$additionalTypes = static::getAdditionalTypesToMap($this->graph);
		if (empty($additionalTypes)){
		    return $this->creativeWork;
		} else {
		    static::mapTypes($additionalTypes);
		    $creativeWork = static::reloadGraph($this->graph)->resource($this->creativeWork->getUri());
		    static::deleteTypeMapping($additionalTypes);
		    return $creativeWork;
		}
    }
    
    
    /**
     * Find and retrieve a Bib by OCLC Number
     * 
     * @param $id string
     * @param $accessToken OCLC/Auth/AccessToken
     * @param $options array
     * @return WorldCat\Discovery\Bib or \Guzzle\Http\Exception\BadResponseException
     */
    public static function find($id, $accessToken, $options = null)
    {
        $validRequestOptions = array('useFRBRGrouping');
        if (isset($options)){
            $parsedOptions = static::parseOptions($options, $validRequestOptions);
            $requestOptions = $parsedOptions['requestOptions'];
            $logger = $parsedOptions['logger'];
        } else {
            $requestOptions = array();
            $logger = null;
        }
        
        if (!is_numeric($id)){
            Throw new \BadMethodCallException('You must pass a valid ID');
        } elseif (!is_a($accessToken, '\OCLC\Auth\AccessToken')) {
            Throw new \BadMethodCallException('You must pass a valid OCLC/Auth/AccessToken object');
        }
        
        static::requestSetup();
        
        
        
        $guzzleOptions = static::getGuzzleOptions(array('accessToken' => $accessToken, 'logger' => $logger));
        
        $bibURI = Bib::$serviceUrl . '/bib/data/' . $id;
        
        if (!empty($requestOptions)){
            $bibURI .= '?' . static::buildParameters(null, $requestOptions);
        }
        
        try {
            $response = \Guzzle::get($bibURI, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($response->getBody(true));
            $bib = $graph->resource('http://www.worldcat.org/title/-/oclc/' . $id);
            return $bib->getCreativeWork();
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
    }
    
    /**
     * @param $query string
     * @param $accessToken OCLC/Auth/AccessToken
     * @param $options array All the optional parameters are valid
     * - dbIds array which is the databases to search within. If not set defaults to 638 WorldCat.org
     * - sortBy string which is how to sort results.
     * - heldBy array which is a limiter to restrict search results to items held by a given institution(s)
     * - notHeldBy array which is limiter to restrict search results to items that are not held by a given institution(s).
     * - heldByGroup array which is a limiter to restrict search results to items held by a given group(s)
     * - heldInCountry string which is a limiter to restrict search results to items held by institutions within a specific ISO country code
     * - inLanguage
     * - materialType array which is a limiter to restrict search results to items which are a particular materialType(s)
     * - datePublished string which is a limiter to restrict search results to a particular datePublished. Can be range
     * - inCatalogLanguage string which is a limiter to restrict search results to items cataloged in a particular language
     * - catalogSource string
     * - itemType string
     * - itemSubType string
     * - peerReview boolean
     * - useFRBRGrouping boolean whether or not the reponse returns the representative record for the FRBR group.
     * - facetQueries an array of facets data to refine query to
     * - facetFields an array of facets to be returned. Takes the form of facetName:numberOfItems
     * - startIndex integer offset from the beginning of the search result set. defaults to 0
     * - itemsPerPage integer representing the number of items to return in the result set. defaults to 10
     * 
     * Limit to items heldby items within a defined radius. These four options MUST be used together
     * - lat
     * - lon
     * - distance
     * - unit
     * @return WorldCat\Discovery\SearchResults or \Guzzle\Http\Exception\BadResponseException
     */
    
    public static function search($query, $accessToken, $options = null)
    {
        $validRequestOptions = array('dbIds', 'sortBy', 'heldBy', 'notHeldBy', 'heldByGroup', 'heldInCountry', 'inLanguage', 'materialType', 'datePublished', 'inCatalogLanguage', 'catalogSource', 'itemType', 'itemSubType', 'peerReview', 'useFRBRGrouping', 'facetQueries', 'facetFields', 'startIndex', 'itemsPerPage', 'lat', 'lon', 'distance', 'unit');
        if (isset($options)){
            $parsedOptions = static::parseOptions($options, $validRequestOptions);
            $requestOptions = $parsedOptions['requestOptions'];
            $logger = $parsedOptions['logger'];
        } else {
            $requestOptions = array();
            $logger = null;
        }
        
        if (!is_string($query)){
            Throw new \BadMethodCallException('You must pass a valid query');
        } elseif (!is_a($accessToken, '\OCLC\Auth\AccessToken')) {
            Throw new \BadMethodCallException('You must pass a valid OCLC/Auth/AccessToken object');
        } elseif ((isset($requestOptions['lat']) || isset($requestOptions['lon']) || isset($requestOptions['distance']) || isset($requestOptions['unit'])) && !($requestOptions['lat'] && $requestOptions['lon'] && $requestOptions['distance'] && $requestOptions['unit'] )){
            Throw new \BadMethodCallException('If you are searching by holding in a radius, lat, lon, distance, and unit options are required');
        }
        
        static::requestSetup();
                
        $guzzleOptions = static::getGuzzleOptions(array('accessToken' => $accessToken, 'logger' => $logger));
        
        if (empty($requestOptions['dbIds'])){
            $requestOptions['dbIds'] = 638;
        }
        
        $bibSearchURI = Bib::$serviceUrl . '/bib/search?' . static::buildParameters($query, $requestOptions);
        
        try {
            $searchResponse = \Guzzle::get($bibSearchURI, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($searchResponse->getBody(true));
            $search = $graph->allOfType('discovery:SearchResults');
            $search = $search[0];
            return $search;
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
    }
}