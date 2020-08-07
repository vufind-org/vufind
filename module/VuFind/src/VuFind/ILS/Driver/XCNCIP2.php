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
    protected $pickupLocations;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

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
                $agency_ID = $data[0] . '|' . $data[1];
                $this->pickupLocations[$agency_ID] = [
                    'locationID' => $agency_ID,
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
        $agencyId = !empty($agencyId) ? (string)$agencyId[0] : '';
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
     * @return object     SimpleXMLElement parsed from response
     */
    protected function sendRequest($xml)
    {
        // Make the NCIP request:
        try {
            $client = $this->httpService->createClient($this->url);
            // Set timeout value
            $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
            $client->setOptions(['timeout' => $timeout]);
            $client->setRawBody($xml);
            $client->setEncType('application/xml; "charset=utf-8"');
            $result = $client->setMethod('POST')->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        // Process the NCIP response:
        $response = $result->getBody();
        $result = @simplexml_load_string($response);
        if (is_a($result, 'SimpleXMLElement')) {
            // If no namespaces are used, add default one and reload the document
            if (empty($result->getNamespaces())) {
                $result->addAttribute('xmlns', 'http://www.niso.org/2008/ncip');
                $result = @simplexml_load_string($result->asXML());
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
        $status = empty($status) ? '' : (string)$status[0];

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
            'availability' => in_array(
                strtolower($status), ["not charged", "available on shelf"]
            ),
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
     * @param array  $current      Current XCItemAvailability chunk.
     * @param string $aggregate_id (Aggregate) ID of the consortial record
     * @param string $bib_id       Bib ID of one of the consortial record's source
     * record(s)
     *
     * @return array
     */
    protected function getHoldingsForChunk($current, $aggregate_id = null,
        $bib_id = null
    ) {
        // Maintain an internal static count of line numbers:
        static $number = 1;

        $this->registerNamespaceFor($current);

        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];

        $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');

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
        $location = (string)$tmp[0];

        $itemCallNo = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = (string)$itemCallNo[0];

        $number = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/' .
            'ns1:CopyNumber'
        );
        $number = (string)$number[0];

        $volume = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/' .
            'ns1:HoldingsInformation/ns1:UnstructuredHoldingsData'
        );
        $volume = (string)$volume[0];

        if ($status === "Not Charged") {
            $holdType = "hold";
        } else {
            $holdType = "recall";
        }

        // Build return array:
        return [
            'id' => empty($aggregate_id) ?
                (empty($bib_id) ? '' : $bib_id) : $aggregate_id,
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'item_id' => (string)$itemId[0],
            'bib_id' => $bib_id,
            'item_agency_id' => (string)$itemAgencyId[0],
            'aggregate_id' => $aggregate_id,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => $itemCallNo,
            'duedate' => '',        // not supported
            //'number' => $number++,
            'volume' => $volume,
            'number' => $number,
            // XC NCIP does not support barcode, but we need a placeholder here
            // to display anything on the record screen:
            'barcode' => 'placeholder' . $number,
            'is_holdable'  => true,
            'addLink' => true,
            'holdtype' => $holdType,
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => 'true',
        ];
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
        // FIXME: We are using the first defined agency, it will probably not work in
        // consortium scenario
        if (null === $agency) {
            $keys = array_keys($this->agency);
            $agency = $keys[0];
        }

        // Build a list of the types of information we want to retrieve:
        $desiredParts = [
            'Bibliographic Description',
            'Circulation Status',
            'Electronic Resource',
            'Hold Queue Length',
            'Item Description',
            'Item Use Restriction Type',
            'Location'
        ];

        // Start the XML:
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/' .
            'ncip_v2_0.xsd"><ns1:Ext><ns1:LookupItemSet>';

        foreach ($idList as $id) {
            $xml .= '<ns1:BibliographicId>' .
                    '<ns1:BibliographicRecordId>' .
                        '<ns1:BibliographicRecordIdentifier>' .
                            htmlspecialchars($id) .
                        '</ns1:BibliographicRecordIdentifier>' .
                        '<ns1:AgencyId>' .
                            htmlspecialchars($agency) .
                        '</ns1:AgencyId>' .
                    '</ns1:BibliographicRecordId>' .
                '</ns1:BibliographicId>';
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
        $xml .= '</ns1:LookupItemSet></ns1:Ext></ns1:NCIPMessage>';
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
                $bib_id = $bib->xpath(
                    'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                    'ns1:BibliographicRecordIdentifier' .
                    ' | ' .
                    'ns1:BibliographicId/ns1:BibliographicItemId/' .
                    'ns1:BibliographicItemIdentifier'
                );
                if (empty($bib_id)) {
                    throw new ILSException(
                        'Bibliographic record/item identifier missing in lookup " .
                        "item set response'
                    );
                }
                $bib_id = (string)$bib_id[0];

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
                        $chunk['id'] = $bib_id;
                        if (!isset($status[$bib_id])) {
                            $status[$bib_id] = [];
                        }
                        $chunk['location'] = $chunk['location']
                            ?? $holdingLocation ?? null;
                        $status[$bib_id][] = $chunk;
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
     * @throws VuFind\Date\DateException;
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
        $aggregate_id = $id;

        $agencyList = [];
        $idList = [];
        if (null !== $ids) {
            foreach ($ids as $_id) {
                // Need to parse out the 035$a format, e.g., "(Agency) 123"
                if (preg_match('/\(([^\)]+)\)\s*([0-9]+)/', $_id, $matches)) {
                    $matched_agency = $matches[1];
                    $matched_id = $matches[2];
                    if (array_key_exists($matched_agency, $this->agency)) {
                        $agencyList[] = $matched_agency;
                        $idList[] = $matched_id;
                    }
                }
            }
        }

        $holdings = [];
        $request = $this->getStatusRequest($idList, null, $agencyList);
        $response = $this->sendRequest($request);

        $bibs = $response->xpath(
            'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
        );

        foreach ($bibs as $bib) {
            $bib_ids = $bib->xpath(
                'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                'ns1:BibliographicRecordIdentifier'
            );
            $bib_id = (string)$bib_ids[0];

            $holdingSets = $bib->xpath('ns1:HoldingsSet');
            foreach ($holdingSets as $holding) {
                $holdCallNo = $holding->xpath('ns1:CallNumber');
                $holdCallNo = (string)$holdCallNo[0];
                $avail = $holding->xpath('ns1:ItemInformation');
                $eResource = $holding->xpath(
                    'ns1:ElectronicResource/ns1:ReferenceToResource'
                );
                $eResource = (string)$eResource[0];

                // Build the array of holdings:
                foreach ($avail as $current) {
                    $chunk = $this->getHoldingsForChunk(
                        $current, $aggregate_id, $bib_id
                    );
                    $chunk['callnumber'] = empty($chunk['callnumber']) ?
                        $holdCallNo : $chunk['callnumber'];
                    $chunk['eresource'] = $eResource;
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
     * @throws VuFind\Date\DateException;
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
            foreach (array_keys($this->agency) as $_agency) {
                $sourceRecord = '(' . $_agency . ') ';
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
        // TODO
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
        // TODO: we somehow need to figure out 'patron_agency_id' in the
        // consortium=true case
        //$request = $this->getLookupUserRequest(
        //    $username, $password, 'patron_agency_id'
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
        $patron_agency_id = $response->xpath(
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
                'patron_agency_id' => (string)$patron_agency_id[0],
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
     * @throws VuFind\Date\DateException;
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $extras = ['<ns1:LoanedItemsDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patron_agency_id'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');
        foreach ($list as $current) {
            $this->registerNamespaceFor($current);
            $tmp = $current->xpath('ns1:DateDue');
            // DateDue could be ommitted in response
            $due = $this->convertDate(!empty($tmp) ? (string)$tmp[0] : null);
            $title = $current->xpath('ns1:Title');
            $item_id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $itemId = (string)$item_id[0];
            $bib_id = $current->xpath(
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

            $notRenewable = $current->xpath(
                'ns1:Ext/ns1:RenewalNotPermitted'
            );

            $itemAgencyId = !empty($itemAgencyId) ? (string)$itemAgencyId[0] : null;
            $bibId = !empty($bib_id) ? (string)$bib_id[0] : null;
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
                'patron_agency_id' => $patron['patron_agency_id'],
                'duedate' => $due,
                'title' => (string)$title[0],
                'item_id' => $itemId,
                'renewable' => empty($notRenewable),
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
     * @throws VuFind\Date\DateException;
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $extras = ['<ns1:UserFiscalAccountDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patron_agency_id'], $extras
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
            $date = $this->convertDate(!empty($tmp) ? (string)$tmp[0] : null);
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
            $id = !empty($bibId) ? (string)$bibId[0] : '';
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
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws VuFind\Date\DateException;
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $extras = ['<ns1:RequestedItemsDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patron_agency_id'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $this->registerNamespaceFor($current);
            $id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier'
            );
            // (unused variable): $created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $requestType = $current->xpath('ns1:RequestType');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $pickupLocation = $current->xpath('ns1:PickupLocation');
            $expireDate = $current->xpath('ns1:PickupExpiryDate');
            $expireDate = $this->convertDate(
                !empty($expireDate) ? (string)$expireDate[0] : null
            );
            $requestType = (string)$requestType[0];
            // Only return requests of type Hold or Recall. Callslips/Stack
            // Retrieval requests are fetched using getMyStorageRetrievalRequests
            if ($requestType === "Hold" or $requestType === "Recall") {
                $retVal[] = [
                    'id' => (string)$id[0],
                    'create' => '',
                    'expire' => $expireDate,
                    'title' => (string)$title[0],
                    'position' => !empty($pos) ? (string)$pos[0] : null,
                    'requestId' => !empty($requestId) ? (string)$requestId[0] : null,
                    'item_id' => (string)$itemId[0],
                    'location' => (string)$pickupLocation[0],
                ];
            }
        }

        return $retVal;
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
            $patron['patron_agency_id'], $extras
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
        // TODO
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
        // TODO
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
                'HMACKeys' => 'item_id:holdtype:item_agency_id:aggregate_id:bib_id',
                'extraHoldFields' => 'comments:pickUpLocation:requiredByDate',
                'defaultRequiredDate' => '0:2:0',
                'consortium' => $this->consortium,
            ];
        }
        if ($function == 'StorageRetrievalRequests') {
            return [
                'HMACKeys' => 'id:item_id:item_agency_id:aggregate_id:bib_id',
                'extraFields' => 'comments:pickUpLocation:requiredByDate:item-issue',
                'helpText' => 'This is a storage retrieval request help text' .
                    ' with some <span style="color: red">styling</span>.',
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
        return $this->pickupLocations[$patron['patron_agency_id']][0]['locationID'];
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
        $extras = ['<ns1:RequestedItemsDesired/>'];
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'],
            $patron['patron_agency_id'], $extras
        );
        $response = $this->sendRequest($request);
        $this->checkResponseForError($response);

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $cancelled = false;
            $this->registerNamespaceFor($current);
            $id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier'
            );
            $itemAgencyId = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:AgencyId'
            );
            //$created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $pickupLocation = $current->xpath('ns1:PickupLocation');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $requestType = $current->xpath('ns1:RequestType');
            $requestType = (string)$requestType[0];
            $created = $current->xpath('ns1:DatePlaced');
            $created = $this->convertDate(
                !empty($created) ? (string)$created[0] : null
            );
            $requestStatusType = $current->xpath('ns1:RequestStatusType');
            $status = !empty($requestStatusType) ? (string)$requestStatusType[0]
                : null;
            if (!in_array($status, ['Available For Pickup', 'In Process'])) {
                $cancelled = true;
            }
            $processed = false;
            if ($status === 'Available For Pickup') {
                $processed = true;
            }
            // Only return requests of type Stack Retrieval/Callslip. Hold
            // and Recall requests are fetched using getMyHolds
            if ($requestType === 'Stack Retrieval') {
                $retVal[] = [
                    'id' => (string)$id[0],
                    'create' => $created,
                    'expire' => null,
                    'title' => (string)$title[0],
                    'position' => !empty($pos) ? (string)$pos[0] : null,
                    'requestId' => !empty($requestId) ? (string)$requestId[0] : null,
                    'item_agency_id' => !empty($itemAgencyId)
                        ? (string)$itemAgencyId[0] : null,
                    'canceled' => $cancelled,
                    'location' => (string)$pickupLocation[0],
                    'processed' => $processed,
                ];
            }
        }

        return $retVal;
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
        $username = $details['patron']['cat_username'];
        $password = $details['patron']['cat_password'];
        $bibId = $details['bib_id'];
        $itemId = $details['item_id'];
        $pickUpLocation = $details['pickUpLocation'];
        list($pickUpAgency, $pickUpLocation) = explode("|", $pickUpLocation);
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-' .
                substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";

        $request = $this->getRequest(
            $username, $password, $bibId, $itemId,
            $details['patron']['patron_agency_id'],
            $pickUpAgency,
            $details['item_agency_id'],
            "Stack Retrieval", "Item", $lastInterestDate, $pickUpLocation
        );

        $response = $this->sendRequest($request);
        $success = $response->xpath(
            'ns1:RequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue'
        );

        if ($success) {
            return [
                'success' => true,
                "sysMessage" => 'Storage Retrieval Request Successful.'
            ];
        } else {
            return [
                'success' => false,
                "sysMessage" => 'Storage Retrieval Request Not Successful.'
            ];
        }
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
        $username = $details['patron']['cat_username'];
        $password = $details['patron']['cat_password'];
        $bibId = $details['bib_id'];
        $itemId = $details['item_id'];
        $pickUpLocation = $details['pickUpLocation'];
        list($pickUpAgency, $pickUpLocation) = explode("|", $pickUpLocation);
        $holdType = $details['holdtype'];
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-'
            . substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";
        $successReturn = [
            'success' => true,
            'sysMessage' => 'Request Successful.'
        ];
        $failureReturn = [
            'success' => false,
            'sysMessage' => 'Request Not Successful.'
        ];

        $request = $this->getRequest(
            $username, $password, $bibId, $itemId,
            $details['patron']['patron_agency_id'],
            $pickUpAgency,
            $details['item_agency_id'],
            $holdType, "Item", $lastInterestDate, $pickUpLocation
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
        $count = 0;
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $patronAgency = $cancelDetails['patron']['patron_agency_id'];
        $details = $cancelDetails['details'];
        $response = [];
        $failureReturn = [
            'success' => false,
            'status' => 'hold_cancel_fail',
        ];
        $successReturn = [
            'success' => true,
            'status' => 'hold_cancel_success',
        ];

        foreach ($details as $detail) {
            list($itemAgencyId, $requestId, $itemId) = explode("|", $detail);
            $request = $this->getCancelRequest(
                $username, $password, $patronAgency,
                $itemAgencyId, $requestId, "Hold",
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
        $cancelDetails = $holdDetails['item_agency_id'] .
                         "|" .
                         $holdDetails['requestId'] .
                         "|" .
                         $holdDetails['item_id'];
        return $cancelDetails;
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
        //TODO: generalize all cancel request methods
        $count = 0;
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $patronAgency = $cancelDetails['patron']['patron_agency_id'];
        $details = $cancelDetails['details'];
        $response = [];

        foreach ($details as $cancelDetails) {
            list($itemAgencyId, $requestId, $itemId) = explode("|", $cancelDetails);
            $request = $this->getCancelRequest(
                $username,
                $password,
                $patronAgency,
                $itemAgencyId,
                $requestId,
                "Stack Retrieval",
                $itemId
            );
            $cancelRequestResponse = $this->sendRequest($request);
            $userId = $cancelRequestResponse->xpath(
                'ns1:CancelRequestItemResponse/' .
                'ns1:UserId/ns1:UserIdentifierValue'
            );
            $itemId = (string)$itemId;
            if ($userId) {
                $count++;
                $response[$itemId] = [
                    'success' => true,
                    'status' => 'storage_retrieval_request_cancel_success',
                ];
            } else {
                $response[$itemId] = [
                    'success' => false,
                    'status' => 'storage_retrieval_request_cancel_fail',
                ];
            }
        }
        $result = ['count' => $count, 'items' => $response];
        return $result;
    }

    /**
     * Get Cancel Storage Retrieval Request (Call Slip) Details
     *
     * This function returns the item id and call slip id as a
     * string separated by a pipe, which is then submitted as form data. This
     * value is then extracted by the CancelStorageRetrievalRequests function.
     * The item id is used as the key in the return value.
     *
     * @param array $callslipDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($callslipDetails)
    {
        return $callslipDetails['item_agency_id'] .
                                "|" .
                                $callslipDetails['requestId'] .
                                "|" .
                                $callslipDetails['id'];
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
            $request = $this->getRenewRequest(
                $renewDetails['patron']['cat_username'],
                $renewDetails['patron']['cat_password'], $itemId,
                $agencyId,
                $renewDetails['patron']['patron_agency_id']
            );
            $response = $this->sendRequest($request);
            /*try {
                $this->checkResponseForError($response);
            } catch (ILSException $exception) {
                $details[$itemId] = $failureReturn;
                continue;
            }*/
            $dueDateXml = $response->xpath('ns1:RenewItemResponse/ns1:DateDue');
            $dueDate = '';
            $dueTime = '';
            if (!empty($dueDateXml)) {
                $dueDateString = (string)$dueDateXml[0];
                $dueDate = $this->convertDate($dueDateString);
                $dueTime = $this->convertTime($dueDateString);
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
     *
     * @return string           NCIP request XML
     */
    protected function getCancelRequest($username,
        $password,
        $patronAgency,
        $itemAgencyId,
        $requestId,
        $type,
        $itemId
    ) {
        if ($requestId === null && $itemId === null) {
            throw new ILSException('No identifiers for CancelRequest');
        }
        $ret = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:CancelRequestItem>' .
                   '<ns1:InitiationHeader>' .
                        '<ns1:ToAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($patronAgency) .
                            '</ns1:AgencyId>' .
                        '</ns1:ToAgencyId>' .
                    '</ns1:InitiationHeader>' .
                    '<ns1:AuthenticationInput>' .
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
                    '</ns1:AuthenticationInput>';
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
            $ret .=
                '<ns1:ItemId>' .
                    '<ns1:AgencyId>' .
                        htmlspecialchars($itemAgencyId) .
                    '</ns1:AgencyId>' .
                    '<ns1:ItemIdentifierValue>' .
                        htmlspecialchars($itemId) .
                    '</ns1:ItemIdentifierValue>' .
                '</ns1:ItemId>';
        }
        $ret .=
                    '<ns1:RequestType>' .
                        htmlspecialchars($type) .
                    '</ns1:RequestType>' .
                '</ns1:CancelRequestItem>' .
            '</ns1:NCIPMessage>';
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
     * @param string $patron_agency_id Patron agency ID
     * @param string $pickup_agency_id Pickup agency ID
     * @param string $item_agency_id   Item agency ID
     * @param string $requestType      Type of the request (Hold, Callslip, etc)
     * @param string $requestScope     Level of request (title, item, etc)
     * @param string $lastInterestDate Last date interested in item
     * @param string $pickupLocation   Code of location to pickup request
     *
     * @return string          NCIP request XML
     */
    protected function getRequest($username, $password, $bibId, $itemId,
        $patron_agency_id, $pickup_agency_id, $item_agency_id,
        $requestType, $requestScope, $lastInterestDate, $pickupLocation = null
    ) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:RequestItem>' .
                   '<ns1:InitiationHeader>' .
                        '<ns1:FromAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($pickup_agency_id) .
                            '</ns1:AgencyId>' .
                        '</ns1:FromAgencyId>' .
                        '<ns1:ToAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($patron_agency_id) .
                            '</ns1:AgencyId>' .
                        '</ns1:ToAgencyId>' .
                    '</ns1:InitiationHeader>' .
                    '<ns1:AuthenticationInput>' .
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
                    '</ns1:AuthenticationInput>' .
                    '<ns1:BibliographicId>' .
                        '<ns1:BibliographicRecordId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($item_agency_id) .
                            '</ns1:AgencyId>' .
                            '<ns1:BibliographicRecordIdentifier>' .
                                htmlspecialchars($bibId) .
                            '</ns1:BibliographicRecordIdentifier>' .
                        '</ns1:BibliographicRecordId>' .
                    '</ns1:BibliographicId>' .
                    '<ns1:ItemId>' .
                        '<ns1:ItemIdentifierValue>' .
                            htmlspecialchars($itemId) .
                        '</ns1:ItemIdentifierValue>' .
                    '</ns1:ItemId>' .
                    '<ns1:RequestType>' .
                            htmlspecialchars($requestType) .
                    '</ns1:RequestType>' .
                    '<ns1:RequestScopeType ' .
                        'ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes' .
                        '/requestscopetype/requestscopetype.scm">' .
                            htmlspecialchars($requestScope) .
                    '</ns1:RequestScopeType>' .
                    '<ns1:PickupLocation>' .
                        htmlspecialchars($pickupLocation) .
                    '</ns1:PickupLocation>' .
                    '<ns1:PickupExpiryDate>' .
                        htmlspecialchars($lastInterestDate) .
                    '</ns1:PickupExpiryDate>' .
                '</ns1:RequestItem>' .
            '</ns1:NCIPMessage>';
    }

    /**
     * Helper function to build the request XML to renew an item:
     *
     * @param string $username       Username for login
     * @param string $password       Password for login
     * @param string $itemId         Id of item to renew
     * @param string $itemAgencyId   Agency of Item Id to renew
     * @param string $patronAgencyId Agency of patron
     *
     * @return string          NCIP request XML
     */
    protected function getRenewRequest($username,
        $password,
        $itemId,
        $itemAgencyId,
        $patronAgencyId
    ) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:RenewItem>' .
                   '<ns1:InitiationHeader>' .
                        '<ns1:ToAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($patronAgencyId) .
                            '</ns1:AgencyId>' .
                        '</ns1:ToAgencyId>' .
                    '</ns1:InitiationHeader>' .
                    '<ns1:AuthenticationInput>' .
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
                    '</ns1:AuthenticationInput>' .
                    '<ns1:ItemId>' .
                        '<ns1:AgencyId>' .
                            htmlspecialchars($itemAgencyId) .
                        '</ns1:AgencyId>' .
                        '<ns1:ItemIdentifierValue>' .
                            htmlspecialchars($itemId) .
                        '</ns1:ItemIdentifierValue>' .
                    '</ns1:ItemId>' .
                '</ns1:RenewItem>' .
            '</ns1:NCIPMessage>';
    }

    /**
     * Helper function to build the request XML to log in a user
     * and/or retrieve loaned items / request information
     *
     * @param string $username         Username for login
     * @param string $password         Password for login
     * @param string $patron_agency_id Patron agency ID (optional)
     * @param string $extras           Extra elements to include in the request
     *
     * @return string          NCIP request XML
     */
    protected function getLookupUserRequest($username, $password,
        $patron_agency_id = null, $extras = []
    ) {
        $ret = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:LookupUser>';

        if (null !== $patron_agency_id) {
            $ret .=
                   '<ns1:InitiationHeader>' .
                        '<ns1:FromAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($patron_agency_id) .
                            '</ns1:AgencyId>' .
                        '</ns1:FromAgencyId>' .
                        '<ns1:ToAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($patron_agency_id) .
                            '</ns1:AgencyId>' .
                        '</ns1:ToAgencyId>' .
                    '</ns1:InitiationHeader>';
        }

        $ret .=
                    '<ns1:AuthenticationInput>' .
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
                    '</ns1:AuthenticationInput>' .
                    implode('', $extras) .
                '</ns1:LookupUser>' .
            '</ns1:NCIPMessage>';

        return $ret;
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
        // FIXME: We are using the first defined agency, it will probably not work in
        // consortium scenario
        if (null === $agency) {
            $keys = array_keys($this->agency);
            $agency = $keys[0];
        }

        $ret = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
            '<ns1:LookupAgency>';

        if (null !== $agency) {
            $ret .=
                '<ns1:InitiationHeader>' .
                    '<ns1:FromAgencyId>' .
                        '<ns1:AgencyId>' .
                            htmlspecialchars($agency) .
                        '</ns1:AgencyId>' .
                    '</ns1:FromAgencyId>' .
                    '<ns1:ToAgencyId>' .
                        '<ns1:AgencyId>' .
                            htmlspecialchars($agency) .
                        '</ns1:AgencyId>' .
                    '</ns1:ToAgencyId>' .
                '</ns1:InitiationHeader>';
        }
        $ret .= '<ns1:AgencyId>My University 1</ns1:AgencyId>';
        $desiredElementTypes = [
            'Agency Address Information', 'Agency User Privilege Type',
            'Application Profile Supported Type', 'Authentication Prompt',
            'Consortium Agreement', 'Organization Name Information'
        ];
        foreach ($desiredElementTypes as $elementType) {
            $ret .= '<ns1:AgencyElementType>' .
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

        $ret = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
            '<ns1:LookupItem>';
        $ret .= '<ns1:ItemId>' .
                '<ns1:AgencyId>' . $agency . '</ns1:AgencyId>';
        if ($idType !== null) {
            $ret .=
                '<ns1:ItemIdentifierType>' .
                    $idType .
                '</ns1:ItemIdentifierType>';
        }
        $ret .=
                '<ns1:ItemIdentifierValue>' .
                    $itemId .
                '</ns1:ItemIdentifierValue>' .
            '</ns1:ItemId>' .
            '<ns1:ItemElementType>Bibliographic Description</ns1:ItemElementType>' .
        '</ns1:LookupItem></ns1:NCIPMessage>';
        return $ret;
    }

    /**
     * Throw an exception if an NCIP error is found
     *
     * @param XML $response from NCIP call
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
     * @param string $date     Date
     * @param bool   $withTime Whether the date includes time
     *
     * @throws DateException
     * @return string
     */
    protected function convertDate($date, $withTime = true)
    {
        if (!$date) {
            return '';
        }
        $createFormat = $withTime ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d';
        try {
            $dateFormatted = $this->dateConverter->convertToDisplayDate(
                $createFormat, $date
            );
        } catch (DateException $e) {
            $createFormat = $withTime ? 'Y-m-d\TH:i:sP' : 'Y-m-d';
            $dateFormatted = $this->dateConverter->convertToDisplayDate(
                $createFormat, $date
            );
        }
        return $dateFormatted;
    }

    /**
     * Convert a time to display format
     *
     * @param string $date Date
     *
     * @throws DateException
     * @return string
     */
    protected function convertTime($date)
    {
        //TODO generalize time and date converting
        if (!$date) {
            return '';
        }
        $createFormat = 'Y-m-d\TH:i:s.uP';
        try {
            $dateFormatted = $this->dateConverter->convertToDisplayTime(
                $createFormat, $date
            );
        } catch (DateException $e) {
            $createFormat = 'Y-m-d\TH:i:sP';
            $dateFormatted = $this->dateConverter->convertToDisplayTime(
                $createFormat, $date
            );
        }
        return $dateFormatted;
    }
}
