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
use \EasyRdf_Namespace;
use \EasyRdf_TypeMapper;
use \EasyRdf_Format;

trait Helpers {

    /**
     * Parse the $options array in parts
     */
    protected static function parseOptions($options, $validRequestOptions)
    {
        if (empty($options) || !is_array($options)){
            Throw new \BadMethodCallException('Options must be a valid array');
        } elseif (isset($options['logger']) && !is_a($options['logger'], 'Guzzle\Plugin\Log\LogPlugin')){
            Throw new \BadMethodCallException('The logger must be a valid Guzzle\Plugin\Log\LogPlugin object');
        }
        
        if (isset($options['logger'])){
            $logger = $options['logger'];
        } else {
            $logger = null;
        }
        
        $optionParts = array(
            'requestOptions' => static::getRequestOptions($options, $validRequestOptions),
            'logger' => $logger
        );
        return $optionParts;
    }
    
    protected static function getRequestOptions($options, $validRequestOptions){
            $requestOptions = array();
            foreach ($options as $optionName => $option) {
                if (in_array($optionName, $validRequestOptions)){
                    $requestOptions[$optionName] = $option;
                }
            }
            return $requestOptions;
    }
    
    /**
     * Perform the appropriate namespace setting and type mapping in EasyRdf before parsing the graph
     */
    protected static function requestSetup()
    {
        EasyRdf_Namespace::set('schema', 'http://schema.org/');
        EasyRdf_Namespace::set('discovery', 'http://worldcat.org/vocab/discovery/');
        EasyRdf_Namespace::set('response', 'http://worldcat.org/xmlschemas/response/');
        EasyRdf_Namespace::set('library', 'http://purl.org/library/');
        EasyRdf_Namespace::set('wcir', 'http://purl.org/oclc/ontology/wcir/');
        EasyRdf_Namespace::set('bgn', 'http://bibliograph.net/');
        EasyRdf_Namespace::set('gr', 'http://purl.org/goodrelations/v1#');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
        EasyRdf_Namespace::set('foaf', 'http://xmlns.com/foaf/0.1/');
        EasyRdf_Namespace::set('umbel', 'http://umbel.org/umbel#');
        EasyRdf_Namespace::set('productontology', 'http://www.productontology.org/id/');
        EasyRdf_Namespace::set('mo', 'http://purl.org/ontology/mo/');
        EasyRdf_Namespace::set('wdrs', 'http://www.w3.org/2007/05/powder-s#');
        EasyRdf_Namespace::set('void', 'http://rdfs.org/ns/void#');
        if (!EasyRdf_Namespace::prefixOfUri('http://purl.org/dc/terms/')){
            EasyRdf_Namespace::set('dc', 'http://purl.org/dc/terms/');
        }
        EasyRdf_Namespace::set('dcmi', 'http://purl.org/dc/dcmitype/');
        EasyRdf_Namespace::set('rdaGr2', 'http://rdvocab.info/ElementsGr2/');
        EasyRdf_Namespace::set('madsrdf', 'http://www.loc.gov/mads/rdf/v1#');
    
        EasyRdf_TypeMapper::delete('schema:MediaObject'); // I do not know why I need this.
        EasyRdf_TypeMapper::set('http://www.w3.org/2006/gen/ont#InformationResource', 'WorldCat\Discovery\Bib');
    
        EasyRdf_TypeMapper::set('schema:Article', 'WorldCat\Discovery\Article');
        EasyRdf_TypeMapper::set('http://www.productontology.org/id/Image', 'WorldCat\Discovery\Image');
        EasyRdf_TypeMapper::set('schema:MusicAlbum', 'WorldCat\Discovery\MusicAlbum');
        EasyRdf_TypeMapper::set('schema:Periodical', 'WorldCat\Discovery\Periodical');
        EasyRdf_TypeMapper::set('productontology:Thesis', 'WorldCat\Discovery\Thesis');
        EasyRdf_TypeMapper::set('library:Kit', 'WorldCat\Discovery\Kit');
        EasyRdf_TypeMapper::set('bgn:Kit', 'WorldCat\Discovery\Kit');
        EasyRdf_TypeMapper::set('schema:Movie', 'WorldCat\Discovery\Movie');
        EasyRdf_TypeMapper::set('schema:Book', 'WorldCat\Discovery\Book');
        EasyRdf_TypeMapper::set('schema:Series', 'WorldCat\Discovery\Series');
    
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
        
        EasyRdf_TypeMapper::set('madsrdf:Topic', 'WorldCat\Discovery\TopicalAuthority');
        EasyRdf_TypeMapper::set('madsrdf:Geographic', 'WorldCat\Discovery\GeographicAuthority');
        EasyRdf_TypeMapper::set('madsrdf:Authority', 'WorldCat\Discovery\Authority');
    
        if (__CLASS__ == 'WorldCat\Discovery\Bib') {
            EasyRdf_TypeMapper::set('discovery:SearchResults', 'WorldCat\Discovery\BibSearchResults');
            EasyRdf_TypeMapper::set('discovery:Facet', 'WorldCat\Discovery\Facet');
            EasyRdf_TypeMapper::set('discovery:FacetItem', 'WorldCat\Discovery\FacetItem');
        } elseif (__CLASS__ == 'WorldCat\Discovery\Offer') {
            EasyRdf_TypeMapper::set('schema:Offer', 'WorldCat\Discovery\Offer');
            EasyRdf_TypeMapper::set('discovery:SearchResults', 'WorldCat\Discovery\OfferSet');
            EasyRdf_TypeMapper::set('schema:SomeProducts', 'WorldCat\Discovery\SomeProducts');
            EasyRdf_TypeMapper::set('dc:Collection', 'WorldCat\Discovery\Collection');
            EasyRdf_TypeMapper::set('schema:Library', 'WorldCat\Discovery\Library');
        } else {
            EasyRdf_TypeMapper::set('dcmi:Dataset', 'WorldCat\Discovery\Database');
        }
                
        EasyRdf_TypeMapper::set('response:ClientRequestError', 'WorldCat\Discovery\Error');
    
        if (!class_exists('Guzzle')) {
            \Guzzle\Http\StaticClient::mount();
        }
    }
    
