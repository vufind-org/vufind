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
 * A class that represents a set of Offers in WorldCat
 *
 */
class OfferSet extends SearchResults
{   
    /**
     * Get an array of Offers
     * 
     * @return array WorldCat\Discovery\Offer
     */  
    function getOffers(){
        
        $offers = $this->graph->allOfType('schema:Offer');
        $sortedOffers = array();
        foreach ($offers as $offer){
            $sortedOffers[(int)$offer->getDisplayPosition()] = $offer;
        }
        ksort($sortedOffers);
        return $sortedOffers;
    }

    /**
     * Get an array of the Creative Works
     * 
     * @return array
     */
    function getCreativeWorks(){
        $bibs = $this->graph->allOfType('http://www.w3.org/2006/gen/ont#InformationResource');
        $creativeWorks = array();
        foreach ($bibs as $bib){
            $creativeWorks[] = $bib->getResource('schema:about');
        }
        return $creativeWorks;
    }
}