<?php
/**
 * Ajax Controller Module
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use VuFind\AjaxHandler\PluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AbstractActionController
    implements TranslatorAwareInterface
{
    use AjaxResponseTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param PluginManager $am AJAX Handler Plugin Manager
     */
    public function __construct(PluginManager $am)
    {
        // Add notices to a key in the output
        set_error_handler([static::class, 'storeError']);
        $this->ajaxManager = $am;
    }

    /**
     * Make an AJAX call with a JSON-formatted response.
     *
     * @return \Zend\Http\Response
     */
    public function jsonAction()
    {
        return $this->callAjaxMethod($this->params()->fromQuery('method'));
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return \Zend\Http\Response
     */
    public function recommendAction()
    {
        return $this->callAjaxMethod('recommend', 'text/html');
    }

    /**
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    public function systemStatusAction()
    {
        return $this->callAjaxMethod('systemStatus', 'text/plain');
    }

    //for relais integration
    public function getHttpClient($url)
    {
        return new \Zend\Http\Client($url);
    }


    /**
     * 
     * ORDER THE ITEM FOR THE LOGGED IN PATRON
     * 
     */
    protected function orderItemFromPalciAjax()
    {

        $config = $this->getConfig();
        $relaisGroupCode = $config['Relais']['group'];
        $relaisApiKey = $config['Relais']['apikey'];
        $relaisAuthenticationUrl = $config['Relais']['authenticateurl'];
        $relaisAvailableUrl = $config['Relais']['availableurl'];
        $relaisPatronForLookup = $config['Relais']['patronForLookup'];
        $relaisAddUrl = $config['Relais']['addurl'];


        $client = $this->getHttpClient($relaisAuthenticationUrl);

        $oclcNumber = $this->params()->fromQuery('oclcNumber');

        $user = $this->getUser();
        $lin = $user['cat_username'];

        $data = '{
              "ApiKey": "' . $relaisApiKey .'",
              "UserGroup": "PATRON",
              "PartnershipId": "'. $relaisGroupCode  . '",
              "LibrarySymbol": "LEHI",
              "PatronId": "' . $lin . '"  
            }';

        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();;

        $jsonReturn = $response->getBody();
        //echo $jsonReturn;
        $jsonReturnObject = json_decode($jsonReturn);

        //DID THE API CALL RETURN AN AUTHORIZATION ID?
        if(!isset($jsonReturnObject->AuthorizationId)) {
             return $this->output($this->translate('Failed'),self::STATUS_ERROR);
        }
            


        $authorizationId = $jsonReturnObject->AuthorizationId;


        $client = $this->getHttpClient("$relaisAddUrl?aid=" . $authorizationId);
        $data = '{
          "ApiKey": "'. $relaisApiKey .'",
          "UserGroup": "PATRON",
          "PartnershipId": "'. $relaisGroupCode .'",
          "LibrarySymbol": "LEHI",
          "PickupLocation:" : "FAIRCHILD",
          "Notes" : "This request was made through the VuFind Catalog interface",
          "PatronId": "' . $lin . '",
          "ExactSearch": [
            {
            "Type": "OCLC",
            "Value": "' . $oclcNumber .'"
            }]
        }';
        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();
        $responseText = $response->getBody();
        if (strpos($responseText, 'error') !== false) {
            return $this->output($response->getBody(), self::STATUS_ERROR);
        }
        else {
            return $this->output($response->getBody(), self::STATUS_OK);
        }

    }


     /**
     * IS THE ITEM ITSELF AVAILABLE?  CALL MADE W/GENERIC PATRON ID
     *
     * @return \Zend\Http\Response
     */
    protected function isItemAvailableAjax()
    {

        $config = $this->getConfig();
        $relaisGroupCode = $config['Relais']['group'];
        $relaisApiKey = $config['Relais']['apikey'];
        $relaisAuthenticationUrl = $config['Relais']['authenticateurl'];
        $relaisAvailableUrl = $config['Relais']['availableurl'];
        $relaisPatronForLookup = $config['Relais']['patronForLookup'];
        $relaisAddUrl = $config['Relais']['addurl'];

        //AUTHENTICATE
        $client = $this->getHttpClient($relaisAuthenticationUrl);

        $oclcNumber = $this->params()->fromQuery('oclcNumber');


        $data = '{
              "ApiKey": "'. $relaisApiKey .'",
              "UserGroup": "PATRON",
              "PartnershipId": "'. $relaisGroupCode .'",
              "LibrarySymbol": "LEHI",
              "PatronId": "' . $relaisPatronForLookup . '"  
            }';
        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();

        $jsonReturn = $response->getBody();
        $jsonReturnObject = json_decode($jsonReturn);

        //DID THE API CALL RETURN AN AUTHORIZATION ID?
        if(!isset($jsonReturnObject->AuthorizationId)) {
             return $this->output($this->translate('Failed'),self::STATUS_ERROR);
        }
            


        $authorizationId = $jsonReturnObject->AuthorizationId;


        $client = $this->getHttpClient($relaisAvailableUrl . "?aid=" . $authorizationId);
        $data = '{
          "ApiKey": "' . $relaisApiKey .'",
          "UserGroup": "PATRON",
          "PartnershipId": "' . $relaisGroupCode .'",
          "LibrarySymbol": "LEHI",
          "PickupLocation:" : "FAIRCHILD",
          "Notes" : "This request was made through the VuFind Catalog interface",
          "PatronId": "'. $relaisPatronForLookup .'",
          "ExactSearch": [
            {
            "Type": "OCLC",
            "Value": "' . $oclcNumber .'"
            }]
        }';
        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();
        $responseText = $response->getBody();
        if (strpos($responseText, 'error') !== false) {
            return $this->output("no", self::STATUS_OK);
        }
        if (strpos($responseText, 'ErrorMessage') !== false) {
            return $this->output("no", self::STATUS_OK);
        }
        if (strpos($responseText, 'false') !== false) {
            return $this->output("no", self::STATUS_OK);
        }
        else {
            return $this->output("ok", self::STATUS_OK);
        }



    }

     /**
     * CAN THE LOGGED IN PATRON ORDER THIS ITEM?
     *
     * @return \Zend\Http\Response
     */
    protected function getRelaisInfoAjax()

    {

        $config = $this->getConfig();
        $relaisGroupCode = $config['Relais']['group'];
        $relaisApiKey = $config['Relais']['apikey'];
        $relaisAuthenticationUrl = $config['Relais']['authenticateurl'];
        $relaisAvailableUrl = $config['Relais']['availableurl'];
        $relaisPatronForLookup = $config['Relais']['patronForLookup'];
        $relaisAddUrl = $config['Relais']['addurl'];

        $client = $this->getHttpClient($relaisAuthenticationUrl);

        $oclcNumber = $this->params()->fromQuery('oclcNumber');

        $data = '{
              "ApiKey": "'. $relaisApiKey .'",
              "UserGroup": "PATRON",
              "PartnershipId": "'. $relaisGroupCode . '",
              "LibrarySymbol": "LEHI",
              "PatronId": "'. $relaisPatronForLookup . '"
            }';
        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();


        $headers = $response->getHeaders();
        $contentHeader = $headers->get('Content-Type');//Zend\Http\Header\HeaderInterface


        $user = $this->getUser();
        $lin = $user['cat_username'];

        $jsonReturn = $response->getBody();
        $jsonReturnObject = json_decode($jsonReturn);
        $authorizationId = $jsonReturnObject->AuthorizationId;
        $allowLoan = $jsonReturnObject->AllowLoanAddRequest;
        if ($allowLoan == false) return $this->output("AllowLoan was false",self::STATUS_ERROR); 
        $client = $this->getHttpClient($relaisAvailableUrl . "?aid=" . $authorizationId);
        $data = '{
          "ApiKey": "' . $relaisApiKey .'",
          "UserGroup": "PATRON",
          "PartnershipId": "' . $relaisGroupCode .'",
          "LibrarySymbol": "LEHI",
          "PatronId": "'. $lin .'",
          "ExactSearch": [
            {
            "Type": "OCLC",
            "Value": "' . $oclcNumber .'"
            }]
        }';
        $client->setMethod('POST');
        $client->setRawBody($data,'application/json');
        $client->setAdapter('Zend\Http\Client\Adapter\Curl');
        $client->setOptions(array('curloptions' => array(CURLOPT_TIMEOUT => 500,CURLOPT_SSL_VERIFYPEER => false,CURLOPT_HTTPHEADER=>array('Content-Type: application/json')),  'sslallowselfsigned' => true, 'sslcapath' => '/etc/ssl/certs/'));
        $headers = $client->getRequest()->getHeaders()->addHeaderLine('Content-Type: application/json');
        $response = $client->send();

        return $this->output($response->getBody(), self::STATUS_OK);
    }
}
