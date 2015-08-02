<?php

// Copyright 2013 OCLC
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
namespace OCLC\Tests\Auth;

use OCLC\Auth\WSKey;
use OCLC\Auth\AccessToken;
use OCLC\User;
use Guzzle\Log\Zf2LogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Log\MessageFormatter;
use Zend\Log\Writer\Mock;
use Zend\Log\Logger;

class AccessTokenLoggerTest extends \PHPUnit_Framework_TestCase
{

    private $accessToken;

    private $options;

    private $wskey;

    private static $services = array(
        'WMS_NCIP',
        'WMS_ACQ',
        'refresh_token'
    );

    function setUp()
    {
        $this->logMock = new Mock();
        $logger = new Logger();
        $logger->addWriter($this->logMock);
        $adapter = new Zf2LogAdapter($logger);
        $logPlugin = new LogPlugin($adapter, MessageFormatter::DEBUG_FORMAT);
        
        $wskeyOptions = array(
            'services' => static::$services
        );
        $wskeyArgs = array(
            'test',
            'secret',
            $wskeyOptions
        );
        $this->wskey = $this->getMock('OCLC\Auth\WSKey', null, $wskeyArgs);
        
        $this->options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => static::$services,
            'logger' => $logPlugin
        );
        $this->accessToken = new AccessToken('client_credentials', $this->options);
        
    }
    
    /**
     * can construct Access Token
     */
    function testGrantTypeSet()
    {
        $this->assertAttributeInternalType('string', 'grantType', $this->accessToken);
        $this->assertAttributeEquals('client_credentials', 'grantType', $this->accessToken);
    }
    
    function testAuthenticatingInstitutionSet()
    {
        $this->assertAttributeInternalType('integer', 'authenticatingInstitutionId', $this->accessToken);
        $this->assertAttributeEquals('128807', 'authenticatingInstitutionId', $this->accessToken);
    }
    
    function testContextInstitutionSet()
    {
        $this->assertAttributeInternalType('integer', 'contextInstitutionId', $this->accessToken);
        $this->assertAttributeEquals('128807', 'contextInstitutionId', $this->accessToken);
    }
    
    function testScopeSet()
    {
        $this->assertAttributeInternalType('array', 'scope', $this->accessToken);
        $this->assertAttributeEquals(static::$services, 'scope', $this->accessToken);
    }
    
    function testAccess_token_urlSet()
    {
        $desiredURL = 'https://authn.sd00.worldcat.org/oauth2/accessToken?grant_type=client_credentials&authenticatingInstitutionId=128807&contextInstitutionId=128807&scope=' . implode(static::$services, ' ');
    
        $this->assertAttributeInternalType('string', 'accessTokenUrl', $this->accessToken);
        $this->assertAttributeEquals($desiredURL, 'accessTokenUrl', $this->accessToken);
    }
    
    function testAccess_token_LoggerSet()
    {
        $this->assertAttributeInstanceOf('Guzzle\Plugin\Log\LogPlugin', 'logger', $this->accessToken);
    }

    /**
     * @vcr accessTokenWithRefreshTokenSuccess
     * can log getting an Access Token
     */

    function testLogger()
    {
        $this->accessToken->create($this->wskey);
        $this->assertNotEmpty($this->logMock);
    }
    
    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The logger must be a valid Guzzle\Plugin\Log\LogPlugin object
     */
    function testInvalidGrantType()
    {
        $options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => static::$services,
            'logger' => 'lala'
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
    }
}
