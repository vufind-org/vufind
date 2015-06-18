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
	use Helpers;
	
	public static $testServer = FALSE;
    public static $userAgent = 'WorldCat Discovery API PHP Client';
	
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
    
    public static function findByURI($uri, $options = null) {
    	if (!isset($options)){
    		$options = array();
    	}
    	
    	static::requestSetup();
    	
    	if (empty($options['accept']) && strpos($uri, 'viaf')){
    		$options['accept'] = 'application/rdf+xml';
    	} elseif (empty($options['accept'])) {
    		$options['accept'] = null;
    	}
    	if (isset($options['logger'])){
    		$logger = $options['logger'];
    	} else {
    		$logger = null;
    	}
    	$guzzleOptions = static::getGuzzleOptions($options);
        
        try {
            $response = \Guzzle::get($uri, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($response->getBody(true));
            if (isset($options['returnGraph'])){
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
