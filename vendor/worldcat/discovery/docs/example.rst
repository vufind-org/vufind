Introduction
============

All of these examples assume the library has been installed via Composer and have the require statement based on that. 

Example: Find a Bibliographic Resource in WorldCat
==================================================

This example reads a single bibliographic record from the WorldCat Discovery using the WSKey class to obtain an Access Token and the Bib Class to request the record

.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807'));
   
   $bib = Bib::Find(7977212, $accessToken);
   
   if (is_a($bib, 'WorldCat\Discovery\Error')) {
        echo $bib->getErrorCode();
        echo $bib->getErrorMessage();
   } else {
   		echo $bib->getName();
   		print_r($bib->getID());
   		echo $bib->getID()
   		print_r($bib->type();
   		echo $bib->type();
   		print_r($bib->getAuthor());
   		echo $bib->getAuthor->getName();
   		$contributors = array_map($bib->getContributors(), function($contributor){return $contributor->getName();});
   		print_r($contributors);
   }
   
   

Example: Search for Bibliographic Resource in WorldCat
======================================================
This example shows how to search for bibs via WorldCat Discovery API using the WSKey class to obtain an Access Token and the Bib Class to perform the search
   
.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807'));
   
   $query = 'cats';
   $results = Bib::Search($query, $accessToken);
   if (is_a($results, '\Guzzle\Http\Exception\BadResponseException')) {
   		print_r($results);
   } else {
   		$searchResults = array_map($results->getSearchResults(), function($bib){return $bib->getName()->getValue() . ($bib->getDatePublished() ?  ' ' . $bib->getDatePublished()->getValue()  : '');});
   		print_r($searchResults);
   }
