Logging
============

The library allows logging to be added via Guzzle's Log Plugin [http://guzzle3.readthedocs.org/plugins/log-plugin.html] which supports logs via a variety of adapters.

Log Message Format
==================
We use the follow message logging for our testing purposes

{host} {method} {resource} {req_header_Authorization} \n {code} {reason} {res_header_X-OCLC-RequestId} {res_header_X-OCLC-SelfId}

Example: 
==================================================

This example adds basic logging using the Zend Framework 2 Logging [http://framework.zend.com/manual/2.3/en/modules/zend.log.overview.html] and sending output to the buffer

.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   use Guzzle\Plugin\Log\LogPlugin;
   use Guzzle\Log\MessageFormatter;
   use Guzzle\Log\Zf2LogAdapter;
   use Zend\Log\Logger;
   use Zend\Log\Writer\Stream
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807'));
   
   $logwriter = new Stream('php://output');
   $logger = new Logger();
   $logger->addWriter($logwriter);
   $adapter = new Zf2LogAdapter($logger);
   $logPlugin = new LogPlugin($adapter, MessageFormatter::DEBUG_FORMAT);
   $options = array(
    'logger' => $logPlugin
   );
   $bib = Bib::find(7977212, $accessToken, $options);
   
Example: 
==================================================

This example adds basic logging using the Zend Framework 2 Logging [http://framework.zend.com/manual/2.3/en/modules/zend.log.overview.html] and sending output to the filesystem

.. code:: php

   require_once('vendor/autoload.php');

   use OCLC\Auth\WSKey;
   use OCLC\Auth\AccessToken;
   use WorldCat\Discovery\Bib;
   
   use Guzzle\Plugin\Log\LogPlugin;
   use Guzzle\Log\MessageFormatter;
   use Guzzle\Log\Zf2LogAdapter;
   use Zend\Log\Logger;
   use Zend\Log\Writer\Stream
   
   $key = 'api-key';
   $secret = 'api-key-secret';
   $options = array('services' => array('WorldCatDiscoveryAPI', 'refresh_token'));
   $wskey = new WSKey($key, $secret, $options);
   $accessToken = $wskey->getAccessTokenWithClientCredentials('128807', '128807'));
   
   $logwriter = new Stream('/path/to/logfile');
   $logger = new Logger();
   $logger->addWriter($logwriter);
   $adapter = new Zf2LogAdapter($logger);
   $logPlugin = new LogPlugin($adapter, MessageFormatter::DEBUG_FORMAT);
   $options = array(
    'logger' => $logPlugin
   );
   $bib = Bib::find(7977212, $accessToken, $options);      
