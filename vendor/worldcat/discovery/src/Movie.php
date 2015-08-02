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
 * A class that represents a Book in WorldCat
 *
 */
class Movie extends CreativeWork
{
    
    /**
     * Get an array of the Actors
     * @return array
     */
    function getActors(){
        return $this->allResources('schema:actor');
    }
    
    /**
     * Get the director
     * @return EasyRDF_Resource
     */
    function getDirector()
    {
        return $this->getResource('schema:director');
    }
    
    /**
     * Get an array of Producers
     * @return array
     */
    function getProducers()
    {
        return $this->allResources('schema:producer');
    }
    
    /**
     * Get musicBy
     * @return array
     */
    function getMusicBy()
    {
        return $this->allResources('schema:musicBy');
    }
    
    /**
     * Get productionCompany
     * @return array
     */
    function getProductionCompany()
    {
        return $this->allResources('schema:productionCompany');
    }
}