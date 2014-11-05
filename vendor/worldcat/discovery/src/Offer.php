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
        
        if (!is_numeric($id)){
            Throw new \BadMethodCallException('You must pass a valid ID');
        } elseif (!is_a($accessToken, '\OCLC\Auth\AccessToken')) {
            Throw new \BadMethodCallException('You must pass a valid OCLC/Auth/AccessToken object');
        }
        
        static::requestSetup();
        
        $guzzleOptions = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $accessToken->getValue(),
                'Accept' => 'application/rdf+xml',
                'User-Agent' => static::$userAgent
            )
        );
        
        if (static::$testServer){
            $guzzleOptions['verify'] = false;
        }
        
        $bibURI = Bib::$serviceUrl . '/offer/oclc/' . $id . '?' . static::buildParameters($options);
        
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
    
    /**
     * Perform the appropriate namespace setting and type mapping in EasyRdf before parsing the graph
     */
    
    private static function requestSetup()
    {
        EasyRdf_Namespace::set('schema', 'http://schema.org/');
        EasyRdf_Namespace::set('discovery', 'http://worldcat.org/vocab/discovery/');
        EasyRdf_Namespace::set('response', 'http://worldcat.org/xmlschemas/response/');
        EasyRdf_Namespace::set('library', 'http://purl.org/library/');
        EasyRdf_Namespace::set('bgn', 'http://bibliograph.net/');
        EasyRdf_Namespace::set('gr', 'http://purl.org/goodrelations/v1#');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
        EasyRdf_Namespace::set('foaf', 'http://xmlns.com/foaf/0.1/');
        EasyRdf_Namespace::set('umbel', 'http://umbel.org/umbel#');
        EasyRdf_Namespace::set('productontology', 'http://www.productontology.org/id/');
        EasyRdf_Namespace::set('wdrs', 'http://www.w3.org/2007/05/powder-s#');
        EasyRdf_Namespace::set('void', 'http://rdfs.org/ns/void#');
        if (!EasyRdf_Namespace::prefixOfUri('http://purl.org/dc/terms/')){
            EasyRdf_Namespace::set('dc', 'http://purl.org/dc/terms/');
        }
        EasyRdf_Namespace::set('rdaGr2', 'http://rdvocab.info/ElementsGr2/');
        EasyRdf_Namespace::set('wcir', 'http://purl.org/oclc/ontology/wcir/');
        
        EasyRdf_TypeMapper::set('schema:Offer', 'WorldCat\Discovery\Offer');
        EasyRdf_TypeMapper::set('discovery:SearchResults', 'WorldCat\Discovery\OfferSet');
        EasyRdf_TypeMapper::set('schema:SomeProducts', 'WorldCat\Discovery\SomeProducts');
        EasyRdf_TypeMapper::set('dc:Collection', 'WorldCat\Discovery\Collection');
        EasyRdf_TypeMapper::set('schema:Library', 'WorldCat\Discovery\Library');
        
        EasyRdf_TypeMapper::set('http://www.w3.org/2006/gen/ont#InformationResource', 'WorldCat\Discovery\Bib');
        EasyRdf_TypeMapper::set('schema:Article', 'WorldCat\Discovery\Article');
        EasyRdf_TypeMapper::set('http://www.productontology.org/id/Image', 'WorldCat\Discovery\Image');
        EasyRdf_TypeMapper::set('schema:MusicAlbum', 'WorldCat\Discovery\MusicAlbum');
        EasyRdf_TypeMapper::set('schema:Periodical', 'WorldCat\Discovery\Periodical');
        EasyRdf_TypeMapper::set('productontology:Thesis', 'WorldCat\Discovery\Thesis');
        EasyRdf_TypeMapper::set('schema:Book', 'WorldCat\Discovery\Book');
        
        EasyRdf_TypeMapper::set('schema:Country', 'WorldCat\Discovery\Country');
        EasyRdf_TypeMapper::set('schema:Event', 'WorldCat\Discovery\Event');
        EasyRdf_TypeMapper::set('schema:Intangible', 'WorldCat\Discovery\Intangible');
        
        EasyRdf_TypeMapper::set('schema:ProductModel', 'WorldCat\Discovery\ProductModel');
        EasyRdf_TypeMapper::set('schema:PublicationVolume', 'WorldCat\Discovery\PublicationVolume');
        EasyRdf_TypeMapper::set('schema:PublicationIssue', 'WorldCat\Discovery\PublicationIssue');
        EasyRdf_TypeMapper::set('foaf:Agent', 'WorldCat\Discovery\Organization');
        
        EasyRdf_TypeMapper::set('schema:Organization', 'WorldCat\Discovery\Organization');
        EasyRdf_TypeMapper::set('foaf:Organization', 'WorldCat\Discovery\Organization'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Person', 'WorldCat\Discovery\Person');
        EasyRdf_TypeMapper::set('foaf:Person', 'WorldCat\Discovery\Person'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Place', 'WorldCat\Discovery\Place');
        EasyRdf_TypeMapper::set('http://dbpedia.org/ontology/Place', 'WorldCat\Discovery\Place'); // will be deprecated
        
        EasyRdf_TypeMapper::set('response:ClientRequestError', 'WorldCat\Discovery\Error');
        
        if (!class_exists('Guzzle')) {
            \Guzzle\Http\StaticClient::mount();
        }
    }
    
    /**
     * Build the query string for the request
     *
     * @param array $options
     * @return string
     */
    private static function buildParameters($options = null)
    {
        $parameters = array();
    
        if (!empty($options)){
            $repeatingQueryParms = '';
            foreach ($options as $option => $optionValue){
                if (!is_array($optionValue)){
                    $parameters[$option] = $optionValue;
                } else {
                    foreach ($optionValue as $value){
                        $repeatingQueryParms .= '&' . $option . '=' . $value;
                    }
                }
            }
        }
    
        $queryString =  http_build_query($parameters) . $repeatingQueryParms;
    
        return $queryString;
    }
    
}