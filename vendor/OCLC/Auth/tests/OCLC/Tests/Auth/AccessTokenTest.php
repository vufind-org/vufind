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
use OCLC\RefreshToken;

class AccessTokenTest extends \PHPUnit_Framework_TestCase
{

    private $accessToken;

    private $wskey;

    private static $services = array(
        'WMS_NCIP',
        'WMS_ACQ'
    );

    private static $redirect_uri = 'http://library.worldshare.edu/test';

    function setUp()
    {
        $wskeyOptions = array(
            'redirectUri' => static::$redirect_uri,
            'services' => static::$services
        );
        $wskeyArgs = array(
            'test',
            'secret',
            $wskeyOptions
        );
        $this->wskey = $this->getMock('OCLC\Auth\WSKey', null, $wskeyArgs);
        
        $options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => static::$services
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
    }

    /**
     * can construct Access Token
     */
    function testGrantTypeSet()
    {
        $this->assertAttributeInternalType('string', 'grantType', $this->accessToken);
        $this->assertAttributeEquals('client_credentials', 'grantType', $this->accessToken);
    }
    
    /**
     * @vcr accessTokenWithRefreshTokenSuccess
     * testProcessGoodAuthServerResponse
     */

    function testProcessGoodAuthServerResponse()
    {
        
        $this->accessToken->create($this->wskey);
        
        // Test all the properties are set from the JSON response
        $this->assertAttributeInternalType('array', 'response', $this->accessToken);
                
        $this->assertAttributeInternalType('string', 'accessTokenString', $this->accessToken);
        $this->assertAttributeEquals('tk_Yebz4BpEp9dAsghA7KpWx6dYD1OZKWBlHjqW', 'accessTokenString', $this->accessToken);
        
        $this->assertAttributeInternalType('string', 'expiresIn', $this->accessToken);
        $this->assertAttributeEquals('3599', 'expiresIn', $this->accessToken);
        
        $this->assertAttributeInternalType('string', 'expiresAt', $this->accessToken);
        $this->assertAttributeEquals('2018-08-23 18:45:29Z', 'expiresAt', $this->accessToken);
        
        $this->assertAttributeInternalType('string', 'contextInstitutionId', $this->accessToken);
        $this->assertAttributeEquals('128807', 'contextInstitutionId', $this->accessToken);
        
        $this->assertInstanceOf('OCLC\Auth\RefreshToken', $this->accessToken->getRefreshToken());
        
        $this->assertInstanceOf('OCLC\User', $this->accessToken->getUser());
        
        $this->assertFalse($this->accessToken->isExpired());
        $this->assertEquals('tk_Yebz4BpEp9dAsghA7KpWx6dYD1OZKWBlHjqW', $this->accessToken->getValue());
    }
    
    /**
     * @vcr accessTokenWithRefreshTokenExpired
     * Test Getting an Access Token which is expired
     */
    
    function testProcessAccessTokenExpired()
    {
        $this->accessToken->create($this->wskey);
    
        // Test all the properties are set from the JSON response
        $this->assertAttributeInternalType('array', 'response', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'accessTokenString', $this->accessToken);
        $this->assertAttributeEquals('tk_Yebz4BpEp9dAsghA7KpWx6dYD1OZKWBlHjqW', 'accessTokenString', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'expiresIn', $this->accessToken);
        $this->assertAttributeEquals('3599', 'expiresIn', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'expiresAt', $this->accessToken);
        $this->assertAttributeEquals('2013-08-23 18:45:29Z', 'expiresAt', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'contextInstitutionId', $this->accessToken);
        $this->assertAttributeEquals('128807', 'contextInstitutionId', $this->accessToken);
    
        $this->assertInstanceOf('OCLC\Auth\RefreshToken', $this->accessToken->getRefreshToken());
    
        $this->assertInstanceOf('OCLC\User', $this->accessToken->getUser());
    
        $this->assertTrue($this->accessToken->isExpired());
        $this->assertNotNull($this->accessToken->getValue(false));
    }
    
    
    
    
    /**
     * @vcr accessTokenSuccess
     * testProcessGoodAuthServerResponseNoRefreshToken
     */
    
    function testProcessGoodAuthServerResponseNoRefreshToken()
    {
    
        $this->accessToken->create($this->wskey);
    
        // Test all the properties are set from the JSON response
        $this->assertAttributeInternalType('array', 'response', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'accessTokenString', $this->accessToken);
        $this->assertAttributeEquals('tk_Yebz4BpEp9dAsghA7KpWx6dYD1OZKWBlHjqW', 'accessTokenString', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'expiresIn', $this->accessToken);
        $this->assertAttributeEquals('3599', 'expiresIn', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'expiresAt', $this->accessToken);
        $this->assertAttributeEquals('2018-08-23 18:45:29Z', 'expiresAt', $this->accessToken);
    
        $this->assertAttributeInternalType('string', 'contextInstitutionId', $this->accessToken);
        $this->assertAttributeEquals('128807', 'contextInstitutionId', $this->accessToken);
    
        $this->assertInstanceOf('OCLC\User', $this->accessToken->getUser());
    
        $this->assertFalse($this->accessToken->isExpired());
        $this->assertEquals('tk_Yebz4BpEp9dAsghA7KpWx6dYD1OZKWBlHjqW', $this->accessToken->getValue());
    }
    
    /* Negative Test Cases */
    
    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass a valid grant type to construct an Access Token
     */
    function testInvalidGrantType()
    {
        $options = array(
            'refreshToken' => 'rt_239308230'
        );
        $this->accessToken = new AccessToken(' ', $options);
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass at least one option to construct an Access Token
     */
    function testInvalidOptions()
    {
        $options = ' ';
        $this->accessToken = new AccessToken('refresh_token', $options);
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass at least one option to construct an Access Token
     */
    function testEmptyArrayOptions()
    {
        $options = array();
        $this->accessToken = new AccessToken('refresh_token', $options);
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass a valid OCLC\Auth\WSKey object to create an Access Token
     */
    function testInvalidWSKey()
    {
        $this->accessToken->create(' ');
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass a valid User object
     */
    function testInvalidUser()
    {
        $User = ' ';
        $this->accessToken->create($this->wskey, $User);
    }

    /**
     * @vcr accessTokenFailure401
     * testProcessBadAuthServerResponse401
     */
    function testProcessBadAuthServerResponse401()
    {
        $this->accessToken->create($this->wskey);
        
        $this->assertAttributeInternalType('string', 'errorCode', $this->accessToken);
        $this->assertAttributeEquals('401', 'errorCode', $this->accessToken);
    }
    
    /**
     * @vcr accessTokenFailure403
     * testProcessBadAuthServerResponse403
     */

    function testProcessBadAuthServerResponse403()
    {
        $this->accessToken->create($this->wskey);
        
        $this->assertAttributeInternalType('string', 'errorCode', $this->accessToken);
        $this->assertAttributeEquals('403', 'errorCode', $this->accessToken);
        
        $this->assertAttributeInternalType('string', 'errorMessage', $this->accessToken);
        $this->assertAttributeEquals('unauthorized_client', 'errorMessage', $this->accessToken);
    }
}
