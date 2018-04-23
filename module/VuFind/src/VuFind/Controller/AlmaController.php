<?php
/**
 * Alma controller
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
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
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;

use \ZfcRbac\Service\AuthorizationServiceAwareInterface;
use \ZfcRbac\Service\AuthorizationServiceAwareTrait;
use \Zend\ServiceManager\ServiceLocatorInterface;
use \Zend\Http\Response as HttpResponse;
//use \Zend\Http\Request as HttpRequest;
//use \Zend\Mail as Mail;


class AlmaController extends AbstractBase implements AuthorizationServiceAwareInterface {
    
    use AuthorizationServiceAwareTrait;
    
    /**
     * Http service
     * @var \VuFindHttp\HttpService
     */
    protected $httpService;
    
    protected $httpResponse;
    protected $httpHeaders;
    
    protected $configAlma;
    
    public function __construct(ServiceLocatorInterface $sm) {
        parent::__construct($sm);
        $this->httpResponse = new HttpResponse();
        $this->httpHeaders = $this->httpResponse->getHeaders();
        $this->configAlma = $sm->get('VuFind\Config\PluginManager')->get('Alma');
    }
    
    public function webhookAction() {
        // Request from external
        $request = $this->getRequest();
        
        // Get request method (GET, POST, ...)
        $requestMethod = $request->getMethod();
        
        // Get request body if method is POST and is not empty
        $requestBodyJson = ($request->getContent() != null && !empty($request->getContent()) && $requestMethod == 'POST') ? json_decode($request->getContent()) : null;
        
        // Get webhook action
        $webhookAction = (isset($requestBodyJson->action)) ? $requestBodyJson->action: null;
        
        // Perform webhook action
        switch ($webhookAction) {
            case 'USER':
                return $this->webhookUser($requestBodyJson);
            case 'NOTIFICATION':
                return $this->webhookNotification();
            case 'JOB_END':
                return $this->webhookJobEnd();
            default:
                return $this->webhookChallenge();
                break;
        }  
    }
    
    protected function webhookUser($requestBodyJson) {
        
        // Get method from webhook (e. g. "create" for "new user")
        $method = (isset($requestBodyJson->webhook_user->method)) ? $requestBodyJson->webhook_user->method : null;
        
        // Get primary ID
        $primaryId = (isset($requestBodyJson->webhook_user->user->primary_id)) ? $requestBodyJson->webhook_user->user->primary_id : null;
        
        
        if ($method == 'CREATE' || $method == 'UPDATE') {
            // Get username (could also be the barcode)
            $username = null;
            $userIdentifiers = (isset($requestBodyJson->webhook_user->user->user_identifier)) ? $requestBodyJson->webhook_user->user->user_identifier : null;
            $idTypeConfig = ($this->configAlma->NewUser->idType != null && isset($this->configAlma->NewUser->idType)) ? $this->configAlma->NewUser->idType : null;
            
            if ($idTypeConfig == null) {
                return $this->createJsonResponse('The setting "NewUser" -> "idType" is empty in Alma.ini.', 500);
            }
            
            foreach ($userIdentifiers as $userIdentifier) {
                $idTypeHook = (isset($userIdentifier->id_type->value)) ? $userIdentifier->id_type->value : null;
                if ($idTypeHook != null && $idTypeHook == $idTypeConfig && $username == null) {
                    $username = (isset($userIdentifier->value)) ? $userIdentifier->value : null;
                }
            }
            
            // TEST
            return $this->createJsonResponse('Test Alma Webhook - USER CREATE. $idTypeConfig: '.$idTypeConfig, 200);
        }
        
        
        /*
        $returnArray = [];
        $returnArray[] = 'Test Alma Webhook - USER.';        
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode(200); // Set HTTP status code to Bad Request (400)
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
        */
    }
    
    protected function webhookNotification() {
        $returnArray = [];
        $returnArray[] = 'Test Alma Webhook - NOTIFICATION.';
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode(200); // Set HTTP status code to Bad Request (400)
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
    }
    
    protected function webhookJobEnd() {
        $returnArray = [];
        $returnArray[] = 'Test Alma Webhook - JOB END.';
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode(200); // Set HTTP status code to Bad Request (400)
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
    }
    
    protected function webhookChallenge() {
        
        // Get challenge string from the get parameter that Alma sends us. We need to return this string in the return message.
        $secret = $this->params()->fromQuery('challenge');
        
        // Create the return array
        $returnArray = [];
        
        if ($secret != null && !empty(trim($secret)) && isset($secret)) {
            $returnArray['challenge'] = $secret;
            // Set HTTP status code to "OK" (200)
            $this->httpResponse->setStatusCode(200);
        } else {
            $returnArray['error'] = 'GET parameter "challenge" is empty, not set or not available when receiving webhook challenge from Alma.';
            // Set HTTP status code to "Bad Request" (400)
            $this->httpResponse->setStatusCode(400);
        }
        
        // Remove null from array
        $returnArray = array_filter($returnArray);
        
        // Create return JSON value and set it to the response
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setContent($returnJson);

        return $this->httpResponse;
    }
    
    protected function createJsonResponse($text, $httpStatusCode) {
        $returnArray = [];
        $returnArray[] = $text;
        $returnJson = json_encode($returnArray, JSON_PRETTY_PRINT);
        $this->httpHeaders->addHeaderLine('Content-type', 'application/json');
        $this->httpResponse->setStatusCode($httpStatusCode);
        $this->httpResponse->setContent($returnJson);
        return $this->httpResponse;
    }
}
?>