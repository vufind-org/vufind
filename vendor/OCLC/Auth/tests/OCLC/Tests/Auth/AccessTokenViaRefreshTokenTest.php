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
use OCLC\Auth\RefreshToken;
use Guzzle\Http\Client;

class AccessTokenViaRefreshTokenTest extends \PHPUnit_Framework_TestCase
{

    private $accessToken;

    private $options;

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
        
        $this->refreshToken = new refreshToken('rt_239308230', '15000', '2018-08-23 18:45:29Z');
        $this->options = array(
            'refreshToken' => $this->refreshToken
        );
        $this->accessToken = new AccessToken('refresh_token', $this->options);
    }

    /**
     * can construct Access Token
     */
    function testGrantTypeSet()
    {
        $this->assertAttributeInternalType('string', 'grantType', $this->accessToken);
        $this->assertAttributeEquals('refresh_token', 'grantType', $this->accessToken);
    }
    
    function testRefreshTokenSet()
    {
        $this->assertAttributeInstanceOf('OCLC\Auth\RefreshToken', 'refreshToken', $this->accessToken);
        $this->assertAttributeEquals('rt_239308230', 'refreshToken', $this->accessToken->getRefreshToken());
    }
    
    function testAccess_token_urlSetRefreshToken()
    {
        $desiredURL = 'https://authn.sd00.worldcat.org/oauth2/accessToken?grant_type=refresh_token&refresh_token=rt_239308230';
        $this->assertAttributeInternalType('string', 'accessTokenUrl', $this->accessToken);
        $this->assertAttributeEquals($desiredURL, 'accessTokenUrl', $this->accessToken);
    }

    /**
     * can create Access Token
     */
    function testCreateWithRefreshToken()
    {
        $accessTokenArgs = array(
            'refresh_token',
            $this->options
        );
        $accessTokenMock = $this->getMock('OCLC\Auth\AccessToken', array(
            'create'
        ), $accessTokenArgs);
        $accessTokenMock->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf('OCLC\Auth\WSKey'))
            ->will($this->returnSelf());
        $this->assertSame($accessTokenMock, $accessTokenMock->create($this->wskey));
    }
    
    /**
     * @vcr accessTokenWithRefreshTokenSuccess
     * testProcessGoodAuthServerResponse
     */
    
    function testRetrieveRefreshToken()
    {        
        $options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => static::$services
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
    
        $this->accessToken->create($this->wskey);
    
        // Test all the properties are set from the JSON response
            
        $this->assertInstanceOf('OCLC\Auth\RefreshToken', $this->accessToken->getRefreshToken());
           
        $this->assertEquals('rt_ZrigZXPJQnB1l2DxF1dCratGNxUHpGLjMw8z', $this->accessToken->getRefreshToken()->getValue());
        $this->assertEquals('604799', $this->accessToken->getRefreshToken()->getExpiresIn());
        $this->assertEquals('2018-08-30 18:25:29Z', $this->accessToken->getRefreshToken()->getExpiresAt());
        $this->assertFalse($this->accessToken->getRefreshToken()->isExpired());
    }
    
    /**
     * @vcr accessTokenWithRefreshTokenExpired
     * testProcessGoodAuthServerResponse
     */
    
    function testRetrieveRefreshTokenExpired()
    {
        $options = array(
            'authenticatingInstitutionId' => 128807,
            'contextInstitutionId' => 128807,
            'scope' => static::$services
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
    
        $this->accessToken->create($this->wskey);
    
        // Test all the properties are set from the JSON response
    
        $this->assertInstanceOf('OCLC\Auth\RefreshToken', $this->accessToken->getRefreshToken());
         
        $this->assertEquals('rt_ZrigZXPJQnB1l2DxF1dCratGNxUHpGLjMw8z', $this->accessToken->getRefreshToken()->getValue());
        $this->assertEquals('604799', $this->accessToken->getRefreshToken()->getExpiresIn());
        $this->assertEquals('2013-08-30 18:25:29Z', $this->accessToken->getRefreshToken()->getExpiresAt());
        $this->assertTrue($this->accessToken->getRefreshToken()->isExpired());
    }
    
    /* Negative Test Cases */
    
    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass the option refreshToken to construct an Access Token using the refresh_token grant type
     */
    function testInvalidOptionsRefreshTokenGrantType()
    {
        $options = array(
            'code' => 'auth_239308230'
        );
        $this->accessToken = new AccessToken('refresh_token', $options);
    }
    
    /**
     * @expectedException LogicException
     * @expectedExceptionMessage You must pass the option refreshToken to construct an Access Token using the refresh_token grant type
     */
    function testInvalidRefreshToken()
    {
        $options = array(
            'refreshToken' => 'rt_123456'
        );
        $this->accessToken = new AccessToken('refresh_token', $options);
    }
}
