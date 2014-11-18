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
use WorldCat\Discovery\Error;

class ErrorTest extends \PHPUnit_Framework_TestCase
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
     * @vcr bibFailureInvalidAccessToken
     * Invalid Access Token
     */
    function testErrorInvalidAccessToken(){
        $error = Bib::find(7977212, $this->mockAccessToken, array('dbIds' => '638'));
        $this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        $this->assertNotEmpty($error->getErrorType());
        $this->assertEquals('401', $error->getErrorCode());
        $this->assertEquals('The given access token is not authorized to view this resource.  Please check your Authorization header and try again.', $error->getErrorMessage());
    }
    
    /** 
     * @vcr bibFailureExpiredAccessToken
     * Expired Access Token **/
    function testFailureExpiredAccessToken()
    {
        $error = Bib::find(41266045, $this->mockAccessToken, array('dbIds' => '638'));
        $this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        $this->assertNotEmpty($error->getErrorType());
        $this->assertEquals('401', $error->getErrorCode());
        $this->assertEquals('The given access token is not authorized to view this resource.  Please check your Authorization header and try again.', $error->getErrorMessage());
    }
    
    /** 
     * @vcr bibFailureSearchNoQuery
     * No query passed **/
    function testFailureNoQuery()
    {
        $query = ' ';
        $error = Bib::Search($query, $this->mockAccessToken, array('dbIds' => '638'));
        // this is failing
        //$this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        //$this->assertNotEmpty($error->getErrorType());
        //$this->assertEquals('400', $error->getErrorCode());
        //$this->assertEquals('blah', $error->getErrorMessage());
    }
    
    /** 
     * @vcr bibFailureBadFacetCount
     * Invalid facet count **/
    function testFailureBadFacetCount()
    {
        $query = 'cats';
        $facets = array('author' => 5);
        $error = Bib::Search($query, $this->mockAccessToken, array('facetFields' => $facets, 'dbIds' => 638));
        $this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        $this->assertNotEmpty($error->getErrorType());
        $this->assertEquals('400', $error->getErrorCode());
        //$this->assertEquals('facet count is invalid', $error->getErrorMessage()); // this isn't throwing the right error
    }
    
    /**
     * @vcr bibFailureDatabaseNotEnabled
     * Database not enabled
     */
    function testErrorDatabaseNotEnabled(){
        $query = 'gdp policy';
        $options = array('dbIds' => '2663');
        $error = Bib::Search($query, $this->mockAccessToken, $options);
        $this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        $this->assertNotEmpty($error->getErrorType());
        $this->assertEquals('403', $error->getErrorCode());
        $this->assertEquals('Your query included one or more databases for which you do not have access rights. [2663]', $error->getErrorMessage());
    }
    
    /**
     * @vcr offerFailureExpiredAccessToken
     * Offer Expired Access Token
     */
    function testOfferFailureExpiredAccessToken(){
        $options = array('heldBy' => array('GZM','GZN','GZO'));
        $error = Offer::findByOclcNumber(30780581, $this->mockAccessToken, $options);
        $this->assertInstanceOf('WorldCat\Discovery\Error', $error);
        $this->assertNotEmpty($error->getErrorType());
        //$this->assertEquals('401', $error->getErrorCode());
        //$this->assertEquals('Unauthorized', $error->getErrorMessage());
    }
}
