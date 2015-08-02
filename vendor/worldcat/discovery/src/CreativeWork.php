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
     * Get Alternative Name
     *
     * @return array
     */
    function getAlternateName()
    {
        $alternateNames = $this->all('schema:alternateName');
        return $alternateNames;
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
     * Return an array of contributors
     * @return array
     */
    
    function getContributors(){
        $contributors = $this->allResources('schema:contributor');
        return $contributors;
    }
    
    /**
     * Return an array of illustrators
     * @return array
     */
    function getIllustrators()
    {
        $illustrators = $this->allResources('schema:illustrators');
        return $illustrators;
    }
    
    /**
     * Return an array of descriptions
     * @return array of EasyRDF_Literals
     */
    function getDescriptions()
    {
        $description = $this->all('schema:description');
        return $description;
    }
    
    /**
     * Return the language
     * @return EasyRDF_Literal
     */
    function getLanguage()
    {
        $language = $this->get('schema:inLanguage');
        return $language;
    }
    
    /**
     * Return the date published
     * @return EasyRDF_Literal
     */
    function getDatePublished()
    {
        $datePublished = $this->get('schema:datePublished');
        return $datePublished;
    }
    
    /**
     * Return the publisher
     * @return \WorldCat\Discovery\Organization
     */
    function getPublisher()
    {
        $publisher = $this->getResource('schema:publisher');
        return $publisher;
    }
    
    /**
     * Return an array of Genres
     * @return array EasyRDF_Literal
     */
    function getGenres(){
        $genres = $this->all('schema:genre');
        return $genres;
    }
    
    /**
     * Return the type
     * @return string
     */
    function getType(){
        return $this->type();
    }
    
    /**
     * Return the Work
     * @return EasyRDF_Resource
     */
    function getWork(){
        return $this->getResource('schema:exampleOfWork');
    }
    
    /**
     * Return an array of abouts
     * @return array EasyRDF_Resources
     */
    function getAbout() {
        $about = $this->allResources('schema:about');
        return $about;
    }
    
    /**
     * Return an array of places of publication
     * @return array
     */
    function getPlacesOfPublication(){
        $placesOfPublication = $this->all('library:placeOfPublication');
        return $placesOfPublication;
    }
    
    /**
     * Return an array of urls
     * @return array
     */
    function getUrls(){
        $urls = $this->all('schema:url');
        return $urls;
    }
    
    /**
     * Return the data set
     * return array EasyRDF_Resource
     */
    function getDataSets()
    {
        if ($this->getResource('wdrs:describedby')){
            $dataset = $this->getResource('wdrs:describedby')->all('void:inDataset');
        }else {
            $schemaUrls = $this->allResources('schema:url');
            $describedBy = array_filter($schemaUrls, function($schemaUrl)
            {
                return(strpos($schemaUrl->getURI(), 'worldcat.org/title'));
            });
            $describedBy = array_shift($describedBy);
            $dataset = $describedBy->all('void:inDataset');
        }
        
        return $dataset;
    }
    
    /**
     * Return the audience
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
     * return the content rating
     * @return EasyRDF_Literal
     */
    function getContentRating()
    {
        return $this->get('schema:contentRating');
    }
    
    /**
     * Return the part of
     * @return EasyRDF_Resource
     */
    function getIsPartOf()
    {
        $isPartOf = $this->getResource('schema:isPartOf');
        return $isPartOf;
    }
    
    /**
     * Get an array of reviews
     * @return array
     */
    function getReviews(){
        $reviews = $this->all('schema:review');
        return $reviews;
    }
    

    /**
     * Get an array of Awards
     * return array
     */
    function getAwards()
    {
        $awards =  $this->all('schema:awards');
        return $awards;
    }
    
    /**
     * Get the number of pages
     * @return EasyRDF_Literal
     */
    function getNumberOfPages(){
        $numberOfPages = $this->get('schema:numberOfPages');
        return $numberOfPages;
    }
}