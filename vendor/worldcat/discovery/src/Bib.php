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
class Bib extends EasyRdf_Resource
{
    public static $serviceUrl = 'https://beta.worldcat.org/discovery';
    public static $testServer = FALSE;
    public static $userAgent = 'WorldCat Discovery API PHP Client';
    private $bib; 
   
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
        if (!$this->creativeWork->type()){
            $this->graph->addType($this->creativeWork->getUri(), 'schema:CreativeWork');
        }
        
        if (get_class($this->creativeWork) == 'EasyRdf_Resource'){
            if ($this->creativeWork->type()){
                $type = $this->creativeWork->type();
            } else {
                $type = 'schema:CreativeWork';
            }
            EasyRdf_TypeMapper::set($type, 'WorldCat\Discovery\CreativeWork');
            $creativeWorkGraph = new EasyRdf_Graph();
            $creativeWorkGraph->parse($this->graph->serialise('rdfxml'));
            return $creativeWorkGraph->resource($this->creativeWork->getUri());
        } else {
            return $this->creativeWork;
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
        
        $bibURI = Bib::$serviceUrl . '/bib/data/' . $id;
        
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
        if (!is_string($query)){
            Throw new \BadMethodCallException('You must pass a valid query');
        } elseif (!is_a($accessToken, '\OCLC\Auth\AccessToken')) {
            Throw new \BadMethodCallException('You must pass a valid OCLC/Auth/AccessToken object');
        }
        
        static::requestSetup();
                
        $guzzleOptions = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $accessToken->getValue(),
                'Accept' => 'application/rdf+xml'
            )
        );
        
        if (static::$testServer){
            $guzzleOptions['verify'] = false;
        }
        
        $bibSearchURI = Bib::$serviceUrl . '/bib/search?' . static::buildParameters($query, $options);
        
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
        EasyRdf_TypeMapper::set('bgn:Agent', 'WorldCat\Discovery\Organization');
        EasyRdf_TypeMapper::set('foaf:Agent', 'WorldCat\Discovery\Organization');
        
        EasyRdf_TypeMapper::set('schema:Organization', 'WorldCat\Discovery\Organization');
        EasyRdf_TypeMapper::set('foaf:Organization', 'WorldCat\Discovery\Organization'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Person', 'WorldCat\Discovery\Person');
        EasyRdf_TypeMapper::set('foaf:Person', 'WorldCat\Discovery\Person'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Place', 'WorldCat\Discovery\Place');
        EasyRdf_TypeMapper::set('http://dbpedia.org/ontology/Place', 'WorldCat\Discovery\Place'); // will be deprecated
        
        EasyRdf_TypeMapper::set('discovery:SearchResults', 'WorldCat\Discovery\BibSearchResults');
        EasyRdf_TypeMapper::set('discovery:Facet', 'WorldCat\Discovery\Facet');
        EasyRdf_TypeMapper::set('discovery:FacetItem', 'WorldCat\Discovery\FacetItem');
        EasyRdf_TypeMapper::set('response:ClientRequestError', 'WorldCat\Discovery\Error');
        
        if (!class_exists('Guzzle')) {
            \Guzzle\Http\StaticClient::mount();
        }
    }
    
    /**
     * Build the query string for the request
     * 
     * @param string $query
     * @param array $options
     * @return string
     */
    private static function buildParameters($query, $options = null)
        {
        $parameters = array('q' => $query);

        $repeatingQueryParms = '';
        if (!empty($options)){
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
        
        if (empty($parameters['dbIds'])){
            $parameters['dbIds'] = 638;
        }
        
        $queryString =  http_build_query($parameters) . $repeatingQueryParms;
        
        return $queryString;         
    }
    
}