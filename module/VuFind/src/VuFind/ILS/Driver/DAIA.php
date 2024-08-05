<?php

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 * Relaunch of the daia driver developed by Oliver Goldschmidt.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use DOMDocument;
use Laminas\Log\LoggerAwareInterface as LoggerAwareInterface;
use VuFind\Exception\ILS as ILSException;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface;

use function count;
use function in_array;
use function is_array;
use function strlen;

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class DAIA extends AbstractBase implements
    HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use \VuFind\Cache\CacheTrait {
        getCacheKey as protected getBaseCacheKey;
    }
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base URL for DAIA Service
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Timeout in seconds to be used for DAIA http requests
     *
     * @var string
     */
    protected $daiaTimeout = null;

    /**
     * Flag to switch on/off caching for DAIA items
     *
     * @var bool
     */
    protected $daiaCacheEnabled = false;

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
     * @var bool
     */
    protected $multiQuery = false;

    /**
     * Acceptable ContentTypes delivered by DAIA server in HTTP header
     *
     * @var array
     */
    protected $contentTypesResponse;

    /**
     * ContentTypes to use in DAIA HTTP requests in HTTP header
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
        // use DAIA specific timeout setting for http requests if configured
        if ((isset($this->config['DAIA']['timeout']))) {
            $this->daiaTimeout = $this->config['DAIA']['timeout'];
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
        if (isset($this->config['DAIA']['daiaCache'])) {
            $this->daiaCacheEnabled = $this->config['DAIA']['daiaCache'];
        } else {
            $this->debug('Caching not enabled, disabling it by default.');
        }
        if (
            isset($this->config['General'])
            && isset($this->config['General']['cacheLifetime'])
        ) {
            $this->cacheLifetime = $this->config['General']['cacheLifetime'];
        } else {
            $this->debug(
                'Cache lifetime not set, using VuFind\ILS\Driver\AbstractBase ' .
                'default value.'
            );
        }
    }

    /**
     * DAIA specific override of method to ensure uniform cache keys for cached
     * VuFind objects.
     *
     * @param string|null $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     *
     * @return string
     */
    protected function getCacheKey($suffix = null)
    {
        return $this->getBaseCacheKey(md5($this->baseUrl) . $suffix);
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = [])
    {
        return $this->config[$function] ?? false;
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
        return (isset($details['ilslink']) && $details['ilslink'] != '')
            ? $details['ilslink']
            : null;
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
        // check ids for existing availability data in cache and skip these ids
        if (
            $this->daiaCacheEnabled
            && $item = $this->getCachedData($this->generateURI($id))
        ) {
            if ($item != null) {
                return $item;
            }
        }

        // let's retrieve the DAIA document by URI
        try {
            $rawResult = $this->doHTTPRequest($this->generateURI($id));
            // extract the DAIA document for the current id from the
            // HTTPRequest's result
            $doc = $this->extractDaiaDoc($id, $rawResult);
            if (null !== $doc) {
                // parse the extracted DAIA document and return the status info
                $data = $this->parseDaiaDoc($id, $doc);
                // cache the status information
                if ($this->daiaCacheEnabled) {
                    $this->putCachedData($this->generateURI($id), $data);
                }
                return $data;
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

        // check cache for given ids and skip these ids if availability data is found
        foreach ($ids as $key => $id) {
            if (
                $this->daiaCacheEnabled
                && $item = $this->getCachedData($this->generateURI($id))
            ) {
                if ($item != null) {
                    $status[] = $item;
                    unset($ids[$key]);
                }
            }
        }

        // only query DAIA service if we have some ids left
        if (count($ids) > 0) {
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
                        if (null !== $doc) {
                            // a document with the corresponding id exists, which
                            // means we got status information for that record
                            $data = $this->parseDaiaDoc($id, $doc);
                            // cache the status information
                            if ($this->daiaCacheEnabled) {
                                $this->putCachedData($this->generateURI($id), $data);
                            }
                            $status[] = $data;
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
                        if (null !== $doc) {
                            // parse the extracted DAIA document and save the status
                            // info
                            $data = $this->parseDaiaDoc($id, $doc);
                            // cache the status information
                            if ($this->daiaCacheEnabled) {
                                $this->putCachedData($this->generateURI($id), $data);
                            }
                            $status[] = $data;
                        }
                    }
                }
            } catch (ILSException $e) {
                $this->debug($e->getMessage());
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
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
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
            'Accept: ' . $this->contentTypesRequest[$this->daiaResponseFormat],
        ];

        $params = [
            'id' => $id,
            'format' => $this->daiaResponseFormat,
        ];

        try {
            $result = $this->httpService->get(
                $this->baseUrl,
                $params,
                $this->daiaTimeout,
                $http_headers
            );
        } catch (\Exception $e) {
            $msg = 'HTTP request exited with Exception ' . $e->getMessage() .
                ' for record: ' . $id;
            $this->throwAsIlsException($e, $msg);
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
                [$responseMediaType] = array_pad(
                    explode(
                        ';',
                        $result->getHeaders()->get('Content-type')->getFieldValue(),
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

        return $result->getBody();
    }

    /**
     * Generate a DAIA URI necessary for the query
     *
     * @param string $id Id of the record whose DAIA document should be queried
     *
     * @return string     URI of the DAIA document
     *
     * @see http://gbv.github.io/daia/daia.html#query-parameters
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
     * @see http://gbv.github.io/daia/daia.html#query-parameters
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
                $this->throwAsIlsException($e);
            }
        } elseif ($this->daiaResponseFormat == 'json') {
            $docs = json_decode($daiaResponse, true);
        }

        if (count($docs)) {
            // check for error messages and write those to log
            if (isset($docs['message'])) {
                $this->logMessages($docs['message'], 'document');
            }

            // do DAIA documents exist?
            if (isset($docs['document']) && $this->multiQuery) {
                // now loop through the found DAIA documents
                foreach ($docs['document'] as $doc) {
                    // DAIA documents should use URIs as value for id
                    if (
                        isset($doc['id'])
                        && $doc['id'] == $this->generateURI($id)
                    ) {
                        // we've found the document element with the matching URI
                        // if the document has an item, then we return it
                        if (isset($doc['item'])) {
                            return $doc;
                        }
                    }
                }
            } elseif (isset($docs['document'])) {
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
                    if (
                        ($domNode->hasAttributes() && strlen($domNode->nodeValue))
                        || (in_array(
                            $domNode->nodeName,
                            ['storage', 'limitation', 'department', 'institution']
                        ) && strlen($domNode->nodeValue))
                    ) {
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
                'message',
            ];
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = $restructure($value);
                }
                if (
                    in_array($key, $elements, true)
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
        $result = [];
        $doc_id = null;
        $doc_href = null;
        if (isset($daiaArray['id'])) {
            $doc_id = $daiaArray['id'];
        }
        if (isset($daiaArray['href'])) {
            // url of the document (not needed for VuFind)
            $doc_href = $daiaArray['href'];
        }
        if (isset($daiaArray['message'])) {
            // log messages for debugging
            $this->logMessages($daiaArray['message'], 'document');
        }
        // if one or more items exist, iterate and build result-item
        if (isset($daiaArray['item']) && is_array($daiaArray['item'])) {
            $number = 0;
            foreach ($daiaArray['item'] as $item) {
                $result_item = [];
                $result_item['id'] = $id;
                // custom DAIA field
                $result_item['doc_id'] = $doc_id;
                $result_item['item_id'] = $item['id'];
                // custom DAIA field used in getHoldLink()
                $result_item['ilslink']
                    = ($item['href'] ?? $doc_href);
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
                $result_item['location'] = $this->getItemDepartment($item);
                // custom DAIA field
                $result_item['locationid'] = $this->getItemDepartmentId($item);
                // get location link
                $result_item['locationhref'] = $this->getItemDepartmentLink($item);
                // custom DAIA field
                $result_item['storage'] = $this->getItemStorage($item);
                // custom DAIA field
                $result_item['storageid'] = $this->getItemStorageId($item);
                // custom DAIA field
                $result_item['storagehref'] = $this->getItemStorageLink($item);
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
        $return = [];
        $availability = false;
        $duedate = null;
        $serviceLink = '';
        $queue = '';
        $item_notes = [];
        $item_limitation_types = [];
        $services = [];

        if (isset($item['available'])) {
            // check if item is loanable or presentation
            foreach ($item['available'] as $available) {
                if (
                    isset($available['service'])
                    && in_array($available['service'], ['loan', 'presentation'])
                ) {
                    $services['available'][] = $available['service'];
                }
                // attribute service can be set once or not
                if (
                    isset($available['service'])
                    && in_array(
                        $available['service'],
                        ['loan', 'presentation', 'openaccess']
                    )
                ) {
                    // set item available if service is loan, presentation or
                    // openaccess
                    $availability = true;
                    if (
                        $available['service'] == 'loan'
                        && isset($available['href'])
                    ) {
                        // save the link to the ils if we have a href for loan
                        // service
                        $serviceLink = $available['href'];
                    }
                }

                // use limitation element for status string
                if (isset($available['limitation'])) {
                    $item_notes = array_merge(
                        $item_notes,
                        $this->getItemLimitationContent($available['limitation'])
                    );
                    $item_limitation_types = array_merge(
                        $item_limitation_types,
                        $this->getItemLimitationTypes($available['limitation'])
                    );
                }

                // log messages for debugging
                if (isset($available['message'])) {
                    $this->logMessages($available['message'], 'item->available');
                }
            }
        }

        if (isset($item['unavailable'])) {
            foreach ($item['unavailable'] as $unavailable) {
                if (
                    isset($unavailable['service'])
                    && in_array($unavailable['service'], ['loan', 'presentation'])
                ) {
                    $services['unavailable'][] = $unavailable['service'];
                }
                // attribute service can be set once or not
                if (
                    isset($unavailable['service'])
                    && in_array(
                        $unavailable['service'],
                        ['loan', 'presentation', 'openaccess']
                    )
                ) {
                    if (
                        $unavailable['service'] == 'loan'
                        && isset($unavailable['href'])
                    ) {
                        //save the link to the ils if we have a href for loan service
                        $serviceLink = $unavailable['href'];
                    }

                    // use limitation element for status string
                    if (isset($unavailable['limitation'])) {
                        $item_notes = array_merge(
                            $item_notes,
                            $this->getItemLimitationContent(
                                $unavailable['limitation']
                            )
                        );
                        $item_limitation_types = array_merge(
                            $item_limitation_types,
                            $this->getItemLimitationTypes($unavailable['limitation'])
                        );
                    }
                }
                // attribute expected is mandatory for unavailable element
                if (!empty($unavailable['expected'])) {
                    try {
                        $duedate = $this->dateConverter
                            ->convertToDisplayDate(
                                'Y-m-d',
                                $unavailable['expected']
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

        /*'returnDate' => '', // false if not recently returned(?)*/

        if (!empty($serviceLink)) {
            $return['ilslink'] = $serviceLink;
        }

        $return['item_notes']      = $item_notes;
        $return['status']          = $this->getStatusString($item);
        $return['availability']    = $availability;
        $return['duedate']         = $duedate;
        $return['requests_placed'] = $queue;
        $return['services']        = $this->getAvailableItemServices($services);

        // In this DAIA driver implementation addLink and is_holdable are assumed
        // Boolean as patron based availability requires either a patron-id or -type.
        // This should be handled in a custom DAIA driver
        $return['addLink']     = $this->checkIsRecallable($item);
        $return['is_holdable'] = $this->checkIsRecallable($item);
        $return['holdtype']    = $this->getHoldType($item);

        // Check if we the item is available for storage retrieval request if it is
        // not holdable.
        $return['addStorageRetrievalRequestLink'] = !$return['is_holdable']
            ? $this->checkIsStorageRetrievalRequest($item) : false;

        // add a custom Field to allow passing custom DAIA data to the frontend in
        // order to use it for more precise display of availability
        $return['customData']      = $this->getCustomData($item);

        $return['limitation_types'] = $item_limitation_types;

        return $return;
    }

    /**
     * Helper function to allow custom data in status array.
     *
     * @param array $item Array with DAIA item data
     *
     * @return array
     */
    protected function getCustomData($item)
    {
        return [];
    }

    /**
     * Helper function to return an appropriate status string for current item.
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getStatusString($item)
    {
        // status cannot be null as this will crash the translator
        return '';
    }

    /**
     * Helper function to determine if item is recallable.
     * DAIA does not genuinly allow distinguishing between holdable and recallable
     * items. This could be achieved by usage of limitations but this would not be
     * shared functionality between different DAIA implementations (thus should be
     * implemented in custom drivers). Therefore this returns whether an item
     * is recallable based on unavailable services and the existence of an href.
     *
     * @param array $item Array with DAIA item data
     *
     * @return bool
     */
    protected function checkIsRecallable($item)
    {
        // This basic implementation checks the item for being unavailable for loan
        // and presentation but with an existing href (as a flag for further action).
        $services = ['available' => [], 'unavailable' => []];
        $href = false;
        if (isset($item['available'])) {
            // check if item is loanable or presentation
            foreach ($item['available'] as $available) {
                if (
                    isset($available['service'])
                    && in_array($available['service'], ['loan', 'presentation'])
                ) {
                    $services['available'][] = $available['service'];
                }
            }
        }

        if (isset($item['unavailable'])) {
            foreach ($item['unavailable'] as $unavailable) {
                if (
                    isset($unavailable['service'])
                    && in_array($unavailable['service'], ['loan', 'presentation'])
                ) {
                    $services['unavailable'][] = $unavailable['service'];
                    // attribute href is used to determine whether item is recallable
                    // or not
                    $href = isset($unavailable['href']) ? true : $href;
                }
            }
        }

        // Check if we have at least one service unavailable and a href field is set
        // (either as flag or as actual value for the next action).
        return $href && count(
            array_diff($services['unavailable'], $services['available'])
        );
    }

    /**
     * Helper function to determine if the item is available as storage retrieval.
     *
     * @param array $item Array with DAIA item data
     *
     * @return bool
     */
    protected function checkIsStorageRetrievalRequest($item)
    {
        // This basic implementation checks the item for being available for loan
        // and presentation but with an existing href (as a flag for further action).
        $services = ['available' => [], 'unavailable' => []];
        $href = false;
        if (isset($item['available'])) {
            // check if item is loanable or presentation
            foreach ($item['available'] as $available) {
                if (
                    isset($available['service'])
                    && in_array($available['service'], ['loan', 'presentation'])
                ) {
                    $services['available'][] = $available['service'];
                    // attribute href is used to determine whether item is
                    // requestable or not
                    $href = isset($available['href']) ? true : $href;
                }
            }
        }

        if (isset($item['unavailable'])) {
            foreach ($item['unavailable'] as $unavailable) {
                if (
                    isset($unavailable['service'])
                    && in_array($unavailable['service'], ['loan', 'presentation'])
                ) {
                    $services['unavailable'][] = $unavailable['service'];
                }
            }
        }

        // Check if we have at least one service unavailable and a href field is set
        // (either as flag or as actual value for the next action).
        return $href && count(
            array_diff($services['available'], $services['unavailable'])
        );
    }

    /**
     * Helper function to determine the holdtype available for current item.
     * DAIA does not genuinly allow distinguishing between holdable and recallable
     * items. This could be achieved by usage of limitations but this would not be
     * shared functionality between different DAIA implementations (thus should be
     * implemented in custom drivers). Therefore getHoldType always returns recall.
     *
     * @param array $item Array with DAIA item data
     *
     * @return string 'recall'|null
     */
    protected function getHoldType($item)
    {
        // return holdtype (hold, recall or block if patron is not allowed) for item
        return $this->checkIsRecallable($item) ? 'recall' : null;
    }

    /**
     * Returns the evaluated value of the provided limitation element
     *
     * @param array $limitations Array with DAIA limitation data
     *
     * @return array
     */
    protected function getItemLimitation($limitations)
    {
        $itemLimitation = [];
        foreach ($limitations as $limitation) {
            // return the first limitation with content set
            if (isset($limitation['content'])) {
                $itemLimitation[] = $limitation['content'];
            }
        }
        return $itemLimitation;
    }

    /**
     * Returns the value of item.department.content (e.g. to be used in VuFind
     * getStatus/getHolding array as location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemDepartment($item)
    {
        return isset($item['department']) && isset($item['department']['content'])
        && !empty($item['department']['content'])
            ? $item['department']['content']
            : 'Unknown';
    }

    /**
     * Returns the value of item.department.id (e.g. to be used in VuFind
     * getStatus/getHolding array as location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemDepartmentId($item)
    {
        return isset($item['department']) && isset($item['department']['id'])
            ? $item['department']['id'] : '';
    }

    /**
     * Returns the value of item.department.href (e.g. to be used in VuFind
     * getStatus/getHolding array for linking the location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemDepartmentLink($item)
    {
        return $item['department']['href'] ?? false;
    }

    /**
     * Returns the value of item.storage.content (e.g. to be used in VuFind
     * getStatus/getHolding array as location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemStorage($item)
    {
        return isset($item['storage']) && isset($item['storage']['content'])
        && !empty($item['storage']['content'])
            ? $item['storage']['content']
            : 'Unknown';
    }

    /**
     * Returns the value of item.storage.id (e.g. to be used in VuFind
     * getStatus/getHolding array as location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemStorageId($item)
    {
        return isset($item['storage']) && isset($item['storage']['id'])
            ? $item['storage']['id'] : '';
    }

    /**
     * Returns the value of item.storage.href (e.g. to be used in VuFind
     * getStatus/getHolding array for linking the location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemStorageLink($item)
    {
        return isset($item['storage']) && isset($item['storage']['href'])
            ? $item['storage']['href'] : '';
    }

    /**
     * Returns the evaluated values of the provided limitations element
     *
     * @param array $limitations Array with DAIA limitation data
     *
     * @return array
     */
    protected function getItemLimitationContent($limitations)
    {
        $itemLimitationContent = [];
        foreach ($limitations as $limitation) {
            // return the first limitation with content set
            if (isset($limitation['content'])) {
                $itemLimitationContent[] = $limitation['content'];
            }
        }
        return $itemLimitationContent;
    }

    /**
     * Returns the evaluated values of the provided limitations element
     *
     * @param array $limitations Array with DAIA limitation data
     *
     * @return array
     */
    protected function getItemLimitationTypes($limitations)
    {
        $itemLimitationTypes = [];
        foreach ($limitations as $limitation) {
            // return the first limitation with content set
            if (isset($limitation['id'])) {
                $itemLimitationTypes[] = $limitation['id'];
            }
        }
        return $itemLimitationTypes;
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
     * Returns the value for "location" in VuFind getStatus/getHolding array
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
        return isset($item['label']) && !empty($item['label'])
            ? $item['label']
            : 'Unknown';
    }

    /**
     * Returns the available services of the given set of available and unavailable
     * services
     *
     * @param array $services Array with DAIA services available/unavailable
     *
     * @return array
     */
    protected function getAvailableItemServices($services)
    {
        $availableServices = [];
        if (isset($services['available'])) {
            foreach ($services['available'] as $service) {
                if (
                    !isset($services['unavailable'])
                    || !in_array($service, $services['unavailable'])
                ) {
                    $availableServices[] = $service;
                }
            }
        }
        return array_intersect(['loan', 'presentation'], $availableServices);
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
                    'Message in DAIA response (' . (string)$context . '): ' .
                    $message['content']
                );
            }
        }
    }
}
