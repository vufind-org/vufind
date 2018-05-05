<?php
/**
 * 
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\DigitalContent;
use VuFind\View\Helper\Root\DateTime;
use Zend\Log\LoggerAwareInterface;
use Zend\Session\Container;

//TODO
//get productsKey token once per session (remove from config file)

//TODO - features
//provide option for autocheckout by default in config
//LATER
//allow override for cover display
//provide option for not requireing email for holds
//provide option for giving users option for every hold
//provide option for asking about autocheckout for every hole

/**
 * OverdriveConnector
 *
 * This is responsible for connecting to the Overdrive API
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OverdriveConnector implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    
    protected $sessionContainer;
    protected $recordConfig;
    protected $mainConfig;
    
    
    
    //protected $metaDataList = array();
    
     /**
     * Constructor
     *
     * @param \Zend\Config\Config $recordConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $recordConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     * @param Callable     $sessionFactory Factory function returning SessionContainer object
     */
    public function __construct($mainConfig = null, $recordConfig = null, $sessManager
    ) {
         $this->debug("SolrOverdrive Connector constructed start");
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig;
        
        // Init session cache for session-specific data
        if($conf=$this->_getConfig()){
            $namespace = md5("VuFind\DigitalContent");
            //$factory = $this->sessionFactory;
            $this->sessionContainer = new Container($namespace);

        }
        $this->debug("SolrOverdrive Connector constructed");
    }
    
     /**
     * Get Availability
     *
     * Description.
     *
     * @since 5.0

     *
     * @param str $overDriveId The Overdrive ID (reserve ID) of the eResource
     * @return type Description.
     */    
     public function getAvailability($overDriveId){
       // $overDriveId = $this->getOverdriveID();

        $res = false;
        if(!$overDriveId){
            $this->logWarning("no overdrive content ID was passed in.",["getOverdriveAvailability"]);
            return false;
        }
        if($conf=$this->_getConfig()){
            
            //if ($productsKey == null){
                $productsKey = $conf->prodKey;
            //}
            $baseUrl = $conf->discURL;
            $availabilityUrl = "$baseUrl/v2/collections/$productsKey/products/$overDriveId/availability";
            $res = $this->_callUrl($availabilityUrl);
        }
        return $res;
    }

     /**
     * Get AvailabilityBulk
     *
     * Gets availability for up to 25 titles at once
     *
     * @since 5.0

     *
     * @param array $overDriveId The Overdrive ID (reserve IDs) of the eResources
     * @return type Description.
     */    
     public function getAvailabilityBulk($overDriveIds = array()){
       // todo, if more tan 25 passed in, make multiple calls 

        $res = false;
        if(count($overDriveIds)< 1){
            $this->logWarning("no overdrive content ID was passed in.",["getOverdriveAvailability"]);
            return false;
        }
        if($conf=$this->_getConfig()){
            
            //if ($productsKey == null){
                $productsKey = $conf->prodKey;
            //}
            $baseUrl = $conf->discURL;
            //$overDriveIds = $this->_stripPrefixes($overDriveIds);
            $availabilityUrl = "$baseUrl/v2/collections/$productsKey/availability?products=".implode(",",$overDriveIds);
            $res = $this->_callUrl($availabilityUrl);
        }
        return $res;
    }
    
    private function _stripPrefixes($ids){
             $result = array();
             foreach($ids as &$id){
               $result[]= $this->_stripPrefix($id); 
             }
             return $result;
    }
    private function _stripPrefix($id){
             //TODO SET prefix in CONFIG
             $prefix = 'overdrive.';
             return substr($id, strlen($prefix)); 
    }

    /**
     * @param      $overDriveId
     * @param bool $user
     *
     * @return array
     */
    public function doOverdriveCheckout($overDriveId, $user=false)
    {
        //$email = "test@icpl.org";
        $this->debug("doOverdriveCheckout");
        
        $this->debug("overdriveID: ". $overDriveId);
        if(!$user){
            $this->error("no user was passed in.");
        }
        if($config=$this->_getConfig()){
            
        
            $url = $config->circURL . '/v1/patrons/me/checkouts';
            $params = array(
                'reserveId' => $overDriveId,
            );
            
            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, $params);
            $holdResult = array();
            $holdResult['result'] = false;
            $holdResult['message'] = '';
            
            if (!empty($response)){
                if (isset($response->reserveId)){
                    $expires = "";
                    if($dt = new DateTime($response->expires)){
                        $expires = $dt->format((string)$config->displayDateFormat);
                    }
                    $holdResult['result'] = true;
                    //todo: translate
                    $holdResult['message'] = "This title was checked out to you. It expires on $expires";
                }else{
                    //todo: translate
                    $holdResult['message'] = '<i class=\'fa fa-exclamation-triangle\'></i>Sorry, but we could not check this title out for you.  ' . $response->message;
                }
            }else{
                //todo: translate
                $holdResult['message'] = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }
        
    /**
     * Places a hold on an item within OverDrive
     *
     * @param string $overDriveId
     * @param int $format
     * @param User $user
     *
     * @return array (result, message)
     */
    public function placeOverDriveHold($overDriveId, $user=false, $email)
    {
        
        $this->debug("placeOverdriveHold");
        $this->debug(print_r($user,true));
        
        if($config=$this->_getConfig()){
            //TODO: Make this a configuration option
            $autoCheckout = true;
            $ignoreHoldEmail = false;
            $url = $config->circURL . '/v1/patrons/me/holds';
            $params = array(
                'reserveId' => $overDriveId,
                'emailAddress' => $email,
                'autoCheckout' => $autoCheckout,
                'ignoreHoldEmail' => $ignoreHoldEmail,
            );
            
            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, $params);
            $holdResult = array();
            $holdResult['result'] = false;
            $holdResult['message'] = '';
            
            if (!empty($response)){
                if (isset($response->holdListPosition)){
                    $holdResult['result'] = true;
                    $holdResult['message'] = [
                            'html' => true,
                            'msg' => 'hold_place_success_html',
                        ];
                }else{
                    $holdResult['message'] = '<i class=\'fa fa-exclamation-triangle\'></i>Sorry, but we could not place a hold for you on this title.  ' . $response->message;
                }
            }else{
                $holdResult['message'] = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }

    /**cancelHold
     *
     * @param      $resourceID
     * @param bool $user
     * @param      $email
     *
     * @return array
     */
    public function cancelHold($resourceID, $user=false, $email)
    {
        $holdResult = array();
        $holdResult['result'] = false;
        $this->debug("cancel Hold");
        $this->debug(print_r($user,true));

        if(!$user){
            $this->error("no user passed in",false,true);
            return $holdResult;
        }
        if($config=$this->_getConfig()){
            $url = $config->circURL . '/v1/patrons/me/holds';

            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, null, "POST");

            $holdResult['message'] = '';

            if (!empty($response)){
                if (isset($response->holdListPosition)){
                    $holdResult['result'] = true;
                    $holdResult['message'] = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                    ];
                }else{
                    $holdResult['message'] = '<i class=\'fa fa-exclamation-triangle\'></i>Sorry, but we could not cancel hold for you on this title.  ' . $response->message;
                }
            }else{
                $holdResult['message'] = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }


    /**
     * @return bool|\stdClass
     */
    private function _getConfig(){
        $conf = new \stdClass(); 
        if(!$this->recordConfig){
            $this->error("Could not locate the Overdrive Record Driver configuration.");
            return false;
        }
        if($this->recordConfig->API->productionMode==false){
            $conf->discURL = $this->recordConfig->API->integrationDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->integrationCircURL;
            $conf->webID = $this->recordConfig->API->integrationWebsiteID;
            $conf->prodKey = $this->recordConfig->API->integrationProductsKey;
        }else{
            $conf->discURL = $this->recordConfig->API->productionDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->productionCircURL;
            $conf->webID = $this->recordConfig->API->productionWebsiteID;
            $conf->prodKey = $this->recordConfig->API->productionProductsKey; 
        }

        $conf->clientKey =  $this->recordConfig->API->clientKey; 
        $conf->clientSecret  =  $this->recordConfig->API->clientSecret; 
        $conf->tokenURL  =  $this->recordConfig->API->tokenURL;
        $conf->idField = $this->recordConfig->Overdrive->overdriveIdMarcField;
        $conf->idSubfield = $this->recordConfig->Overdrive->overdriveIdMarcSubfield;
        $conf->ILSname = $this->recordConfig->API->ILSname;
        //TODO
        $conf->isMarc = false;
        $conf->displayDateFormat = $this->mainConfig->displayDateFormat;
        //$this->debug("OD Record driver config: ".print_r($this->recordConfig,true));
        return $conf;
    }
    
    /**
     * Returns a hash of metadata keyed on overdrive reserveID
     *
     * @param array $overDriveIds
     *
     * @return array () 
     */
    public function getMetadata($overdriveIDs = array())
    {
        //example URL:
        //https://api.overdrive.com/v1/collections/v1L1BYwAAAA2Q/bulkmetadata?reserveIds=33312cf6-2696-4aae-9b25-59a28874b6e6,679b3696-ef6f-4738-b4b3-363c6b096012
        $res = false;
        $metadata = array();
        if(!$overdriveIDs || count($overdriveIDs)<1){
            $this->logWarning("no overdrive content IDs waere passed in.",["getMetadata"]);
            return array();
        }
        if($conf=$this->_getConfig()){
            $productsKey = $conf->prodKey;
            $baseUrl = $conf->discURL;
            $metadataUrl = "$baseUrl/v1/collections/$productsKey/bulkmetadata?reserveIds=".implode(",",$overdriveIDs);
            $res = $this->_callUrl($metadataUrl);
            $md = $res->metadata;
            foreach($md as $item){
                $metadata[$item->id] = $item;
            }
        }
        return $metadata;
    }


    /**
     * @param      $user
     * @param bool $refresh
     * @param bool $withMetadata
     *
     * @return OverdriveResult
     */
    public function getCheckouts($user,$refresh=true,$withMetadata=false)
    {

       //the checkouts are cached in the session, but we can force a refresh
       //
        $this->debug("get Overdrive Checkouts");
       // $this->debug(print_r($user,true));
        $result = new OverdriveResult();

        $checkouts = $this->sessionContainer->checkouts;
        if(!$checkouts || $refresh){
            if($config=$this->_getConfig()){
                $url = $config->circURL . '/v1/patrons/me/checkouts';

                $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, false);

                
                if (!empty($response)){
                        $result->status = true;
                        $result->msg="";
                        $result->data = $response->checkouts;
                        $this->sessionContainer->checkouts = $response->checkouts;
                }else{
                    $result->message = 'There was an unexpected error while connecting to Overdrive.';
                }
            }
        }else{
            $result->status = true;
            $result->msg = [];
            $result->data = $this->sessionContainer->checkouts;
        }
        return $result;
    }
   
    public function getHolds($user,$refresh=true){

       //the checkouts are cached in the session, but we can force a refresh
       //
        $this->debug("get Overdrive Holds");
       // $this->debug(print_r($user,true));
        $result = new OverdriveResult();
        $holds = $this->sessionContainer->holds;
        if(!$holds || $refresh){
            if($config=$this->_getConfig()){
                $url = $config->circURL . '/v1/patrons/me/holds';

                $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, $params);

                $result->status = false;
                $result->message = '';
                
                if (!empty($response)){

                        $result->status = true;
                        $result->message = 'hold_place_success_html';
                        $result->data = $response->holds;
                        //Check for holds ready for chechout
                        foreach($response->holds as $key=>$hold){
                            if(!$hold->autoCheckout && holdListPosition==1){
                                $result->data[$key]->holdReadyForCheckout = true;
                                //format the expires date.
                                $holdExpires = new DateTime($hold->holdExpires);
                                $result->data[$key]->holdExpires = $holdExpires->format((string)$config->displayDateFormat);
                            }
                        }
                        $this->sessionContainer->holds = $response->holds;

                }else{
                    $result->message = 'There was an unexpected error while connecting to Overdrive.';
                }
            }
        }else{
            $this->debug("found Overdrive Holds in cache");
            $result->status = true;
            $result->message = [];
            $result->data = $this->sessionContainer->holds;
        }
        return $result;
    }   
    
    private function _connectToAPI($forceNewConnection = false){
        $conf = $this->_getConfig();
        $tokenData = $this->sessionContainer->tokenData;
        if( $forceNewConnection || $tokenData == null || time() >= $tokenData->expirationTime ) {
            $authHeader = base64_encode ( $conf->clientKey . ":" . $conf->clientSecret);
            $this->debug("tokenURL: ".$conf->tokenURL);
            $this->debug("authHeader: $authHeader");
            $ch = curl_init($conf->tokenURL);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8', "Authorization: Basic $authHeader"));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $return = curl_exec($ch);
            curl_close($ch);
            $tokenData = json_decode($return);
            $this->debug("return from OD API Call: ". print_r($tokenData,true));
            
            if ($tokenData != null){
                if (isset($tokenData->error)){
                    $this->error("Overdrive Token Error: ".$tokenData->error);
                    return false;
                }else{
                    $tokenData->expirationTime = time() + $tokenData->expires_in;
                    $this->sessionContainer->tokenData = $tokenData;
                    return $tokenData;
                }
            }
        }
        return $tokenData;
    }
    private function _connectToPatronAPI($patronBarcode, $patronPin = 1234, $forceNewConnection = false){
        $patronTokenData = $this->sessionContainer->patronTokenData;
        $config = $this->_getConfig();
        if( $forceNewConnection || $patronTokenData == null || time() >= $patronTokenData->expirationTime ) {
            $this->debug("connecting to patron API for new token.");
            $ch = curl_init("https://oauth-patron.overdrive.com/patrontoken");
            $websiteId = $config->webID;
            $ilsname = $config->ILSname;

            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            $encodedAuthValue = base64_encode($config->clientKey . ":" . $config->clientSecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic " . $encodedAuthValue,
                "User-Agent: VuFind-Plus"
            ));

            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            
            //grant_type=password&username=1234567890&password=1234&scope=websiteid:12345 authorizationname:default
            if ($patronPin == null){
                $postFields = "grant_type=password&username={$patronBarcode}&password=ignore&password_required=false&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
            }else{
                $postFields = "grant_type=password&username={$patronBarcode}&password={$patronPin}&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
            }
            $this->debug("postFields: $postFields");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            $patronTokenData = json_decode($return);
            $this->debug("return from OD patron API Call: ". print_r($patronTokenData,true));
            if( isset($patronTokenData->expires_in) ) {
                $patronTokenData->expirationTime = time() + $patronTokenData->expires_in;
            } else {
                $patronTokenData = null;
            }
            $this->sessionContainer->patronTokenData = $patronTokenData;
        }
        return $patronTokenData;
    }
    private function _callUrl($url){
        if ( $this->_connectToAPI() ){
            $tokenData = $this->sessionContainer->tokenData;
            $this->debug("url for OD API Call: $url");
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: {$tokenData->token_type} {$tokenData->access_token}", "User-Agent: VuFind-Plus"));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            $returnVal = json_decode($return);
            $this->debug("return from OD API Call: ". print_r($returnVal,true));
            if ($returnVal != null){
                if (isset($returnVal->errorCode)){
                    $this->error("Overdrive Error: ".$returnVal->errorCode);
                    return false;
                }else{
                    return $returnVal;
                }
            }
        }
        return false;
    }
    private function _callPatronUrl($patronBarcode, $patronPin, $url, $params = null, $requestType = null){
        $this->debug("calling patronURL: $url");
        if ($this->_connectToPatronAPI($patronBarcode, $patronPin, false)){
            $patronTokenData = $this->sessionContainer->patronTokenData;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            $authorizationData = $patronTokenData->token_type . ' ' . $patronTokenData->access_token;
            $headers = array(
                "Authorization: $authorizationData",
                "User-Agent: VuFind-Plus",
                "Content-Type: application/json"
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            if( $requestType != null ) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
            }
            if ($params != null){
                curl_setopt($ch, CURLOPT_POST, 1);
                //Convert post fields to json
                $jsonData = array('fields' => array());
                foreach ($params as $key => $value){
                    $jsonData['fields'][] = array(
                        'name' => $key,
                        'value' => $value
                    );
                }
                $postData = json_encode($jsonData);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }else{
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            $this->debug("curl Info: ".print_r($curlInfo,true));
            if ($curlInfo['http_code'] == 204){
                $result = true;
            }else{
                $result = false;
            }
            curl_close($ch);
            //$this->debug("response from call: ".print_r($returnVal,true));
            $returnVal = json_decode($return);
            $this->debug("response from call: ".print_r($returnVal,true));
            if ($returnVal != null){
                if (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.'){
                    return $returnVal;
                }
            }else{
                return $result;
            }
        }else{
            $this->Error("not connected to Patron API");
        }
        return false;
    } 
    
}

/**
 * Class OverdriveResult
 *
 * @package VuFind\DigitalContent
 */
class OverdriveResult{

    /**
     * @var bool
     */
    public $status = false;
    /**
     * @var string
     */
    public $msg = "";
    /**
     * @var mixed
     */
    public $data = false;
}