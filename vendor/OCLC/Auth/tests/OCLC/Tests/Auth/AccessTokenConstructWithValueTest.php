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

class AccessTokenConstructWithValueTest extends \PHPUnit_Framework_TestCase
{

    function testConstructAccessTokenWithValue()
    {
        $options = array(
            'accessTokenString' => 'tk_12345',
            'expiresAt' => '2018-08-23 18:45:29Z'
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
                
        $this->assertAttributeInternalType('string', 'accessTokenString', $this->accessToken);
        $this->assertAttributeEquals('tk_12345', 'accessTokenString', $this->accessToken);
        
        $this->assertAttributeInternalType('string', 'expiresAt', $this->accessToken);
        $this->assertAttributeEquals('2018-08-23 18:45:29Z', 'expiresAt', $this->accessToken);
        
        $this->assertFalse($this->accessToken->isExpired());
        $this->assertEquals('tk_12345', $this->accessToken->getValue());
    }
    
    /* Negative Test Cases */
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must pass an expires_at when passing an Access Token string
     */
    function testNoExpiresAt()
    {
        $options = array(
            'accessTokenString' => 'tk_12345',
            'expiresAt' => ''
        );
        $this->accessToken = new AccessToken('client_credentials', $options);
    }
}
