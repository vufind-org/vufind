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
use WorldCat\Discovery\Bib;

class ArticleTest extends \PHPUnit_Framework_TestCase
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
     * @vcr articleSuccess
     */
    function testGetBib(){
        $bib = Bib::find(5131938809, $this->mockAccessToken);
        $this->assertInstanceOf('WorldCat\Discovery\Article', $bib);
        return $bib;
    }

    /**
     * can parse Single Bibs Literal values
     * @depends testGetBib
     */
    function testParseLiterals($bib)
    {
        $this->assertNotEmpty($bib->getId());
        $this->assertNotEmpty($bib->getName());
        $this->assertNotEmpty($bib->getOCLCNumber());
        $this->assertNotEmpty($bib->getDescriptions());
        $this->assertNotEmpty($bib->getLanguage());
        $this->assertNotEmpty($bib->getDatePublished());
        $this->assertNotEmpty($bib->getPageStart());
        $this->assertNotEmpty($bib->getPageEnd());
    }

    /**
     * can parse Single Bibs Resources
     * @depends testGetBib
     */
    function testParseResources($bib){
        $this->assertThat($bib->getAuthor(), $this->logicalOr(
            $this->isInstanceOf('WorldCat\Discovery\Person'),
            $this->isInstanceOf('WorldCat\Discovery\Organization')
        ));

        foreach ($bib->getContributors() as $contributor){
            $this->assertThat($contributor, $this->logicalOr(
                $this->isInstanceOf('WorldCat\Discovery\Person'),
                $this->isInstanceOf('WorldCat\Discovery\Organization')
            ));
        }

        $this->assertInstanceOf('EasyRdf_Resource', $bib->getWork());

        foreach ($bib->getAbout() as $about){
            $this->assertInstanceOf('WorldCat\Discovery\Intangible', $about);
        }
        
        $this->assertInstanceOf('WorldCat\Discovery\PublicationIssue', $bib->getIsPartOf());
        
        return $bib->getIsPartOf();
        
    }
    
    /**
     * can parse Publication Issue
     * @depends testParseResources
     */
    
    function testParsePublicationIssue($publicationIssue)
    {
        $this->assertNotEmpty($publicationIssue->getIssueNumber());
        $this->assertNotEmpty($publicationIssue->getDatePublished());
        $this->assertInstanceOf('WorldCat\Discovery\PublicationVolume', $publicationIssue->getVolume());
        $this->assertNotEmpty($publicationIssue->getVolume()->getVolumeNumber());
        $this->assertInstanceOf('WorldCat\Discovery\Periodical', $publicationIssue->getVolume()->getPeriodical());
        $this->assertNotEmpty($publicationIssue->getVolume()->getPeriodical()->getName());
        $this->assertInstanceOf('WorldCat\Discovery\Organization',$publicationIssue->getVolume()->getPeriodical()->getPublisher());
        $this->assertNotEmpty($publicationIssue->getVolume()->getPeriodical()->getPublisher()->getName());
    }
}
