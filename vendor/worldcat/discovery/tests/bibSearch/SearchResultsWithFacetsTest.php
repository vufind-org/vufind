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

use Guzzle\Http\StaticClient;
use OCLC\Auth\WSKey;
use OCLC\Auth\AccessToken;
use OCLC\User;
use WorldCat\Discovery\Bib;

class SearchResultsWithFacetsTest extends \PHPUnit_Framework_TestCase
{

    function setUp()
    {
        $options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => array('WorldCatDiscoveryAPI')
        );
        $this->mockAccessToken = $this->getMock('OCLC\Auth\AccessToken', array('getValue'), array('client_credentials', $options));
        $this->mockAccessToken->expects($this->any())
                    ->method('getValue')
                    ->will($this->returnValue('tk_12345'));
    }
    
    /**
     * @vcr bibSearchFacets
     * can parse set of Bibs from a Search Result with Facets*/
    
    function testSearchFacets(){
        $query = 'cats';
        $facets = array('creator:10', 'inLanguage:10');
        $search = Bib::Search($query, $this->mockAccessToken, array('facetFields' => $facets));
        $this->assertInstanceOf('WorldCat\Discovery\BibSearchResults', $search);
        $this->assertEquals('0', $search->getStartIndex());
        $this->assertEquals('10', $search->getItemsPerPage());
        $this->assertInternalType('integer', $search->getTotalResults());
        $this->assertEquals('10', count($search->getSearchResults()));
        $results = $search->getSearchResults();
        $i = $search->getStartIndex();
        foreach ($search->getSearchResults() as $searchResult){
            $this->assertFalse(get_class($searchResult) == 'EasyRdf_Resource');
            $i++;
            $this->assertEquals($i, $searchResult->getDisplayPosition());
        }
        $facetList = $search->getFacets();
        $this->assertNotEmpty($facetList);
        return $facetList;
    }
    
    /**
     * 
     * @depends testSearchFacets
     */
    function testFacetList($facetList){
        foreach ($facetList as $facet){
            $this->assertInstanceOf('WorldCat\Discovery\Facet', $facet); 
            $this->assertNotEmpty($facet->getFacetIndex());
            $this->assertNotEmpty($facet->getFacetItems());
        }
        return current($facetList);
    }
    
    /**
     * 
     * @depends testFacetList
     */
    function testFacetValue($facet){
        $previousCount = current($facet->getFacetItems())->getCount();
        foreach ($facet->getFacetItems() as $facetItem){
            $this->assertInstanceOf('WorldCat\Discovery\FacetItem', $facetItem);
            $this->assertNotEmpty($facetItem->getName());
            $this->assertNotEmpty($facetItem->getCount());
            $this->assertGreaterThanOrEqual($facetItem->getCount(), $previousCount);
            $previousCount = $facetItem->getCount();
        }
    }
}