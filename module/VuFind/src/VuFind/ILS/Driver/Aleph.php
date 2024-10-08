<?php

/**
 * Aleph ILS driver
 *
 * PHP version 8
 *
 * Copyright (C) UB/FU Berlin
 *
 * last update: 7.11.2007
 * tested with X-Server Aleph 18.1.
 *
 * TODO: login, course information, getNewItems, duedate in holdings,
 * https connection to x-server, ...
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
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use Laminas\I18n\Translator\TranslatorInterface;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function array_key_exists;
use function count;
use function in_array;
use function is_callable;
use function strlen;

/**
 * Aleph ILS driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Christoph Krempe <vufind-tech@lists.sourceforge.net>
 * @author   Alan Rykhus <vufind-tech@lists.sourceforge.net>
 * @author   Jason L. Cooper <vufind-tech@lists.sourceforge.net>
 * @author   Kun Lin <vufind-tech@lists.sourceforge.net>
 * @author   Vaclav Rosecky <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Aleph extends AbstractBase implements
    \Laminas\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    public const RECORD_ID_BASE_SEPARATOR = '-';

    /**
     * Translator object
     *
     * @var Aleph\Translator
     */
    protected $alephTranslator = false;

    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Translator
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;

    /**
     * The base URL, where the REST DLF API is running
     *
     * @var string
     */
    protected $dlfbaseurl = null;

    /**
     * Aleph server
     *
     * @var string
     */
    protected $host;

    /**
     * Bibliographic bases
     *
     * @var array
     */
    protected $bib;

    /**
     * User library
     *
     * @var string
     */
    protected $useradm;

    /**
     * Item library
     *
     * @var string
     */
    protected $admlib;

    /**
     * X server user name
     *
     * @var string
     */
    protected $wwwuser;

    /**
     * X server user password
     *
     * @var string
     */
    protected $wwwpasswd;

    /**
     * Is X server enabled?
     *
     * @var bool
     */
    protected $xserver_enabled;

    /**
     * X server port (defaults to 80)
     *
     * @var int
     */
    protected $xport;

    /**
     * DLF REST API port
     *
     * @var int
     */
    protected $dlfport;

    /**
     * Statuses considered as available
     *
     * @var array
     */
    protected $available_statuses;

    /**
     * List of patron hoe libraries
     *
     * @var array
     */
    protected $sublibadm;

    /**
     * If enabled and Xserver is disabled, slower RESTful API is used for
     * availability check.
     *
     * @var bool
     */
    protected $quick_availability;

    /**
     * Is debug mode enabled?
     *
     * @var bool
     */
    protected $debug_enabled;

    /**
     * Preferred pickup locations
     *
     * @var array
     */
    protected $preferredPickUpLocations;

    /**
     * Patron id used when no specific patron defined
     *
     * @var string
     */
    protected $defaultPatronId;

    /**
     * Mapping of z304 address elements in Aleph to getMyProfile attributes
     *
     * @var array
     */
    protected $addressMappings = null;

    /**
     * ISO 3166-1 alpha-2 to ISO 3166-1 alpha-3 mapping for
     * translation in REST DLF API.
     *
     * @var array
     */
    protected $languages = [];

    /**
     * Regex for extracting position in queue from status in holdings.
     *
     * @var string
     */
    protected $queuePositionRegex = '/Waiting in position '
        . '(?<position>[0-9]+) in queue;/';

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter
     * @param \VuFind\Cache\Manager  $cacheManager  Cache manager (optional)
     * @param TranslatorInterface    $translator    Translator (optional)
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        \VuFind\Cache\Manager $cacheManager = null,
        TranslatorInterface $translator = null
    ) {
        $this->dateConverter = $dateConverter;
        $this->cacheManager = $cacheManager;
        $this->translator = $translator;
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
        // Validate config
        $required = [
            'host', 'bib', 'useradm', 'admlib', 'dlfport', 'available_statuses',
        ];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }
        if (!isset($this->config['sublibadm'])) {
            throw new ILSException('Missing sublibadm config setting.');
        }

        // Process config
        $this->host = $this->config['Catalog']['host'];
        $this->bib = explode(',', $this->config['Catalog']['bib']);
        $this->useradm = $this->config['Catalog']['useradm'];
        $this->admlib = $this->config['Catalog']['admlib'];
        if (
            isset($this->config['Catalog']['wwwuser'])
            && isset($this->config['Catalog']['wwwpasswd'])
        ) {
            $this->wwwuser = $this->config['Catalog']['wwwuser'];
            $this->wwwpasswd = $this->config['Catalog']['wwwpasswd'];
            $this->xserver_enabled = true;
            $this->xport = $this->config['Catalog']['xport'] ?? 80;
        } else {
            $this->xserver_enabled = false;
        }
        $this->dlfport = $this->config['Catalog']['dlfport'];
        if (isset($this->config['Catalog']['dlfbaseurl'])) {
            $this->dlfbaseurl = $this->config['Catalog']['dlfbaseurl'];
        }
        $this->sublibadm = $this->config['sublibadm'];
        $this->available_statuses
            = explode(',', $this->config['Catalog']['available_statuses']);
        $this->quick_availability
            = $this->config['Catalog']['quick_availability'] ?? false;
        $this->debug_enabled = $this->config['Catalog']['debug'] ?? false;
        if (
            isset($this->config['util']['tab40'])
            && isset($this->config['util']['tab15'])
            && isset($this->config['util']['tab_sub_library'])
        ) {
            $cache = null;
            if (
                isset($this->config['Cache']['type'])
                && null !== $this->cacheManager
            ) {
                $cache = $this->cacheManager
                    ->getCache($this->config['Cache']['type']);
                $this->alephTranslator = $cache->getItem('alephTranslator');
            }
            if ($this->alephTranslator == false) {
                $this->alephTranslator = new Aleph\Translator($this->config);
                if (isset($cache)) {
                    $cache->setItem('alephTranslator', $this->alephTranslator);
                }
            }
        }
        if (isset($this->config['Catalog']['preferred_pick_up_locations'])) {
            $this->preferredPickUpLocations = explode(
                ',',
                $this->config['Catalog']['preferred_pick_up_locations']
            );
        }
        if (isset($this->config['Catalog']['default_patron_id'])) {
            $this->defaultPatronId = $this->config['Catalog']['default_patron_id'];
        }

        $this->addressMappings = $this->getDefaultAddressMappings();

        if (isset($this->config['AddressMappings'])) {
            foreach ($this->config['AddressMappings'] as $key => $val) {
                $this->addressMappings[$key] = $val;
            }
        }

        if (isset($this->config['Catalog']['queue_position_regex'])) {
            $this->queuePositionRegex
                = $this->config['Catalog']['queue_position_regex'];
        }

        if (isset($this->config['Languages'])) {
            foreach ($this->config['Languages'] as $locale => $lang) {
                $this->languages[$locale] = $lang;
            }
        }
    }

    /**
     * Return default mapping of z304 address elements in Aleph
     * to getMyProfile attributes.
     *
     * @return array
     */
    protected function getDefaultAddressMappings()
    {
        return [
            'fullname' => 'z304-address-1',
            'address1' => 'z304-address-2',
            'address2' => 'z304-address-3',
            'city'     => 'z304-address-4',
            'zip'      => 'z304-zip',
            'email'    => 'z304-email-address',
            'phone'    => 'z304-telephone-1',
        ];
    }

    /**
     * Perform an XServer request.
     *
     * @param string $op     Operation
     * @param array  $params Parameters
     * @param bool   $auth   Include authentication?
     *
     * @return \SimpleXMLElement
     */
    protected function doXRequest($op, $params, $auth = false)
    {
        if (!$this->xserver_enabled) {
            throw new \Exception(
                'Call to doXRequest without X-Server configuration in Aleph.ini'
            );
        }
        $url = "http://$this->host:$this->xport/X?op=$op";
        $url = $this->appendQueryString($url, $params);
        if ($auth) {
            $url = $this->appendQueryString(
                $url,
                [
                    'user_name' => $this->wwwuser,
                    'user_password' => $this->wwwpasswd,
                ]
            );
        }
        $result = $this->doHTTPRequest($url);
        if ($result->error) {
            if ($this->debug_enabled) {
                $this->debug(
                    "XServer error, URL is $url, error message: $result->error."
                );
            }
            throw new ILSException("XServer error: $result->error.");
        }
        return $result;
    }

    /**
     * Perform a RESTful DLF request.
     *
     * @param array  $path_elements URL path elements
     * @param array  $params        GET parameters (null for none)
     * @param string $method        HTTP method
     * @param string $body          HTTP body
     *
     * @return \SimpleXMLElement
     */
    protected function doRestDLFRequest(
        $path_elements,
        $params = null,
        $method = 'GET',
        $body = null
    ) {
        $path = implode('/', $path_elements);
        if ($this->dlfbaseurl === null) {
            $url = "http://$this->host:$this->dlfport/rest-dlf/" . $path;
        } else {
            $url = $this->dlfbaseurl . $path;
        }
        if ($params == null) {
            $params = [];
        }
        if (!empty($this->languages) && $this->translator != null) {
            $locale = $this->translator->getLocale();
            if (isset($this->languages[$locale])) {
                $params['lang'] = $this->languages[$locale];
            }
        }
        $url = $this->appendQueryString($url, $params);
        $result = $this->doHTTPRequest($url, $method, $body);
        $replyCode = (string)$result->{'reply-code'};
        if ($replyCode != '0000') {
            $replyText = (string)$result->{'reply-text'};
            $this->logError(
                'DLF request failed',
                [
                    'url' => $url, 'reply-code' => $replyCode,
                    'reply-message' => $replyText,
                ]
            );
            $ex = new Aleph\RestfulException($replyText, $replyCode);
            $ex->setXmlResponse($result);
            throw $ex;
        }
        return $result;
    }

    /**
     * Add values to an HTTP query string.
     *
     * @param string $url    URL so far
     * @param array  $params Parameters to add
     *
     * @return string
     */
    protected function appendQueryString($url, $params)
    {
        $sep = (!str_contains($url, '?')) ? '?' : '&';
        if ($params != null) {
            foreach ($params as $key => $value) {
                $url .= $sep . $key . '=' . urlencode($value);
                $sep = '&';
            }
        }
        return $url;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $url    URL of request
     * @param string $method HTTP method
     * @param string $body   HTTP body (null for none)
     *
     * @return \SimpleXMLElement
     */
    protected function doHTTPRequest($url, $method = 'GET', $body = null)
    {
        if ($this->debug_enabled) {
            $this->debug("URL: '$url'");
        }

        $result = null;
        try {
            $client = $this->httpService->createClient($url);
            $client->setMethod($method);
            if ($body != null) {
                $client->setRawBody($body);
            }
            $result = $client->send();
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }
        $answer = $result->getBody();
        if ($this->debug_enabled) {
            $this->debug("url: $url response: $answer");
        }
        $answer = str_replace('xmlns=', 'ns=', $answer);
        $result = @simplexml_load_string($answer);
        if (!$result) {
            if ($this->debug_enabled) {
                $this->debug("XML is not valid, URL: $url");
            }
            throw new ILSException(
                "XML is not valid, URL: $url method: $method answer: $answer."
            );
        }
        return $result;
    }

    /**
     * Convert an ID string into an array of bibliographic base and ID within
     * the base.
     *
     * @param string $id ID to parse.
     *
     * @return array
     */
    protected function parseId($id)
    {
        $result = null;
        if (str_contains($id, self::RECORD_ID_BASE_SEPARATOR)) {
            $result = explode(self::RECORD_ID_BASE_SEPARATOR, $id);
            $base = $result[0];
            if (!in_array($base, $this->bib)) {
                throw new \Exception("Unknown library base '$base'");
            }
        } elseif (count($this->bib) == 1) {
            $result = [$this->bib[0], $id];
        } else {
            throw new \Exception(
                "Invalid record identifier '$id' "
                . 'without library base'
            );
        }
        return $result;
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
        $statuses = $this->getHolding($id);
        foreach ($statuses as &$status) {
            $status['status']
                = ($status['availability'] == 1) ? 'available' : 'unavailable';
        }
        return $statuses;
    }

    /**
     * Support method for getStatuses -- load ID information from a particular
     * bibliographic library.
     *
     * @param string $bib Library to search
     * @param array  $ids IDs to search within library
     *
     * @return array
     *
     * Description of AVA tag:
     * http://igelu.org/wp-content/uploads/2011/09/Staff-vs-Public-Data-views.pdf
     * (page 28)
     *
     * a  ADM code - Institution Code
     * b  Sublibrary code - Library Code
     * c  Collection (first found) - Collection Code
     * d  Call number (first found)
     * e  Availability status  - If it is on loan (it has a Z36), if it is on hold
     *    shelf (it has  Z37=S) or if it has a processing status.
     * f  Number of items (for entire sublibrary)
     * g  Number of unavailable loans
     * h  Multi-volume flag (Y/N) If first Z30-ENUMERATION-A is not blank or 0, then
     *    the flag=Y, otherwise the flag=N.
     * i  Number of loans (for ranking/sorting)
     * j  Collection code
     */
    public function getStatusesX($bib, $ids)
    {
        $doc_nums = '';
        $sep = '';
        foreach ($ids as $id) {
            $doc_nums .= $sep . $id;
            $sep = ',';
        }
        $xml = $this->doXRequest(
            'publish_avail',
            ['library' => $bib, 'doc_num' => $doc_nums],
            false
        );
        $holding = [];
        foreach ($xml->xpath('/publish-avail/OAI-PMH') as $rec) {
            $identifier = $rec->xpath('.//identifier/text()');
            $id = ((count($this->bib) > 1) ? $bib . '-' : '')
                . substr($identifier[0], strrpos($identifier[0], ':') + 1);
            $temp = [];
            foreach ($rec->xpath(".//datafield[@tag='AVA']") as $datafield) {
                $status = $datafield->xpath('./subfield[@code="e"]/text()');
                $location = $datafield->xpath('./subfield[@code="a"]/text()');
                $signature = $datafield->xpath('./subfield[@code="d"]/text()');
                $availability
                    = ($status[0] == 'available' || $status[0] == 'check_holdings');
                $reserve = true;
                $temp[] = [
                    'id' => $id,
                    'availability' => $availability,
                    'status' => (string)$status[0],
                    'location' => (string)$location[0],
                    'signature' => (string)$signature[0],
                    'reserve' => $reserve,
                    'callnumber' => (string)$signature[0],
                ];
            }
            $holding[] = $temp;
        }
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        if (!$this->xserver_enabled) {
            if (!$this->quick_availability) {
                return [];
            }
            $result = [];
            foreach ($idList as $id) {
                $items = $this->getStatus($id);
                $result[] = $items;
            }
            return $result;
        }
        $ids = [];
        $holdings = [];
        foreach ($idList as $id) {
            [$bib, $sys_no] = $this->parseId($id);
            $ids[$bib][] = $sys_no;
        }
        foreach ($ids as $key => $values) {
            $holds = $this->getStatusesX($key, $values);
            foreach ($holds as $hold) {
                $holdings[] = $hold;
            }
        }
        return $holdings;
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
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        $holding = [];
        [$bib, $sys_no] = $this->parseId($id);
        $resource = $bib . $sys_no;
        $params = ['view' => 'full'];
        if (!empty($patron['id'])) {
            $params['patron'] = $patron['id'];
        } elseif (isset($this->defaultPatronId)) {
            $params['patron'] = $this->defaultPatronId;
        }
        $xml = $this->doRestDLFRequest(['record', $resource, 'items'], $params);
        if (!empty($xml->{'items'})) {
            $items = $xml->{'items'}->{'item'};
        } else {
            $items = [];
        }
        foreach ($items as $item) {
            $item_status         = (string)$item->{'z30-item-status-code'}; // $isc
            // $ipsc:
            $item_process_status = (string)$item->{'z30-item-process-status-code'};
            $sub_library_code    = (string)$item->{'z30-sub-library-code'}; // $slc
            $z30 = $item->z30;
            if ($this->alephTranslator) {
                $item_status = $this->alephTranslator->tab15Translate(
                    $sub_library_code,
                    $item_status,
                    $item_process_status
                );
            } else {
                $item_status = [
                    'opac'         => 'Y',
                    'request'      => 'C',
                    'desc'         => (string)$z30->{'z30-item-status'},
                    'sub_lib_desc' => (string)$z30->{'z30-sub-library'},
                ];
            }
            if ($item_status['opac'] != 'Y') {
                continue;
            }
            $availability = false;
            //$reserve = ($item_status['request'] == 'C')?'N':'Y';
            $collection = (string)$z30->{'z30-collection'};
            $collection_desc = ['desc' => $collection];
            if ($this->alephTranslator) {
                $collection_code = (string)$item->{'z30-collection-code'};
                $collection_desc = $this->alephTranslator->tab40Translate(
                    $collection_code,
                    $sub_library_code
                );
            }
            $requested = false;
            $duedate = '';
            $addLink = false;
            $status = (string)$item->{'status'};
            if (in_array($status, $this->available_statuses)) {
                $availability = true;
            }
            if ($item_status['request'] == 'Y' && $availability == false) {
                $addLink = true;
            }
            if (!empty($patron)) {
                $hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
                $addLink = ($hold_request[0] == 'Y');
            }
            $matches = [];
            $dueDateWithStatusRegEx
                = '/([0-9]*\\/[a-zA-Z0-9]*\\/[0-9]*);([a-zA-Z ]*)/';
            $dueDateRegEx = '/([0-9]*\\/[a-zA-Z0-9]*\\/[0-9]*)/';
            if (preg_match($dueDateWithStatusRegEx, $status, $matches)) {
                $duedate = $this->parseDate($matches[1]);
                $requested = (trim($matches[2]) == 'Requested');
            } elseif (preg_match($dueDateRegEx, $status, $matches)) {
                $duedate = $this->parseDate($matches[1]);
            } else {
                $duedate = null;
            }
            $item_id = $item->attributes()->href;
            $item_id = substr($item_id, strrpos($item_id, '/') + 1);
            $note    = (string)$z30->{'z30-note-opac'};
            $holding[] = [
                'id'                => $id,
                'item_id'           => $item_id,
                'availability'      => $availability,
                'status'            => (string)$item_status['desc'],
                'location'          => $sub_library_code,
                'reserve'           => 'N',
                'callnumber'        => (string)$z30->{'z30-call-no'},
                'duedate'           => (string)$duedate,
                'number'            => (string)$z30->{'z30-inventory-number'},
                'barcode'           => (string)$z30->{'z30-barcode'},
                'description'       => (string)$z30->{'z30-description'},
                'notes'             => ($note == null) ? null : [$note],
                'is_holdable'       => true,
                'addLink'           => $addLink,
                'holdtype'          => 'hold',
                /* below are optional attributes*/
                'collection'        => (string)$collection,
                'collection_desc'   => (string)$collection_desc['desc'],
                'callnumber_second' => (string)$z30->{'z30-call-no-2'},
                'sub_lib_desc'      => (string)$item_status['sub_lib_desc'],
                'no_of_loans'       => (string)$z30->{'$no_of_loans'},
                'requested'         => (string)$requested,
            ];
        }
        return $holding;
    }

    /**
     * Get Patron Loan History
     *
     * @param array $user   The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array      Array of the patron's historic loans on success.
     */
    public function getMyTransactionHistory($user, $params = null)
    {
        return $this->getMyTransactions($user, $params, true);
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array   $user    The patron array from patronLogin
     * @param array   $params  Parameters
     * @param boolean $history History
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user, $params = [], $history = false)
    {
        $userId = $user['id'];

        $alephParams = [];
        if ($history) {
            $alephParams['type'] = 'history';
        }

        // total count without details is fast
        $totalCount = count(
            $this->doRestDLFRequest(
                ['patron', $userId, 'circulationActions', 'loans'],
                $alephParams
            )->xpath('//loan')
        );

        // with full details and paging
        $pageSize = $params['limit'] ?? 50;
        $itemsNoKey = $history ? 'no_loans' : 'noItems';
        $alephParams += [
            'view' => 'full',
            'startPos' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            $itemsNoKey => $pageSize,
        ];

        $xml = $this->doRestDLFRequest(
            ['patron', $userId, 'circulationActions', 'loans'],
            $alephParams
        );

        $transList = [];
        foreach ($xml->xpath('//loan') as $item) {
            $z36 = ($history) ? $item->z36h : $item->z36;
            $prefix = ($history) ? 'z36h-' : 'z36-';
            $z13 = $item->z13;
            $z30 = $item->z30;
            $group = $item->xpath('@href');
            $group = substr(strrchr($group[0], '/'), 1);
            $renew = $item->xpath('@renew');

            $location = (string)$z36->{$prefix . 'pickup_location'};
            $reqnum = (string)$z36->{$prefix . 'doc-number'}
                . (string)$z36->{$prefix . 'item-sequence'}
                . (string)$z36->{$prefix . 'sequence'};

            $due = (string)$z36->{$prefix . 'due-date'};
            $title = (string)$z13->{'z13-title'};
            $author = (string)$z13->{'z13-author'};
            $isbn = (string)$z13->{'z13-isbn-issn'};
            $barcode = (string)$z30->{'z30-barcode'};
            // Secondary, Aleph-specific identifier that may be useful for
            // local customizations
            $adm_id = (string)$z30->{'z30-doc-number'};

            $transaction = [
                'id' => $this->barcodeToID($barcode),
                'adm_id'   => $adm_id,
                'item_id' => $group,
                'location' => $location,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'reqnum' => $reqnum,
                'barcode' => $barcode,
                'duedate' => $this->parseDate($due),
                'renewable' => $renew[0] == 'Y',
            ];
            if ($history) {
                $issued = (string)$z36->{$prefix . 'loan-date'};
                $returned = (string)$z36->{$prefix . 'returned-date'};
                $transaction['checkoutDate'] = $this->parseDate($issued);
                $transaction['returnDate'] = $this->parseDate($returned);
            }
            $transList[] = $transaction;
        }

        $key = ($history) ? 'transactions' : 'records';

        return [
            'count' => $totalCount,
            $key => $transList,
        ];
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Voyager requires the patron details and an item
     * id. This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($details)
    {
        return $details['item_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items. The data in
     * $details['details'] is determined by getRenewDetails().
     *
     * @param array $details An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($details)
    {
        $patron = $details['patron'];
        $result = [];
        foreach ($details['details'] as $id) {
            try {
                $xml = $this->doRestDLFRequest(
                    [
                        'patron', $patron['id'], 'circulationActions', 'loans', $id,
                    ],
                    null,
                    'POST',
                    null
                );
                $due = (string)current($xml->xpath('//new-due-date'));
                $result[$id] = [
                    'success' => true, 'new_date' => $this->parseDate($due),
                ];
            } catch (Aleph\RestfulException $ex) {
                $result[$id] = [
                    'success' => false, 'sysMessage' => $ex->getMessage(),
                ];
            }
        }
        return ['blocks' => false, 'details' => $result];
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array      Array of the patron's holds on success.
     */
    public function getMyHolds($user)
    {
        $userId = $user['id'];
        $holdList = [];
        $xml = $this->doRestDLFRequest(
            ['patron', $userId, 'circulationActions', 'requests', 'holds'],
            ['view' => 'full']
        );
        foreach ($xml->xpath('//hold-request') as $item) {
            $z37 = $item->z37;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $delete = $item->xpath('@delete');
            $href = $item->xpath('@href');
            $item_id = substr($href[0], strrpos($href[0], '/') + 1);
            $type = 'hold';
            $location = (string)$z37->{'z37-pickup-location'};
            $reqnum = (string)$z37->{'z37-doc-number'}
                . (string)$z37->{'z37-item-sequence'}
                . (string)$z37->{'z37-sequence'};
            $expire = (string)$z37->{'z37-end-request-date'};
            $create = (string)$z37->{'z37-open-date'};
            $holddate = (string)$z37->{'z37-hold-date'};
            $title = (string)$z13->{'z13-title'};
            $author = (string)$z13->{'z13-author'};
            $isbn = (string)$z13->{'z13-isbn-issn'};
            $barcode = (string)$z30->{'z30-barcode'};
            // remove superfluous spaces in status
            $status = preg_replace("/\s[\s]+/", ' ', $item->status);
            $position = null;
            // Extract position in the hold queue from item status
            if (preg_match($this->queuePositionRegex, $status, $matches)) {
                $position = $matches['position'];
            }
            if ($holddate == '00000000') {
                $holddate = null;
            } else {
                $holddate = $this->parseDate($holddate);
            }
            $delete = ($delete[0] == 'Y');
            // Secondary, Aleph-specific identifier that may be useful for
            // local customizations
            $adm_id = (string)$z30->{'z30-doc-number'};

            $holdList[] = [
                'type' => $type,
                'item_id' => $item_id,
                'adm_id'   => $adm_id,
                'location' => $location,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'reqnum' => $reqnum,
                'barcode' => $barcode,
                'id' => $this->barcodeToID($barcode),
                'expire' => $this->parseDate($expire),
                'holddate' => $holddate,
                'delete' => $delete,
                'create' => $this->parseDate($create),
                'status' => $status,
                'position' => $position,
            ];
        }
        return $holdList;
    }

    /**
     * Get Cancel Hold Details
     *
     * @param array $holdDetails A single hold array from getMyHolds
     * @param array $patron      Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelHoldDetails($holdDetails, $patron = [])
    {
        if ($holdDetails['delete']) {
            return $holdDetails['item_id'];
        } else {
            return '';
        }
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $details An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($details)
    {
        $patron = $details['patron'];
        $patronId = $patron['id'];
        $count = 0;
        $items = [];
        foreach ($details['details'] as $id) {
            try {
                $result = $this->doRestDLFRequest(
                    [
                        'patron', $patronId, 'circulationActions', 'requests',
                        'holds', $id,
                    ],
                    null,
                    'DELETE'
                );
                $count++;
                $items[$id] = ['success' => true, 'status' => 'cancel_hold_ok'];
            } catch (Aleph\RestfulException $e) {
                $items[$id] = [
                    'success' => false,
                    'status' => 'cancel_hold_failed',
                    'sysMessage' => $e->getMessage(),
                ];
            }
        }
        return ['count' => $count, 'items' => $items];
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return mixed      Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $finesList = [];

        $xml = $this->doRestDLFRequest(
            ['patron', $user['id'], 'circulationActions', 'cash'],
            ['view' => 'full']
        );

        foreach ($xml->xpath('//cash') as $item) {
            $z31 = $item->z31;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $title = (string)$z13->{'z13-title'};
            $description = (string)$z31->{'z31-description'};
            $transactiondate = date('d-m-Y', strtotime((string)$z31->{'z31-date'}));
            $transactiontype = (string)$z31->{'z31-credit-debit'};
            $id = (string)$z13->{'z13-doc-number'};
            $barcode = (string)$z30->{'z30-barcode'};
            $checkout = (string)$z31->{'z31-date'};
            $id = $this->barcodeToID($barcode);
            $cachetype = strtolower((string)($item->attributes()->type ?? ''));
            $mult = $cachetype == 'debit' ? -100 : 100;
            $amount
                = (float)(preg_replace("/[\(\)]/", '', (string)$z31->{'z31-sum'}))
                * $mult;
            $cashref = (string)$z31->{'z31-sequence'};

            $finesList["$cashref"]  = [
                    'title'   => $title,
                    'barcode' => $barcode,
                    'amount' => $amount,
                    'transactiondate' => $transactiondate,
                    'transactiontype' => $transactiontype,
                    'checkout' => $this->parseDate($checkout),
                    'balance'  => $amount,
                    'id'  => $id,
                    'printLink' => 'test',
                    'fine' => $description,
            ];
        }
        ksort($finesList);
        return array_values($finesList);
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfile($user)
    {
        if ($this->xserver_enabled) {
            $profile = $this->getMyProfileX($user);
        } else {
            $profile = $this->getMyProfileDLF($user);
        }
        $profile['cat_username'] ??= $user['id'];
        return $profile;
    }

    /**
     * Get profile information using X-server.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfileX($user)
    {
        if (!isset($user['college'])) {
            $user['college'] = $this->useradm;
        }
        $xml = $this->doXRequest(
            'bor-info',
            [
                'loans' => 'N', 'cash' => 'N', 'hold' => 'N',
                'library' => $user['college'], 'bor_id' => $user['id'],
            ],
            true
        );
        $id = (string)$xml->z303->{'z303-id'};
        $address1 = (string)$xml->z304->{'z304-address-2'};
        $address2 = (string)$xml->z304->{'z304-address-3'};
        $zip = (string)$xml->z304->{'z304-zip'};
        $phone = (string)$xml->z304->{'z304-telephone'};
        $barcode = (string)$xml->z304->{'z304-address-0'};
        $group = (string)$xml->z305->{'z305-bor-status'};
        $expiry = (string)$xml->z305->{'z305-expiry-date'};
        $credit_sum = (string)$xml->z305->{'z305-sum'};
        $credit_sign = (string)$xml->z305->{'z305-credit-debit'};
        $name = (string)$xml->z303->{'z303-name'};
        if (strstr($name, ',')) {
            [$lastname, $firstname] = explode(',', $name);
        } else {
            $lastname = $name;
            $firstname = '';
        }
        if ($credit_sign == null) {
            $credit_sign = 'C';
        }
        $recordList = compact('firstname', 'lastname');
        if (isset($user['email'])) {
            $recordList['email'] = $user['email'];
        }
        $recordList['address1'] = $address1;
        $recordList['address2'] = $address2;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['group'] = $group;
        $recordList['barcode'] = $barcode;
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['credit'] = $expiry;
        $recordList['credit_sum'] = $credit_sum;
        $recordList['credit_sign'] = $credit_sign;
        $recordList['id'] = $id;
        return $recordList;
    }

    /**
     * Get profile information using DLF service.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfileDLF($user)
    {
        $recordList = [];
        $xml = $this->doRestDLFRequest(
            ['patron', $user['id'], 'patronInformation', 'address']
        );
        $profile = [];
        $profile['id'] = $user['id'];
        $profile['cat_username'] = $user['id'];
        $address = $xml->xpath('//address-information')[0];
        foreach ($this->addressMappings as $key => $value) {
            if (!empty($value)) {
                $profile[$key] = (string)$address->{$value};
            }
        }
        $fullName = $profile['fullname'];
        if (!str_contains($fullName, ',')) {
            $profile['lastname'] = $fullName;
            $profile['firstname'] = '';
        } else {
            [$profile['lastname'], $profile['firstname']]
                = explode(',', $fullName);
        }
        $xml = $this->doRestDLFRequest(
            ['patron', $user['id'], 'patronStatus', 'registration']
        );
        $status = $xml->xpath('//institution/z305-bor-status');
        $expiry = $xml->xpath('//institution/z305-expiry-date');
        $profile['expiration_date'] = $this->parseDate($expiry[0]);
        $profile['group'] = $status[0];
        return $profile;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $user     The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($user, $password)
    {
        if ($password == null) {
            $temp = ['id' => $user];
            $temp['college'] = $this->useradm;
            return $this->getMyProfile($temp);
        }
        try {
            $xml = $this->doXRequest(
                'bor-auth',
                [
                    'library' => $this->useradm, 'bor_id' => $user,
                    'verification' => $password,
                ],
                true
            );
        } catch (\Exception $ex) {
            if (str_contains($ex->getMessage(), 'Error in Verification')) {
                return null;
            }
            $this->throwAsIlsException($ex);
        }
        $patron = [];
        $name = $xml->z303->{'z303-name'};
        if (strstr($name, ',')) {
            [$lastName, $firstName] = explode(',', $name);
        } else {
            $lastName = $name;
            $firstName = '';
        }
        $email_addr = $xml->z304->{'z304-email-address'};
        $id = $xml->z303->{'z303-id'};
        $home_lib = $xml->z303->z303_home_library;
        // Default the college to the useradm library and overwrite it if the
        // home_lib exists
        $patron['college'] = $this->useradm;
        if (($home_lib != '') && (array_key_exists("$home_lib", $this->sublibadm))) {
            if ($this->sublibadm["$home_lib"] != '') {
                $patron['college'] = $this->sublibadm["$home_lib"];
            }
        }
        $patron['id'] = (string)$id;
        $patron['barcode'] = (string)$user;
        $patron['firstname'] = (string)$firstName;
        $patron['lastname'] = (string)$lastName;
        $patron['cat_username'] = (string)$user;
        $patron['cat_password'] = $password;
        $patron['email'] = (string)$email_addr;
        $patron['major'] = null;
        return $patron;
    }

    /**
     * Support method for placeHold -- get holding info for an item.
     *
     * @param string $patronId Patron ID
     * @param string $id       Bib ID
     * @param string $group    Item ID
     *
     * @return array
     */
    public function getHoldingInfoForItem($patronId, $id, $group)
    {
        [$bib, $sys_no] = $this->parseId($id);
        $resource = $bib . $sys_no;
        $xml = $this->doRestDLFRequest(
            ['patron', $patronId, 'record', $resource, 'items', $group]
        );
        $locations = [];
        $part = $xml->xpath('//pickup-locations');
        if ($part) {
            foreach ($part[0]->children() as $node) {
                $arr = $node->attributes();
                $code = (string)$arr['code'];
                $loc_name = (string)$node;
                $locations[$code] = $loc_name;
            }
        } else {
            throw new ILSException('No pickup locations');
        }
        $requests = 0;
        $str = $xml->xpath('//item/queue/text()');
        if ($str != null) {
            [$requests] = explode(' ', trim($str[0]));
        }
        $date = $xml->xpath('//last-interest-date/text()');
        $date = $date[0];
        $date = '' . substr($date, 6, 2) . '.' . substr($date, 4, 2) . '.'
            . substr($date, 0, 4);
        return [
            'pickup-locations' => $locations, 'last-interest-date' => $date,
            'order' => $requests + 1,
        ];
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int|null
     */
    public function getHoldDefaultRequiredDate($patron, $holdInfo)
    {
        $details = [];
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'],
                $holdInfo['id'],
                $holdInfo['item_id']
            );
        }
        if (isset($details['last-interest-date'])) {
            try {
                return $this->dateConverter
                    ->convert('d.m.Y', 'U', $details['last-interest-date']);
            } catch (DateException $e) {
                // If we couldn't convert the date, fail gracefully.
                $this->debug(
                    'Could not convert date: ' . $details['last-interest-date']
                );
            }
        }
        return null;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $details An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($details)
    {
        [$bib, $sys_no] = $this->parseId($details['id']);
        $recordId = $bib . $sys_no;
        $itemId = $details['item_id'];
        $patron = $details['patron'];
        $pickupLocation = $details['pickUpLocation'];
        if (!$pickupLocation) {
            $pickupLocation = $this->getDefaultPickUpLocation($patron, $details);
        }
        $comment = $details['comment'];
        if (strlen($comment) <= 50) {
            $comment1 = $comment;
            $comment2 = null;
        } else {
            $comment1 = substr($comment, 0, 50);
            $comment2 = substr($comment, 50, 50);
        }
        try {
            $requiredBy = $this->dateConverter
                ->convertFromDisplayDate('Ymd', $details['requiredBy']);
        } catch (DateException $de) {
            return [
                'success'    => false,
                'sysMessage' => 'hold_date_invalid',
            ];
        }
        $patronId = $patron['id'];
        $body = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<hold-request-parameters></hold-request-parameters>'
        );
        $body->addChild('pickup-location', $pickupLocation);
        $body->addChild('last-interest-date', $requiredBy);
        $body->addChild('note-1', $comment1);
        if (isset($comment2)) {
            $body->addChild('note-2', $comment2);
        }
        $body = 'post_xml=' . $body->asXML();
        try {
            $this->doRestDLFRequest(
                [
                    'patron', $patronId, 'record', $recordId, 'items', $itemId,
                    'hold',
                ],
                null,
                'PUT',
                $body
            );
        } catch (Aleph\RestfulException $exception) {
            $message = $exception->getMessage();
            $note = $exception->getXmlResponse()
                ->xpath('/put-item-hold/create-hold/note[@type="error"]');
            $note = $note[0];
            return [
                'success' => false,
                'sysMessage' => "$message ($note)",
            ];
        }
        return ['success' => true];
    }

    /**
     * Convert a barcode to an item ID.
     *
     * @param string $bar Barcode
     *
     * @return string|null
     */
    public function barcodeToID($bar)
    {
        if (!$this->xserver_enabled) {
            return null;
        }
        foreach ($this->bib as $base) {
            try {
                $xml = $this->doXRequest(
                    'find',
                    ['base' => $base, 'request' => "BAR=$bar"],
                    false
                );
                $docs = (int)$xml->{'no_records'};
                if ($docs == 1) {
                    $set = (string)$xml->{'set_number'};
                    $result = $this->doXRequest(
                        'present',
                        ['set_number' => $set, 'set_entry' => '1'],
                        false
                    );
                    $id = $result->xpath('//doc_number/text()');
                    $idString = (string)$id[0];
                    if (count($this->bib) == 1) {
                        return $idString;
                    } else {
                        return $base . '-' . $idString;
                    }
                }
            } catch (\Exception $ex) {
            }
        }
        throw new ILSException('barcode not found');
    }

    /**
     * Parse a date.
     *
     * @param string $date Date to parse
     *
     * @return string
     */
    public function parseDate($date)
    {
        if ($date == null || $date == '') {
            return '';
        } elseif (preg_match('/^[0-9]{8}$/', $date) === 1) { // 20120725
            return $this->dateConverter->convertToDisplayDate('Ynd', $date);
        } elseif (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
            // 13/jan/2012
            return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
        } elseif (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
            // 13/7/2012
            return $this->dateConverter->convertToDisplayDate('d/m/Y', $date);
        } elseif (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{2}$/", $date) === 1) {
            // 13/7/12
            return $this->dateConverter->convertToDisplayDate('d/m/y', $date);
        } else {
            throw new \Exception("Invalid date: $date");
        }
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        // Loan history is only available if properly configured
        if ($method == 'getMyTransactionHistory') {
            return !empty($this->config['TransactionHistory']['enabled']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Public Function which retrieves historic loan, renew, hold and cancel
     * settings from the driver ini file.
     *
     * @param string $func   The name of the feature to be checked
     * @param array  $params Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($func, $params = [])
    {
        if ($func == 'Holds') {
            $holdsConfig = $this->config['Holds'] ?? [];
            $defaults = [
                'HMACKeys' => 'id:item_id',
                'extraHoldFields' => 'comments:requiredByDate:pickUpLocation',
                'defaultRequiredDate' => '0:1:0',
            ];
            return $holdsConfig + $defaults;
        } elseif ('getMyTransactionHistory' === $func) {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }
            return [
                'max_results' => 10000,
            ];
        } else {
            return [];
        }
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for getting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron, $holdInfo = null)
    {
        $pickupLocations = [];
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'],
                $holdInfo['id'],
                $holdInfo['item_id']
            );
            foreach ($details['pickup-locations'] as $key => $value) {
                $pickupLocations[] = [
                    'locationID' => $key,
                    'locationDisplay' => $value,
                ];
            }
        } else {
            $default = $this->getDefaultPickUpLocation($patron);
            if (!empty($default)) {
                $pickupLocations[] = [
                    'locationID' => $default,
                    'locationDisplay' => $default,
                ];
            }
        }
        return $pickupLocations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron, $holdInfo = null)
    {
        if ($holdInfo != null) {
            $details = $this->getHoldingInfoForItem(
                $patron['id'],
                $holdInfo['id'],
                $holdInfo['item_id']
            );
            $pickupLocations = $details['pickup-locations'];
            if (isset($this->preferredPickUpLocations)) {
                foreach (array_keys($details['pickup-locations']) as $locationID) {
                    if (in_array($locationID, $this->preferredPickUpLocations)) {
                        return $locationID;
                    }
                }
            }
            // nothing found or preferredPickUpLocations is empty? Return the first
            // locationId in pickupLocations array
            return array_key_first($pickupLocations);
        } elseif (isset($this->preferredPickUpLocations)) {
            return $this->preferredPickUpLocations[0];
        } else {
            throw new ILSException(
                'Missing Catalog/preferredPickUpLocations config setting.'
            );
        }
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return [];
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @throws ILSException
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        $items = [];
        return $items;
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        // TODO
        return [];
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        // TODO
        return [];
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        // TODO
        return [];
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return [];
    }
}
