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
     * Acceptable ContentTypes delivered by DAIA server in HTTP header
     *
     * @var array
     */
    protected $contentTypesResponse;

    /**
     * ContentTypes to use in PAIA HTTP requests
     * in HTTP header
     *
     * @var array
     */
    protected $contentTypesRequest = [
        'xml'  => 'application/xml',
        'json' => 'application/json',
    ];

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter)
    {
        $this->dateConverter = $converter;
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
        if (isset($this->config['DAIA']['baseUrl'])) {
            $this->baseUrl = $this->config['DAIA']['baseUrl'];
        } elseif (isset($this->config['Global']['baseUrl'])) {
            throw new ILSException(
                'Deprecated [Global] section in DAIA.ini present, but no [DAIA] ' .
                'section found: please update DAIA.ini (cf. config/vufind/DAIA.ini).'
            );
        } else {
            throw new ILSException('DAIA/baseUrl configuration needs to be set.');
        }
        if (isset($this->config['DAIA']['daiaResponseFormat'])) {
            $this->daiaResponseFormat = strtolower(
                $this->config['DAIA']['daiaResponseFormat']
            );
        } else {
            $this->debug('No daiaResponseFormat setting found, using default: xml');
            $this->daiaResponseFormat = 'xml';
        }
        if (isset($this->config['DAIA']['daiaIdPrefix'])) {
            $this->daiaIdPrefix = $this->config['DAIA']['daiaIdPrefix'];
        } else {
            $this->debug('No daiaIdPrefix setting found, using default: ppn:');
            $this->daiaIdPrefix = 'ppn:';
        }
        if (isset($this->config['DAIA']['multiQuery'])) {
            $this->multiQuery = $this->config['DAIA']['multiQuery'];
        } else {
            $this->debug('No multiQuery setting found, using default: false');
        }
        if (isset($this->config['DAIA']['daiaContentTypes'])) {
            $this->contentTypesResponse = $this->config['DAIA']['daiaContentTypes'];
        } else {
            $this->debug('No ContentTypes for response defined. Accepting any.');
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
        // let's retrieve the DAIA document by URI
        try {
            $rawResult = $this->doHTTPRequest($this->generateURI($id));
            // extract the DAIA document for the current id from the
            // HTTPRequest's result
            $doc = $this->extractDaiaDoc($id, $rawResult);
            if (!is_null($doc)) {
                // parse the extracted DAIA document and return the status info
                return $this->parseDaiaDoc($id, $doc);
            }
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
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
     * once, id containing the list of ids to be retrieved. This would cause some
     * trouble as the list of ids does not necessarily correspond to the VuFind
     * Record-id. Therefore getStatuses(ids) has its own logic for multiQuery-support
     * and performs the HTTPRequest itself, retrieving one DAIA response for all ids
     * and uses helper functions to split this one response into documents
     * corresponding to the queried ids.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array    An array of status information values on success.
     */
    public function getStatuses($ids)
    {
        $status = [];

        try {
            if ($this->multiQuery) {
                // perform one DAIA query with multiple URIs
                $rawResult = $this
                    ->doHTTPRequest($this->generateMultiURIs($ids));
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
                // multiQuery is not supported, so retrieve DAIA documents one by
                // one
                foreach ($ids as $id) {
                    $rawResult = $this->doHTTPRequest($this->generateURI($id));
                    // extract the DAIA document for the current id from the
                    // HTTPRequest's result
                    $doc = $this->extractDaiaDoc($id, $rawResult);
                    if (!is_null($doc)) {
                        // parse the extracted DAIA document and save the status
                        // info
                        $status[] = $this->parseDaiaDoc($id, $doc);
                    }
                }
            }
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
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
     * Support method to handle date uniformly
     *
     * @param string $date String representing a date
     *
     * @return string Formatted date
     */
    protected function convertDate($date)
    {
        try {
            return $this->dateConverter
                ->convertToDisplayDate('Y-m-d', $date);
        } catch (\Exception $e) {
            $this->debug('Date conversion failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Support method to handle datetime uniformly
     *
     * @param string $datetime String representing a datetime
     *
     * @return string Formatted datetime
     */
    protected function convertDatetime($datetime)
    {
        return $this->convertDate($datetime);
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
        $http_headers = [
            'Content-type: ' . $this->contentTypesRequest[$this->daiaResponseFormat],
            'Accept: ' .  $this->contentTypesRequest[$this->daiaResponseFormat],
        ];

        $params = [
            'id' => $id,
            'format' => $this->daiaResponseFormat,
        ];

        try {
            $result = $this->httpService->get(
                $this->baseUrl,
                $params, null, $http_headers
            );
        } catch (\Exception $e) {
            throw new ILSException(
                'HTTP request exited with Exception ' . $e->getMessage() .
                ' for record: ' . $id
            );
        }

        if (!$result->isSuccess()) {
            throw new ILSException(
                'HTTP status ' . $result->getStatusCode() .
                ' received, retrieving availability information for record: ' . $id
            );

        }

        // check if result matches daiaResponseFormat
        if ($this->contentTypesResponse != null) {
            if ($this->contentTypesResponse[$this->daiaResponseFormat]) {
                $contentTypesResponse = array_map(
                    'trim',
                    explode(
                        ',',
                        $this->contentTypesResponse[$this->daiaResponseFormat]
                    )
                );
                list($responseMediaType) = array_pad(
                    explode(
                        ';',
                        $result->getHeaders()->get('ContentType')->getFieldValue(),
                        2
                    ),
                    2,
                    null
                ); // workaround to avoid notices if encoding is not set in header
                if (!in_array(trim($responseMediaType), $contentTypesResponse)) {
                    throw new ILSException(
                        'DAIA-ResponseFormat not supported. Received: ' .
                        $responseMediaType . ' - ' .
                        'Expected: ' .
                        $this->contentTypesResponse[$this->daiaResponseFormat]
                    );
                }
            }
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
        return $this->daiaIdPrefix . $id;
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
            $multiURI .= $this->generateURI($id) . '|';
        }
        return rtrim($multiURI, '|');
    }

    /**
     * Parse a DAIA document depending on its type.
     *
     * Parse a DAIA document depending on its type and return a VuFind
     * compatible array of status information.
     * Supported types are:
     *      - array (for JSON results)
     *
     * @param string $id      Record Id corresponding to the DAIA document
     * @param mixed  $daiaDoc The DAIA document, only array is supported
     *
     * @return array An array with status information for the record
     * @throws ILSException
     */
    protected function parseDaiaDoc($id, $daiaDoc)
    {
        if (is_array($daiaDoc)) {
            return $this->parseDaiaArray($id, $daiaDoc);
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
        $docs = [];
        if ($this->daiaResponseFormat == 'xml') {
            try {
                $docs = $this->convertDaiaXmlToJson($daiaResponse);
            } catch (\Exception $e) {
                throw new ILSException($e->getMessage());
            }
        } elseif ($this->daiaResponseFormat == 'json') {
            $docs = json_decode($daiaResponse, true);
        }

        if (count($docs)) {
            // check for error messages and write those to log
            if (array_key_exists('message', $docs)) {
                $this->logMessages($docs['message'], 'document');
            }

            // do DAIA documents exist?
            if (array_key_exists('document', $docs) && $this->multiQuery) {
                // now loop through the found DAIA documents
                foreach ($docs['document'] as $doc) {
                    // DAIA documents should use URIs as value for id
                    if (isset($doc['id'])
                        && $doc['id'] == $this->generateURI($id)
                    ) {
                        // we've found the document element with the matching URI
                        // if the document has an item, then we return it
                        if (isset($doc['item'])) {
                            return $doc;
                        }
                    }
                }
            } elseif (array_key_exists('document', $docs)) {
                // since a document exists but multiQuery is disabled, the first
                // document is returned if it contains an item
                $doc = array_shift($docs['document']);
                if (isset($doc['item'])) {
                    return $doc;
                }
            }
            // no (id matching) document element found
            return null;
        } else {
            throw new ILSException('Unsupported document format.');
        }
    }

    /**
     * Converts a DAIA XML response to an array identical with a DAIA JSON response
     * for the sent query.
     *
     * @param string $daiaResponse Response in XML format from DAIA service
     *
     * @return mixed
     */
    protected function convertDaiaXmlToJson($daiaResponse)
    {
        $dom = new DOMDocument();
        $dom->loadXML($daiaResponse);

        // prepare DOMDocument as json_encode does not support save attributes if
        // elements have values (see http://stackoverflow.com/a/20506281/2115462)
        $prepare = function ($domNode) use (&$prepare) {
            foreach ($domNode->childNodes as $node) {
                if ($node->hasChildNodes()) {
                    $prepare($node);
                } else {
                    if (($domNode->hasAttributes() && strlen($domNode->nodeValue))
                        || (in_array(
                            $domNode->nodeName,
                            ['storage', 'limitation', 'department', 'institution']
                        ) && strlen($domNode->nodeValue))) {
                        if (trim($node->textContent)) {
                            $domNode->setAttribute('content', $node->textContent);
                            $node->nodeValue = '';
                        }
                    }
                }
            }
        };
        $prepare($dom);

        // now let json_encode/decode convert XML into an array
        $daiaArray = json_decode(
            json_encode(simplexml_load_string($dom->saveXML())),
            true
        );

        // merge @attributes fields in parent array
        $merge = function ($array) use (&$merge) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = $merge($value);
                }
                if ($key === '@attributes') {
                    $array = array_merge($array, $value);
                    unset($array[$key]);
                } else {
                    $array[$key] = $value;
                }
            }
            return $array;
        };
        $daiaArray = $merge($daiaArray);

        // restructure the array, moving single elements to their parent's index [0]
        $restructure = function ($array) use (&$restructure) {
            $elements = [
                'document', 'item', 'available', 'unavailable', 'limitation',
                'message'
            ];
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = $restructure($value);
                }
                if (in_array($key, $elements, true)
                    && !isset($array[$key][0])
                ) {
                    unset($array[$key]);
                    $array[$key][] = $value;
                } else {
                    $array[$key] = $value;
                }
            }
            return $array;
        };
        $daiaArray = $restructure($daiaArray);

        return $daiaArray;
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
        if (array_key_exists('id', $daiaArray)) {
            $doc_id = $daiaArray['id'];
        }
        if (array_key_exists('href', $daiaArray)) {
            // url of the document (not needed for VuFind)
            $doc_href = $daiaArray['href'];
        }
        if (array_key_exists('message', $daiaArray)) {
            // log messages for debugging
            $this->logMessages($daiaArray['message'], 'document');
        }
        // if one or more items exist, iterate and build result-item
        if (array_key_exists('item', $daiaArray)) {
            $number = 0;
            foreach ($daiaArray['item'] as $item) {
                $result_item = [];
                $result_item['id'] = $id;
                $result_item['item_id'] = $item['id'];
                // custom DAIA field used in getHoldLink()
                $result_item['ilslink']
                    = (isset($item['href']) ? $item['href'] : $doc_href);
                // count items
                $number++;
                $result_item['number'] = $this->getItemNumber($item, $number);
                // set default value for barcode
                $result_item['barcode'] = $this->getItemBarcode($item);
                // set default value for reserve
                $result_item['reserve'] = $this->getItemReserveStatus($item);
                // get callnumber
                $result_item['callnumber'] = $this->getItemCallnumber($item);
                // get location
                $result_item['location'] = $this->getItemLocation($item);
                // get location link
                $result_item['locationhref'] = $this->getItemLocationLink($item);
                // status and availability will be calculated in own function
                $result_item = $this->getItemStatus($item) + $result_item;
                // add result_item to the result array
                $result[] = $result_item;
            } // end iteration on item
        }

        return $result;
    }

    /**
     * Returns an array with status information for provided item.
     *
     * @param array $item Array with DAIA item data
     *
     * @return array
     */
    protected function getItemStatus($item)
    {
        $availability = false;
        $status = ''; // status cannot be null as this will crash the translator
        $duedate = null;
        $availableLink = '';
        $queue = '';
        if (array_key_exists('available', $item)) {
            if (count($item['available']) === 1) {
                $availability = true;
            } else {
                // check if item is loanable or presentation
                foreach ($item['available'] as $available) {
                    // attribute service can be set once or not
                    if (isset($available['service'])
                        && in_array(
                            $available['service'],
                            ['loan', 'presentation', 'openaccess']
                        )
                    ) {
                        // set item available if service is loan, presentation or
                        // openaccess
                        $availability = true;
                        if ($available['service'] == 'loan'
                            && isset($available['service']['href'])
                        ) {
                            // save the link to the ils if we have a href for loan
                            // service
                            $availableLink = $available['service']['href'];
                        }
                    }

                    // use limitation element for status string
                    if (isset($available['limitation'])) {
                        $status = $this->getItemLimitation($available['limitation']);
                    }

                    // log messages for debugging
                    if (isset($available['message'])) {
                        $this->logMessages($available['message'], 'item->available');
                    }
                }
            }
        }
        if (array_key_exists('unavailable', $item)) {
            foreach ($item['unavailable'] as $unavailable) {
                // attribute service can be set once or not
                if (isset($unavailable['service'])
                    && in_array(
                        $unavailable['service'],
                        ['loan', 'presentation', 'openaccess']
                    )
                ) {
                    if ($unavailable['service'] == 'loan'
                        && isset($unavailable['service']['href'])
                    ) {
                        //save the link to the ils if we have a href for loan service
                    }

                    // use limitation element for status string
                    if (isset($unavailable['limitation'])) {
                        $status = $this
                            ->getItemLimitation($unavailable['limitation']);
                    }
                }
                // attribute expected is mandatory for unavailable element
                if (isset($unavailable['expected'])) {
                    try {
                        $duedate = $this->dateConverter
                            ->convertToDisplayDate(
                                'Y-m-d', $unavailable['expected']
                            );
                    } catch (\Exception $e) {
                        $this->debug('Date conversion failed: ' . $e->getMessage());
                        $duedate = null;
                    }
                }

                // attribute queue can be set
                if (isset($unavailable['queue'])) {
                    $queue = $unavailable['queue'];
                }

                // log messages for debugging
                if (isset($unavailable['message'])) {
                    $this->logMessages($unavailable['message'], 'item->unavailable');
                }
            }
        }

        /*'availability' => '0',
        'status' => '',  // string - needs to be computed from availability info
        'duedate' => '', // if checked_out else null
        'returnDate' => '', // false if not recently returned(?)
        'requests_placed' => '', // total number of placed holds
        'is_holdable' => false, // place holding possible?*/

        if (!empty($availableLink)) {
            $return['ilslink'] = $availableLink;
        }

        $return['status']          = $status;
        $return['availability']    = $availability;
        $return['duedate']         = $duedate;
        $return['requests_placed'] = $queue;

        return $return;
    }

    /**
     * Returns the value for "number" in VuFind getStatus/getHolding array
     *
     * @param array $item    Array with DAIA item data
     * @param int   $counter Integer counting items as alternative return value
     *
     * @return mixed
     */
    protected function getItemNumber($item, $counter)
    {
        return $counter;
    }

    /**
     * Returns the value for "barcode" in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemBarcode($item)
    {
        return '1';
    }

    /**
     * Returns the value for "reserve" in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemReserveStatus($item)
    {
        return 'N';
    }

    /**
     * Returns the value for "callnumber" in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemCallnumber($item)
    {
        return array_key_exists('label', $item) && !empty($item['label'])
            ? $item['label']
            : 'Unknown';
    }

    /**
     * Returns the value for "location" in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemLocation($item)
    {
        if (isset($item['storage'])
            && array_key_exists('content', $item['storage'])
        ) {
            return $item['storage']['content'];
        } elseif (isset($item['department'])
            && array_key_exists('content', $item['department'])
        ) {
            return $item['department']['content'];
        }
        return 'Unknown';
    }

    /**
     * Returns the value for "location" href in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemLocationLink($item)
    {
        return isset($item['storage']['href'])
            ? $item['storage']['href'] : false;
    }

    /**
     * Returns the evaluated value of the provided limitation element
     *
     * @param array $limitations Array with DAIA limitation data
     *
     * @return string
     */
    protected function getItemLimitation($limitations)
    {
        foreach ($limitations as $limitation) {
            // return the first limitation with content set
            if (isset($limitation['content'])) {
                return $limitation['content'];
            }
        }
        return '';

    }

    /**
     * Logs content of message elements in DAIA response for debugging
     *
     * @param array  $messages Array with message elements to be logged
     * @param string $context  Description of current message context
     *
     * @return void
     */
    protected function logMessages($messages, $context)
    {
        foreach ($messages as $message) {
            if (isset($message['content'])) {
                $this->debug(
                    'Message in DAIA response (' . (string) $context . '): ' .
                    $message['content']
                );
            }
        }
    }
}
