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
use WorldCat\Discovery\Database;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{

    function setUp()
    {   global $config;
        
        $this->config = $config;
        
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
     * @vcr databaseSuccess
     * Get a single database resource
     */
    function testGetDatabase(){
        $database = Database::find(638, $this->mockAccessToken);
        $this->assertInstanceOf('WorldCat\Discovery\Database', $database);
        $this->assertNotEmpty($database->getId());
        $this->assertNotEmpty($database->getName());
        $this->assertNotEmpty($database->getRequiresAuthentication());
        $this->assertNotEmpty($database->getDescription());
    }
    
    /**
     * @vcr databaseListSuccess
     * List database resources
     */
    function testlistDatabases(){
        $databaseList = Database::getList($this->mockAccessToken);
        $this->assertNotEmpty($databaseList);
        foreach ($databaseList as $database){
            $this->assertInstanceOf('WorldCat\Discovery\Database', $database);
        }
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must pass a valid ID
     */
    function testIDNotInteger()
    {
        $database = Database::find('string', $this->mockAccessToken);
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must pass a valid OCLC/Auth/AccessToken object
     */
    function tesccessTokenNotAccessTokenObject()
    {
        $database = Database::find(638, 'NotAnAccessToken');
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage You must pass a valid OCLC/Auth/AccessToken object
     */
    function testListDatabasesAccessTokenNotAccessTokenObject()
    {
        $databaseList = Database::getList('NotAnAccessToken');
    }
}
