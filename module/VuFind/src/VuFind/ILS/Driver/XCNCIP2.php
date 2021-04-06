<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 7
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

use VuFind\Config\Locator as ConfigLocator;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class XCNCIP2 extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

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
     * Are renewals disabled for this driver instance? Defaults to false
     *
     * @var bool
     */
    protected $disableRenewals = false;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
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
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        $this->url = $this->config['Catalog']['url'];
        $this->fromAgency = $this->config['Catalog']['fromAgency'] ?? null;
        if ($this->config['Catalog']['consortium']) {
            $this->consortium = true;
            foreach ($this->config['Catalog']['agency'] as $agency) {
                $this->agency[$agency] = 1;
            }
        } else {
            $this->consortium = false;
            if (is_array($this->config['Catalog']['agency'])) {
                $this->agency[$this->config['Catalog']['agency'][0]] = 1;
            } else {
                $this->agency[$this->config['Catalog']['agency']] = 1;
            }
        }
        $this->disableRenewals
            = $this->config['Catalog']['disableRenewals'] ?? false;
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
            throw new ILSException(
                'XCNCIP2 ILS driver bad configuration. You should set up ' .
                'one of these options: "pickupLocationsFile" or ' .
                '"pickupLocationsFromNCIP"'
            );
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
        $pickupLocationsFile
            = ConfigLocator::getConfigPath($filename, 'config/vufind');
        if (!file_exists($pickupLocationsFile)) {
            throw new ILSException(
                "Cannot load pickup locations file: {$pickupLocationsFile}."
            );
        }
        if (($handle = fopen($pickupLocationsFile, "r")) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $agencyId = $data[0] . '|' . $data[1];
                $this->pickupLocations[$agencyId] = [
                    'locationId' => $agencyId,
                    'locationDisplay' => $data[2]
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
                'locationId' => $agencyId . '|' . (string)$id[0],
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
     * @return object     SimpleXMLElement parsed from response
     */
    protected function sendRequest($xml)
    {
        $this->debug('Sendig NCIP request: ' . $xml);
        // Make the NCIP request:
        try {
            $client = $this->httpService->createClient($this->url);
            // Set timeout value
            $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
            $client->setOptions(['timeout' => $timeout]);
            $client->setRawBody($xml);
            $client->setEncType('application/xml; charset=UTF-8');
            $result = $client->setMethod('POST')->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        // Process the NCIP response:
        $response = $result->getBody();
        $this->debug('Got NCIP response: ' . $response);
        $result = @simplexml_load_string($response);
        if (is_a($result, 'SimpleXMLElement')) {
            // If no namespaces are used, add default one and reload the document
            if (empty($result->getNamespaces())) {
                $result->addAttribute('xmlns', 'http://www.niso.org/2008/ncip');
                $xml = $result->asXML();
                $result = @simplexml_load_string($xml);
                if ($result === false) {
                    throw new ILSException('Problem parsing XML: ' . $xml);
                }
            }
            $this->registerNamespaceFor($result);
            return $result;
        } else {
            throw new ILSException("Problem parsing XML");
        }
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
     * @param array  $current     Current XCItemAvailability chunk.
     * @param string $aggregateId (Aggregate) ID of the consortial record
     * @param string $bibId       Bib ID of one of the consortial record's source
     * record(s)
     *
     * @return array
     */
    protected function getHoldingsForChunk($current, $aggregateId = null,
        $bibId = null
    ) {
        $this->registerNamespaceFor($current);

        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = (string)($status[0] ?? '');

        $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
        $itemType = $current->xpath('ns1:ItemId/ns1:ItemIdentifierType');
        $itemType = (string)($itemType[0] ?? '');

        $itemAgencyId = $current->xpath('ns1:ItemId/ns1:AgencyId');

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

        $tmp = $current->xpath(
            'ns1:ItemOptionalFields/ns1:Location/' .
            'ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue'
        );
        $location = !empty($tmp) ? (string)$tmp[0] : null;

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

        // Build return array:
        $return = [
            'id' => $aggregateId,
            'availability' =>  $this->isAvailable($status),
            'status' => $status,
            'item_id' => (string)($itemId[0] ?? ''),
            'bib_id' => $bibId,
            'item_agency_id' => (string)($itemAgencyId[0] ?? ''),
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => $itemCallNo,
            'duedate' => '',        // not supported
            'volume' => $volume,
            'number' => $number,
            'barcode' => ($itemType === 'Barcode')
                ? (string)$itemId[0] : 'Unknown barcode',
            'is_holdable'  => true,
            'addLink' => true,
            'holdtype' => $this->getHoldType($status),
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => 'true',
        ];
        if (strtolower($status) === 'circulation status undefined') {
            $return['use_unknown_message'] = true;
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
            $xml .= '<ns1:ItemElementType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/' .
                'itemelementtype/itemelementtype.scm">' .
                htmlspecialchars($current) . '</ns1:ItemElementType>';
        }

        // Add resumption token if necessary:
        if (!empty($resumption)) {
            $xml .= '<ns1:NextItemToken>' . htmlspecialchars($resumption) .
                '</ns1:NextItemToken>';
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
                        $chunk['location'] = $chunk['location']
                            ?? $holdingLocation ?? null;
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
    public function getConsortialHoldings($id, array $patron = null,
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
        $request = $this->getStatusRequest($idList, null, $agencyList);
        $response = $this->sendRequest($request);

        $bibs = $response->xpath(
            'ns1:LookupItemSetResponse/ns1:BibInformation'
        );

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
                $holdingLocation = $holding->xpath(
                    'ns1:Location/ns1:LocationName/ns1:LocationNameInstance/' .
                    'ns1:LocationNameValue'
                );
                $holdingLocation = !empty($holdingLocation)
                    ? (string)$holdingLocation[0] : null;

                // Build the array of holdings:
                foreach ($avail as $current) {
                    $chunk = $this->getHoldingsForChunk(
                        $current, $aggregateId, $bibId
                    );
                    $chunk['callnumber'] = empty($chunk['callnumber']) ?
                        $holdCallNo : $chunk['callnumber'];
                    $chunk['eresource'] = $eResource;
                    $chunk['location'] = $chunk['location']
                        ?? $holdingLocation ?? null;
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
        // TODO: we somehow need to figure out 'patronAgencyId' in the
        // consortium=true case
        //$request = $this->getLookupUserRequest(
        //    $username, $password, 'patronAgencyId'
        //);

        $extras = [
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
            'schemes/userelementtype/userelementtype.scm">' .
            'User Address Information' .
            '</ns1:UserElementType>',
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
            'schemes/userelementtype/userelementtype.scm">' .
            'Name Information' .
            '</ns1:UserElementType>'
        ];

        $request = $this->getLookupUserRequest($username, $password, null, $extras);

        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
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

        $patron = null;
        if (!empty($id)) {
            // Fill in basic patron details:
            $patron = [
                'id' => (string)$id[0],
                'patronAgencyId' => (string)$patronAgencyId[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => !empty($email) ? (string)$email[0] : null,
                'major' => null,
                'college' => null,
                'firstname' => (string)$first[0],
                'lastname' => (string)$last[0],
            ];
        }
        return $patron;
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
        $extras = ['<ns1:LoanedItemsDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patronAgencyId'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

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
            if ($bibId === null || $itemAgencyId === null) {
                $itemType = $current->xpath('ns1:ItemId/ns1:ItemIdentifierType');
                $itemType = !empty($itemType) ? (string)$itemType[0] : null;
                $itemRequest = $this->getLookupItemRequest($itemId, $itemType);
                $itemResponse = $this->sendRequest($itemRequest);
            }
            if ($bibId === null) {
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
                $bibId = !empty($bibId) ? (string)$bibId[0] : "1";
            }
            if ($itemAgencyId === null) {
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

            $retVal[] = [
                'id' => $bibId,
                'item_agency_id' => $itemAgencyId,
                'patronAgencyId' => $patron['patronAgencyId'],
                'duedate' => $due,
                'title' => (string)$title[0],
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
        $extras = ['<ns1:UserFiscalAccountDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patronAgencyId'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $list = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserFiscalAccount/ns1:AccountDetails'
        );

        $fines = [];
        foreach ($list as $current) {
            $this->registerNamespaceFor($current);

            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue'
            );
            $amount = (string)$tmp[0];
            $tmp = $current->xpath('ns1:AccrualDate');
            $date = $this->displayDate(!empty($tmp) ? (string)$tmp[0] : null);
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:FiscalTransactionType'
            );
            $desc = (string)$tmp[0];

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
                'id' => $id
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
        $extras = ['<ns1:RequestedItemsDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patronAgencyId'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $retVal = [];
        $requests = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');

        foreach ($requests as $current) {
            $this->registerNamespaceFor($current);
            $id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier' .
                ' | ' .
                'ns1:Ext/ns1:BibliographicDescription/' .
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
                    'id' => (string)$id[0],
                    'create' => $created,
                    'expire' => $expireDate,
                    'title' => (string)$title[0],
                    'position' => !empty($pos) ? (string)$pos[0] : null,
                    'requestId' => !empty($requestId) ? (string)$requestId[0] : null,
                    'item_agency_id' => !empty($itemAgencyId)
                        ? (string)$itemAgencyId[0] : null,
                    'canceled' => $this->isRequestCancelled($status),
                    'item_id' => (string)$itemId[0],
                    'location' => (string)$pickupLocation[0],
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
        $extras = [
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'User Address Information' .
            '</ns1:UserElementType>',
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'Name Information' .
            '</ns1:UserElementType>'
        ];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patronAgencyId'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

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

        // TODO: distinguish between permanent and other types of addresses; look
        // at the UnstructuredAddressType field and handle multiple options.
        $address = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
            'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
            'ns1:UnstructuredAddress/ns1:UnstructuredAddressData'
        );
        $address = explode("\n", trim((string)$address[0]));
        return [
            'firstname' => (string)$first[0],
            'lastname' => (string)$last[0],
            'address1' => $address[0] ?? '',
            'address2' => ($address[1] ?? '') .
                (isset($address[2]) ? ', ' . $address[2] : ''),
            'zip' => $address[3] ?? '',
            'phone' => '',  // TODO: phone number support
            'group' => ''
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
        // TODO
        return [];
    }

    /**
     * Public Function which retrieves Holds, StorageRetrivalRequests, and
     * Consortial settings from the driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        if ($function == 'Holds') {
            return [
                'HMACKeys' => 'item_id:holdtype:item_agency_id:id:bib_id',
                'extraHoldFields' => 'comments:pickUpLocation:requiredByDate',
                'defaultRequiredDate' => '0:2:0',
                'consortium' => $this->consortium,
            ];
        }
        if ($function == 'StorageRetrievalRequests') {
            return [
                'HMACKeys' => 'id:item_id:item_agency_id:id:bib_id',
                'extraFields' => 'comments:pickUpLocation:requiredByDate:item-issue',
                'defaultRequiredDate' => '0:2:0',
            ];
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
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron, $holdDetails = null)
    {
        return $this->pickupLocations[$patron['patronAgencyId']][0]['locationId'];
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
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationId and
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
     * @param patron $patron An array of patron data
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
            "|" . $checkOutDetails['item_id'];
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
        $msgPrefix = ($type == 'Stack Retrieval') ? 'Storage Retrieval ' : '';
        $username = $details['patron']['cat_username'];
        $password = $details['patron']['cat_password'];
        $bibId = $details['bib_id'];
        $itemId = $details['item_id'];
        $pickUpLocation = $details['pickUpLocation'];
        list($pickUpAgency, $pickUpLocation) = explode("|", $pickUpLocation);
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-'
            . substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";
        $successReturn = [
            'success' => true,
            'sysMessage' => $msgPrefix . 'Request Successful.'
        ];
        $failureReturn = [
            'success' => false,
            'sysMessage' => $msgPrefix . 'Request Not Successful.'
        ];

        $request = $this->getRequest(
            $username, $password, $bibId, $itemId,
            $details['patron']['patronAgencyId'], $details['item_agency_id'],
            $type, "Item", $lastInterestDate, $pickUpLocation
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
            return $failureReturn;
        }

        return !empty($success) ? $successReturn : $failureReturn;
    }

    /**
     * General cancel request method
     *
     * Attempts to Cancel a request on a particular item. The data in
     * $cancelDetails['details'] is determined by getCancel*Details().
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
            list($itemAgencyId, $requestId, $itemId) = explode("|", $detail);
            $request = $this->getCancelRequest(
                $username, $password, $patronAgency,
                $itemAgencyId, $requestId, $type,
                $itemId
            );
            $cancelRequestResponse = $this->sendRequest($request);
            $userId = $cancelRequestResponse->xpath(
                'ns1:CancelRequestItemResponse/' .
                'ns1:UserId/ns1:UserIdentifierValue'
            );
            $itemId = $itemId ?? $requestId;
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
        return $details['item_agency_id'] .
            "|" . $details['requestId'] .
            "|" . $details['item_id'];
    }

    /**
     * Get Cancel Hold Details
     *
     * This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.  item id is used as the
     * array key in the response.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
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
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        return $this->getCancelRequestDetails($details);
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
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
        foreach ($renewDetails['details'] as $detail) {
            list($agencyId, $itemId) = explode("|", $detail);
            $failureReturn = [
                "success" => false,
                "item_id" => $itemId,
            ];
            if ($this->disableRenewals) {
                $details[$itemId] = $failureReturn;
                continue;
            }
            $request = $this->getRenewRequest(
                $renewDetails['patron']['cat_username'],
                $renewDetails['patron']['cat_password'], $itemId,
                $agencyId,
                $renewDetails['patron']['patronAgencyId']
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
                    "success" => true,
                    "new_date" => $dueDate,
                    "new_time" => $dueTime,
                    "item_id" => $itemId,
                ];
            } else {
                $details[$itemId] = $failureReturn;
            }
        }

        return [ 'blocks' => false, 'details' => $details];
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
    protected function getCancelRequest($username,
        $password,
        $patronAgency,
        $itemAgencyId,
        $requestId,
        $type,
        $itemId,
        $patronId = null
    ) {
        if ($requestId === null && $itemId === null) {
            throw new ILSException('No identifiers for CancelRequest');
        }

        $ret = $this->getNCIPMessageStart() .
            '<ns1:CancelRequestItem>' .
            $this->getInitiationHeaderXml($patronAgency) .
            $this->getAuthenticationInputXml($username, $password);

        $ret .= $this->getUserIdXml($patronAgency, $patronId);

        if ($requestId !== null) {
            $ret .=
                    '<ns1:RequestId>' .
                        '<ns1:AgencyId>' .
                            htmlspecialchars($itemAgencyId) .
                        '</ns1:AgencyId>' .
                        '<ns1:RequestIdentifierValue>' .
                            htmlspecialchars($requestId) .
                        '</ns1:RequestIdentifierValue>' .
                    '</ns1:RequestId>';
        }
        if ($itemId !== null) {
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
    protected function getRequest($username, $password, $bibId, $itemId,
        $patronAgencyId, $itemAgencyId, $requestType, $requestScope,
        $lastInterestDate, $pickupLocation = null, $patronId = null
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
            $ret .= '<ns1:PickupLocation>' .
                htmlspecialchars($pickupLocation) .
            '</ns1:PickupLocation>';
        }
        if (!empty($lastInterestDate)) {
            $ret .= '<ns1:NeedBeforeDate>' .
                htmlspecialchars($lastInterestDate) .
            '</ns1:NeedBeforeDate>';
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
    protected function getRenewRequest($username,
        $password,
        $itemId,
        $itemAgencyId,
        $patronAgencyId,
        $patronId = null
    ) {
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
     * @param string $extras         Extra elements to include in the request
     * @param string $patronId       Patron internal identifier
     *
     * @return string          NCIP request XML
     */
    protected function getLookupUserRequest($username, $password,
        $patronAgencyId = null, $extras = [], $patronId = null
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
            '<ns1:AgencyId>' . htmlspecialchars($agency) . '</ns1:AgencyId>';

        $desiredElementTypes = [
            'Agency Address Information', 'Agency User Privilege Type',
            'Application Profile Supported Type', 'Authentication Prompt',
            'Consortium Agreement', 'Organization Name Information'
        ];
        foreach ($desiredElementTypes as $elementType) {
            $ret .= '<ns1:AgencyElementType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes/' .
                'agencyelementtype/agencyelementtype.scm">' .
                    $elementType .
                '</ns1:AgencyElementType>';
        }
        $ret .= '</ns1:LookupAgency></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Create Lookup Item Request
     *
     * @param string $itemId Item identifier
     * @param string $idType Item identifier type
     *
     * @return string XML document
     */
    protected function getLookupItemRequest($itemId, $idType = null)
    {
        $keys = array_keys($this->agency);
        $agency = $keys[0];

        $ret = $this->getNCIPMessageStart() .
            '<ns1:LookupItem>' .
            $this->getInitiationHeaderXml($agency) .
            $this->getItemIdXml($agency, $itemId, $idType) .
            '<ns1:ItemElementType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/' .
                'itemelementtype/itemelementtype.scm">' .
                'Bibliographic Description</ns1:ItemElementType>' .
        '</ns1:LookupItem></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Get InitiationHeader element XML string
     *
     * @param string $agency Agency of NCIP responder
     *
     * @return string
     */
    protected function getInitiationHeaderXml($agency = '')
    {
        if (empty($agency) || empty($this->fromAgency)) {
            return '';
        }
        return '<ns1:InitiationHeader>' .
                '<ns1:FromAgencyId>' .
                    '<ns1:AgencyId>' .
                        htmlspecialchars($this->fromAgency) .
                    '</ns1:AgencyId>' .
                '</ns1:FromAgencyId>' .
                '<ns1:ToAgencyId>' .
                    '<ns1:AgencyId>' .
                        htmlspecialchars($agency) .
                    '</ns1:AgencyId>' .
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
                '<ns1:AuthenticationInputData>' .
                    htmlspecialchars($username) .
                '</ns1:AuthenticationInputData>' .
                '<ns1:AuthenticationDataFormatType>' .
                    'text' .
                '</ns1:AuthenticationDataFormatType>' .
                '<ns1:AuthenticationInputType>' .
                    'Username' .
                '</ns1:AuthenticationInputType>' .
            '</ns1:AuthenticationInput>' .
            '<ns1:AuthenticationInput>' .
                '<ns1:AuthenticationInputData>' .
                    htmlspecialchars($password) .
                '</ns1:AuthenticationInputData>' .
                '<ns1:AuthenticationDataFormatType>' .
                    'text' .
                '</ns1:AuthenticationDataFormatType>' .
                '<ns1:AuthenticationInputType>' .
                    'Password' .
                '</ns1:AuthenticationInputType>' .
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
        $ret = '<ns1:ItemId><ns1:AgencyId>' .
            htmlspecialchars($agency) . '</ns1:AgencyId>';
        if ($idType !== null) {
            $ret .= '<ns1:ItemIdentifierType>' .
                htmlspecialchars($idType) . '</ns1:ItemIdentifierType>';
        }
        $ret .= '<ns1:ItemIdentifierValue>' .
            htmlspecialchars($itemId) . '</ns1:ItemIdentifierValue>' .
            '</ns1:ItemId>';
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
        if ($patronId !== null) {
            return '<ns1:UserId>' .
                '<ns1:AgencyId>' .
                    htmlspecialchars($patronAgency) .
                '</ns1:AgencyId>' .
                '<ns1:UserIdentifierType>Institution Id Number' .
                '</ns1:UserIdentifierType>' .
                '<ns1:UserIdentifierValue>' .
                    htmlspecialchars($patronId) .
                '</ns1:UserIdentifierValue>' .
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
        return '<ns1:RequestType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes/' .
                'requesttype/requesttype.scm">' .
                htmlspecialchars($type) .
            '</ns1:RequestType>' .
            '<ns1:RequestScopeType ' .
                'ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes/' .
                'requestscopetype/requestscopetype.scm">' .
                htmlspecialchars($scope) .
            '</ns1:RequestScopeType>';
    }

    /**
     * Get BibliographicId element
     *
     * @param string $id Bibliographic item id
     *
     * @return string Get BibiographicId XML element string
     */
    protected function getBibliographicId($id)
    {
        return '<ns1:BibliographicId>' .
            '<ns1:BibliographicItemId>' .
                '<ns1:BibliographicItemIdentifier>' .
                    htmlspecialchars($id) .
                '</ns1:BibliographicItemIdentifier>' .
                '<ns1:BibliographicItemIdentifierCode ' .
                    'ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/' .
                    'schemes/bibliographicitemidentifiercode/' .
                    'bibliographicitemidentifiercode.scm">' .
                    'Legal Deposit Number' .
                '</ns1:BibliographicItemIdentifierCode>' .
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
            '//ns1:Problem/ns1:ProblemDetail'
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
                        $format, $dateString
                    )
                    : $this->dateConverter->convertToDisplayDate(
                        $format, $dateString
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
        if (null === $agency) {
            $keys = array_keys($this->agency);
            $agency = $keys[0];
        }

        return is_array($agency) ? $agency[0] : $agency;
    }
}
