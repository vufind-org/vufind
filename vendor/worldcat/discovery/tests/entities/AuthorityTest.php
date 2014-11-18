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

use WorldCat\Discovery\Authority;
use WorldCat\Discovery\TopicalAuthority;
use WorldCat\Discovery\GeographicAuthority;

class AuthorityTest extends \PHPUnit_Framework_TestCase
{
    /**
     *@vcr authoritySuccess
     */
    function testGetAuthority(){
        $url = 'http://id.loc.gov/authorities/subjects/sh2008124372';
        $authority = Authority::findByURI($url);
        $this->assertInstanceOf('WorldCat\Discovery\Authority', $authority);
        return $authority;
    }

    /**
     * can parse and return authority information
     * @depends testGetAuthority
     */
    function testAuthority($authority){
        $this->assertNotEmpty($authority->getTopics());
        $this->assertNotEmpty($authority->getGeographics());
        $this->assertNotEmpty($authority->getGenres());
        
        foreach ($authority->getGenres() as $genre){
            $this->assertNotEmpty($genre->label());
        }
    }
    
    /**
     * can parse and return topical authority information
     * @depends testGetAuthority
     */
    
    function testTopicAuthority($authority)
    {
        foreach ($authority->getTopics() as $topic){
            $this->assertInstanceOf('WorldCat\Discovery\TopicalAuthority', $topic);
            $this->assertNotEmpty($topic->label());
            $this->assertNotEmpty($topic->getBroaderAuthorities());
            $this->assertNotEmpty($topic->getNarrowerAuthorities());
            $this->assertNotEmpty($topic->getReciprocalAuthorities());
            $this->assertNotEmpty($topic->getCloseExternalAuthorities());
        }
    }
    
    /**
     * can parse and return geographic authority information
     * @depends testGetAuthority
     */
    function testGeographicAuthority($authority)
    {
        foreach ($authority->getGeographics() as $geographic){
            $this->assertNotEmpty($geographic->label());
            $this->assertInstanceOf('WorldCat\Discovery\GeographicAuthority', $geographic);
            $this->assertNotEmpty($geographic->getVariants());
        }
    }
}