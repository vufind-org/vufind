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

/**
 * 
 * @author Karen Coombs
 *
 * This takes three options: 
 * type - the type of mocks to create. Valid values are all, bibFind, bibSearch, database, offers, viaf, authority
 * environment - what environment to generates mocks from. The default is from the production environment
 * filter - the specific mock to filter the create to.
 * 
 * Example usage
 * php getMock.php --type all
 * php getMock.php --type bibSearch --filter="bibSearchSortDate"
 */
namespace WorldCat\Discovery;

use Guzzle\Http\StaticClient;
use Symfony\Component\Yaml\Yaml;
use OCLC\Auth\WSKey;
use OCLC\Auth\AccessToken;
use WorldCat\Discovery\Bib;
use WorldCat\Discovery\Offer;
use WorldCat\Discovery\Person;
use WorldCat\Discovery\Organization;
use WorldCat\Discovery\Place;

global $environment, $mockFolder, $retrievedToken, $mockValue;

require __DIR__ . '/../vendor/autoload.php';

$shortopts  = "";
$shortopts .= "t:";
$shortopts .= "e::";
$shortopts .= "f::";

$longopts  = array(
    "type:",
    "environment::",
    "filter::"
);

$scriptOptions = getopt($shortopts, $longopts);

if (!class_exists('Guzzle')) {
    \Guzzle\Http\StaticClient::mount();
}

\VCR\VCR::turnOn();

$type = $scriptOptions['type'];

if ($type == 'all' && isset($scriptOptions['filter'])){
    Throw new \Exception('You must specific a type other than all in order to filter. Valid types are bibFind, bibSearch, database, offers, viaf, authority.');
}
$filter = isset($scriptOptions['filter']) ? $scriptOptions['filter'] : null ;

if (isset($scriptOptions['environment'])){
    $cassettePath = 'mocks/' . $scriptOptions['environment'];
} else {
    $cassettePath = 'mocks';
}
\VCR\VCR::configure()->setCassettePath($cassettePath);

\VCR\VCR::insertCassette('accessToken'); 

$mockFolder = __DIR__ . "/mocks/";

// load the YAML for mocks
$mockBuilder = Yaml::parse(__DIR__ . '/mockBuilder.yml');

    // load the YAML for config
    $config = Yaml::parse(__DIR__ . '/config.yml');
    if (empty($config['institution'])) {
    	Throw new \Exception('No valid config file present');
    }
    if (isset($scriptOptions['environment'])){
        $mockFolder .= $scriptOptions['environment'] . '/';
        $environment = $scriptOptions['environment'];
        AccessToken::$authorizationServer = $config[$environment]['authorizationServiceUrl'];
        WSKey::$testServer = TRUE;
        Bib::$serviceUrl = $config[$environment]['discoveryUrl'];
        Bib::$testServer = TRUE;
        Database::$serviceUrl = $config[$environment]['discoveryUrl'];
        Database::$testServer = TRUE;
        Offer::$serviceUrl = $config[$environment]['discoveryUrl'];
        Offer::$testServer = TRUE;
        Person::$viafServiceUrl = $config[$environment]['viafUrl'];
        Organization::$viafServiceUrl = $config[$environment]['viafUrl'];
        Place::$viafServiceUrl = $config[$environment]['viafUrl'];
    } else {
        $environment = 'prod';
    }


// Go get an accessToken
$options =  array(
    'services' => array('WorldCatDiscoveryAPI')
);
$wskey = new WSKey($config[$environment]['wskey'], $config[$environment]['secret'], $options);

$retrievedToken = $wskey->getAccessTokenWithClientCredentials($config['institution'], $config['institution']);
\VCR\VCR::eject();

if ($type == 'all'  || $type == 'bibFind'){    
    
    if (isset($filter)){
        createMock($filter, $mockBuilder['bibFind'][$filter]);
    } else{
        //BibFind mocks
        foreach ($mockBuilder['bibFind'] as $mock => $mockValues) {
            createMock($mock, $mockValues);
        }
    }
}

if ($type == 'all'  || $type == 'bibSearch'){
    if (isset($filter)){
        createMock($filter, $mockBuilder['bibSearch'][$filter]);
    } else{
        //bibSearch mocks
        foreach ($mockBuilder['bibSearch'] as $mock => $mockValues) {
            createMock($mock, $mockValues);
        }
    }
}

