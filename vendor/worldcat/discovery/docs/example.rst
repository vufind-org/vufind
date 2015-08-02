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
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $bib = Bib::Find(7977212, $accessToken);
   
   if (is_a($bib, 'WorldCat\Discovery\Error')) {
      echo $bib->getErrorCode();
      echo $bib->getErrorMessage();
   } else {
      echo $bib->getName();
      print_r($bib->getID());
      echo $bib->getID();
      print_r($bib->type());
      echo $bib->type();
      print_r($bib->getAuthor());
      echo $bib->getAuthor->getName();
      $contributors = array_map($bib->getContributors(), 
         function($contributor){return $contributor->getName();}
      );
      print_r($contributors);
   }
   

Example: Search for Bibliographic Resources in WorldCat
======================================================
This example searches bibliographic records and returns results from the WorldCat Discovery API using the WSKey class to obtain an Access Token and the Bib Class to perform the search
   
.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $query = 'cats';
   $results = Bib::Search($query, $accessToken);
   if (is_a($bib, 'WorldCat\Discovery\Error')) {
      echo $results->getErrorCode();
      echo $results->getErrorMessage();
   } else {
      foreach ($results->getSearchResults() as $bib){
         echo $bib->getName()->getValue();
         echo ($bib->getDatePublished() ?  ' ' . $bib->getDatePublished()->getValue()  : '');
      }
   }
   
Example: Search for Bibliographic Resources in WorldCat and Returning Facets
============================================================================
This example searches bibliographic records and returns results and related facets from the WorldCat Discovery API using the WSKey class to obtain an Access Token and the Bib Class to perform the search
   
.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $options = array(
      'facetFields' => array(
      'about:10', 
      'creator:10',
      'datePublished:10',
      'genre:10',
      'itemType:10',
      'inLanguage:10')
   );
   $query = 'cats';
   $results = Bib::Search($query, $accessToken, $options);
   if (is_a($bib, 'WorldCat\Discovery\Error')) {
      echo $results->getErrorCode();
      echo $results->getErrorMessage();
   } else {
      $facets = $results->getFacets();
      foreach ($facets as $facet) {
         echo $facet->getFacetIndex();
         foreach ($facet->getFacetItems() as $facetItem){
            echo $facetItem->getName() . ' ' . $facetItem->getCount();
         }
      }
   }
   
Example: Search for Offers in WorldCat
============================================================================
This example searches for Offers related to a particular Bib and return the basic bibliographic data and the offers from the WorldCat Discovery using the WSKey class to obtain an Access Token and the Offer Class to request the Offers
   
.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Offer;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $options = array('heldBy' => array('OCPBS', 'OCWMS'));
   $response = Offer::findByOclcNumber(7977212, $accessToken);
   if (is_a($response, 'WorldCat\Discovery\Error')) {
      echo $response->getErrorCode();
      echo $response->getErrorMessage();
   } else {
      $offers = $response->getOffers();
      $creativeWork = $response->getCreativeWorks();
      $creativeWork = $creativeWork[0];
      echo $creativeWork->getName();
      echo $creativeWork->getID();
      echo $creativeWork->type();
      echo $creativeWork->getAuthor->getName(); 
      foreach ($offers as $offer) {
         echo $offer->getSeller()->getName();
      }
   }
   
Example: Find a Database in WorldCat
============================================================================
This example reads a single bibliographic record from the WorldCat Discovery using the WSKey class to obtain an Access Token and the Database Class to request the database   

.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Database;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $response = Database::find(638, $accessToken);
   if (is_a($response, 'WorldCat\Discovery\Error')) {
      echo $response->getErrorCode();
      echo $response->getErrorMessage();
   } else {
      echo $response->getId();
      echo $response->getName();
      echo $response->getRequiresAuthentication();
      echo $response->getDescription();
   }   
   
Example: List Databases related to a specific institution
============================================================================
This example lists databases related to a specific institution from the WorldCat Discovery using the WSKey class to obtain an Access Token and the Database Class to request the database   

.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Database;
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807');
   
   $databases = Database::getList($accessToken);
   if (is_a($databases, 'WorldCat\Discovery\Error')) {
      echo $databases->getErrorCode();
      echo $databases->getErrorMessage();
   } else {
      foreach ($databases as $database) {
         echo $database->getId();
         echo $database->getName();
         echo $database->getRequiresAuthentication();
         echo $database->getDescription();
      }
   }
