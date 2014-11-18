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
use Guzzle\Log\Zf2LogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Log\MessageFormatter;
use Zend\Log\Writer\Mock;
use Zend\Log\Logger;

class BibLogTest extends \PHPUnit_Framework_TestCase
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
    function testLoggerSuccess(){
        $logMock = new Mock();
        $logger = new Logger();
        $logger->addWriter($logMock);
        $adapter = new Zf2LogAdapter($logger);
        $logPlugin = new LogPlugin($adapter, MessageFormatter::DEBUG_FORMAT);
        $options = array(
            'logger' => $logPlugin
        );
        $bib = Bib::find(7977212, $this->mockAccessToken, $options);
        $this->assertNotEmpty($logMock);
        
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage The logger must be a valid Guzzle\Plugin\Log\LogPlugin object
     */
    function testLoggerNotValid()
    {
        $options = array(
            'logger' => 'lala'
        );
        $bib = Bib::find('string', $this->mockAccessToken, $options);
    }
}
