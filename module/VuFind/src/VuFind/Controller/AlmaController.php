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

use \Zend\ServiceManager\ServiceLocatorInterface;
use \Zend\Http\Response as HttpResponse;
//use \Zend\Http\Request as HttpRequest;
//use \Zend\Mail as Mail;


class AlmaController extends AbstractBase {
    
    /**
     * Http service
     * @var \VuFindHttp\HttpService
     */
    protected $httpService;
    
    /**
     * Http response
     * @var \Zend\Http\Response
     */
    protected $httpResponse;
    
    /**
     * Http headers
     * @var \Zend\Http\Headers
     */
    protected $httpHeaders;
    
    /**
     * Alma.ini config
     * @var \Zend\Config\Config
     */
    protected $configAlma;
    
    /**
     * User table
     * 
     * @var \VuFind\Db\Table\User
     */
    protected $userTable;
    
    /**
     * Authorization with permissions.ini
     * 
     * 
     */
    protected $auth;
    
    public function __construct(ServiceLocatorInterface $sm) {
    	
        parent::__construct($sm);
        $this->httpResponse = new HttpResponse();
        $this->httpHeaders = $this->httpResponse->getHeaders();
        $this->configAlma = $this->getConfig('Alma');
    	$this->userTable = $this->getTable('user');
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
            	$accessPermission = 'access.alma.webhook.user';
            	try {
					$this->checkPermission($accessPermission, $webhookAction);
				} catch (\VuFind\Exception\Forbidden $ex) {
					return $this->createJsonResponse('Access to Alma Webhook \'' . $webhookAction . '\' forbidden. Set permission \''.$accessPermission.'\' in \'permissions.ini\'.', 403);
				}
		
                return $this->webhookUser($requestBodyJson);
                break;
            case 'JOB_END':
            case 'NOTIFICATION':
            case 'LOAN':
            case 'REQUEST':
            case 'BIB':
            case 'ITEM':
                return $this->webhookNotImplemented($webhookAction);
            	break;
            default:
            	$accessPermission = 'access.alma.webhook.challenge';
            	try {
					$this->checkPermission($accessPermission, $webhookAction);
				} catch (\VuFind\Exception\Forbidden $ex) {
					return $this->createJsonResponse('Access to Alma Webhook challenge forbidden. Set permission \''.$accessPermission.'\' in \'permissions.ini\'.', 403);
				}
                return $this->webhookChallenge();
                break;
        }  
    }
    
    protected function webhookUser($requestBodyJson) {
    	
    	// Initialize user variable that should hold the user table row
        $user = null;
        
        // Initialize response variable
        $jsonResponse = null;
        
        // Get method from webhook (e. g. "create" for "new user")
        $method = (isset($requestBodyJson->webhook_user->method)) ? $requestBodyJson->webhook_user->method : null;
        
        // Get primary ID
        $primaryId = (isset($requestBodyJson->webhook_user->user->primary_id)) ? $requestBodyJson->webhook_user->user->primary_id : null;
        
        if ($method == 'CREATE' || $method == 'UPDATE') {
            // Get username (could e. g. be the barcode)
            $username = null;
            $userIdentifiers = (isset($requestBodyJson->webhook_user->user->user_identifier)) ? $requestBodyJson->webhook_user->user->user_identifier : null;
            $idTypeConfig = ($this->configAlma->NewUser->idType != null && isset($this->configAlma->NewUser->idType)) ? $this->configAlma->NewUser->idType : null;
            foreach ($userIdentifiers as $userIdentifier) {
                $idTypeHook = (isset($userIdentifier->id_type->value)) ? $userIdentifier->id_type->value : null;
                if ($idTypeHook != null && $idTypeHook == $idTypeConfig && $username == null) {
                    $username = (isset($userIdentifier->value)) ? $userIdentifier->value : null;
                }
            }
            
            // Use primary ID as username as a fallback if no other username ID is available
            $username = ($username == null) ? $primaryId : $username;
            
            // Get user details from Alma Webhook message
            $firstname = (isset($requestBodyJson->webhook_user->user->first_name)) ? $requestBodyJson->webhook_user->user->first_name : null;
            $lastname = (isset($requestBodyJson->webhook_user->user->last_name)) ? $requestBodyJson->webhook_user->user->last_name : null;
            
            $allEmails = (isset($requestBodyJson->webhook_user->user->contact_info->email)) ? $requestBodyJson->webhook_user->user->contact_info->email : null;
            $email = null;
            foreach ($allEmails as $currentEmail) {
				$preferred = (isset($currentEmail->preferred)) ? $currentEmail->preferred : false;
				if ($preferred && $email == null) {
					$email = (isset($currentEmail->email_address)) ? $currentEmail->email_address : null;
				}
			}
			
            if ($method == 'CREATE') {
				$user = $this->userTable->getByUsername($username, true);
            }
            
            if ($method == 'UPDATE') {
            	$user = $this->userTable->getByCatalogId($primaryId);
            }
            
            if ($user) {
            	$user->username = $username;
				$user->password = 'password';
				$user->pass_hash = 'pass_hash';
				$user->firstname = $firstname;
				$user->lastname = $lastname;
				$user->email = $email;
				$user->cat_id = $primaryId;
				$user->cat_username = $username;
				$user->cat_password = 'cat_password';
				$user->cat_pass_enc = 'cat_pass_enc';

				try {
					$user->save();
					$jsonResponse = $this->createJsonResponse('Successfully '.strtolower($method).'d user with primary ID \''.$primaryId.'\' | username \''.$username.'\'.', 200);
				} catch (\Exception $ex) {
            		$jsonResponse = $this->createJsonResponse('Error when saving new user with primary ID \''.$primaryId.'\' | username \''.$username.'\' to VuFind database: '.$ex->getMessage(), 400);
				}
            } else {
	        	$jsonResponse = $this->createJsonResponse('User with primary ID \''.$primaryId.'\' | username \''.$username.'\' was not found in VuFind database and therefore could not be '.strtolower($method).'d.', 404);
            }
        } else if ($method == 'DELETE') {
        	$user = $this->userTable->getByCatalogId($primaryId);
        	if ($user) {
	        	$rowsAffected = $user->delete();
	        	if ($rowsAffected == 1) {
	        		$jsonResponse = $this->createJsonResponse('Successfully deleted use with primary ID \''.$primaryId.'\' in VuFind.', 200);
	        	} else {
	        		$jsonResponse = $this->createJsonResponse('Problem when deleting user with \''.$primaryId.'\' in VuFind. It is expected that only 1 row of the VuFind user table is affected by the deletion. But '.$rowsAffected.' were affected. Please check the status of the user in the VuFind database.', 400);
	        	}
        	} else {
	        	$jsonResponse = $this->createJsonResponse('User with primary ID \''.$primaryId.'\' was not found in VuFind database and therefore could not be deleted.', 404);
        	}
        }
        
        return $jsonResponse;
    }
        
    protected function webhookChallenge() {
        
        // Get challenge string from the get parameter that Alma sends us. We need to return this string in the return message.
        $secret = $this->params()->fromQuery('challenge');
        
        // Create the return array
        $returnArray = [];
        
        if ($secret != null && !empty(trim($secret)) && isset($secret)) {
            $returnArray['challenge'] = $secret;
            $this->httpResponse->setStatusCode(200);
        } else {
            $returnArray['error'] = 'GET parameter \'challenge\' is empty, not set or not available when receiving webhook challenge from Alma.';
            $this->httpResponse->setStatusCode(500);
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
    
    protected function webhookNotImplemented($webhookType) {
        return $this->createJsonResponse($webhookType.' Alma Webhook is not (yet) implemented in VuFind.', 400);
    }
    
    protected function checkPermission($accessPermission, $webhookAction) {
    	$this->accessPermission = $accessPermission;
		$this->accessDeniedBehavior = 'exception';
		$this->validateAccessPermission($this->getEvent());
    }
    
}
?>