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
 * A class that represents a Thing in Schema.org
 *
 */
class Thing extends EasyRdf_Resource
{
    /**
     * Get ID
     * @return string
     */
    function getId()
    {
        return $this->getUri();
    }
    
    /**
     * Get Name
     *
     * @return string
     */
    function getName()
    {
        $name = $this->get('schema:name');
        return $name;
    }
    
    /**
     * Get a graph for a Thing by the URI
     * 
     * @param string $uri
     * @param string $returnGraph
     * @return \EasyRdf_Graph | WorldCat\Discovery\Error
     */
    
    public static function findByURI($uri, $returnGraph = false) {
        EasyRdf_Namespace::set('schema', 'http://schema.org/');
        EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
        EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        EasyRdf_Namespace::set('foaf', 'http://xmlns.com/foaf/0.1/');
        EasyRdf_Namespace::set('madsrdf', 'http://www.loc.gov/mads/rdf/v1#');
        EasyRdf_TypeMapper::set('schema:Organization', 'WorldCat\Discovery\Organization');
        EasyRdf_TypeMapper::set('foaf:Organization', 'WorldCat\Discovery\Organization'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Person', 'WorldCat\Discovery\Person');
        EasyRdf_TypeMapper::set('foaf:Person', 'WorldCat\Discovery\Person'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:Place', 'WorldCat\Discovery\Place');
        EasyRdf_TypeMapper::set('http://dbpedia.org/ontology/Place', 'WorldCat\Discovery\Place'); // will be deprecated
        EasyRdf_TypeMapper::set('schema:CreativeWork', 'WorldCat\Discovery\CreativeWork');
        EasyRdf_TypeMapper::set('madsrdf:Topic', 'WorldCat\Discovery\TopicalAuthority');
        EasyRdf_TypeMapper::set('madsrdf:Geographic', 'WorldCat\Discovery\GeographicAuthority');
        EasyRdf_TypeMapper::set('madsrdf:Authority', 'WorldCat\Discovery\Authority');
    
        $guzzleOptions = array(
            'headers' => array(
                'Accept' => 'application/rdf+xml'
            )
        );
        
        try {
            $response = \Guzzle::get($uri, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($response->getBody(true));
            if ($returnGraph){
                return $graph;
            } else {
                $resource = $graph->resource($uri);
                return $resource;
            }
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
        
        
    }
}
