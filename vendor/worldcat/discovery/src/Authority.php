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

use \EasyRdf_Format;

/**
* A class that represents a MADS Authority
* 
* Used for compound authorities like http://id.loc.gov/authorities/subjects/sh2008124372
*
*/
class Authority extends Thing
{   
    /**
     * Load the Authority from the URI
     * @param string $format
     */ 
    public function load($format = null)
    {
        $formats = EasyRdf_Format::getNames();
        foreach ($formats as $format){
            if ($format != 'rdfxml'){
                EasyRdf_Format::unregister($format);
            }
        }
        parent::load($format = null);
    }
    
    /**
     * Get the Label 
     * @param string $lang the language you want the label in
     * return string
     */
    function label($lang = null) {
        if (parent::label($lang)){
            return parent::label($lang);
        } else {
            return $this->getLiteral('madsrdf:authoritativeLabel', $lang)->getValue();
        }
    }
    
    /**
     * Get the Component List 
     * @return EasyRDF_Resource 
     */
    function getComponentList()
    {
        return $this->getResource('madsrdf:componentList');
    }
    
    /**
     * Get the Topics
     * 
     * @return array of WorldCat\Discovery\TopicalAuthority
     */
    function getTopics()
    {
        $topics = $this->graph->allOfType('madsrdf:Topic');
        return $topics;
    }
    
    /**
     * Get the Geographic Authorities
     *
     * @return array of WorldCat\Discovery\GeographicAuthority
     */
    function getGeographics()
    {
        $geographics = $this->graph->allOfType('madsrdf:Geographic');
        return $geographics;
    }
    
    /**
     * Get the Genre Authorities
     *
     * @return array of WorldCat\Discovery\Authority
     */
    function getGenres()
    {
        $genres = $this->graph->allOfType('madsrdf:GenreForm');
        return $genres;
    }
}