if ($type == 'all'  || $type == 'database'){
    if (isset($filter)){
        if ($filter == 'databaseListSuccess') {
            createMock($filter);
        } else {
            createMock($filter, $mockBuilder['databaseFind'][$filter]);
        }
    } else{
        //database mocks
        foreach ($mockBuilder['databaseFind'] as $mock => $mockValues) {
            createMock($mock, $mockValues);
        }
        $mock = 'databaseListSuccess';
        createMock($mock);
    }
}

if ($type == 'all'  || $type == 'offers'){
    
    if (isset($filter)){
        createMock($filter, $mockBuilder['offers'][$filter]);
    } else {
        //offer mocks
        foreach ($mockBuilder['offers'] as $mock => $mockValues) {
            createMock($mock, $mockValues);
        }
    }
}

if ($type == 'all'  || $type == 'viaf'){
    if (isset($filter)){
        createMock($filter, $mockBuilder['viaf'][$filter]);
    } else {
        foreach ($mockBuilder['viaf'] as $mock => $mockValues) {
            createMock($mock, $mockValues);
        }
    }
}
    
if ($type == 'all'  || $type == 'authority'){
    if (file_exists($mockFolder . 'authoritySuccess')){
        unlink($mockFolder . 'authoritySuccess');
    }
    foreach ($mockBuilder['authority'] as $mock => $mockValue) {
        createMock($mock, $mockValue, true);
    }
}

// delete the accessToken file
unlink($mockFolder . 'accessToken'); 

function createMock($mock, $mockValues = null, $authority = FALSE){
    global $environment, $mockFolder, $retrievedToken, $mockValue;
    
    if (file_exists($mockFolder . $mock)){
        unlink($mockFolder . $mock);
    }
    if ($authority == true){
        $mockFile = 'authoritySuccess';
    } else {
        $mockFile = $mock;
    }
    \VCR\VCR::insertCassette($mockFile);
    printf("Mock created for '%s'.\n", $mock);
    if (isset($mockValues['accessToken'])){
        $accessToken = new AccessToken('client_credentials', array('accessTokenString' => $mockValues['accessToken'], 'expiresAt' => '2018-08-30 18:25:29Z'));
    } else {
        $accessToken = $retrievedToken;
    }
    
    // call the appropriate function
    if (isset($mockValues['oclcNumber'])){
        $results = Bib::find($mockValues['oclcNumber'], $accessToken);
    } elseif (isset($mockValues['query'])) {
        $options = array();
        if (isset($mockValues['facetFields'])){
            $options['facetFields'] = $mockValues['facetFields'];
        }
        
        if (isset($mockValues['facetQueries'])){
            $options['facetQueries'] = $mockValues['facetQueries'];
        }
        
        if (isset($mockValues['startIndex'])) {
            $options['startIndex'] = $mockValues['startIndex'];
        }
        
        if (isset($mockValues['itemsPerPage'])) {
            $options['itemsPerPage'] = $mockValues['itemsPerPage'];
        }
        
        if (isset($mockValues['dbIds'])) {
            $options['dbIds'] = $mockValues['dbIds'];
        }
        
        if (isset($mockValues['sortBy'])){
            $options['sortBy'] = $mockValues['sortBy'];
        }
        $results = Bib::search($mockValues['query'], $accessToken, $options);
    }elseif ($mock == 'databaseSuccess'){
        Database::find($mockValues['id'], $accessToken);
    }elseif ($mock == 'databaseListSuccess'){
        Database::getList($accessToken);
    }elseif (isset($mockValues['id'])){
        $options = $mockValues['options'];
        $results = Offer::findByOclcNumber($mockValues['id'], $accessToken, $options);    
    } elseif (strpos($mock, 'VIAF')) {
        $response = Thing::findByURI($mockValues[$environment]);
        file_put_contents($mockFolder . $mock, str_replace('/rdf.xml', '', file_get_contents($mockFolder . $mock)));
        if ($environment != 'prod'){
            file_put_contents($mockFolder . $mock, str_replace('http://viaf.org', 'http://test.viaf.org', file_get_contents($mockFolder . $mock)));
        }
        file_put_contents($mockFolder . $mock, str_replace('rdap02pxdu.dev.oclc.org:8080', 'test.viaf.org', file_get_contents($mockFolder . $mock)));
    } else {
        $options = [
            'Accept' => 'text/turtle'
        ];
        $authority = Authority::findByURI($mockValue, $options);
        file_put_contents($mockFolder . $mockFile, str_replace($mockValue, rtrim($mockValue, '.nt'), file_get_contents($mockFolder . $mockFile)));
    }
    \VCR\VCR::eject();
    file_put_contents($mockFolder . $mockFile, str_replace("Bearer " . $accessToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mockFile)));
}


?>