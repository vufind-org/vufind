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

/**
 * A class that represents an Error in WorldCat Discovery
 *
 */
class Error extends EasyRdf_Resource
{
    
    /**
     * Get Error Type
     *
     * @return string
     */
    function getErrorType()
    {   
        $errorType = $this->get('discovery:errorType');
        return $errorType->getValue();
    }
    
    /**
     * Get Error Code
     *
     * @return string
     */
    function getErrorCode()
    {
        $errorCode = $this->get('discovery:errorCode');
        return $errorCode->getValue();
    }
    
    /**
     * Get Error Message
     *
     * @return string
     */
    function getErrorMessage()
    {
        $errorMessage = $this->get('discovery:errorMessage');
        return $errorMessage->getValue();
    }
    
    /**
     * Parse the response body for the error information
     * 
     * @param string $error
     * @return WorldCat\Discovery\Error
     */
    static function parseError($error){
        EasyRdf_Namespace::set('discovery', 'http://worldcat.org/vocab/discovery/');
        EasyRdf_Namespace::set('response', 'http://worldcat.org/xmlschemas/response/');
        
        $graph = new EasyRdf_Graph();
        try {
            $graph->parse($error->getResponse()->getBody(true));
            $errors = $graph->allOfType('response:ClientRequestError');
            return $errors[0];
        } catch (\EasyRdf_Exception $e) {
            $clientError = $graph->newBNode('response:ClientRequestError');
            $clientError->set('discovery:errorType', 'http');
            $clientError->set('discovery:errorCode', $error->getResponse()->getStatusCode());
            $clientError->set('discovery:errorMessage', $error->getResponse()->getReasonPhrase());
            $errors = $graph->allOfType('response:ClientRequestError');
            return $errors[0];
        }
        
    }
    
    
}