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

use OCLC\Auth\RefreshToken;

class RefreshTokenTest extends \PHPUnit_Framework_TestCase
{

    private $refreshToken;

    function setUp()
    {
        $this->refreshToken = new refreshToken('rt_1234567', '15000', '2013-08-23 18:45:29Z');
    }

    /**
     * can construct Refresh Token
     */
    function testrefreshTokenSet()
    {
        $this->assertAttributeInternalType('string', 'refreshToken', $this->refreshToken);
        $this->assertAttributeEquals('rt_1234567', 'refreshToken', $this->refreshToken);
    }

    function testExpiresInSet()
    {
        $this->assertAttributeInternalType('integer', 'expiresIn', $this->refreshToken);
        $this->assertAttributeEquals('15000', 'expiresIn', $this->refreshToken);
    }

    function testExpiresAtSet()
    {
        $this->assertAttributeInternalType('string', 'expiresAt', $this->refreshToken);
        $this->assertAttributeEquals('2013-08-23 18:45:29Z', 'expiresAt', $this->refreshToken);
    }

    /**
     * getValue should return a Refresh Token String
     */
    function testgetValue()
    {
        $this->assertEquals('rt_1234567', $this->refreshToken->getValue());
    }

    /**
     * getValue should return an integer of how many millseconds until token expires
     */
    function testgetExpiresIn()
    {
        $this->assertEquals('15000', $this->refreshToken->getExpiresIn());
    }

    /**
     * getExpiresAt should return an Atom timestamp for when the Token expires
     */
    function testgetExpiresAt()
    {
        $this->assertEquals('2013-08-23 18:45:29Z', $this->refreshToken->getExpiresAt());
    }

    function testIsExpired()
    {
        $this->assertTrue($this->refreshToken->isExpired());
    }
    
    function testConstructRefreshTokenZeroExpiresIn(){
        $refreshToken = new refreshToken('rt_1234567', '0', '2013-08-23 18:45:29Z');
        $this->assertInstanceOf('OCLC\Auth\RefreshToken', $refreshToken);
    }
}
