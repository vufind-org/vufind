<?php

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Config\PathResolver;
use VuFind\Date\DateException;
use VuFind\Exception\AuthToken as AuthTokenException;
use VuFind\Exception\ILS as ILSException;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class XCNCIP2 extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Cache\CacheTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Is this a consortium? Default: false
     *
     * @var bool
     */
    protected $consortium = false;

    /**
     * Agency definitions (consortial) - Array list of consortium members
     *
     * @var array
     */
    protected $agency = [];

    /**
     * NCIP server URL
     *
     * @var string
     */
    protected $url;

    /**
     * Pickup locations
     *
     * @var array
     */
    protected $pickupLocations = null;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Config file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * From agency id
     *
     * @var string
     */
    protected $fromAgency = null;

    /**
     * Statuses of available items lowercased status string from CirculationStatus
     * NCIP element
     *
     * @var string[]
     */
    protected $availableStatuses = ['not charged', 'available on shelf'];

    /**
     * Statuses of active requests, lowercased status strings from RequestStatusType
     * NCIP element
     *
     * @var string[]
     */
    protected $activeRequestStatuses = ['available for pickup', 'in process'];

    /**
     * Lowercased status string for requests available for pickup by patron
     *
     * @var string
     */
    protected $requestAvailableStatus = 'available for pickup';

    /**
     * Lowercased request type strings identifying holds
     *
     * @var string[]
     */
    protected $holdRequestTypes = ['hold', 'recall'];

    /**
     * Lowercased request type strings identifying storage retrievals
     *
     * @var string[]
     */
    protected $storageRetrievalRequestTypes = ['stack retrieval'];

    /**
     * Lowercased item use restriction types we consider to be holdable
     *
     * @var string[]
     */
    protected $notHoldableRestriction = ['not for loan'];

    /**
     * Lowercased circulation statuses we consider not be holdable
     *
     * @var string[]
     */
    protected $notHoldableStatuses = [
        'circulation status undefined', 'not available', 'lost',
    ];

    /**
     * Are renewals disabled for this driver instance? Defaults to false
     *
     * @var bool
     */
    protected $disableRenewals = false;

    /**
     * Which subelements of Problem element show to user when placing hold fails.
     * Possible values are: 'ProblemType', 'ProblemDetail', 'ProblemElement',
     * 'ProblemValue'
     *
     * @var string[]
     */
    protected $holdProblemsDisplay = ['ProblemType'];

    /**
     * Schemes preset for certain elements. See implementation profile:
     * http://www.ncip.info/uploads/7/1/4/6/7146749/z39-83-2-2012_ncip.pdf
     *
     * @var string[]
     */
    protected $schemes = [
        'AgencyElementType' =>
            'http://www.niso.org/ncip/v1_0/imp1/schemes/agencyelementtype/' .
            'agencyelementtype.scm',
        'AuthenticationDataFormatType' =>
            'http://www.iana.org/assignments/media-types/',
        'AuthenticationInputType' =>
            'http://www.niso.org/ncip/v1_0/imp1/schemes/authenticationinputtype/' .
            'authenticationinputype.scm',
        'BibliographicItemIdentifierCode' =>
            'http://www.niso.org/ncip/v1_0/imp1/schemes/' .
            'bibliographicitemidentifiercode/bibliographicitemidentifiercode.scm',
        'ItemElementType' =>
            'http://www.niso.org/ncip/v1_0/schemes/itemelementtype/' .
            'itemelementtype.scm',
        'RequestScopeType' =>
            'http://www.niso.org/ncip/v1_0/imp1/schemes/requestscopetype/' .
            'requestscopetype.scm',
        'RequestType' =>
            'http://www.niso.org/ncip/v1_0/imp1/schemes/requesttype/requesttype.scm',
        'UserElementType' =>
            'http://www.niso.org/ncip/v1_0/schemes/userelementtype/' .
            'userelementtype.scm',
    ];

    /**
     * L1 cache for NCIP responses to save some http connections. Responses are
     * save as in following structure:
     * [ 'ServiceName' => [ 'someId' => \SimpleXMLElement ] ]
     *
     * @var array
     */
    protected $responses = [
        'LookupUser' => [],
    ];

    /**
     * If the NCIP need an authorization using OAuth2
     *
     * @var bool
     */
    protected $useOAuth2 = false;

    /**
     * Use HTTP basic authorization when getting OAuth2 token
     *
     * @var bool
     */
    protected $tokenBasicAuth = false;

    /**
     * If the NCIP need an authorization using HTTP Basic
     *
     * @var bool
     */
    protected $useHttpBasic = false;

    /**
     * HTTP Basic username
     *
     * @var string
     */
    protected $username;

    /**
     * HTTP Basic password
     *
     * @var string
     */
    protected $password;

    /**
     * Mapping block messages from NCIP API to VuFind internal values
     *
     * @var array
     */
    protected $blockCodes = [
        'Block Check Out' => 'checkout_block',
        'Block Electronic Resource Access' => 'electronic_resources_block',
        'Block Hold' => 'requests_blocked',
        'Block Recall' => 'requests_blocked',
        'Block Renewal' => 'renewal_block',
        'Block Request Item' => 'requests_blocked',
        'Trap For Lost Card' => 'lost_card',
        'Trap For Message' => 'message_from_library',
        'Trap For Pickup' => 'available_for_pickup_notification',
    ];

    /**
     * Domain used to translate messages from ILS
     *
     * @var string
     */
    protected $translationDomain = 'ILSMessages';

    /**
     * Other than 2xx HTTP status codes, which could be accepted as correct response.
     * Some NCIP servers could return some 4xx codes similar to REST API (like 404
     * Not found) altogether with correct XML in response body.
     *
     * @var array
     */
    protected $otherAcceptedHttpStatusCodes = [];

    /**
     * Max number of pages taken from API at once. Sometimes NCIP responders could
     * paginate even if we want all data at one time. We then ask for all pages, but
     * it could possibly lead to long response times.
     *
     * @var int
     */
    protected $maxNumberOfPages;

    /**
     * Some ItemUseRestrictionType values could be useful as status. This property controls which values from
     * ItemRestrictionType should replace the status value in response of getHolding method.
     *
     * @var array
     */
    protected $itemUseRestrictionTypesForStatus = [];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     * @param PathResolver           $pathResolver  Config file path resolver
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        PathResolver $pathResolver = null
    ) {
        $this->dateConverter = $dateConverter;
        $this->pathResolver = $pathResolver;
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
        $required = ['url', 'agency'];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }

        $this->url = $this->config['Catalog']['url'];
        $this->fromAgency = $this->config['Catalog']['fromAgency'] ?? null;
        if ($this->config['Catalog']['consortium'] ?? false) {
            $this->consortium = true;
            foreach ($this->config['Catalog']['agency'] ?? [] as $agency) {
                $this->agency[$agency] = 1;
            }
        } else {
            $this->consortium = false;
            if (is_array($this->config['Catalog']['agency'] ?? null)) {
                $this->agency[$this->config['Catalog']['agency'][0]] = 1;
            } else {
                $this->agency[$this->config['Catalog']['agency']] = 1;
            }
        }
        $this->disableRenewals
            = $this->config['Catalog']['disableRenewals'] ?? false;

        $this->useOAuth2 = ($this->config['Catalog']['tokenEndpoint'] ?? false)
            && ($this->config['Catalog']['clientId'] ?? false)
            && ($this->config['Catalog']['clientSecret'] ?? false);
        $this->tokenBasicAuth = $this->config['Catalog']['tokenBasicAuth'] ?? false;

        $this->useHttpBasic = ($this->config['Catalog']['httpBasicAuth'] ?? false)
            && ($this->config['Catalog']['username'] ?? false)
            && ($this->config['Catalog']['password'] ?? false);

        $this->username = $this->config['Catalog']['username'] ?? '';
        $this->password = $this->config['Catalog']['password'] ?? '';

        if (isset($this->config['Catalog']['translationDomain'])) {
            $this->translationDomain
                = $this->config['Catalog']['translationDomain'];
        }

        if (isset($this->config['Catalog']['otherAcceptedHttpStatusCodes'])) {
            $otherAcceptedHttpStatusCodes
                = explode(
                    ',',
                    $this->config['Catalog']['otherAcceptedHttpStatusCodes']
                );
            $this->otherAcceptedHttpStatusCodes = array_map(
                'trim',
                $otherAcceptedHttpStatusCodes
            );
        }
        $this->maxNumberOfPages = $this->config['Catalog']['maxNumberOfPages'] ?? 0;
        if (isset($this->config['Catalog']['holdProblemsDisplay'])) {
            $holdProblemsDisplay
                = explode(
                    ',',
                    $this->config['Catalog']['holdProblemsDisplay']
                );
            $this->holdProblemsDisplay = array_map('trim', $holdProblemsDisplay);
        }

        $this->itemUseRestrictionTypesForStatus
            = (array)($this->config['Catalog']['itemUseRestrictionTypesForStatus'] ?? []);
    }

    /**
     * Load pickup locations from file or from NCIP responder - it depends on
     * configuration
     *
     * @throws ILSException
     * @return void
     */
    public function loadPickUpLocations()
    {
        $filename = $this->config['Catalog']['pickupLocationsFile'] ?? null;
        if ($filename) {
            $this->loadPickUpLocationsFromFile($filename);
        } elseif ($this->config['Catalog']['pickupLocationsFromNCIP'] ?? false) {
            $this->loadPickUpLocationsFromNcip();
        } else {
            $this->pickupLocations = [];
        }
    }

    /**
     * Loads pickup location information from configuration file.
     *
     * @param string $filename File to load from
     *
     * @throws ILSException
     * @return void
     */
    protected function loadPickUpLocationsFromFile($filename)
    {
        // Load pickup locations file:
        $pickupLocationsFile = $this->pathResolver
            ? $this->pathResolver->getConfigPath($filename)
            : \VuFind\Config\Locator::getConfigPath($filename);
        if (!file_exists($pickupLocationsFile)) {
            throw new ILSException(
                "Cannot load pickup locations file: {$pickupLocationsFile}."
            );
        }
        if (($handle = fopen($pickupLocationsFile, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $agencyId = $data[0] . '|' . $data[1];
                $this->pickupLocations[$agencyId] = [
                    'locationID' => $agencyId,
                    'locationDisplay' => $data[2],
                ];
            }
            fclose($handle);
        }
    }

    /**
     * Loads pickup location information from LookupAgency NCIP service.
     *
     * @return void
     */
    public function loadPickUpLocationsFromNcip()
    {
        $request = $this->getLookupAgencyRequest();
        $response = $this->sendRequest($request);

        $return = [];

        $agencyId = $response->xpath('ns1:LookupAgencyResponse/ns1:AgencyId');
        $agencyId = (string)($agencyId[0] ?? '');
        $locations = $response->xpath(
            'ns1:LookupAgencyResponse/ns1:Ext/ns1:LocationName/' .
            'ns1:LocationNameInstance'
        );
        foreach ($locations as $loc) {
            $this->registerNamespaceFor($loc);
            $id = $loc->xpath('ns1:LocationNameLevel');
            $name = $loc->xpath('ns1:LocationNameValue');
            if (empty($id) || empty($name)) {
                continue;
            }
            $location = [
                'locationID' => $agencyId . '|' . (string)$id[0],
                'locationDisplay' => (string)$name[0],
            ];
            $return[] = $location;
        }
        $this->pickupLocations = $return;
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml XML request document
     *
     * @return \SimpleXMLElement SimpleXMLElement parsed from response
     * @throws ILSException
     */
    protected function sendRequest($xml)
    {
        $this->debug('Sending NCIP request: ' . $xml);
        $client = $this->httpService->createClient($this->url);
        if ($this->useOAuth2) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Authorization', $this->getOAuth2Token());
        } elseif ($this->useHttpBasic) {
            $client->setAuth($this->username, $this->password);
        }
        // Set timeout value
        $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
        $client->setOptions(['timeout' => $timeout]);
        $client->setRawBody($xml);
        $client->setEncType('application/xml; charset=UTF-8');
        $client->setMethod('POST');
        // Make the NCIP request:
        try {
            $result = $client->send();
        } catch (\Exception $e) {
            $this->logError('Error in NCIP communication: ' . $e->getMessage());
            $this->throwAsIlsException($e, 'Problem with NCIP API');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($this->useOAuth2 && $result->getStatusCode() == 401) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Authorization', $this->getOAuth2Token(true));
            try {
                $result = $client->send();
            } catch (\Exception $e) {
                $this->logError('Error in NCIP communication: ' . $e->getMessage());
                $this->throwAsIlsException($e, 'Problem with NCIP API');
            }
        }

        if (
            !$result->isSuccess()
            && !in_array(
                $result->getStatusCode(),
                $this->otherAcceptedHttpStatusCodes
            )
        ) {
            throw new ILSException(
                'HTTP error: ' . $this->parseProblem($result->getBody())
            );
        }

        // Process the NCIP response:
        $response = $result->getBody();
        $this->debug('Got NCIP response: ' . $response);
        return $this->parseXml($response);
    }

    /**
     * Get a new or cached OAuth2 token (type + token)
     *
     * @param bool $renew Force renewal of token
     *
     * @return string
     */
    protected function getOAuth2Token($renew = false)
    {
        $cacheKey = 'oauth';

        if (!$renew) {
            $token = $this->getCachedData($cacheKey);
            if ($token) {
                return $token;
            }
        }

        try {
            $token = $this->getNewOAuth2Token(
                $this->config['Catalog']['tokenEndpoint'],
                $this->config['Catalog']['clientId'],
                $this->config['Catalog']['clientSecret'],
                $this->config['Catalog']['grantType'] ?? 'client_credentials',
                $this->tokenBasicAuth
            );
        } catch (AuthTokenException $exception) {
            $this->throwAsIlsException(
                $exception,
                'Problem with NCIP API authorization: ' . $exception->getMessage()
            );
        }

        $this->putCachedData(
            $cacheKey,
            $token->getHeaderValue(),
            $token->getExpiresIn()
        );

        return $token->getHeaderValue();
    }

    /**
     * Method to ensure uniform cache keys for cached VuFind objects.
     *
     * @param string|null $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     *
     * @return string
     */
    protected function getCacheKey($suffix = null)
    {
        return 'XCNCIP2' . '-' . md5($this->url . $suffix);
    }

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param SimpleXMLElement $current Current LUIS holding chunk.
     *
     * @return array of status information for this holding
     */
    protected function getStatusForChunk($current)
    {
        $this->registerNamespaceFor($current);
        $status = $current->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = (string)($status[0] ?? '');

        $itemCallNo = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = !empty($itemCallNo) ? (string)$itemCallNo[0] : null;

        $location = $current->xpath(
            'ns1:ItemOptionalFields/ns1:Location/ns1:LocationName/' .
            'ns1:LocationNameInstance/ns1:LocationNameValue'
        );
        $location = !empty($location) ? (string)$location[0] : null;

        $return = [
            'status' => $status,
            'location' => $location,
            'callnumber' => $itemCallNo,
            'availability' => $this->isAvailable($status),
            'reserve' => 'N',       // not supported
        ];
        if (strtolower($status) === 'circulation status undefined') {
            $return['use_unknown_message'] = true;
        }
        return $return;
    }

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param \SimpleXMLElement $current     Current ItemInformation element
     * @param string            $aggregateId (Aggregate) ID of the consortial record
     * @param string            $bibId       Bib ID of one of the consortial
     * record's source record(s)
     * @param array             $patron      Patron array from patronLogin
     *
     * @return array
     * @throws ILSException
     */
    protected function getHoldingsForChunk(
        $current,
        $aggregateId = null,
        $bibId = null,
        $patron = null
    ) {
        $this->registerNamespaceFor($current);

        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = (string)($status[0] ?? '');

        $itemUseRestrictionType = $current->xpath('ns1:ItemOptionalFields/ns1:ItemUseRestrictionType');
        $itemUseRestrictionType = (string)($itemUseRestrictionType[0] ?? '');

        if (in_array($itemUseRestrictionType, $this->itemUseRestrictionTypesForStatus)) {
            $status = $itemUseRestrictionType;
        }

        $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
        $itemId = (string)($itemId[0] ?? '');
        $itemType = $current->xpath('ns1:ItemId/ns1:ItemIdentifierType');
        $itemType = (string)($itemType[0] ?? '');

        $itemAgencyId = $current->xpath('ns1:ItemId/ns1:AgencyId');
        $itemAgencyId = !empty($itemAgencyId) ? ((string)$itemAgencyId[0]) : null;
        $itemAgencyId = $this->determineToAgencyId($itemAgencyId);

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
        // $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
        // $location = '';
        // foreach ($locationNodes as $curLoc) {
        //     $type = $curLoc->xpath('ns1:LocationType');
        //     if ((string)$type[0] == 'Permanent') {
        //         $tmp = $curLoc->xpath(
        //             'ns1:LocationName/ns1:LocationNameInstance' .
        //             '/ns1:LocationNameValue'
        //         );
        //         $location = (string)$tmp[0];
        //     }
        // }

        $locations = $current->xpath(
            'ns1:ItemOptionalFields/ns1:Location/ns1:LocationName/' .
            'ns1:LocationNameInstance'
        );
        [$location, $collection] = $this->parseLocationInstance($locations);

        $itemCallNo = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = (string)($itemCallNo[0] ?? '');

        $number = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/' .
            'ns1:CopyNumber'
        );
        $number = (string)($number[0] ?? '');

        $volume = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/' .
            'ns1:HoldingsInformation/ns1:UnstructuredHoldingsData'
        );
        $volume = (string)($volume[0] ?? '');

        $dateDue = $current->xpath(
            'ns1:DateDue' .
            '| ' .
            'ns1:ItemOptionalFields/ns1:DateDue'
        );
        $dateDue = !empty($dateDue)
            ? $this->displayDate((string)$dateDue[0]) : null;

        $isHoldable = $this->isItemHoldable($current);
        // Build return array:
        $return = [
            'id' => $aggregateId,
            'availability' =>  $this->isAvailable($status),
            'status' => $status,
            'item_id' => $itemId,
            'bib_id' => $bibId,
            'item_agency_id' => $itemAgencyId,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => $itemCallNo,
            'duedate' => $dateDue,
            'volume' => $volume,
            'number' => $number,
            'barcode' => ($itemType === 'Barcode')
                ? $itemId : 'Unknown barcode',
            'is_holdable'  => $isHoldable,
            'addLink' => $this->isPatronBlocked($patron) ? false : $isHoldable,
            'holdtype' => $this->getHoldType($status),
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => 'true',
        ];
        if (strtolower($status) === 'circulation status undefined') {
            $return['use_unknown_message'] = true;
        }
        if (!empty($collection)) {
            $return['collection_desc'] = $collection;
        }

        return $return;
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
        // For now, we'll just use getHolding, since getStatus should return a
        // subset of the same fields, and the extra values will be ignored.
        return $this->getHolding($id);
    }

    /**
     * Build NCIP2 request XML for item status information.
     *
     * @param array  $idList     IDs to look up.
     * @param string $resumption Resumption token (null for first page of set).
     * @param string $agency     Agency ID.
     *
     * @return string            XML request
     */
    protected function getStatusRequest($idList, $resumption = null, $agency = null)
    {
        $agency = $this->determineToAgencyId($agency);

        // Build a list of the types of information we want to retrieve:
        $desiredParts = [
            'Bibliographic Description',
            'Circulation Status',
            'Electronic Resource',
            'Hold Queue Length',
            'Item Description',
            'Item Use Restriction Type',
            'Location',
        ];

        // Start the XML:
        $xml = $this->getNCIPMessageStart() . '<ns1:LookupItemSet>';
        $xml .= $this->getInitiationHeaderXml($agency);

        foreach ($idList as $id) {
            $xml .= $this->getBibliographicId($id);
        }

        // Add the desired data list:
        foreach ($desiredParts as $current) {
            $xml .= $this->element('ItemElementType', $current);
        }

        // Add resumption token if necessary:
        if (!empty($resumption)) {
            $xml .= $this->element('NextItemToken', $resumption);
        }

        // Close the XML and send it to the caller:
        $xml .= '</ns1:LookupItemSet></ns1:NCIPMessage>';
        return $xml;
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
        $status = [];

        if ($this->consortium) {
            return $status; // (empty) TODO: add support for consortial statuses.
        }
        $resumption = null;
        do {
            $request = $this->getStatusRequest($idList, $resumption);
            $response = $this->sendRequest($request);
            $bibInfo = $response->xpath(
                'ns1:LookupItemSetResponse/ns1:BibInformation'
            );

            // Build the array of statuses:
            foreach ($bibInfo as $bib) {
                $this->registerNamespaceFor($bib);
                $bibId = $bib->xpath(
                    'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                    'ns1:BibliographicRecordIdentifier' .
                    ' | ' .
                    'ns1:BibliographicId/ns1:BibliographicItemId/' .
                    'ns1:BibliographicItemIdentifier'
                );
                if (empty($bibId)) {
                    throw new ILSException(
                        'Bibliographic record/item identifier missing in lookup ' .
                        'item set response'
                    );
                }
                $bibId = (string)$bibId[0];

                $holdings = $bib->xpath('ns1:HoldingsSet');

                foreach ($holdings as $holding) {
                    $this->registerNamespaceFor($holding);
                    $holdCallNo = $holding->xpath('ns1:CallNumber');
                    $holdCallNo = !empty($holdCallNo) ? (string)$holdCallNo[0]
                        : null;

                    $items = $holding->xpath('ns1:ItemInformation');

                    $holdingLocation = $holding->xpath(
                        'ns1:Location/ns1:LocationName/ns1:LocationNameInstance/' .
                        'ns1:LocationNameValue'
                    );
                    $holdingLocation = !empty($holdingLocation)
                        ? (string)$holdingLocation[0] : null;

                    foreach ($items as $item) {
                        // Get data on the current chunk of data:
                        $chunk = $this->getStatusForChunk($item);

                        $chunk['callnumber'] = empty($chunk['callnumber']) ?
                            $holdCallNo : $chunk['callnumber'];

                        // Each bibliographic ID has its own key in the $status
                        // array; make sure we initialize new arrays when necessary
                        // and then add the current chunk to the right place:
                        $chunk['id'] = $bibId;
                        if (!isset($status[$bibId])) {
                            $status[$bibId] = [];
                        }
                        $chunk['location'] ??= $holdingLocation ?? null;
                        $status[$bibId][] = $chunk;
                    }
                }
            }

            // Check for resumption token:
            $resumption = $response->xpath(
                'ns1:LookupItemSetResponse/ns1:NextItemToken'
            );
            $resumption = count($resumption) > 0 ? (string)$resumption[0] : null;
        } while (!empty($resumption));
        return $status;
    }

    /**
     * Get Consortial Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * consortial record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     * @param array  $ids    The (consortial) source records for the record id
     *
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsortialHoldings(
        $id,
        array $patron = null,
        array $ids = null
    ) {
        $aggregateId = $id;

        $agencyList = [];
        $idList = [];
        if (null !== $ids) {
            foreach ($ids as $id) {
                // Need to parse out the 035$a format, e.g., "(Agency) 123"
                if (preg_match('/\(([^\)]+)\)\s*(.+)/', $id, $matches)) {
                    $matchedAgency = $matches[1];
                    $matchedId = $matches[2];
                    if (array_key_exists($matchedAgency, $this->agency)) {
                        $agencyList[] = $matchedAgency;
                        $idList[] = $matchedId;
                    }
                }
            }
        }

        $holdings = [];
        $bibs = $this->getBibs($idList, $agencyList);

        foreach ($bibs as $bib) {
            $this->registerNamespaceFor($bib);
            $bibIds = $bib->xpath(
                'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                'ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:BibliographicId/ns1:BibliographicItemId/' .
                'ns1:BibliographicItemIdentifier'
            );
            $bibId = (string)$bibIds[0];

            $holdingSets = $bib->xpath('ns1:HoldingsSet');
            foreach ($holdingSets as $holding) {
                $this->registerNamespaceFor($holding);
                $holdCallNo = $holding->xpath('ns1:CallNumber');
                $holdCallNo = (string)($holdCallNo[0] ?? '');
                $avail = $holding->xpath('ns1:ItemInformation');
                $eResource = $holding->xpath(
                    'ns1:ElectronicResource/ns1:ReferenceToResource'
                );
                $eResource = (string)($eResource[0] ?? '');

                $locations = $holding->xpath(
                    'ns1:Location/ns1:LocationName/ns1:LocationNameInstance'
                );
                [$holdingLocation, $collection]
                    = $this->parseLocationInstance($locations);

                // Build the array of holdings:
                foreach ($avail as $current) {
                    $chunk = $this->getHoldingsForChunk(
                        $current,
                        $aggregateId,
                        $bibId,
                        $patron
                    );
                    $chunk['callnumber'] = empty($chunk['callnumber']) ?
                        $holdCallNo : $chunk['callnumber'];
                    $chunk['eresource'] = $eResource;
                    $chunk['location'] ??= $holdingLocation ?? null;
                    if (
                        !isset($chunk['collection_desc'])
                        && !empty($collection)
                    ) {
                        $chunk['collection_desc'] = $collection;
                    }
                    $holdings[] = $chunk;
                }
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
        $ids = null;
        if (! $this->consortium) {
            // Translate $id into consortial (035$a) format,
            // e.g., "123" -> "(Agency) 123"
            $sourceRecord = '';
            foreach (array_keys($this->agency) as $Agency) {
                $sourceRecord = '(' . $Agency . ') ';
            }
            $sourceRecord .= $id;
            $ids = [$sourceRecord];
        }

        return $this->getConsortialHoldings($id, $patron, $ids);
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
        // NCIP is not able to send acquisition data
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $response = $this->getLookupUserResponse($username, $password);

        $id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
        if (empty($id)) {
            return null;
        }

        $patronAgencyId = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:AgencyId'
        );
        $first = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:GivenName'
        );
        $last = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:Surname'
        );
        $email = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:ElectronicAddress/' .
                'ns1:ElectronicAddressData'
        );

        // Fill in basic patron details:
        return [
            'id' => (string)$id[0],
            'patronAgencyId' => !empty($patronAgencyId)
                ? (string)$patronAgencyId[0] : null,
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => !empty($email) ? (string)$email[0] : null,
            'major' => null,
            'college' => null,
            'firstname' => (string)($first[0] ?? ''),
            'lastname' => (string)($last[0] ?? ''),
        ];
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $response = $this->getLookupUserResponse(
            $patron['cat_username'],
            $patron['cat_password']
        );

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');
        foreach ($list as $current) {
            $this->registerNamespaceFor($current);
            $tmp = $current->xpath('ns1:DateDue');
            // DateDue could be omitted in response
            $due = $this->displayDate(!empty($tmp) ? (string)$tmp[0] : null);
            $title = $current->xpath('ns1:Title');
            $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $itemId = (string)$itemId[0];
            $bibId = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier'
            );
            $itemAgencyId = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:AgencyId' .
                ' | ' .
                'ns1:ItemId/ns1:AgencyId'
            );

            $renewable = $this->disableRenewals
                ? false
                : empty($current->xpath('ns1:Ext/ns1:RenewalNotPermitted'));

            $itemAgencyId = !empty($itemAgencyId) ? (string)$itemAgencyId[0] : null;
            $bibId = !empty($bibId) ? (string)$bibId[0] : null;
            if ($bibId === null || $itemAgencyId === null || empty($due)) {
                $itemType = $current->xpath('ns1:ItemId/ns1:ItemIdentifierType');
                $itemType = !empty($itemType) ? (string)$itemType[0] : null;
                $itemRequest = $this->getLookupItemRequest($itemId, $itemType);
                $itemResponse = $this->sendRequest($itemRequest);
            }
            if ($bibId === null && isset($itemResponse)) {
                $bibId = $itemResponse->xpath(
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/' .
                    'ns1:BibliographicDescription/ns1:BibliographicItemId/' .
                    'ns1:BibliographicItemIdentifier' .
                    ' | ' .
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/' .
                    'ns1:BibliographicDescription/ns1:BibliographicRecordId/' .
                    'ns1:BibliographicRecordIdentifier'
                );
                // Hack to account for bibs from other non-local institutions
                // temporarily until consortial functionality is enabled.
                $bibId = !empty($bibId) ? (string)$bibId[0] : '1';
            }
            if ($itemAgencyId === null && isset($itemResponse)) {
                $itemAgencyId = $itemResponse->xpath(
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/' .
                    'ns1:BibliographicDescription/ns1:BibliographicRecordId/' .
                    'ns1:AgencyId' .
                    ' | ' .
                    'ns1:LookupItemResponse/ns1:ItemId/ns1:AgencyId'
                );
                $itemAgencyId = !empty($itemAgencyId)
                    ? (string)$itemAgencyId[0] : null;
            }
            if (empty($due) && isset($itemResponse)) {
                $rawDueDate = $itemResponse->xpath(
                    'ns1:LookupItemResponse/ns1:ItemOptionalFields/' .
                    'ns1:DateDue'
                );
                $due = $this->displayDate(
                    !empty($rawDueDate) ? (string)$rawDueDate[0] : null
                );
            }

            $retVal[] = [
                'id' => $bibId,
                'item_agency_id' => $itemAgencyId,
                'patronAgencyId' => $patron['patronAgencyId'],
                'duedate' => $due,
                'title' => !empty($title) ? (string)$title[0] : null,
                'item_id' => $itemId,
                'renewable' => $renewable,
            ];
        }

        return $retVal;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $response = $this->getLookupUserResponse(
            $patron['cat_username'],
            $patron['cat_password']
        );

        $list = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserFiscalAccount/ns1:AccountDetails'
        );

        $fines = [];
        foreach ($list as $current) {
            $this->registerNamespaceFor($current);

            $amount = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue'
            );
            $amount = (string)($amount[0] ?? '');
            $date = $current->xpath('ns1:AccrualDate');
            $date = $this->displayDate(!empty($date) ? (string)$date[0] : null);
            $desc = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:FiscalTransactionType'
            );
            $desc = (string)($desc[0] ?? '');

            $bibId = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
                'ns1:BibliographicDescription/ns1:BibliographicRecordId/' .
                'ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
                'ns1:BibliographicDescription/ns1:BibliographicItemId/' .
                'ns1:BibliographicItemIdentifier'
            );
            $id = (string)($bibId[0] ?? '');
            $fines[] = [
                'amount' => $amount,
                'balance' => $amount,
                'checkout' => '',
                'fine' => $desc,
                'duedate' => '',
                'createdate' => $date,
                'id' => $id,
            ];
        }
        return $fines;
    }

    /**
     * Get Patron requests by type
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $types  Request types
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    protected function getMyRequests(array $patron, array $types)
    {
        $response = $this->getLookupUserResponse(
            $patron['cat_username'],
            $patron['cat_password']
        );

        $retVal = [];
        $requests = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');

        foreach ($requests as $current) {
            $this->registerNamespaceFor($current);
            $id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier' .
                ' | ' .
                'ns1:BibliographicId/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:BibliographicId/' .
                'ns1:BibliographicItemId/ns1:BibliographicItemIdentifier'
            );
            $itemAgencyId = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:AgencyId'
            );

            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $pickupLocation = $current->xpath('ns1:PickupLocation');
            $created = $current->xpath('ns1:DatePlaced');
            $created = $this->displayDate(
                !empty($created) ? (string)$created[0] : null
            );
            $expireDate = $current->xpath('ns1:PickupExpiryDate');
            $expireDate = $this->displayDate(
                !empty($expireDate) ? (string)$expireDate[0] : null
            );

            $requestStatusType = $current->xpath('ns1:RequestStatusType');
            $status = !empty($requestStatusType) ? (string)$requestStatusType[0]
                : null;
            $available = strtolower($status) === $this->requestAvailableStatus;

            // Only return requests of desired type
            if ($this->checkRequestType($current, $types)) {
                $retVal[] = [
                    'id' => (string)($id[0] ?? ''),
                    'create' => $created,
                    'expire' => $expireDate,
                    'title' => !empty($title) ? (string)$title[0] : null,
                    'position' => !empty($pos) ? (string)$pos[0] : null,
                    'requestId' => !empty($requestId) ? (string)$requestId[0] : null,
                    'item_agency_id' => !empty($itemAgencyId)
                        ? (string)$itemAgencyId[0] : null,
                    'canceled' => $this->isRequestCancelled($status),
                    'item_id' => !empty($itemId[0]) ? (string)$itemId[0] : null,
                    'location' => !empty($pickupLocation[0])
                        ? (string)$pickupLocation[0] : null,
                    'available' => $available,
                ];
            }
        }
        return $retVal;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        return $this->getMyRequests($patron, $this->holdRequestTypes);
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $response = $this->getLookupUserResponse(
            $patron['cat_username'],
            $patron['cat_password']
        );

        $firstname = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:GivenName'
        );
        $lastname = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:Surname'
        );
        if (empty($firstname) && empty($lastname)) {
            $lastname = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
                'ns1:NameInformation/ns1:PersonalNameInformation/' .
                'ns1:UnstructuredPersonalUserName'
            );
        }

        $address1 = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:StructuredAddress/ns1:Line1' .
            '|' .
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:StructuredAddress/ns1:Street'
        );
        $address1 = !empty($address1) ? (string)$address1[0] : null;
        $address2 = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:StructuredAddress/ns1:Line2' .
            '|' .
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:StructuredAddress/ns1:Locality'
        );
        $address2 = !empty($address2) ? (string)$address2[0] : null;
        $zip = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:StructuredAddress/ns1:PostalCode'
        );
        $zip = !empty($zip) ? (string)$zip[0] : null;

        if (empty($address1)) {
            // TODO: distinguish between more formatting types; look
            // at the UnstructuredAddressType field and handle multiple options.
            $address = $response->xpath(
                'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
                'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
                'ns1:UnstructuredAddress/ns1:UnstructuredAddressData'
            );
            $address = explode("\n", trim((string)($address[0] ?? '')));
            $address1 = $address[0] ?? null;
            $address2 = ($address[1] ?? null);
            if (isset($address[2])) {
                $address2 .= ', ' . $address[2];
            }
            $zip ??= $address[3] ?? null;
        }

        $expirationDate = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:UserPrivilege/' .
            'ns1:ValidToDate'
        );
        $expirationDate = !empty($expirationDate) ?
            $this->displayDate((string)$expirationDate[0]) : null;

        return [
            'firstname' => (string)($firstname[0] ?? null),
            'lastname' => (string)($lastname[0] ?? null),
            'address1' => $address1,
            'address2' => $address2,
            'zip' => $zip,
            'phone' => null,  // TODO: phone number support
            'group' => null,
            'expiration_date' => $expirationDate,
        ];
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
        // NCIP is not able to send acquisition data
        return [];
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws ILSException
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        // NCIP is not able to send acquisition data, so we don't need getFunds
        return [];
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
        // NCIP does not support course reserves
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
        // NCIP does not support course reserves
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
        // NCIP does not support course reserves
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
        // NCIP does not support course reserves
        return [];
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        // NCIP does not support this
        return [];
    }

    /**
     * Public Function which retrieves Holds, StorageRetrievalRequests, and
     * Consortial settings from the driver ini file.
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
        if ($function == 'Holds') {
            $holdsConfig = $this->config['Holds'] ?? [];
            $extraHoldFields = empty($this->getPickUpLocations(null))
                ? 'comments:requiredByDate'
                : 'comments:pickUpLocation:requiredByDate';
            $defaults =  [
                'HMACKeys' => 'item_id:holdtype:item_agency_id:id:bib_id',
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => '0:2:0',
                'consortium' => $this->consortium,
            ];
            return $holdsConfig + $defaults;
        }
        if ($function == 'StorageRetrievalRequests') {
            $config = $this->config['StorageRetrievalRequests'] ?? [];
            $extraFields = empty($this->getPickUpLocations(null))
                ? 'comments:requiredByDate:item-issue'
                : 'comments:pickUpLocation:requiredByDate:item-issue';
            $defaults =  [
                'HMACKeys' => 'id:item_id:item_agency_id:id:bib_id',
                'extraFields' => $extraFields,
                'defaultRequiredDate' => '0:2:0',
            ];
            return $config + $defaults;
        }
        return [];
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in HorizonXMLAPI.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron, $holdDetails = null)
    {
        return $this->pickupLocations[$patron['patronAgencyId']][0]['locationID'];
    }

    /**
     * Return patron blocks
     *
     * @param array $patron Patron data from patronLogin method
     *
     * @return array
     * @throws ILSException
     */
    protected function getPatronBlocks($patron = null): ?array
    {
        if (empty($patron)) {
            return [];
        }
        $response = $this->getLookupUserResponse($patron['cat_username']);
        $blocks = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:BlockOrTrap/' .
            'ns1:BlockOrTrapType'
        );
        $blocks = ($blocks === false) ? [] : $blocks;
        return array_map(
            function ($block) {
                return (string)$block;
            },
            $blocks
        );
    }

    /**
     * Helper function to distinguish if blocks are really blocking patron from
     * actions on ILS, or if they are more like notifies
     *
     * @param array $patron Patron from patronLogin
     *
     * @return bool
     * @throws ILSException
     */
    protected function isPatronBlocked(?array $patron): bool
    {
        $blocks = $this->getPatronBlocks($patron);
        $blocks = array_filter(
            $blocks,
            function ($item) {
                return str_starts_with($item, 'Block');
            }
        );
        return !empty($blocks);
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron, $holdDetails = null)
    {
        if (!isset($this->pickupLocations)) {
            $this->loadPickUpLocations();
        }
        return array_values($this->pickupLocations);
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array        Array of the patron's storage retrieval requests.
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        return $this->getMyRequests($patron, $this->storageRetrievalRequestTypes);
    }

    /**
     * Check if storage retrieval request available
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        return true;
    }

    /**
     * Place Storage Retrieval Request (Call Slip)
     *
     * Attempts to place a call slip request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful.
     */
    public function placeStorageRetrievalRequest($details)
    {
        return $this->placeRequest($details, 'Stack Retrieval');
    }

    /**
     * Get Renew Details
     *
     * This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['item_agency_id'] .
            '|' . $checkOutDetails['item_id'];
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
     * whether or not it was successful
     */
    public function placeHold($details)
    {
        return $this->placeRequest($details, $details['holdtype']);
    }

    /**
     * Place a general request
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array  $details An array of item and patron data
     * @param string $type    Type of request, could be 'Hold' or 'Stack Retrieval'
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful
     */
    public function placeRequest($details, $type = 'Hold')
    {
        $username = $details['patron']['cat_username'];
        $password = $details['patron']['cat_password'];
        $bibId = $details['bib_id'];
        $itemId = $details['item_id'];
        $requestType = $details['holdtype'] ?? $type;
        $pickUpLocation = null;
        if (isset($details['pickUpLocation'])) {
            [, $pickUpLocation] = explode('|', $details['pickUpLocation']);
        }

        $convertedDate = $this->dateConverter->convertFromDisplayDate(
            'U',
            $details['requiredBy']
        );
        $lastInterestDate = \DateTime::createFromFormat('U', $convertedDate);
        $lastInterestDate->setTime(23, 59, 59);
        $lastInterestDateStr = $lastInterestDate->format('c');
        $request = $this->getRequest(
            $username,
            $password,
            $bibId,
            $itemId,
            $details['patron']['patronAgencyId'],
            $details['item_agency_id'],
            $requestType,
            'Item',
            $lastInterestDateStr,
            $pickUpLocation,
            $username
        );
        $response = $this->sendRequest($request);

        $success = $response->xpath(
            'ns1:RequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue' .
            ' | ' .
            'ns1:RequestItemResponse/ns1:RequestId/ns1:RequestIdentifierValue'
        );

        try {
            $this->checkResponseForError($response);
        } catch (ILSException $exception) {
            $failureReturn = ['success' => false];
            $problemDescription = $this->getProblemDescription(
                $response,
                $this->holdProblemsDisplay,
                false
            );
            if (!empty($problemDescription)) {
                $failureReturn['sysMessage']
                    = $this->translateMessage($problemDescription);
            }
            return $failureReturn;
        }

        $this->invalidateResponseCache('LookupUser', $username);
        return !empty($success) ? ['success' => true] : ['success' => false];
    }

    /**
     * General cancel request method
     *
     * Attempts to Cancel a request on a particular item. The data in
     * $cancelDetails['details'] is determined by getCancelRequestDetails().
     *
     * @param array  $cancelDetails An array of item and patron data
     * @param string $type          Type of request, could be: 'Hold',
     * 'Stack Retrieval'
     *
     * @return array               An array of data on each request including
     * whether or not it was successful.
     */
    public function handleCancelRequest($cancelDetails, $type = 'Hold')
    {
        $msgPrefix = ($type === 'Stack Retrieval')
            ? 'storage_retrieval_request_cancel_'
            : 'hold_cancel_';
        $count = 0;
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $patronAgency = $cancelDetails['patron']['patronAgencyId'];
        $details = $cancelDetails['details'];
        $patronId = $cancelDetails['patron']['id'] ?? null;
        $response = [];
        $failureReturn = [
            'success' => false,
            'status' => $msgPrefix . 'fail',
        ];
        $successReturn = [
            'success' => true,
            'status' => $msgPrefix . 'success',
        ];

        foreach ($details as $detail) {
            [$itemAgencyId, $requestId, $itemId] = explode('|', $detail);
            $request = $this->getCancelRequest(
                $username,
                $password,
                $patronAgency,
                $itemAgencyId,
                $requestId,
                $type,
                $itemId,
                $patronId
            );
            $cancelRequestResponse = $this->sendRequest($request);
            $userId = $cancelRequestResponse->xpath(
                'ns1:CancelRequestItemResponse/' .
                'ns1:UserId/ns1:UserIdentifierValue'
            );
            $itemId = $itemId ?: $requestId;
            try {
                $this->checkResponseForError($cancelRequestResponse);
            } catch (ILSException $exception) {
                $response[$itemId] = $failureReturn;
                continue;
            }
            if ($userId) {
                $count++;
                $response[$itemId] = $successReturn;
            } else {
                $response[$itemId] = $failureReturn;
            }
        }
        $this->invalidateResponseCache('LookupUser', $username);
        $result = ['count' => $count, 'items' => $response];
        return $result;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful.
     */
    public function cancelHolds($cancelDetails)
    {
        return $this->handleCancelRequest($cancelDetails, 'Hold');
    }

    /**
     * Get Cancel Request Details
     *
     * General method for getting details for cancel requests
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelRequestDetails($details)
    {
        if ($details['available']) {
            return '';
        }
        return $details['item_agency_id'] .
            '|' . $details['requestId'] .
            '|' . $details['item_id'];
    }

    /**
     * Get Cancel Hold Details
     *
     * This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function. Item id is used as the
     * array key in the response.
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
        return $this->getCancelRequestDetails($holdDetails);
    }

    /**
     * Cancel Storage Retrieval Requests (Call Slips)
     *
     * Attempts to Cancel a call slip on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelStorageRetrievalRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful.
     */
    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        return $this->handleCancelRequest($cancelDetails, 'Stack Retrieval');
    }

    /**
     * Get Cancel Storage Retrieval Request (Call Slip) Details
     *
     * This function returns the item id and call slip id as a
     * string separated by a pipe, which is then submitted as form data. This
     * value is then extracted by the CancelStorageRetrievalRequests function.
     * The item id is used as the key in the return value.
     *
     * @param array $details An array of item data
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelStorageRetrievalRequestDetails($details, $patron)
    {
        return $this->getCancelRequestDetails($details);
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items. The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $details = [];
        $username = $renewDetails['patron']['cat_username'];
        foreach ($renewDetails['details'] as $detail) {
            [$agencyId, $itemId] = explode('|', $detail);
            $failureReturn = [
                'success' => false,
                'item_id' => $itemId,
            ];
            if ($this->disableRenewals) {
                $details[$itemId] = $failureReturn;
                continue;
            }
            $request = $this->getRenewRequest(
                $username,
                $renewDetails['patron']['cat_password'],
                $itemId,
                $agencyId,
                $renewDetails['patron']['patronAgencyId'],
                $username
            );
            $response = $this->sendRequest($request);
            $dueDateXml = $response->xpath('ns1:RenewItemResponse/ns1:DateDue');
            $dueDate = '';
            $dueTime = '';
            if (!empty($dueDateXml)) {
                $dueDateString = (string)$dueDateXml[0];
                $dueDate = $this->displayDate($dueDateString);
                $dueTime = $this->displayTime($dueDateString);
            }

            if ($dueDate !== '') {
                $details[$itemId] = [
                    'success' => true,
                    'new_date' => $dueDate,
                    'new_time' => $dueTime,
                    'item_id' => $itemId,
                ];
            } else {
                $details[$itemId] = $failureReturn;
            }
        }
        $this->invalidateResponseCache('LookupUser', $username);
        return [ 'blocks' => false, 'details' => $details];
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     * @throws ILSException
     */
    public function getAccountBlocks($patron)
    {
        $blocks = $this->getPatronBlocks($patron);
        $blocks = array_map(
            function ($block) {
                return $this->translateMessage($this->blockCodes[$block] ?? $block);
            },
            $blocks
        );
        return empty($blocks) ? false : array_values(array_unique($blocks));
    }

    /**
     * Helper function to build the request XML to cancel a request:
     *
     * @param string $username     Username for login
     * @param string $password     Password for login
     * @param string $patronAgency Agency for patron
     * @param string $itemAgencyId Agency ID for item
     * @param string $requestId    Id of the request to cancel
     * @param string $type         The type of request to cancel (Hold, etc)
     * @param string $itemId       Item identifier
     * @param string $patronId     Patron identifier
     *
     * @return string           NCIP request XML
     */
    protected function getCancelRequest(
        $username,
        $password,
        $patronAgency,
        $itemAgencyId,
        $requestId,
        $type,
        $itemId,
        $patronId = null
    ) {
        if (empty($requestId) && empty($itemId)) {
            throw new ILSException('No identifiers for CancelRequest');
        }
        $itemAgencyId = $this->determineToAgencyId($itemAgencyId);
        $ret = $this->getNCIPMessageStart() .
            '<ns1:CancelRequestItem>' .
            $this->getInitiationHeaderXml($patronAgency) .
            $this->getAuthenticationInputXml($username, $password);

        $ret .= $this->getUserIdXml($patronAgency, $patronId);

        if (!empty($requestId)) {
            $ret .=
                '<ns1:RequestId>' .
                    $this->element('AgencyId', $itemAgencyId) .
                    $this->element('RequestIdentifierValue', $requestId) .
                '</ns1:RequestId>';
        }
        if (!empty($itemId)) {
            $ret .= $this->getItemIdXml($itemAgencyId, $itemId);
        }
        $ret .= $this->getRequestTypeXml($type) .
            '</ns1:CancelRequestItem></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Helper function to build the request XML to request an item
     * (Hold, Storage Retrieval, etc)
     *
     * @param string $username         Username for login
     * @param string $password         Password for login
     * @param string $bibId            Bib Id of item to request
     * @param string $itemId           Id of item to request
     * @param string $patronAgencyId   Patron agency ID
     * @param string $itemAgencyId     Item agency ID
     * @param string $requestType      Type of the request (Hold, Callslip, etc)
     * @param string $requestScope     Level of request (title, item, etc)
     * @param string $lastInterestDate Last date interested in item
     * @param string $pickupLocation   Code of location to pickup request
     * @param string $patronId         Patron internal identifier
     *
     * @return string          NCIP request XML
     */
    protected function getRequest(
        $username,
        $password,
        $bibId,
        $itemId,
        $patronAgencyId,
        $itemAgencyId,
        $requestType,
        $requestScope,
        $lastInterestDate,
        $pickupLocation = null,
        $patronId = null
    ) {
        $ret = $this->getNCIPMessageStart() .
            '<ns1:RequestItem>' .
            $this->getInitiationHeaderXml($patronAgencyId) .
            $this->getAuthenticationInputXml($username, $password) .
            $this->getUserIdXml($patronAgencyId, $patronId) .
            $this->getBibliographicId($bibId) .
            $this->getItemIdXml($itemAgencyId, $itemId) .
            $this->getRequestTypeXml($requestType, $requestScope);

        if (!empty($pickupLocation)) {
            $ret .= $this->element('PickupLocation', $pickupLocation);
        }
        if (!empty($lastInterestDate)) {
            $ret .= $this->element('NeedBeforeDate', $lastInterestDate);
        }
        $ret .= '</ns1:RequestItem></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Helper function to build the request XML to renew an item:
     *
     * @param string $username       Username for login
     * @param string $password       Password for login
     * @param string $itemId         Id of item to renew
     * @param string $itemAgencyId   Agency of Item Id to renew
     * @param string $patronAgencyId Agency of patron
     * @param string $patronId       Internal patron id
     *
     * @return string          NCIP request XML
     */
    protected function getRenewRequest(
        $username,
        $password,
        $itemId,
        $itemAgencyId,
        $patronAgencyId,
        $patronId = null
    ) {
        $itemAgencyId = $this->determineToAgencyId($itemAgencyId);
        return $this->getNCIPMessageStart() .
            '<ns1:RenewItem>' .
            $this->getInitiationHeaderXml($patronAgencyId) .
            $this->getAuthenticationInputXml($username, $password) .
            $this->getUserIdXml($patronAgencyId, $patronId) .
            $this->getItemIdXml($itemAgencyId, $itemId) .
            '</ns1:RenewItem></ns1:NCIPMessage>';
    }

    /**
     * Helper function to build the request XML to log in a user
     * and/or retrieve loaned items / request information
     *
     * @param string $username       Username for login
     * @param string $password       Password for login
     * @param string $patronAgencyId Patron agency ID (optional)
     * @param array  $extras         Extra elements to include in the request
     * @param string $patronId       Patron internal identifier
     *
     * @return string          NCIP request XML
     */
    protected function getLookupUserRequest(
        $username,
        $password,
        $patronAgencyId = null,
        $extras = [],
        $patronId = null
    ) {
        return $this->getNCIPMessageStart() .
            '<ns1:LookupUser>' .
            $this->getInitiationHeaderXml($patronAgencyId) .
            $this->getAuthenticationInputXml($username, $password) .
            $this->getUserIdXml($patronAgencyId, $patronId) .
            implode('', $extras) .
            '</ns1:LookupUser></ns1:NCIPMessage>';
    }

    /**
     * Get LookupAgency Request XML message
     *
     * @param string|null $agency Agency Id
     *
     * @return string XML Document
     */
    public function getLookupAgencyRequest($agency = null)
    {
        $agency = $this->determineToAgencyId($agency);

        $ret = $this->getNCIPMessageStart() .
            '<ns1:LookupAgency>' .
            $this->getInitiationHeaderXml($agency) .
            $this->element('AgencyId', $agency);

        $desiredElementTypes = [
            'Agency Address Information', 'Agency User Privilege Type',
            'Application Profile Supported Type', 'Authentication Prompt',
            'Consortium Agreement', 'Organization Name Information',
        ];
        foreach ($desiredElementTypes as $elementType) {
            $ret .= $this->element('AgencyElementType', $elementType);
        }
        $ret .= '</ns1:LookupAgency></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Create Lookup Item Request
     *
     * @param string  $itemId       Item identifier
     * @param ?string $idType       Item identifier type
     * @param array   $desiredParts Needed data, available options are:
     * 'Bibliographic Description', 'Circulation Status', 'Electronic Resource',
     * 'Hold Queue Length', 'Date Due', 'Item Description',
     * 'Item Use Restriction Type', 'Location', 'Physical Condition',
     * 'Security Marker', 'Sensitization Flag'
     *
     * @return string XML document
     */
    protected function getLookupItemRequest(
        string $itemId,
        ?string $idType = null,
        array $desiredParts = ['Bibliographic Description']
    ): string {
        $agency = $this->determineToAgencyId();
        $ret = $this->getNCIPMessageStart() .
            '<ns1:LookupItem>' .
            $this->getInitiationHeaderXml($agency) .
            $this->getItemIdXml($agency, $itemId, $idType);
        foreach ($desiredParts as $current) {
            $ret .= $this->element('ItemElementType', $current);
        }
        $ret .= '</ns1:LookupItem></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Get InitiationHeader element XML string
     *
     * @param string $agency Agency of NCIP responder
     *
     * @return string
     */
    protected function getInitiationHeaderXml($agency = null)
    {
        $agency = $this->determineToAgencyId($agency);
        if (empty($agency) || empty($this->fromAgency)) {
            return '';
        }
        return '<ns1:InitiationHeader>' .
                '<ns1:FromAgencyId>' .
                    $this->element('AgencyId', $this->fromAgency) .
                '</ns1:FromAgencyId>' .
                '<ns1:ToAgencyId>' .
                    $this->element('AgencyId', $agency) .
                '</ns1:ToAgencyId>' .
            '</ns1:InitiationHeader>';
    }

    /**
     * Helper method for creating XML header and main element start
     *
     * @return string
     */
    protected function getNCIPMessageStart()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_02/ncip_v2_02.xsd">';
    }

    /**
     * Get XML string for AuthenticationInput element
     *
     * @param string $username User login
     * @param string $password User password
     *
     * @return string XML string for AuthenticationInput element
     */
    protected function getAuthenticationInputXml($username, $password)
    {
        return (!empty($username) && !empty($password))
            ? '<ns1:AuthenticationInput>' .
                $this->element('AuthenticationInputData', $username) .
                $this->element('AuthenticationDataFormatType', 'text') .
                $this->element('AuthenticationInputType', 'Username') .
            '</ns1:AuthenticationInput>' .
            '<ns1:AuthenticationInput>' .
                $this->element('AuthenticationInputData', $password) .
                $this->element('AuthenticationDataFormatType', 'text') .
                $this->element('AuthenticationInputType', 'Password') .
            '</ns1:AuthenticationInput>'
            : '';
    }

    /**
     * Get ItemId element XML
     *
     * @param string      $agency Agency id
     * @param string      $itemId Item id
     * @param null|string $idType Item id type
     *
     * @return string ItemId element XML string
     */
    protected function getItemIdXml($agency, $itemId, $idType = null)
    {
        $agency = $this->determineToAgencyId($agency);
        $ret = '<ns1:ItemId>' . $this->element('AgencyId', $agency);
        if ($idType !== null) {
            $ret .= $this->element('ItemIdentifierType', $idType);
        }
        $ret .= $this->element('ItemIdentifierValue', $itemId);
        $ret .= '</ns1:ItemId>';
        return $ret;
    }

    /**
     * Get UserId element XML
     *
     * @param string $patronAgency Patron agency id
     * @param string $patronId     Internal patron identifier
     *
     * @return string Get UserId element XML string
     */
    protected function getUserIdXml($patronAgency, $patronId = null)
    {
        $agency = $this->determineToAgencyId($patronAgency);
        if ($patronId !== null) {
            return '<ns1:UserId>' .
                $this->element('AgencyId', $agency) .
                $this->element('UserIdentifierType', 'Institution Id Number') .
                $this->element('UserIdentifierValue', $patronId) .
            '</ns1:UserId>';
        }
        return '';
    }

    /**
     * Get request type elements XML
     *
     * @param string $type  Request type
     * @param string $scope Request type scope (defaults to 'Bibliographic Item')
     *
     * @return string RequestType and RequestScopeType element XML string
     */
    protected function getRequestTypeXml($type, $scope = 'Bibliographic Item')
    {
        return
            $this->element('RequestType', $type) .
            $this->element('RequestScopeType', $scope);
    }

    /**
     * Get BibliographicId element
     *
     * @param string $id Bibliographic item id
     *
     * @return string Get BibliographicId XML element string
     */
    protected function getBibliographicId($id)
    {
        return '<ns1:BibliographicId>' .
            '<ns1:BibliographicItemId>' .
                $this->element('BibliographicItemIdentifier', $id) .
                $this->element(
                    'BibliographicItemIdentifierCode',
                    'Legal Deposit Number'
                ) .
            '</ns1:BibliographicItemId>' .
        '</ns1:BibliographicId>';
    }

    /**
     * Throw an exception if an NCIP error is found
     *
     * @param \SimpleXMLElement $response from NCIP call
     *
     * @throws ILSException
     * @return void
     */
    protected function checkResponseForError($response)
    {
        $error = $response->xpath(
            '//ns1:Problem'
        );
        if (!empty($error)) {
            throw new ILSException($error[0]);
        }
    }

    /**
     * Register namespace(s) for an XML element/tree
     *
     * @param \SimpleXMLElement $element Element to register namespace for
     *
     * @return void
     */
    protected function registerNamespaceFor(\SimpleXMLElement $element)
    {
        $element->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
    }

    /**
     * Convert a date to display format
     *
     * @param string $date Date and time string
     *
     * @return string
     */
    protected function displayDate($date)
    {
        return $this->convertDateOrTime($date);
    }

    /**
     * Convert a time to display format
     *
     * @param string $date Date and time string
     *
     * @return string
     */
    protected function displayTime($date)
    {
        return $this->convertDateOrTime($date, 'time');
    }

    /**
     * Convert datetime to display format
     *
     * @param string $dateString Datetime string
     * @param string $dateOrTime Desired datetime part, could be 'date' or 'time'
     *
     * @return string
     */
    protected function convertDateOrTime($dateString, $dateOrTime = 'date')
    {
        if (!$dateString) {
            return '';
        }
        $createFormats = ['Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:sP'];
        $formatted = '';
        foreach ($createFormats as $format) {
            try {
                $formatted = ($dateOrTime === 'time')
                    ? $this->dateConverter->convertToDisplayTime(
                        $format,
                        $dateString
                    )
                    : $this->dateConverter->convertToDisplayDate(
                        $format,
                        $dateString
                    );
            } catch (DateException $exception) {
                continue;
            }
        }
        return $formatted;
    }

    /**
     * Get Hold Type
     *
     * @param string $status Status string from CirculationStatus NCIP element
     *
     * @return string Hold type, could be 'Hold' or 'Recall'
     */
    protected function getHoldType(string $status)
    {
        return in_array(strtolower($status), $this->availableStatuses)
            ? 'Hold' : 'Recall';
    }

    /**
     * Is an item available?
     *
     * @param string $status Status string from CirculationStatus NCIP element
     *
     * @return bool Return true if item is available
     */
    protected function isAvailable(string $status)
    {
        return in_array(strtolower($status), $this->availableStatuses);
    }

    /**
     * Is request cancelled?
     *
     * @param string $status Status string from RequestStatusType NCIP element
     *
     * @return bool Return true if a request was cancelled
     */
    protected function isRequestCancelled(string $status)
    {
        return !in_array(strtolower($status), $this->activeRequestStatuses);
    }

    /**
     * Is request of desired type?
     *
     * @param \SimpleXMLElement $request RequestedItem NCIP Element
     * @param array             $types   Array of types to check against
     *
     * @return bool Return true if request is of desired type
     */
    protected function checkRequestType(\SimpleXMLElement $request, array $types)
    {
        $requestType = $request->xpath('ns1:RequestType');
        $requestType = (string)$requestType[0];
        return in_array(strtolower($requestType), $types);
    }

    /**
     * Check if item is holdable
     *
     * @param \SimpleXMLElement $itemInformation Item information element
     *
     * @return bool
     */
    protected function isItemHoldable(\SimpleXMLElement $itemInformation): bool
    {
        $restrictions = $itemInformation->xpath(
            'ns1:ItemOptionalFields/ns1:ItemUseRestrictionType'
        );
        foreach ($restrictions as $restriction) {
            $restStr = strtolower((string)$restriction);
            if (in_array($restStr, $this->notHoldableRestriction)) {
                return false;
            }
        }
        $statuses = $itemInformation->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        foreach ($statuses as $status) {
            $statusStr = strtolower((string)$status);
            if (in_array($statusStr, $this->notHoldableStatuses)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine ToAgencyId
     *
     * @param array|string|null $agency List of available (configured) agencies or
     * Agency Id
     *
     * @return string|null First Agency Id found
     */
    protected function determineToAgencyId($agency = null)
    {
        // FIXME: We are using the first defined agency, it will probably not work in
        // consortium scenario
        if (empty($agency)) {
            $keys = array_keys($this->agency);
            $agency = $keys[0];
        }

        return is_array($agency) ? $agency[0] : $agency;
    }

    /**
     * Get Lookup user response
     *
     * @param string      $username User name
     * @param string|null $password User password
     *
     * @return \SimpleXMLElement
     * @throws ILSException
     */
    protected function getLookupUserResponse(
        string $username,
        ?string $password = null
    ): \SimpleXMLElement {
        if (isset($this->responses['LookupUser'][$username])) {
            return $this->responses['LookupUser'][$username];
        }
        $extras = $this->getLookupUserExtras();
        $request = $this->getLookupUserRequest(
            $username,
            $password,
            $this->determineToAgencyId(),
            $extras,
            $username
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);
        $this->responses['LookupUser'][$username] = $response;
        return $response;
    }

    /**
     * Creates array for Lookup user desired information
     *
     * @return array
     */
    protected function getLookupUserExtras(): array
    {
        return [
            $this->element('UserElementType', 'User Address Information'),
            $this->element('UserElementType', 'Name Information'),
            $this->element('UserElementType', 'User Privilege'),
            $this->element('UserElementType', 'Block Or Trap'),
            '<ns1:LoanedItemsDesired />',
            '<ns1:RequestedItemsDesired />',
            '<ns1:UserFiscalAccountDesired />',
        ];
    }

    /**
     * Parse http response into XML object representation
     *
     * @param string $xmlString XML string
     *
     * @return \SimpleXMLElement
     * @throws ILSException
     */
    protected function parseXml(string $xmlString): \SimpleXMLElement
    {
        $result = @simplexml_load_string($xmlString);
        if ($result === false) {
            throw new ILSException('Problem parsing XML: ' . $xmlString);
        }
        // If no namespaces are used, add default one and reload the document
        if (empty($result->getNamespaces())) {
            $result->addAttribute('xmlns', 'http://www.niso.org/2008/ncip');
            $xml = $result->asXML();
            $result = @simplexml_load_string($xml);
            if ($result === false) {
                throw new ILSException('Problem parsing XML: ' . $xmlString);
            }
        }
        $this->registerNamespaceFor($result);
        return $result;
    }

    /**
     * Parse all reported problem and return its string representation
     *
     * @param string $xmlString XML string
     *
     * @return string
     */
    protected function parseProblem(string $xmlString): string
    {
        $xml = $this->parseXml($xmlString);
        $problems = $xml->xpath('//ns1:Problem');
        if (empty($problems)) {
            return 'Cannot identify problem in response: ' . $xmlString;
        }
        return $this->getProblemDescription($xml);
    }

    /**
     * Get problem description as one string
     *
     * @param \SimpleXMLElement $xml              XML response
     * @param array|string[]    $elements         Which of Problem subelements
     * return in description - defaulting to full list: ProblemType, ProblemDetail,
     * ProblemElement and ProblemValue
     * @param bool              $withElementNames Whether to add element names as
     * value labels (for example for debug purposes)
     *
     * @return string
     */
    protected function getProblemDescription(
        \SimpleXMLElement $xml,
        array $elements = [
            'ProblemType', 'ProblemDetail', 'ProblemElement', 'ProblemValue',
        ],
        bool $withElementNames = true
    ): string {
        $problems = $xml->xpath('//ns1:Problem');
        if (empty($problems)) {
            return '';
        }
        $allProblems = [];
        foreach ($problems as $problem) {
            $this->registerNamespaceFor($problem);
            $oneProblem = [];
            foreach ($elements as $element) {
                $detail = $problem->xpath('ns1:' . $element);
                if (!empty($detail)) {
                    $oneProblem[] = $withElementNames
                        ? $element . ': ' . (string)$detail[0]
                        : (string)$detail[0];
                }
            }
            $allProblems[] = implode(', ', $oneProblem);
        }
        return implode(', ', $allProblems);
    }

    /**
     * Creates scheme attribute based on $this->schemes array
     *
     * @param string $element         Element name
     * @param string $namespacePrefix Namespace identifier
     *
     * @return string Scheme attribute or empty string
     */
    protected function schemeAttr(string $element, $namespacePrefix = 'ns1'): string
    {
        return isset($this->schemes[$element])
            ? ' ' . $namespacePrefix . ':Scheme="' . $this->schemes[$element] . '"'
            : '';
    }

    /**
     * Creates simple element as XML string
     *
     * @param string $elementName     Element name
     * @param string $text            Content of element
     * @param string $namespacePrefix Namespace
     *
     * @return string XML string
     */
    protected function element(
        string $elementName,
        string $text,
        string $namespacePrefix = 'ns1'
    ): string {
        $fullElementName = $namespacePrefix . ':' . $elementName;
        return '<' . $fullElementName .
            $this->schemeAttr($elementName, $namespacePrefix) . '>' .
            htmlspecialchars($text) .
            '</' . $fullElementName . '>';
    }

    /**
     * Parse the LocationNameInstanceElement for multi-level locations
     *
     * @param array $locations Array of \SimpleXMLElement objects for
     * LocationNameInstance element
     *
     * @return array Two item, 1st and 2nd level from LocationNameInstance
     */
    protected function parseLocationInstance(array $locations): array
    {
        $location = $collection = null;
        $initialLevel = 0;
        foreach ($locations as $loc) {
            $this->registerNamespaceFor($loc);
            $name = $loc->xpath('ns1:LocationNameValue');
            $name = (string)($name[0] ?? '');
            $level = $loc->xpath('ns1:LocationNameLevel');
            $level = !empty($level) ? (int)($level[0]) : 1;
            if ($initialLevel === 0) {
                $initialLevel = $level;
            }
            if ($level === $initialLevel) {
                $location = $name;
            } elseif ($level === $initialLevel + 1) {
                $collection = $name;
            }
        }
        return [$location, $collection];
    }

    /**
     * Translate a message from ILS
     *
     * @param string $message Message to be translated
     *
     * @return string
     */
    protected function translateMessage(string $message): string
    {
        return $this->translate($this->translationDomain . '::' . $message);
    }

    /**
     * Invalidate L1 cache for responses
     *
     * @param string $message NCIP message type - currently only 'LookupUser'
     * @param string $key     Cache key (For LookupUser its cat_username)
     *
     * @return void
     */
    protected function invalidateResponseCache(string $message, string $key): void
    {
        unset($this->responses['LookupUser'][$key]);
    }

    /**
     * Get all bibliographic records
     *
     * @param array $idList     List of bibliographic IDs.
     * @param array $agencyList List of possible toAgency values
     *
     * @return \SimpleXMLElement[]
     * @throws ILSException
     */
    protected function getBibs(array $idList, array $agencyList): array
    {
        $bibs = [];
        $nextItemToken = [];
        $request = true;
        $page = 0;
        while ($request) {
            $page++;
            $resumption = !empty($nextItemToken) ? (string)$nextItemToken[0] : null;
            $request = $this->getStatusRequest($idList, $resumption, $agencyList);
            $response = $this->sendRequest($request);
            $bibs = array_merge(
                $bibs,
                $response->xpath('ns1:LookupItemSetResponse/ns1:BibInformation')
            );
            $nextItemToken = $response->xpath('//ns1:NextItemToken');
            $request = $this->isNextItemTokenEmpty($nextItemToken);
            if ($page == $this->maxNumberOfPages) {
                break;
            }
        }
        return $bibs;
    }

    /**
     * Check NextItemToken for emptiness
     *
     * @param \SimpleXMLElement[] $nextItemToken Next item token elements from NCIP
     * Response
     *
     * @return bool
     */
    protected function isNextItemTokenEmpty(array $nextItemToken): bool
    {
        return !empty($nextItemToken) && (string)$nextItemToken[0] !== '';
    }
}
