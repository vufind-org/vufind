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
 * This takes two parameters. 
 * The first parameter is required and represents what the mocks to generate. The valid values are: all, bibFind, bibSearch, database, offers, viaf, authority
 * The second parameter is optional and represents what environment to generates mocks from the default is from the production environment
 * 
 * Example usage
 * php getMock.php all
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

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists('Guzzle')) {
    \Guzzle\Http\StaticClient::mount();
}

\VCR\VCR::turnOn();
if (isset($argv[2])){
    $cassettePath = 'mocks/' . $argv[2];
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
    if (isset($argv[2])){
        $mockFolder .= $argv[2] . '/';
        $environment = $argv[2];
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
if ($argv[1] == 'all'  || $argv[1] == 'bibFind'){    
    //BibFind mocks
    foreach ($mockBuilder['bibFind'] as $mock => $mockValues) {
            // delete files
            if (file_exists($mockFolder . $mock)){
                unlink($mockFolder . $mock);
            }
            \VCR\VCR::insertCassette($mock);
            printf("Mock created for '%s'.\n", $mock);
            if (isset($mockValues['accessToken'])){
                $accessToken = new AccessToken('client_credentials', array('accessTokenString' => $mockValues['accessToken'], 'expiresAt' => '2018-08-30 18:25:29Z'));
            } else {
                $accessToken = $retrievedToken;
            }
            $bib = Bib::find($mockValues['oclcNumber'], $accessToken);
            \VCR\VCR::eject();
            file_put_contents($mockFolder . $mock, str_replace("Bearer " . $accessToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mock)));
    }
}

if ($argv[1] == 'all'  || $argv[1] == 'bibSearch'){
    //BibSearch mocks
    foreach ($mockBuilder['bibSearch'] as $mock => $mockValues) {
        // delete files
        if (file_exists($mockFolder . $mock)){
            unlink($mockFolder . $mock);
        }
        \VCR\VCR::insertCassette($mock);
        printf("Mock created for '%s'.\n", $mock);
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
        
        $bib = Bib::search($mockValues['query'], $retrievedToken, $options);
        \VCR\VCR::eject();
        file_put_contents($mockFolder . $mock, str_replace("Bearer " . $retrievedToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mock)));
    }
}

if ($argv[1] == 'all'  || $argv[1] == 'database'){
    //database mocks
    foreach ($mockBuilder['databaseFind'] as $mock => $mockValues) {
        // delete files
        if (file_exists($mockFolder . $mock)){
            unlink($mockFolder . $mock);
        }
        \VCR\VCR::insertCassette($mock);
        printf("Mock created for '%s'.\n", $mock);
        $database = Database::find($mockValues['id'], $retrievedToken);
        \VCR\VCR::eject();
        file_put_contents($mockFolder . $mock, str_replace("Bearer " . $retrievedToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mock)));
    }
    
    //database list mock
    $mock = 'databaseListSuccess';
    // delete files
    if (file_exists($mockFolder . $mock)){
        unlink($mockFolder . $mock);
    }
    \VCR\VCR::insertCassette($mock);
    printf("Mock created for '%s'.\n", $mock);
    \VCR\VCR::insertCassette($mockBuilder['databaseSearch']);
    $database = Database::getList($retrievedToken);
    \VCR\VCR::eject();
    file_put_contents($mockFolder . $mock, str_replace("Bearer " . $retrievedToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mock)));
}

if ($argv[1] == 'all'  || $argv[1] == 'offers'){
    //offer mocks
    foreach ($mockBuilder['offers'] as $mock => $mockValues) {
        // delete files
        if (file_exists($mockFolder . $mock)){
            unlink($mockFolder . $mock);
        }
        \VCR\VCR::insertCassette($mock);
        printf("Mock created for '%s'.\n", $mock);
        
        if (isset($mockValues['options'])){
            $options = $mockValues['options'];
        } else {
            $options = array();
        }
        
        if (isset($mockValues['accessToken'])){
            $accessToken = new AccessToken('client_credentials', array('accessTokenString' => $mockValues['accessToken'], 'expiresAt' => '2018-08-30 18:25:29Z'));
        } else {
            $accessToken = $retrievedToken;
        }
        
        $bib = Offer::findByOclcNumber($mockValues['id'], $accessToken, $options);
        \VCR\VCR::eject();
        file_put_contents($mockFolder . $mock, str_replace("Bearer " . $retrievedToken->getValue(), "Bearer tk_12345", file_get_contents($mockFolder . $mock)));
    }
}

if ($argv[1] == 'all'  || $argv[1] == 'viaf'){
    foreach ($mockBuilder['viaf'] as $mock => $mockValues) {
        // delete files
        if (file_exists($mockFolder . $mock)){
            unlink($mockFolder . $mock);
        }
        \VCR\VCR::insertCassette($mock);
        printf("Mock created for '%s'.\n", $mock);
        
        $response = Thing::findByURI($mockValues[$environment]);
         
        \VCR\VCR::eject();
        file_put_contents($mockFolder . $mock, str_replace('/rdf.xml', '', file_get_contents($mockFolder . $mock)));
        if ($environment != 'prod'){
            file_put_contents($mockFolder . $mock, str_replace('http://viaf.org', 'http://test.viaf.org', file_get_contents($mockFolder . $mock)));
        }
        file_put_contents($mockFolder . $mock, str_replace('rdap02pxdu.dev.oclc.org:8080', 'test.viaf.org', file_get_contents($mockFolder . $mock)));

    }
}
    
if ($argv[1] == 'all'  || $argv[1] == 'authority'){    
    //authority mocks
    $mock = 'authoritySuccess';
    // delete files
    if (file_exists($mockFolder . $mock)){
        unlink($mockFolder . $mock);
    }
    \VCR\VCR::insertCassette($mock);
    foreach ($mockBuilder['authority']['authoritySuccess'] as $mock => $mockValue) {
        printf("Mock created for '%s'.\n", $mock);
        $authority = Authority::findByURI($mockValue);
        file_put_contents($mockFolder . 'authoritySuccess', str_replace($mockValue, rtrim($mockValue, '.rdf'), file_get_contents($mockFolder . 'authoritySuccess')));
    }
    \VCR\VCR::eject();

}

// delete the accessToken file
unlink($mockFolder . 'accessToken'); 
?>