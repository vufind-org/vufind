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
use \EasyRdf_Format;
use \EasyRdf_Namespace;
use \EasyRdf_TypeMapper;

/**
 * A class that represents a Bibliographic Resource in WorldCat
 *
 */
class Offer extends EasyRdf_Resource
{
    use Helpers;
    
    public static $serviceUrl = 'https://beta.worldcat.org/discovery';
    public static $testServer = FALSE;
    public static $userAgent = 'WorldCat Discovery API PHP Client';
    
    /**
     * @param $id string
     * @param $accessToken OCLC/Auth/AccessToken
     * @param $options array All the optional parameters are valid
     * - heldBy array which is a limiter to restrict search results to items held by a given institution(s)
     * - notHeldBy array which is imiter to restrict search results to items that are not held by a given institution(s).
     * - heldByGroup array which is a limiter to restrict search results to items held by a given group(s)
     * - heldInCountry string which is a limiter to restrict search results to items held by institutions within a specific ISO country code
     * - useFRBRGrouping boolean whether or not the reponse returns the representative record for the FRBR group.
     * - startIndex integer offset from the beginning of the search result set. defaults to 0
     * - itemsPerPage integer representing the number of items to return in the result set. defaults to 10
     * 
     * Limit to items heldby items within a defined radius. These four options MUST be used together
     * - lat
     * - lon
     * - distance
     * - unit
     * @return WorldCat\Discovery\OfferSet or WorldCat\Discovery\Error
     */
    
    public static function findByOclcNumber($id, $accessToken, $options = null)
    {
        $validRequestOptions = array('heldBy', 'notHeldBy', 'heldByGroup', 'heldInCountry', 'useFRBRGrouping', 'startIndex', 'itemsPerPage', 'lat', 'lon', 'distance', 'unit');
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
            } elseif ((isset($requestOptions['lat']) || isset($requestOptions['lon']) || isset($requestOptions['distance']) || isset($requestOptions['unit'])) && !($requestOptions['lat'] && $requestOptions['lon'] && $requestOptions['distance'] && $requestOptions['unit'] )){
            Throw new \BadMethodCallException('If you are limiting by holding in a radius, lat, lon, distance, and unit options are required');
        }
        
        static::requestSetup();
        
        $guzzleOptions = static::getGuzzleOptions($accessToken, $logger);
        
        $bibURI = Bib::$serviceUrl . '/offer/oclc/' . $id . '?' . static::buildParameters(null, $requestOptions);
        
        try {
            $response = \Guzzle::get($bibURI, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($response->getBody(true));
            $results = $graph->allOfType('discovery:SearchResults');
            return $results[0];
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
    }
    
    /**
     * Get ID
     *
     * @return string
     */
    function getId()
    {
        return $this->getUri();
    }
    
    /**
     * Get Display Position
     *
     * @return string
     */
    function getDisplayPosition()
    {
        return $this->get('gr:displayPosition')->getValue();
    }
    
    /**
     * Get the Item Offered
     * @return WorldCat\Discovery\SomeProducts
     */
    public function getItemOffered()
    {
        $itemOffered = $this->get('schema:itemOffered');
        return $itemOffered;
    }
    
    /**
     * Get Price
     *
     * @return integer
     */
    public function getPrice()
    {
        $price = $this->get('schema:price');
        return $price->getValue();
    }
    
    /**
     * Get Seller
     *
     * @return WorldCat\Discovery\Library
     */
    
    function getSeller()
    {
        $seller = $this->get('schema:seller');
        return $seller;
    }
}