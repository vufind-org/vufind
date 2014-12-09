<?php
/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 * Relaunch of the daia driver developed by Oliver Goldschmidt.
 *
 * PHP version 5
 *
 * Copyright (C) Jochen Lienhard 2014.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException,
    Zend\Http\Client\Adapter\Curl as CurlAdapter;

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class DAIA2 extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
	/**
	 * daia URL
	 *
	 * @var string
	 */
	protected $daiaurl;

	/**
	 * daia request format
	 *
	 * @var string
	 */
	protected $daiaformat="json";

	/**
	 * daia query field 
	 *
	 * @var string
	 */
	protected $daiafield="ppn";

	/**
	 * daia query field 
	 *
	 * @var string
	 */
	protected $daiamethod="GET";


	/**
	 * HTTP service
	 *
	 * @var \VuFindHttp\HttpServiceInterface
	 */
	protected $httpService = null;

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
	 * Perform an HTTP request.
	 *
	 * @param string   $id    id for query in daia
	 *
	 * @return xml or json object 
	 */
	protected function doHTTPRequest($id)
	{
		$format = "format=" . $this->daiaformat;
		$http_headers = array(
				"Content-type: application/$this->daiaformat",
				"Accept: application/$this->daiaformat");
		$url = $this->daiaurl . "?id=" . $this->daiafield . ":" . $id . "&" . $format;
		$adapter = new CurlAdapter();

		try {
			$client = $this->httpService->createClient($url);
			$client->setHeaders($http_headers);
			$client->setMethod($this->daiamethod);
			$client->setAdapter($adapter);
			$result = $client->send();
		} catch (\Exception $e) {
			throw new ILSException($e->getMessage());
		}

		if (!$result->isSuccess()) {
			throw new ILSException('HTTP error');
		}
		return ($result->getBody());

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
		if (isset($this->config['Global']['daiaurl'])) {
			$this->daiaurl = $this->config['Global']['daiaurl'];
		} else {
			throw new ILSException('Global/daiaurl configuration needs to be set.');
		}
		if (isset($this->config['Global']['daiafield'])) {
			$this->daiafield = $this->config['Global']['daiafield'];
		} else {
			throw new ILSException('Global/daiafield configuration needs to be set.');
		}
	}

	/**
	 * Get Hold Link
	 *
	 * The goal for this method is to return a URL to a "place hold" web page on
	 * the ILS OPAC. This is used for ILSs that do not support an API or method
	 * to place Holds.
	 *
	 * @param string $id      The id of the bib record
	 * @param array  $details Item details from getHoldings return array
	 *
	 * @return string         URL to ILS's OPAC's place hold screen.
	 */
	public function getHoldLink($id, $details)
	{
		return($details['ilslink']);
	}

        /**
         * Get Status of JSON Result
         */
        protected function getNewJSONStatus($id)
        {
	   // get daia json request for id and decode it
           $daia=json_decode($this->doHTTPRequest($id,"json"), true);
           $result = array();
	   if(array_key_exists("message",$daia)) {
	      // analyse the message for the error handling and debugging
           }
	   if(array_key_exists("instituion",$daia)) {
	      // information about the institution that grants or knows about services and their availability
           }
           if(array_key_exists("document",$daia)) {
	      // analyse the items 
	      $dummy_item = array("id"=>"0815",
                                  "availability"=>true,
                                  "status"=>"Available",
        		          "location"=>"physical location no HTML",
                                  "reserve"=>"N",
                                  "callnumber"=>"UB 007",
                                  "number"=>"1",
                                  "item_id"=>"0815",
                                  "barcode"=>"1");
              $result[]=$dummy_item;                          
	   }
	   return $result;
	}


	/**
	 * Get Status of JSON Result
	 */
	protected function getJSONStatus($id) 
	{
		// get daia json request for id and decode it
		$daia=json_decode($this->doHTTPRequest($id,"json"), true);
		$short = array();
		if(array_key_exists("document",$daia)) {
			foreach ($daia["document"] as $document) {
				$href = $document["href"];
				$number = 0;
				foreach ($document["item"] as $item) {
					$number++;
					$ishort = array();
					$status=null;
					$date=null;
					$limitation=null;
					$message=null;
					if(isset($item["label"])) {
						$callnumber = $item["label"];
					} else {
						$callnumber = null;
					}
					if(isset($item["storage"])) {
						$storage=$item["storage"]["content"];
					} else {
						$storage = null;
					}
					if(isset($item["limitation"])) {
						if(isset($item["limitation"]["content"])) {
							$limitation = $item["limitation"]["content"];
						}
					} else {
						$limitation = null;
					}
					if(isset($item["message"])) {
						if(isset($item["message"][0]["content"])) {
							$message = $item["message"][0]["content"];
						}
					} else {
						$message = null;
					}


					if(array_key_exists("available",$item)) {
						$loan = 0;
						$presentation = 0;

						foreach ($item["available"] as $available) {
							if ($available["service"] == "loan") {
								$loan=0;
							}
							if ($available["service"] == "presentation") {
								$presentation=0;
							}
						}
					}
					if(array_key_exists("unavailable",$item)) {
						foreach ($item["unavailable"] as $unavailable) {
							if ($unavailable["service"] == "loan") {
								$loan = 1;
							}
							if ($unavailable["service"] == "presentation") {
								if(isset($unavailable["expected"])) {
									$loan=2;
									$date=$unavailable["expected"];
								}
								$presentation=1;
							}
						}
					}
					if ($loan == 0 && $presentation == 0) {
						if ($limitation == 'restricted') {
							$status="order";
						} else {
							$status="loanable";
						}
					} else {
						if ($loan == 2) {
							$status="Checked Out";
						} else {
							if ($loan == 1 && $presentation == 0) {
								$status="present";
								//$href=null;
							} else {
								$status="unknown";
							}
						}
					}
					if ($loan == 0) {$ishort['availability']=true;}else{$ishort['availability']=false;}				  if ($status == 'present') {$ishort['availability']=true;}

					if ($status) {$ishort['status'] = $status;}
					if ($date) {$ishort['duedate'] = $date;}

					if ($storage) {
						$ishort['location'] = $storage;
					} else {$ishort['location'] ='';}
					if ($callnumber) {$ishort['callnumber'] = $callnumber;}
					if ($href && $status!='present') {$ishort['ilslink'] = $href;}
					$ishort['reserve'] = 'N';
					$ishort['id'] = $id;
					$ishort['number'] = strval($number);
					$ishort['item_id'] = $id;
					$ishort['barcode'] = 1;

					$short[]=$ishort;
				}
			} 
		}  
		return $short;
	}

	/**
	 * Get Status
	 *
	 * This is responsible for retrieving the status information of a certain
	 * record.
	 *
	 * @param string $id The record id to retrieve the holdings for
	 *
	 * @throws ILSException
	 * @return mixed     On success, an associative array with the following keys:
	 * id, availability (boolean), status, location, reserve, callnumber.
	 */
	public function getStatus($id)
	{
		$holding = $this->getJSONStatus($id);
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
	 * @throws ILSException
	 * @return array     An array of getStatus() return values on success.
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
	 * @throws \VuFind\Exception\Date
	 * @throws ILSException
	 * @return array         On success, an associative array with the following
	 * keys: id, availability (boolean), status, location, reserve, callnumber,
	 * duedate, number, barcode.
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
	 * @throws ILSException
	 * @return array     An array with the acquisitions data on success.
	 */
	public function getPurchaseHistory($id)
	{
		return array();
	}
}
