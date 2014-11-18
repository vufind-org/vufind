<?php
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

$environment = 'prod';

$config = Yaml::parse(__DIR__ . '/config.yml');

$options =  array(
    'services' => array('WorldCatDiscoveryAPI')
);

$wskey = new WSKey($config[$environment]['wskey'], $config[$environment]['secret'], $options);

$accessToken = $wskey->getAccessTokenWithClientCredentials($config['institution'], $config['institution']);

$bib = Bib::find(7977212, $accessToken);

foreach ($bib->getManifestations() as $manifestation){
    echo get_class($manifestation);
}