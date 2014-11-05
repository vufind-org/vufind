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

/**
 * A class that represents a Person in Schema.org
 *
 */
class Person extends Thing
{   
    public static $viafServiceUrl = 'http://viaf.org/viaf';
    
    /**
     * Get the Given Name
     * @return string
     */
    function getGivenName(){
        return $this->getLiteral('schema:givenName');
    }
    
    /**
     * Get the Family Name
     * @return string
     */
    function getFamilyName(){
        return $this->getLiteral('schema:familyName');
    }
    
    /**
     * Get the BirthDate
     * @return string
     */
    function getBirthDate(){
        if ($this->getLiteral('schema:birthDate')){
            return $this->getLiteral('schema:birthDate');
        } else {
            if (strpos($this->getURI(), 'viaf')){
                $viaf = static::findByURI($this->getUri());
            }
            return $viaf->getLiteral('rdaGr2:schema:birthDate');
        }
    }
    
    /**
     * Get the DeathDate
     * @string
     */
    function getDeathDate(){
        
        if ($this->getLiteral('schema:deathDate')){
            return $this->getLiteral('schema:deathDate');
        } else {
            if (strpos($this->getURI(), 'viaf')){
                $viaf = static::findByURI($this->getUri());
            }
            return $viaf->getLiteral('schema:deathDate');
        }
    }
    
    /**
     * Get the Same As properties
     * @return array of EasyRDF_Resource
     */
    function getSameAsProperties(){
        if ($this->all('owl:sameAs')){
            return $this->all('owl:sameAs');
        } else {
            return $this->all('schema:sameAs');
        }
    }
    
    /**
     * Get the See Also properties
     * @return array of EasyRDF_Resource
     */
    function getSeeAlsoProperties(){
        return $this->all('rdfs:seeAlso');
    }
    
    /**
     * Get the Dbpedia URI properties
     * @return EasyRDF_Resource
     */
    function getDbpediaUri(){
        if (strpos($this->getURI(), 'viaf')){
            $viafResource = static::findByURI($this->getURI());
        
            $sameAsProperties = $viafResource->getSameAsProperties();
            $dbpediaPerson = array_filter($sameAsProperties, function($sameAs)
            {
                return(strpos($sameAs->getURI(), 'dbpedia'));
            }); 
            $dbpediaPerson = array_shift($dbpediaPerson);
            if (isset($dbpediaPerson)){
                return $dbpediaPerson->getURI();
            }
        }
    }
    
    /**
     * Get the Creative Works
     * @return array of EasyRDF_Resource
     */
    public function getCreativeWorks(){
        if (strpos($this->getURI(), 'viaf')){
            $graph = static::getByURI($this->getURI(), true);
            $creativeWorks = $graph->allOfType('schema:CreativeWork');
            return $creativeWorks;
        }
    }
    
    /**
     * Retrieve a person by its VIAF ID
     *
     * @param string $id
     * @return WorldCat\Discovery\Person
     */
    public static function findByVIAFID($id){
        $uri = static::$viafServiceUrl . '/' . $id;
        return static::findByURI($uri);
    }
    
}
