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
* A class that represents a MADS Topical Authority
* http://id.loc.gov/authorities/subjects/sh85149296
*
*/
class TopicalAuthority extends Authority
{
    /**
     * Get Broader Authorities
     * 
     * @return array of WorldCat\Discovery\TopicalAuthority
     */
    function getBroaderAuthorities()
    {
        $broaderAuthorities = $this->all('madsrdf:hasBroaderAuthority');
        if (empty($broaderAuthorities)){
            $this->load();
            $broaderAuthorities = $this->all('madsrdf:hasBroaderAuthority');
        }
        return $broaderAuthorities;
    }
    
    /**
     * Get Narrower Authorities
     *
     * @return array of WorldCat\Discovery\TopicalAuthority
     */
    function getNarrowerAuthorities()
    {
        $narrowerAuthorities = $this->all('madsrdf:hasNarrowerAuthority');
        if (empty($narrowerAuthorities)){
            $this->load();
            $narrowerAuthorities = $this->all('madsrdf:hasNarrowerAuthority');
        }
        return $narrowerAuthorities;
    }
    
    /**
     * Get Reciprocal Authorities
     *
     * @return array of WorldCat\Discovery\TopicalAuthority
     */
    function getReciprocalAuthorities()
    {
        $reciprocalAuthorities = $this->all('madsrdf:hasReciprocalAuthority');
        if (empty($reciprocalAuthorities)){
            $this->load();
            $reciprocalAuthorities = $this->all('madsrdf:hasReciprocalAuthority');
        }
        return $reciprocalAuthorities;
    }
    
    /**
     * Get Close External Authorities
     *
     * @return array of WorldCat\Discovery\TopicalAuthority
     */
    function getCloseExternalAuthorities()
    {
        $closeExternalAuthorities = $this->all('madsrdf:hasCloseExternalAuthority');
        if (empty($closeExternalAuthorities)){
            $this->load();
            $closeExternalAuthorities = $this->get('madsrdf:hasCloseExternalAuthority');
        }
        return $closeExternalAuthorities;
    }
    
}