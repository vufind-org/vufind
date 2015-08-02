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
class Database extends EasyRdf_Resource
{
    use Helpers;
    
    public static $serviceUrl = 'https://beta.worldcat.org/discovery';
    public static $testServer = FALSE;
    public static $userAgent = 'WorldCat Discovery API PHP Client';
    
    private $database;
    
    /**
     * Get the ID in the form of a URI
     */
    function getId()
    {
        return $this->getUri();
    }
    
    /**
     * Get Name
     *
     * @return EasyRDF_Literal
     */
    function getName()
    {
        $name = $this->get('schema:name');
        return $name;
    }
    
    /**
     * Get Requires Authentication property value
     *
     * @return EasyRDF_Literal
     */
    function getRequiresAuthentication()
    {
        $requiresAuthentication = $this->get('discovery:requiresAuthentication');
        return $requiresAuthentication;
    }
    
    /**
     * Get Description
     * 
     * @return EasyRDF_Literal
     */
    function getDescription()
    {
        $description = $this->get('schema:description');
        return $description;
    }
    
    
    /**
     * Find and retrieve a WorldCat Discovery Database by ID
     * @param integer $id
     * @param $accessToken OCLC/Auth/AccessToken
     * @param array $options
     * @throws \BadMethodCallException
     * @return WorldCat\Discovery\Database or \Guzzle\Http\Exception\BadResponseException
     */
    
    public static function find($id, $accessToken, $options = null)
    {
        $validRequestOptions = array();
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
        
        $databaseURI = static::$serviceUrl . '/database/data/' . $id;
        
        try {
            $response = \Guzzle::get($databaseURI, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($response->getBody(true));
            //$database = $graph->resource($databaseURI);
            $database = $graph->allOfType('dcmi:Dataset');
            return $database[0];
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
    }
    
    /**
     * 
     * @param $accessToken OCLC/Auth/AccessToken
     * @param $options
     * @throws \BadMethodCallException
     * @return array of WorldCat\Discovery\Database objects or \Guzzle\Http\Exception\BadResponseException
     */
    
    public static function getList($accessToken, $options = null)
    {
        $validRequestOptions = array();
        if (isset($options)){
            $parsedOptions = static::parseOptions($options, $validRequestOptions);
            $requestOptions = $parsedOptions['requestOptions'];
            $logger = $parsedOptions['logger'];
        } else {
            $requestOptions = array();
            $logger = null;
        }
        
        if (!is_a($accessToken, '\OCLC\Auth\AccessToken')) {
            Throw new \BadMethodCallException('You must pass a valid OCLC/Auth/AccessToken object');
        }
        
        static::requestSetup();
                
        $guzzleOptions = static::getGuzzleOptions(array('accessToken' => $accessToken, 'logger' => $logger));
        
        $databaseListURI = static::$serviceUrl . '/database/list';
        
        try {
            $listResponse = \Guzzle::get($databaseListURI, $guzzleOptions);
            $graph = new EasyRdf_Graph();
            $graph->parse($listResponse->getBody(true));
            $list = $graph->allOfType('dcmi:Dataset');
            return $list;
        } catch (\Guzzle\Http\Exception\BadResponseException $error) {
            return Error::parseError($error);
        }
    }
    
}