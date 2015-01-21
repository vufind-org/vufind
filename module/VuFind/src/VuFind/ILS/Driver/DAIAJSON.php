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
class DAIAJSON extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    /**
     * Daia URL
     *
     * @var string
     */
    protected $daiaurl;

    /**
     * Daia query identifier prefix
     *
     * @var string
     */
    protected $daiaidprefix;

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
     * @param string $id id for query in daia
     *
     * @return xml or json object 
     */
    protected function doHTTPRequest($id)
    {
        $http_headers = array(
        "Content-type: application/json",
        "Accept: application/json");
        $url = $this->daiaurl . "?id=" . $this->daiaidprefix . $id . "&format=json";
        $adapter = new CurlAdapter();

        try {
            $client = $this->httpService->createClient($url);
            $client->setHeaders($http_headers);
            $client->setMethod("GET");
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
        if (isset($this->config['Global']['daiaidprefix'])) {
            $this->daiaidprefix = $this->config['Global']['daiaidprefix'];
        } else {
            $this->daiaidprefix = "";
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
         *
         * This method gets a json result from the DAIA server and
         * analyses it. Than a vufind result is build.
         *
         * @param string $id The id of the bib record
         *
         * @return array()      of items
         */
        protected function getJSONStatus($id)
        {
        // get daia json request for id and decode it
           $daia=json_decode($this->doHTTPRequest($id, "json"), true);
           $result = array();
        if (array_key_exists("message", $daia)) {
            // analyse the message for the error handling and debugging
        }
        if (array_key_exists("instituion", $daia)) {
            // information about the institution that grants or 
            // knows about services and their availability
            // this fields could be analyzed: href, content, id
        }
        if (array_key_exists("document", $daia)) {
            // analyse the items 
            $dummy_item = array("id"=>"0815",
                               "availability"=>true,
                               "status"=>"Available",
                 "location"=>"physical location no HTML",
                               "reserve"=>"N",
                               "callnumber"=>"007",
                               "number"=>"1",
                               "item_id"=>"0815",
                               "barcode"=>"1");
            // each document may contain: id, href, message, item
            foreach ($daia["document"] as $document) {
                $doc_id=null;
                $doc_href=null;
                $doc_message=null;
                if (array_key_exists("id", $document)) {
                    $doc_id=$document["id"];
                }
                if (array_key_exists("href", $document)) {
                    // url of the document 
                    $doc_href=$document["href"];
                }
                if (array_key_exists("message", $document)) {
                    // array of messages with language code and content
                    $doc_message=$document["message"];
                }
                // if one or more items exist, iterate and build result-item
                if (array_key_exists("item", $document)) {
                    $number=0;
                    foreach ($document["item"] as $item) {
                        $result_item=array();
                        $result_item["id"]=$id;
                        $result_item["item_id"]=$id;
                        $number++; // count items
                        $result_item["number"]=$number;
                        // set default value for barcode
                        $result_item["barcode"]="1";
                        // set default value for reserve 
                        $result_item["reserve"]="N";
                        // get callnumber
                        if (isset($item["label"])) {
                            $result_item["callnumber"]=$item["label"];
                        } else {
                            $result_item["callnumber"]="Unknown";
                        }
                        // get location
                        if (isset($item["storage"])) {
                            $result_item["location"]=$item["storage"]["content"];
                        } else {
                            $result_item["location"]="Unknown";
                        }
                        // status and availability will be calculated in own function
                        $result_item=$this->calculateStatus($item)+$result_item;
                        // add result_item to the result array
                        $result[]=$result_item;
                    } // end iteration on item
                }
            } // end iteration on document
            // $result[]=$dummy_item;                          
        }
        return $result;
        }

        /**
        * Calaculate Status and Availability of an item
        *
        * If availability is false the string of status will be shown in vufind
        *
        * @param string $item json DAIA item
        *
        * @return array("status"=>"only for VIPs" ... )
        */
        protected function calculateStatus($item) 
        {
            $availability=false;
            $status=null;
            $duedate=null;
            if (array_key_exists("available", $item)) {
                // check if item is loanable or presentation
                foreach ($item["available"] as $available) {
                    if ($available["service"] == "loan") {
                        $availability=true;
                    }
                    if ($available["service"] == "presentation") {
                        $availability=true;
                    }
                }
            } 
            if (array_key_exists("unavailable", $item)) {
                foreach ($item["unavailable"] as $unavailable) {
                    if ($unavailable["service"] == "loan") {
                        if (isset($unavailable["expected"])) {
                            $duedate=$unavailable["expected"];
                        }
                        $status="dummy text";
                    }
                }
            }
            return (array("status"=>$status,
                          "availability"=>$availability,
                          "duedate"=>$duedate));
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
