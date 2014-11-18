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

class ProductModelTest extends \PHPUnit_Framework_TestCase
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
     *@vcr bibSuccess
     */
    function testGetBib(){
        $bib = Bib::find(7977212, $this->mockAccessToken);
        $this->assertInstanceOf('WorldCat\Discovery\Book', $bib);
        $this->assertNotEmpty($bib->getManifestations());
        $manifestations = $bib->getManifestations();
        return $manifestations;
    }

    /**
     * can parse Manifestation aka ProductModel data
     * @depends testGetBib
     */
    function testParseManifestations($manifestations){
        foreach ($manifestations as $manifestation){
            $this->assertInstanceOf('WorldCat\Discovery\ProductModel', $manifestation);
            $this->assertNotEmpty($manifestation->getISBNs());
            $this->assertNotEmpty($manifestation->getISBN());
            if ($manifestation->getURI() == 'http://worldcat.org/isbn/9780156685689'){
                $this->assertNotEmpty($manifestation->getBookFormat());
            }
        }
        
    }
    
    /* Need a test for $manifestation->getDescription() */
}
