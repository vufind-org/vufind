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
 * A class that represents a generic Creative Work in WorldCat
 *
 */
class CreativeWork extends EasyRdf_Resource
{
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
     * @return EasyRDF_Literal
     */
    function getOCLCNumber()
    {
        $oclcNumber = $this->get('library:oclcnum');
        return $oclcNumber;
    }
    
    /**
     *
     * @return array of WorldCat\Discovery\Person and/or WorldCat\Discovery\Organization objects
     */
    function getAuthors(){
        $authors = array();
        if ($this->getResource('schema:author')){
            $authors[] = $this->getResource('schema:author');
        }
        if ($this->getResource('schema:creator')){
            $authors[] = $this->getResource('schema:creator');
        }
        return $authors;
    }
    
    /**
     * Backwards compatible function that gets the primary author
     * @return WorldCat\Discovery\Person or WorldCat\Discovery\Organization
     */
    function getAuthor(){
        $author = $this->getResource('schema:author');
        if (empty($author)){
            $author = $this->getResource('schema:creator');
        }
        return $author;
    }
    
    /**
     * 
     * @return WorldCat\Discovery\Person or WorldCat\Discovery\Organization
     */
    function getCreator(){
        $creator = $this->getResource('schema:creator');

        return $creator;
    }    
    
    /**
     *
     * @return array
     */
    
    function getContributors(){
        $contributors = $this->allResources('schema:contributor');
        return $contributors;
    }
    
    /**
     *
     * @return array of EasyRDF_Literals
     */
    function getDescriptions()
    {
        $description = $this->all('schema:description');
        return $description;
    }
    
    /**
     * @return EasyRDF_Literal
     */
    function getLanguage()
    {
        $language = $this->get('schema:inLanguage');
        return $language;
    }
    
    /**
     * @return EasyRDF_Literal
     */
    function getDatePublished()
    {
        $datePublished = $this->get('schema:datePublished');
        return $datePublished;
    }
    
    /**
     * @return \WorldCat\Discovery\Organization
     */
    function getPublisher()
    {
        $publisher = $this->getResource('schema:publisher');
        return $publisher;
    }
    
    /**
     * @return array EasyRDF_Literal
     */
    function getGenres(){
        $genres = $this->all('schema:genre');
        return $genres;
    }
    
    /**
     * @return string
     */
    function getType(){
        return $this->type();
    }
    
    /**
     * @return EasyRDF_Resource
     */
    function getWork(){
        return $this->getResource('schema:exampleOfWork');
    }
    
    /**
     * @return array EasyRDF_Resources
     */
    function getAbout() {
        $about = $this->allResources('schema:about');
        return $about;
    }
    
    /**
     * @return array
     */
    function getPlacesOfPublication(){
        $placesOfPublication = $this->all('library:placeOfPublication');
        return $placesOfPublication;
    }
    
    /**
     * @return array
     */
    function getUrls(){
        $urls = $this->all('schema:url');
        return $urls;
    }
    
    /**
     * return EasyRDF_Resource
     */
    function getDataSet()
    {
        if ($this->getResource('wdrs:describedby')){
            $dataset = $this->getResource('wdrs:describedby')->get('void:inDataset');
        }else {
            $schemaUrls = $this->allResources('schema:url');
            $describedBy = array_filter($schemaUrls, function($schemaUrl)
            {
                return(strpos($schemaUrl->getURI(), 'worldcat.org/title'));
            });
            $describedBy = array_shift($describedBy);
            $dataset = $describedBy->get('void:inDataset');
        }
        
        return $dataset;
    }
    
    /**
     * @return EasyRDF_Literal
     */
    function getAudience()
    {
        if ($this->get('schema:audience')){
            $audience = $this->get('schema:audience')->get('schema:audienceType');
            return $audience;
        } 
    }
    
    /**
     * @return EasyRDF_Literal
     */
    function getContentRating()
    {
        return $this->get('schema:contentRating');
    }
    
    /**
     *
     * @return EasyRDF_Resource
     */
    function getIsPartOf()
    {
        $isPartOf = $this->getResource('schema:isPartOf');
        return $isPartOf;
    }
    
}