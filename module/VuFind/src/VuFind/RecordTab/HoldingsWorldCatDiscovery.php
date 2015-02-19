<?php
/**
 * Holdings (WorldCatDiscovery) tab
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Holdings (WorldCatDiscovery) tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class HoldingsWorldCatDiscovery extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
	/**
	 * HTTP service
	 *
	 * @var \VuFindHttp\HttpServiceInterface
	 */
	protected $httpService = null;
	
    /**
     * Constructor
     */
	public function __construct($config){
		$this->config = $config;
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
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Holdings';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $offers = $this->getRecordDriver()->tryMethod('getOffers');
        return !empty($offers);
    }
    
    /**
     * Get the EHoldings
     *
     */
    public function getEHoldings($openURLParameters)
    {
    	//if (isset($this->config->eHoldings->active) && $this->config->eHoldings->active) {
    		$kbrequest = "http://worldcat.org/webservices/kb/openurl/resolve?";
    		$kbrequest .= $openURLParameters;
    		$kbrequest .= '&wskey=' . $this->config->General->wskey;
			
    		$client = $this->httpService
    		->createClient($kbrequest);
    		$adapter = new \Zend\Http\Client\Adapter\Curl();
    		$client->setAdapter($adapter);
    		$result = $client->setMethod('GET')->send();
    		
    		if ($result->isSuccess()){
	    		$kbresponse = json_decode($result->getBody(), true);
	    		if (isset($kbresponse[0]['url'])){
	    			return $kbresponse[0]['url'];
	    		}
    		} else {
    			throw new \Exception('WorldCat Knowledge Base API error - ' . $result->getStatusCode() . ' - ' . $result->getReasonPhrase());
    		}
    		
    		
    	//}
    }
    
}