    /**
     * Get the relevant Guzzle options for the request
     */
    protected static function getGuzzleOptions($options = null){
    	if (isset($options['accept'])){
    		$accept = $options['accept'];
    	} else{
    		$accept = 'text/plain';
    	}
        $guzzleOptions = array(
            'headers' => array(
                'Accept' => $accept,
                'User-Agent' => static::$userAgent
            )
        );
        
        if (isset($options['accessToken'])){
        	$guzzleOptions['headers']['Authorization'] = 'Bearer ' . $options['accessToken']->getValue();
        }
    
        if (static::$testServer){
            $guzzleOptions['verify'] = false;
        }
    
        if (isset($options['logger'])){
            $guzzleOptions['plugins'] = array($options['logger']);
        }
        return $guzzleOptions;
    }
    
    /**
     * Build the query string for the request
     *
     * @param string $query
     * @param array $options
     * @return string
     */
    protected static function buildParameters($query = null, $options = null)
    {
        $parameters = array();
        
        if (isset($query)){
            $parameters['q'] = $query;
        }
    
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
        
        $queryString =  http_build_query($parameters) . $repeatingQueryParms;
    
        return $queryString;
    }
    
    /**
     * Find out if graph has resources which need type mapping
     * @param EasyRDF_Graph $graph
     * @return array
     */
    protected static function getAdditionalTypesToMap($graph)
    {
        $additionalTypes = array();
        foreach ($graph->allOfType('http://www.w3.org/2006/gen/ont#InformationResource') as $resource) {
            if (get_class($resource->getResource('schema:about')) == 'EasyRdf_Resource'){
                foreach ($resource->getResource('schema:about')->types() as $type){
                    if (isset($type) && !EasyRdf_TypeMapper::get($type)){
                        $additionalTypes[] = $type;
                    }
                }
            }
        }
        return array_unique($additionalTypes);
    }
    
    /**
     * Map types
     * @param array $types
     * @param string $class
     */
    protected static function mapTypes($types, $class = 'WorldCat\Discovery\CreativeWork')
    {
        foreach ($types as $type){
            if (isset($type) && !EasyRdf_TypeMapper::get($type)){
                EasyRdf_TypeMapper::set($type, $class);
            }
        }
    }
    
    /**
     * Delete type mapping
     * @param array $types
     * @param string $class
     */
    protected static function deleteTypeMapping($types)
    {
        foreach ($types as $type){
            if (isset($type) && !EasyRdf_TypeMapper::get($type)){
                EasyRdf_TypeMapper::delete($type);
            }
        }
    }
    
    /**
     * Reload a graph
     * @param EasyRDF_Graph $graph
     * @return EasyRDF_Graph
     */
    protected static function reloadGraph($graph)
    {
        $newGraph = new EasyRdf_Graph();
        $newGraph->parse($graph->serialise('turtle'));
        
        return $newGraph;
    }
}