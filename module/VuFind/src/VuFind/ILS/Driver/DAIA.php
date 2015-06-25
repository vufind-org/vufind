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
     * Flag to enable multiple DAIA-queries
     *
     * @var boolean
     */
    protected $multiQuery = false;

    /**
     * DAIA legacySupport flag
     *
     * @var        boolean
     * @deprecated Will be removed in the next driver version
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
        if (isset($this->config['DAIA']['multiQuery'])) {
            $this->multiQuery = $this->config['DAIA']['multiQuery'];
        } else {
            $this->debug("No multiQuery setting found, using default: false");
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
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        if ($this->legacySupport) {
            // we are in legacySupport mode, so use the deprecated
            // getXMLStatus() method
            return $this->getXMLStatus($id);
        } else {
            // let's retrieve the DAIA document by URI
            $rawResult = $this->doHTTPRequest($this->generateURI($id));
            // extract the DAIA document for the current id from the
            // HTTPRequest's result
            $doc = $this->extractDaiaDoc($id, $rawResult);
            if (!is_null($doc)) {
                // parse the extracted DAIA document and return the status info
                return $this->parseDaiaDoc($id, $doc);
            }
        }
        return [];
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     * As the DAIA Query API supports querying multiple ids simultaneously
     * (all ids divided by "|") getStatuses(ids) would call getStatus(id) only
     * once, id containing the list of ids to be retrieved. Apart from the
     * legacySupport this would cause some trouble as the list of ids does not
     * necessarily correspond to the VuFind Record-id. Therefore getStatuses(ids)
     * has its own logic for multiQuery-support and performs the HTTPRequest
     * itself, retrieving one DAIA response for all ids and uses helper
     * functions to split this one response into documents corresponding to the
     * queried ids.
     * If multiQueries are not supported, getStatus(id) is used.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array    An array of status information values on success.
     */
    public function getStatuses($ids)
    {
        $status = [];

        if ($this->legacySupport) {
            // we are in legacySupport mode, so use the deprecated
            // getXMLStatus() method for each id
            foreach ($ids as $id) {
                $status[] = $this->getXMLShortStatus($id);
            }
        } else {
            if ($this->multiQuery) {
                // perform one DAIA query with multiple URIs
                $rawResult = $this->doHTTPRequest($this->generateMultiURIs($ids));
                // now we need to reestablish the key-value pair id=>document as
                // the id used in VuFind can differ from the document-URI
                // (depending on how the URI is generated)
                foreach ($ids as $id) {
                    // it is assumed that each DAIA document has a unique URI,
                    // so get the document with the corresponding id
                    $doc = $this->extractDaiaDoc($id, $rawResult);
                    if (!is_null($doc)) {
                        // a document with the corresponding id exists, which
                        // means we got status information for that record
                        $status[] = $this->parseDaiaDoc($id, $doc);
                    }
                    unset($doc);
                }
            } else {
                // multiQuery is not supported, so retrieve DAIA documents by
                // performing getStatus(id) for all ids
                foreach ($ids as $id) {
                    $status[] = $this->getStatus($id);
                }
            }
        }
        return $status;
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
            "Accept: " .  $contentTypes[$this->daiaResponseFormat],
        ];

        $params = [
            "id" => $id,
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

        // check if result matches daiaResponseFormat
        if (!preg_match(
            "/^" .
            str_replace("/", "\/", $contentTypes[$this->daiaResponseFormat]) .
            "(\s*)(\;.*)?/",
            strtolower($result->getHeaders()->get("ContentType")->getFieldValue())
        )) {
            throw new ILSException(
                "DAIA-ResponseFormat not supported. Received: " .
                $result->getHeaders()->get("ContentType")->getFieldValue() . " - " .
                "Expected: " . $contentTypes[$this->daiaResponseFormat]
            );
        }

        return ($result->getBody());
    }

    /**
     * Generate a DAIA URI necessary for the query
     *
     * @param string $id Id of the record whose DAIA document should be queried
     *
     * @return string     URI of the DAIA document
     *
     * @see http://gbv.github.io/daiaspec/daia.html#query-api
     */
    protected function generateURI($id)
    {
        if ($this->legacySupport) {
            return $id;
        } else {
            return $this->daiaIdPrefix . $id;
        }
    }

    /**
     * Combine several ids to DAIA Query API conform URIs
     *
     * @param array $ids Array of ids which shall be converted into URIs and
     *                  combined for querying multiple DAIA documents.
     *
     * @return string   Combined URIs (delimited by "|")
     *
     * @see http://gbv.github.io/daiaspec/daia.html#query-api
     */
    protected function generateMultiURIs($ids)
    {
        $multiURI = '';
        foreach ($ids as $id) {
            $multiURI .= $this->generateURI($id) . "|";
        }
        return rtrim($multiURI, "|");
    }

    /**
     * Parse a DAIA document depending on its type.
     *
     * Parse a DAIA document depending on its type and return a VuFind
     * compatible array of status information.
     * Supported types are:
     *      - array (for JSON results)
     *      - DOMNode (for XML results)
     *
     * @param string $id      Record Id corresponding to the DAIA document
     * @param mixed  $daiaDoc The DAIA document, supported types are array and
     *                        DOMNode
     *
     * @return array An array with status information for the record
     * @throws ILSException
     */
    protected function parseDaiaDoc($id, $daiaDoc)
    {
        if (is_array($daiaDoc)) {
            return $this->parseDaiaArray($id, $daiaDoc);
        } elseif (is_subclass_of($daiaDoc, "DOMNode")) {
            return $this->parseDaiaDom($id, $daiaDoc);
        } else {
            throw new ILSException(
                'Unsupported document type (did not match Array or DOMNode).'
            );
        }
    }

    /**
     * Extract a DAIA document identified by an id
     *
     * This method loops through all the existing DAIA document-elements in
     * the given DAIA response and returns the first document whose id matches
     * the given id.
     *
     * @param string $id           Record Id of the DAIA document in question.
     * @param string $daiaResponse Raw response from DAIA request.
     *
     * @return Array|DOMNode|null   The DAIA document identified by id and
     *                                  type depending on daiaResponseFormat.
     * @throws ILSException
     */
    protected function extractDaiaDoc($id, $daiaResponse)
    {

        if ($this->daiaResponseFormat == 'xml') {
            try {
                $docs = new DOMDocument();
                $docs->loadXML($daiaResponse);
                // get all the DAIA documents
                $doc = $docs->getElementsByTagName("document");
                if (!is_null($doc) && $this->multiQuery) {
                    // now loop through the found DAIA documents
                    for ($i = 0; $i < $doc->length; $i++) {
                        $attr = $doc->item($i)->attributes;
                        // DAIA documents should use URIs as value for id
                        $nodeValue = $attr->getNamedItem("id")->nodeValue;
                        if ($nodeValue == $this->generateURI($id)) {
                            // we've found the document element with the
                            // matching URI
                            return $doc->item($i);
                        }
                    }
                } elseif (!is_null($doc)) {
                    // as multiQuery is not enabled we can be sure that the
                    // DAIA response only contains one document.
                    return $doc->item(0);
                }
                // no (id matching) document element found
                return null;
            } catch (\Exception $e) {
                throw new ILSException($e->getMessage());
            }

        } elseif ($this->daiaResponseFormat == 'json') {
            $docs = json_decode($daiaResponse, true);
            // do DAIA documents exist?
            if (array_key_exists("document", $docs) && $this->multiQuery) {
                // now loop through the found DAIA documents
                foreach ($docs["document"] as $doc) {
                    // DAIA documents should use URIs as value for id
                    if (isset($doc["id"])
                        && $doc["id"] == $this->generateURI($id)
                    ) {
                        // we've found the document element with the matching URI
                        return $doc;
                    }
                }
            } elseif (array_key_exists("document", $docs)) {
                // since a document exists but multiQuery is disabled, the first
                // document is returned
                return array_shift($docs['document']);
            }
            // no (id matching) document element found
            return null;
        } else {
            throw new ILSException('Unsupported document format.');
        }
    }

    /**
     * Parse an array with DAIA status information.
     *
     * @param string $id        Record id for the DAIA array.
     * @param array  $daiaArray Array with raw DAIA status information.
     *
     * @return array            Array with VuFind compatible status information.
     */
    protected function parseDaiaArray($id, $daiaArray)
    {
        $doc_id = null;
        $doc_href = null;
        $doc_message = null;
        if (array_key_exists("id", $daiaArray)) {
            $doc_id = $daiaArray["id"];
        }
        if (array_key_exists("href", $daiaArray)) {
            // url of the document
            $doc_href = $daiaArray["href"];
        }
        if (array_key_exists("message", $daiaArray)) {
            // array of messages with language code and content
            $doc_message = $daiaArray["message"];
        }
        // if one or more items exist, iterate and build result-item
        if (array_key_exists("item", $daiaArray)) {
            $number = 0;
            foreach ($daiaArray["item"] as $item) {
                $result_item = [];
                $result_item["id"] = $id;
                $result_item["item_id"] = $item["id"];
                $result_item["ilslink"] = $doc_href;
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
                if (isset($item["storage"]["content"])) {
                    $result_item["location"] = $item["storage"]["content"];
                } else {
                    $result_item["location"] = "Unknown";
                }
                // status and availability will be calculated in own function
                $result_item = $this->calculateStatus($item) + $result_item;
                // add result_item to the result array
                $result[] = $result_item;
            } // end iteration on item
        }

        return $result;
    }

    /**
     * Parse a DOMNode Object with DAIA status information.
     *
     * @param string  $id      Record id for the DAIA array.
     * @param DOMNode $daiaDom DOMNode object with raw DAIA status information.
     *
     * @return array            Array with VuFind compatible status information.
     */
    protected function parseDaiaDom($id, $daiaDom)
    {
        $itemlist = $daiaDom->getElementsByTagName('item');
        $ilslink = '';
        if ($daiaDom->attributes->getNamedItem('href') !== null) {
            $ilslink = $daiaDom->attributes
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
                'number' => ($c + 1),
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

        return $status;
    }

    /**
     * Calculate Status and Availability of an item
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
        $status = ''; // status cannot be null as this will crash the translator
        $duedate = null;
        if (array_key_exists("available", $item)) {
            // check if item is loanable or presentation
            foreach ($item["available"] as $available) {
                // attribute service can be set once or not
                if (isset($available["service"])) {
                    if ($available["service"] == "loan") {
                        $availability = true;
                    }
                    if ($available["service"] == "presentation") {
                        $availability = true;
                    }
                }
            }
        }
        if (array_key_exists("unavailable", $item)) {
            foreach ($item["unavailable"] as $unavailable) {
                // attribute service can be set once or not
                if (isset($unavailable["service"])) {
                    if ($unavailable["service"] == "loan") {
                        $status = "dummy text";
                    }
                }
                // attribute expected is mandatory for unavailable element
                if (isset($unavailable["expected"])) {
                    $duedate = $unavailable["expected"];
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
     *
     * @deprecated Only kept for legacySupport
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
                    'number' => ($c + 1),
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
     *
     * @deprecated Only kept for legacySupport
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
                'number'        => ($c + 1),
                'presenceOnly'  => isset($presenceOnly) ? $presenceOnly : '',
            ];
        }
        return $holding;
    }
}
