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

class MusicAlbumTest extends \PHPUnit_Framework_TestCase
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
     * @vcr cdSuccess
     */
    function testGetBibCD(){
        $bib = Bib::find(38027615, $this->mockAccessToken);
        $this->assertInstanceOf('WorldCat\Discovery\MusicAlbum', $bib);
        $this->assertNotEmpty($bib->getFormat());
    }

    /**
     * @vcr lpSuccess
     */
    function testGetBibLP(){
        $bib = Bib::find(5791214, $this->mockAccessToken);
        $this->assertInstanceOf('WorldCat\Discovery\MusicAlbum', $bib);
        $this->assertNotEmpty($bib->getFormat());
    }
}

