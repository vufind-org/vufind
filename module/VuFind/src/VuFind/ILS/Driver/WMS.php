<?php
/**
 * WorldShare Management System Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Karen Coombs <librarywebchic@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;

use OCLC\Auth\WSKey;
use OCLC\Auth\AccessToken;
use VuFind\Exception\ILS as ILSException;
use Zend\Session\Container;

/**
 * WorldShare Management System Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Karen Coombs <librarywebchic@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class WMS extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
	/**
	 * HTTP service
	 *
	 * @var \VuFindHttp\HttpServiceInterface
	 */
	protected $httpService = null;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct($config, $recordLoader) {
		$this->config = $config;
	
		$this->recordLoader = $recordLoader;
		
		// want to make the configuration able to be set from WorldCat Discovery or WMS ILS Driver
		$this->wskey = $this->config->General->wskey;;
		$this->secret = $this->config->General->secret;;
		$this->institution = $this->config->General->institution;
		
		$this->session = new Container('WorldCatDiscovery');
	}
	
	/**
	 * Set the HTTP service to be used for HTTP requests.
	 *
	 * @param HttpServiceInterface $service HTTP service
	 *
	 * @return void
	 */
	public function setHttpService(\VuFindHttp\HttpServiceInterface $service)
	{
		$this->httpService = $service;
	}
	
    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
	
    public function init()
    {
        // access token stuff should go here
    }
    
    /**
     * Get or create an access token.
     *
     * @return string
     */
    protected function getAccessToken()
    {
    	if (empty($this->session->accessToken) || $this->session->accessToken->isExpired()){
    		$options = array(
    				'services' => array('WorldCatDiscoveryAPI', 'WMS_Availability', 'WMS_NCIP','refresh_token')
    		);
    		$wskey = new WSKey($this->wskey, $this->secret, $options);
    		$accessToken = $wskey->getAccessTokenWithClientCredentials($this->institution, $this->institution);
    		$this->session->accessToken = $accessToken;
    	}
    	return $this->session->accessToken;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
    	$holding = array();
    	if ($this->recordLoader->load($id, 'WorldCatDiscovery')->getOffer($this->institution)){
	    	// Make the request to WMS_Availability web service
	    	$wmsAvailabilityRequest = "https://worldcat.org/circ/availability/sru/service?x-registryId=" . $this->institution;
	    	$wmsAvailabilityRequest .= "&query=no:" . $id;
	    	
	    	try {
	    		$client = $this->httpService
	    		->createClient($wmsAvailabilityRequest);
	    		$adapter = new \Zend\Http\Client\Adapter\Curl();
	    		$client->setAdapter($adapter);
	    		$client->setHeaders(array(
	    				"Authorization" => 'Bearer ' . $this->getAccessToken()->getValue()
	    			));
	    		$wmsAvailabilityResponse = $client->setMethod('GET')->send();
	    	} catch (\Exception $e) {
	    		throw new ILSException($e->getMessage());
	    	}
	    	$availabilityXML = simplexml_load_string($wmsAvailabilityResponse->getContent());
	    	$copies = $availabilityXML->xpath('//holdings/holding');

	    	foreach ($copies as $copy){
	    		$holding[] = array('availability' => ($copy->circulations->circulation->availableNow->attributes()->value == "1") ? true : false,
	    				'status' => ($copy->circulations->circulation->availableNow->attributes()->value == "1") ? 'On the shelf' : $copy->circulations->circulation->reasonUnavailable,
	    				'location' => (isset($copy->temporaryLocation)) ? $copy->temporaryLocation : $copy->localLocation .  ' ' . $copy->shelvingLocation,
	    				'reserve' => 'No',
	    				'callnumber' => $copy->callNumber,
	    				'duedate' => $copy->circulations->circulation->availabilityDate,
	    				'number' => $copy->copyNumber,
	    				'item_id' => $copy->circulations->circulation->itemId,
	    				'requests_placed' => $copy->circulations->circulation->onHold->attributes()->value
	    		);
	    	}
    	}
    	
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = array();
        foreach ($ids as $id) {
            $items[] = $this->getStatus($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getStatus($id);
    }    

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
    	return array();
    }
    
    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        return null;
    }
}
