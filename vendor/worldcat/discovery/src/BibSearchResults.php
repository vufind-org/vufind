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

use \EasyRdf_Resource;
use \EasyRdf_Format;

/**
 * A class that represents Bibliographic Search Results in WorldCat
 *
 */
class BibSearchResults extends SearchResults
{ 
    
    /**
     * Get an array of search results (EasyRDF_Resource objects)
     * 
     * @return array
     */
    function getSearchResults(){
        $searchResults = $this->graph->allOfType('http://www.w3.org/2006/gen/ont#InformationResource');
        $sortedSearchResults = array();
        foreach ($searchResults as $result){
            $sortedSearchResults[(int)$result->getCreativeWork()->getDisplayPosition()] = $result->getCreativeWork();
        }
        ksort($sortedSearchResults);
        return $sortedSearchResults;
    }
    
    /**
     * Get an array of Facets (WorldCat/Discovery/Facet)
     * 
     * @return array
     */
     function getFacets(){
         $facetList = $this->graph->allOfType('discovery:Facet');
         return $facetList;
     } 
}