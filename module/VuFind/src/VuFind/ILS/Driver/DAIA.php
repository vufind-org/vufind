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
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use DOMDocument, VuFind\Exception\ILS as ILSException,
    VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface,
    Zend\Log\LoggerAwareInterface as LoggerAwareInterface;

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class DAIA extends AbstractBase implements
   HttpServiceAwareInterface, LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base URL for DAIA Service
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * DAIA query identifier prefix
     *
     * @var string
     */
    protected $daiaIdPrefix;

    /**
     * DAIA response format
     *
     * @var string
     */
    protected $daiaResponseFormat;

    /**
     * DAIA legacySupport flag
     *
     * @var boolean
     */
    protected $legacySupport = false;

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
        // DAIA.ini sections changed, therefore move old [Global] section to
        // new [DAIA] section as fallback
        if (isset($this->config['Global']) && !isset($this->config['DAIA'])) {
            $this->config['DAIA'] = $this->config['Global'];
            $this->legacySupport = true;
        }

        if (isset($this->config['DAIA']['baseUrl'])) {
            $this->baseUrl = $this->config['DAIA']['baseUrl'];
        } else {
            throw new ILSException('DAIA/baseUrl configuration needs to be set.');
        }
        if (isset($this->config['DAIA']['daiaResponseFormat'])) {
            $this->daiaResponseFormat = strtolower(
                $this->config['DAIA']['daiaResponseFormat']
            );
        } else {
            $this->debug("No daiaResponseFormat setting found, using default: xml");
            $this->daiaResponseFormat = "xml";
        }
        if (isset($this->config['DAIA']['daiaIdPrefix'])) {
            $this->daiaIdPrefix = $this->config['DAIA']['daiaIdPrefix'];
        } else {
            $this->debug("No daiaIdPrefix setting found, using default: ppn:");
            $this->daiaIdPrefix = "ppn:";
        }
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        return isset($this->config[$function]) ? $this->config[$function] : false;
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($id, $details)
    {
        return ($details['ilslink'] != '') ? $details['ilslink'] : null;
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
        if ($this->daiaResponseFormat == 'xml') {
            return $this->getXMLStatus($id);
        } elseif ($this->daiaResponseFormat == 'json') {
            return $this->getJSONStatus($id);
        } else {
            throw new ILSException('No matching format found for status retrieval.');
        }
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
        $items = [];

        if ($this->daiaResponseFormat == 'xml') {
            foreach ($ids as $id) {
                $items[] = $this->getXMLShortStatus($id);
            }
        } elseif ($this->daiaResponseFormat == 'json') {
            foreach ($ids as $id) {
                $items[] = $this->getJSONStatus($id);
            }
        } else {
            throw new ILSException('No matching format found for status retrieval.');
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
        return [];
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $id id for query in daia
     *
     * @return xml or json object
     * @throws ILSException
     */
    protected function doHTTPRequest($id)
    {
        $contentTypes = [
            "xml"  => "application/xml",
            "json" => "application/json",
        ];

        $http_headers = [
            "Content-type: " . $contentTypes[$this->daiaResponseFormat],
            "Accept: " .  $contentTypes[$this->daiaResponseFormat]
        ];

        $params = [
            "id" => $this->daiaIdPrefix . $id,
            "format" => $this->daiaResponseFormat,
        ];

        try {
            if ($this->legacySupport) {
                // HttpRequest for DAIA legacy support as all
                // the parameters are contained in the baseUrl
                $result = $this->httpService->get(
                    $this->baseUrl . $id,
                    [], null, $http_headers
                );
            } else {
                $result = $this->httpService->get(
                    $this->baseUrl,
                    $params, null, $http_headers
                );
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            // throw ILSException disabled as this will be shown in VuFind-Frontend
            //throw new ILSException('HTTP error ' . $result->getStatusCode() .
            //                       ' retrieving status for record: ' . $id);
            // write to Debug instead
            $this->debug(
                'HTTP status ' . $result->getStatusCode() .
                ' received, retrieving availability information for record: ' . $id
            );

            // return false as DAIA request failed
            return false;
        }
        return ($result->getBody());

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
        $daia = json_decode($this->doHTTPRequest($id), true);
        $result = [];
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
            $dummy_item = ["id" => "0815",
                "availability" => true,
                "status" => "Available",
                "location" => "physical location no HTML",
                "reserve" => "N",
                "callnumber" => "007",
                "number" => "1",
                "item_id" => "0815",
                "barcode" => "1"];
            // each document may contain: id, href, message, item
            foreach ($daia["document"] as $document) {
                $doc_id = null;
                $doc_href = null;
                $doc_message = null;
                if (array_key_exists("id", $document)) {
                    $doc_id = $document["id"];
                }
                if (array_key_exists("href", $document)) {
                    // url of the document
                    $doc_href = $document["href"];
                }
                if (array_key_exists("message", $document)) {
                    // array of messages with language code and content
                    $doc_message = $document["message"];
                }
                // if one or more items exist, iterate and build result-item
                if (array_key_exists("item", $document)) {
                    $number = 0;
                    foreach ($document["item"] as $item) {
                        $result_item = [];
                        $result_item["id"] = $id;
                        $result_item["item_id"] = $id;
                        $number++; // count items
                        $result_item["number"] = $number;
                        // set default value for barcode
                        $result_item["barcode"] = "1";
                        // set default value for reserve
                        $result_item["reserve"] = "N";
                        // get callnumber
                        if (isset($item["label"])) {
                            $result_item["callnumber"] = $item["label"];
                        } else {
                            $result_item["callnumber"] = "Unknown";
                        }
                        // get location
                        if (isset($item["storage"])) {
                            $result_item["location"] = $item["storage"]["content"];
                        } else {
                            $result_item["location"] = "Unknown";
                        }
                        // status and availability will be calculated in own function
                        $result_item = $this->calculateStatus($item)+$result_item;
                        // add result_item to the result array
                        $result[] = $result_item;
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
        $availability = false;
        $status = null;
        $duedate = null;
        if (array_key_exists("available", $item)) {
            // check if item is loanable or presentation
            foreach ($item["available"] as $available) {
                if ($available["service"] == "loan") {
                    $availability = true;
                }
                if ($available["service"] == "presentation") {
                    $availability = true;
                }
            }
        }
        if (array_key_exists("unavailable", $item)) {
            foreach ($item["unavailable"] as $unavailable) {
                if ($unavailable["service"] == "loan") {
                    if (isset($unavailable["expected"])) {
                        $duedate = $unavailable["expected"];
                    }
                    $status = "dummy text";
                }
            }
        }
        return (["status" => $status,
            "availability" => $availability,
            "duedate" => $duedate]);
    }

    /**
     * Flatten a DAIA response to an array of holding information.
     *
     * @param string $id Document to look up.
     *
     * @return array
     */
    protected function getXMLStatus($id)
    {
        $daia = new DOMDocument();
        $response = $this->doHTTPRequest($id);
        if ($response) {
            $daia->loadXML($response);
        }
        // get Availability information from DAIA
        $documentlist = $daia->getElementsByTagName('document');

        // handle empty DAIA response
        if ($documentlist->length == 0
            && $daia->getElementsByTagName("message") != null
        ) {
            // analyse the message for the error handling and debugging
        }

        $status = [];
        for ($b = 0; $documentlist->item($b) !== null; $b++) {
            $itemlist = $documentlist->item($b)->getElementsByTagName('item');
            $ilslink = '';
            if ($documentlist->item($b)->attributes->getNamedItem('href') !== null) {
                $ilslink = $documentlist->item($b)->attributes
                    ->getNamedItem('href')->nodeValue;
            }
            $emptyResult = [
                    'callnumber' => '-',
                    'availability' => '0',
                    'number' => 1,
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 'No samples',
                    'status' => '',
                    'id' => $id,
                    'location' => '',
                    'ilslink' => $ilslink,
                    'label' => 'No samples'
            ];
            for ($c = 0; $itemlist->item($c) !== null; $c++) {
                $result = [
                    'callnumber' => '',
                    'availability' => '0',
                    'number' => ($c+1),
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 1,
                    'status' => '',
                    'id' => $id,
                    'item_id' => '',
                    'recallhref' => '',
                    'location' => '',
                    'location.id' => '',
                    'location.href' => '',
                    'label' => '',
                    'notes' => [],
                ];
                if ($itemlist->item($c)->attributes->getNamedItem('id') !== null) {
                    $result['item_id'] = $itemlist->item($c)->attributes
                        ->getNamedItem('id')->nodeValue;
                }
                if ($itemlist->item($c)->attributes->getNamedItem('href') !== null) {
                    $result['recallhref'] = $itemlist->item($c)->attributes
                        ->getNamedItem('href')->nodeValue;
                }
                $departmentElements = $itemlist->item($c)
                    ->getElementsByTagName('department');
                if ($departmentElements->length > 0) {
                    if ($departmentElements->item(0)->nodeValue) {
                        $result['location']
                            = $departmentElements->item(0)->nodeValue;
                        $result['location.id'] = $departmentElements
                            ->item(0)->attributes->getNamedItem('id')->nodeValue;
                        $result['location.href'] = $departmentElements
                            ->item(0)->attributes->getNamedItem('href')->nodeValue;
                    }
                }
                $storageElements
                    = $itemlist->item($c)->getElementsByTagName('storage');
                if ($storageElements->length > 0) {
                    if ($storageElements->item(0)->nodeValue) {
                        $result['location'] = $storageElements->item(0)->nodeValue;
                        //$result['location.id'] = $storageElements->item(0)
                        //  ->attributes->getNamedItem('id')->nodeValue;
                        $href = $storageElements->item(0)->attributes
                            ->getNamedItem('href');
                        if ($href !== null) {
                            //href attribute is recommended but not mandatory
                            $result['location.href'] = $storageElements->item(0)
                                ->attributes->getNamedItem('href')->nodeValue;
                        }
                        //$result['barcode'] = $result['location.id'];
                    }
                }
                $barcodeElements
                    = $itemlist->item($c)->getElementsByTagName('identifier');
                if ($barcodeElements->length > 0) {
                    if ($barcodeElements->item(0)->nodeValue) {
                        $result['barcode'] = $barcodeElements->item(0)->nodeValue;
                    }
                }
                $labelElements = $itemlist->item($c)->getElementsByTagName('label');
                if ($labelElements->length > 0) {
                    if ($labelElements->item(0)->nodeValue) {
                        $result['label'] = $labelElements->item(0)->nodeValue;
                        $result['callnumber']
                            = urldecode($labelElements->item(0)->nodeValue);
                    }
                }
                $messageElements
                    = $itemlist->item($c)->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    for ($m = 0; $messageElements->item($m) !== null; $m++) {
                        $errno = $messageElements->item($m)->attributes
                            ->getNamedItem('errno')->nodeValue;
                        if ($errno === '404') {
                            $result['status'] = 'missing';
                        } else if ($this->logger) {
                            $lang = $messageElements->item($m)->attributes
                                ->getNamedItem('lang')->nodeValue;
                            $logString = "[DAIA] message for {$lang}: "
                                . $messageElements->item($m)->nodeValue;
                            $this->debug($logString);
                        }
                    }
                }

                //$loanAvail = 0;
                //$loanExp = 0;
                //$presAvail = 0;
                //$presExp = 0;

                $unavailableElements = $itemlist->item($c)
                    ->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        $service = $unavailableElements->item($n)->attributes
                            ->getNamedItem('service');
                        $expectedNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('expected');
                        $queueNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('queue');
                        if ($service !== null) {
                            $service = $service->nodeValue;
                            if ($service === 'presentation') {
                                $result['presentation.availability'] = '0';
                                $result['presentation_availability'] = '0';
                                if ($expectedNode !== null) {
                                    $result['presentation.duedate']
                                        = $expectedNode->nodeValue;
                                }
                                if ($queueNode !== null) {
                                    $result['presentation.queue']
                                        = $queueNode->nodeValue;
                                }
                                $result['availability'] = '0';
                            } elseif ($service === 'loan') {
                                $result['loan.availability'] = '0';
                                $result['loan_availability'] = '0';
                                if ($expectedNode !== null) {
                                    $result['loan.duedate']
                                        = $expectedNode->nodeValue;
                                }
                                if ($queueNode !== null) {
                                    $result['loan.queue'] = $queueNode->nodeValue;
                                }
                                $result['availability'] = '0';
                            } elseif ($service === 'interloan') {
                                $result['interloan.availability'] = '0';
                                if ($expectedNode !== null) {
                                    $result['interloan.duedate']
                                        = $expectedNode->nodeValue;
                                }
                                if ($queueNode !== null) {
                                    $result['interloan.queue']
                                        = $queueNode->nodeValue;
                                }
                                $result['availability'] = '0';
                            } elseif ($service === 'openaccess') {
                                $result['openaccess.availability'] = '0';
                                if ($expectedNode !== null) {
                                    $result['openaccess.duedate']
                                        = $expectedNode->nodeValue;
                                }
                                if ($queueNode !== null) {
                                    $result['openaccess.queue']
                                        = $queueNode->nodeValue;
                                }
                                $result['availability'] = '0';
                            }
                        }
                        // TODO: message/limitation
                        if ($expectedNode !== null) {
                            $result['duedate'] = $expectedNode->nodeValue;
                        }
                        if ($queueNode !== null) {
                            $result['queue'] = $queueNode->nodeValue;
                        }
                    }
                }

                $availableElements = $itemlist->item($c)
                    ->getElementsByTagName('available');
                if ($availableElements->item(0) !== null) {
                    for ($n = 0; $availableElements->item($n) !== null; $n++) {
                        $service = $availableElements->item($n)->attributes
                            ->getNamedItem('service');
                        $delayNode = $availableElements->item($n)->attributes
                            ->getNamedItem('delay');
                        if ($service !== null) {
                            $service = $service->nodeValue;
                            if ($service === 'presentation') {
                                $result['presentation.availability'] = '1';
                                $result['presentation_availability'] = '1';
                                if ($delayNode !== null) {
                                    $result['presentation.delay']
                                        = $delayNode->nodeValue;
                                }
                                $result['availability'] = '1';
                            } elseif ($service === 'loan') {
                                $result['loan.availability'] = '1';
                                $result['loan_availability'] = '1';
                                if ($delayNode !== null) {
                                    $result['loan.delay'] = $delayNode->nodeValue;
                                }
                                $result['availability'] = '1';
                            } elseif ($service === 'interloan') {
                                $result['interloan.availability'] = '1';
                                if ($delayNode !== null) {
                                    $result['interloan.delay']
                                        = $delayNode->nodeValue;
                                }
                                $result['availability'] = '1';
                            } elseif ($service === 'openaccess') {
                                $result['openaccess.availability'] = '1';
                                if ($delayNode !== null) {
                                    $result['openaccess.delay']
                                        = $delayNode->nodeValue;
                                }
                                $result['availability'] = '1';
                            }
                        }
                        // TODO: message/limitation
                        if ($delayNode !== null) {
                            $result['delay'] = $delayNode->nodeValue;
                        }
                    }
                }
                // document has no availability elements, so set availability
                // and barcode to -1
                if ($availableElements->item(0) === null
                    && $unavailableElements->item(0) === null
                ) {
                    $result['availability'] = '-1';
                    $result['barcode'] = '-1';
                }
                $result['ilslink'] = $ilslink;
                $status[] = $result;
                /* $status = "available";
                if (loanAvail) return 0;
                if (presAvail) {
                    if (loanExp) return 1;
                    return 2;
                }
                if (loanExp) return 3;
                if (presExp) return 4;
                return 5;
                */
            }
            if (count($status) === 0) {
                $status[] = $emptyResult;
            }
        }
        return $status;
    }

    /**
     * Return an abbreviated set of status information.
     *
     * @param string $id The record id to retrieve the status for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number
     */
    public function getXMLShortStatus($id)
    {
        $daia = new DOMDocument();
        $response = $this->doHTTPRequest($id);
        if ($response) {
            $daia->loadXML($response);
        }
        // get Availability information from DAIA
        $itemlist = $daia->getElementsByTagName('item');
        $label = "Unknown";
        $storage = "Unknown";
        $presenceOnly = '1';
        $holding = [];
        for ($c = 0; $itemlist->item($c) !== null; $c++) {
            $earliest_href = '';
            $storageElements = $itemlist->item($c)->getElementsByTagName('storage');
            if ($storageElements->item(0) && $storageElements->item(0)->nodeValue) {
                if ($storageElements->item(0)->nodeValue === 'Internet') {
                    $href = $storageElements->item(0)->attributes
                        ->getNamedItem('href')->nodeValue;
                    $storage = '<a href="' . $href . '">' . $href . '</a>';
                } else {
                    $storage = $storageElements->item(0)->nodeValue;
                }
            }
            $labelElements = $itemlist->item($c)->getElementsByTagName('label');
            if ($labelElements->item(0)->nodeValue) {
                $label = $labelElements->item(0)->nodeValue;
            }
            $availableElements = $itemlist->item($c)
                ->getElementsByTagName('available');
            if ($availableElements->item(0) !== null) {
                $availability = 1;
                $status = 'Available';
                $href = $availableElements->item(0)->attributes
                    ->getNamedItem('href');
                if ($href !== null) {
                    $earliest_href = $href->nodeValue;
                }
                for ($n = 0; $availableElements->item($n) !== null; $n++) {
                    $svc = $availableElements->item($n)->getAttribute('service');
                    if ($svc === 'loan') {
                        $presenceOnly = '0';
                    }
                    // $status .= ' ' . $svc;
                }
            } else {
                $leanable = 1;
                $unavailableElements = $itemlist->item($c)
                    ->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    $earliest = [];
                    $queue = [];
                    $hrefs = [];
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        $unavailHref = $unavailableElements->item($n)->attributes
                            ->getNamedItem('href');
                        if ($unavailHref !== null) {
                            $hrefs['item' . $n] = $unavailHref->nodeValue;
                        }
                        $expectedNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('expected');
                        if ($expectedNode !== null) {
                            //$duedate = $expectedNode->nodeValue;
                            //$duedate_arr = explode('-', $duedate);
                            //$duedate_timestamp = mktime(
                            //    '0', '0', '0', $duedate_arr[1], $duedate_arr[2],
                            //    $duedate_arr[0]
                            //);
                            //array_push($earliest, array(
                            //    'expected' => $expectedNode->nodeValue,
                            //    'recall' => $unavailHref->nodeValue);
                            //array_push($earliest, $expectedNode->nodeValue);
                            $earliest['item' . $n] = $expectedNode->nodeValue;
                        } else {
                            array_push($earliest, "0");
                        }
                        $queueNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('queue');
                        if ($queueNode !== null) {
                            $queue['item' . $n] = $queueNode->nodeValue;
                        } else {
                            array_push($queue, "0");
                        }
                    }
                }
                if (count($earliest) > 0) {
                    arsort($earliest);
                    $earliest_counter = 0;
                    foreach ($earliest as $earliest_key => $earliest_value) {
                        if ($earliest_counter === 0) {
                            $earliest_duedate = $earliest_value;
                            $earliest_href = isset($hrefs[$earliest_key])
                                 ? $hrefs[$earliest_key] : '';
                            $earliest_queue = isset($queue[$earliest_key])
                                 ? $queue[$earliest_key] : '';
                        }
                        $earliest_counter = 1;
                    }
                } else {
                    $leanable = 0;
                }
                $messageElements = $itemlist->item($c)
                    ->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    $errno = $messageElements->item(0)->attributes
                        ->getNamedItem('errno')->nodeValue;
                    if ($errno === '404') {
                        $status = 'missing';
                    }
                }
                if (!isset($status)) {
                    $status = 'Unavailable';
                }
                $availability = 0;
            }
            $reserve = 'N';
            if (isset($earliest_queue) && $earliest_queue > 0) {
                $reserve = 'Y';
            }
            $holding[] = [
                'availability' => $availability,
                'id'            => $id,
                'status'        => isset($status) ? "$status" : '',
                'location'      => isset($storage) ? "$storage" : '',
                'reserve'       => isset($reserve) ? $reserve : '',
                'queue'         => isset($earliest_queue) ? $earliest_queue : '',
                'callnumber'    => isset($label) ? "$label" : '',
                'duedate'       => isset($earliest_duedate) ? $earliest_duedate : '',
                'leanable'      => isset($leanable) ? $leanable : '',
                'recallhref'    => isset($earliest_href) ? $earliest_href : '',
                'number'        => ($c+1),
                'presenceOnly'  => isset($presenceOnly) ? $presenceOnly : '',
            ];
        }
        return $holding;
    }
}
