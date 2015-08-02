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
namespace OCLC\Tests;

use OCLC\User;

class UserTest extends \PHPUnit_Framework_TestCase
{

    private $user;
    
    function setUp()
    {
        $this->user = new User('128807', 'principalID', 'principalIDNS');
    }
    
    /**
     * can construct User
     */
    function testauthenticatingInstitutionIDSet()
    {
        $this->assertAttributeInternalType('string', 'authenticatingInstitutionID', $this->user);
        $this->assertAttributeEquals('128807', 'authenticatingInstitutionID', $this->user);
    }
    
    function testPrincipalIDSet()
    {
        $this->assertAttributeInternalType('string', 'principalID', $this->user);
        $this->assertAttributeEquals('principalID', 'principalID', $this->user);
    }
    
    function testPrincipalIDNSSet()
    {
        $this->assertAttributeInternalType('string', 'principalIDNS', $this->user);
        $this->assertAttributeEquals('principalIDNS', 'principalIDNS', $this->user);
    }
    
    /**
     * getAuthenticatingInstitutionID should return an Authenticating Institution ID String
     */
    function testgetAuthenticatingInstitutionID()
    {
        $this->assertEquals('128807', $this->user->getAuthenticatingInstitutionID());
    }
    
    /**
     * getPrincipalID should return a PrincipalID string
     */
    function testgetPrincipalID()
    {
        $this->assertEquals('principalID', $this->user->getPrincipalID());
    }
    
    /**
     * getPrincipalIDNS should return a PrincipalIDNS string
     */
    function testgetPrincipalIDNS()
    {
        $this->assertEquals('principalIDNS', $this->user->getPrincipalIDNS());
    }
    
    /** Negative Test Cases */
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must set a valid authenticating institution ID
     */
    function testNoAuthenticatingInstitutionID()
    {
        $user = new User('', 'principalID', 'principalIDNS');
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must set a principalID and principalIDNS
     */
    function testNoPrincipalID()
    {
        $user = new User('128807', '', 'principalIDNS');
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must set a principalID and principalIDNS
     */
    function testNoPrincipalIDNS()
    {
        $user = new User('128807', 'principalID', '');
    }
    
}
