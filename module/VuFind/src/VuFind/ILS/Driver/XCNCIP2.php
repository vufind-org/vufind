<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException,
    VuFind\Config\Locator as ConfigLocator;

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class XCNCIP2 extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Is this a consortium? Default: false
     *
     * @var boolean
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
    protected $pickupLocations = [];

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

        $this->loadPickupLocations($this->config['Catalog']['pickupLocationsFile']);
    }

    /**
     * Loads pickup location information from configuration file.
     *
     * @param string $filename File to load from
     *
     * @throws ILSException
     * @return void
     */
    protected function loadPickupLocations($filename)
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
                $this->pickupLocations[$data[0]][] = [
                    'locationID' => $data[1],
                    'locationDisplay' => $data[2]
                ];
            }
            fclose($handle);
        }
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
            $result->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
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
        $status = $current->xpath(
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];

        /* unused variable -- can we remove?
        $itemId = $current->xpath(
            'ns1:ItemId/ns1:ItemIdentifierValue'
        );
        $item_id = (string)$itemId[0];
         */

        $itemCallNo = $current->xpath(
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = (string)$itemCallNo[0];

        $location = $current->xpath(
            'ns1:ItemOptionalFields/ns1:Location/ns1:LocationName/' .
            'ns1:LocationNameInstance/ns1:LocationNameValue'
        );
        $location = (string)$location[0];

        return [
            //'id' => ...
            'status' => $status,
            'location' => $location,
            'callnumber' => $itemCallNo,
            'availability' => ($status == "Not Charged"),
            'reserve' => 'N',       // not supported
        ];
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

        $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');

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
            'number' => $volume,
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
        // cedelis:
        //if (is_null($agency)) $agency = $this->agency[0];

        // pzurek:
        //if (is_null($agency)) $agency = array_keys($this->agency)[0];
        // The above does not work on older versions of php
        $keys = array_keys($this->agency);
        if (is_null($agency)) {
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

        // Add the ID list:
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
            $avail = $response->xpath(
                'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
            );

            // Build the array of statuses:
            foreach ($avail as $current) {
                $bib_id = $current->xpath(
                    'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                    'ns1:BibliographicRecordIdentifier'
                );
                $bib_id = (string)$bib_id[0];

                $holdings = $current->xpath('ns1:HoldingsSet');

                foreach ($holdings as $current) {

                    $holdCallNo = $current->xpath('ns1:CallNumber');
                    $holdCallNo = (string)$holdCallNo[0];

                    $items = $current->xpath('ns1:ItemInformation');

                    foreach ($items as $item) {
                        // Get data on the current chunk of data:
                        $chunk = $this->getStatusForChunk($item);

                        $chunk['callnumber'] = empty($chunk['callnumber']) ?
                            $holdCallNo : $chunk['callnumber'];

                        // Each bibliographic ID has its own key in the $status
                        // array; make sure we initialize new arrays when necessary
                        // and then add the current chunk to the right place:
                        $chunk['id'] = $bib_id;
                        if (!isset($status[$id])) {
                            $status[$id] = [];
                        }
                        $status[$bib_id][] = $chunk;
                    }
                }
            }

            // Check for resumption token:
            $resumption = $response->xpath(
                'ns1:Ext/ns1:LookupItemSetResponse/ns1:NextItemToken'
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
     * @throws \VuFind\Exception\Date
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

        $item_agency_id = [];
        if (! is_null($ids)) {
            foreach ($ids as $_id) {
                // Need to parse out the 035$a format, e.g., "(Agency) 123"
                if (preg_match('/\(([^\)]+)\)\s*([0-9]+)/', $_id, $matches)) {
                    $matched_agency = $matches[1];
                    $matched_id = $matches[2];
                    if ($this->agency[$matched_agency]) {
                        $item_agency_id[$matched_agency] = $matched_id;
                    }
                }
            }
        }

        $holdings = [];
        foreach ($item_agency_id as $_agency => $_id) {
            $request = $this->getStatusRequest([$_id], null, $_agency);
            $response = $this->sendRequest($request);

            $bib_id = $response->xpath(
                'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation/' .
                'ns1:BibliographicId/ns1:BibliographicRecordId/' .
                'ns1:BibliographicRecordIdentifier'
            );

            $holdingSets = $response->xpath('//ns1:HoldingsSet');

            foreach ($holdingSets as $holding) {
                $holdCallNo = $holding->xpath('ns1:CallNumber');
                $holdCallNo = (string)$holdCallNo[0];
                $avail = $holding->xpath('ns1:ItemInformation');

                // Build the array of holdings:
                foreach ($avail as $current) {
                    $chunk = $this->getHoldingsForChunk(
                        $current, $aggregate_id, (string)$bib_id[0]
                    );
                    $chunk['callnumber'] = empty($chunk['callnumber']) ?
                        $holdCallNo : $chunk['callnumber'];
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
        $request = $this->getLookupUserRequest($username, $password);
        $response = $this->sendRequest($request);
        $id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
        $patron_agency_id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:AgencyId'
        );
        if (!empty($id)) {
            // Fill in basic patron details:
            $patron = [
                'id' => (string)$id[0],
                'patron_agency_id' => (string)$patron_agency_id[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => null,
                'major' => null,
                'college' => null
            ];

            // Look up additional details:
            $details = $this->getMyProfile($patron);
            if (!empty($details)) {
                $patron['firstname'] = $details['firstname'];
                $patron['lastname'] = $details['lastname'];
                return $patron;
            }
        }

        return null;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
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

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');

        foreach ($list as $current) {
            $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
            $tmp = $current->xpath('ns1:DateDue');
            $due = strtotime((string)$tmp[0]);
            $due = date("l, d-M-y h:i a", $due);
            $title = $current->xpath('ns1:Title');
            $item_id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $bib_id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier'
            );
            // Hack to account for bibs from other non-local institutions
            // temporarily until consortial functionality is enabled.
            if ((string)$bib_id[0]) {
                $tmp = (string)$bib_id[0];
            } else {
                $tmp = "1";
            }
            $retVal[] = [
                'id' => $tmp,
                'duedate' => $due,
                'title' => (string)$title[0],
                'item_id' => (string)$item_id[0],
                'renewable' => true,
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
     * @throws \VuFind\Exception\Date
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

        $list = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserFiscalAccount/ns1:AccountDetails'
        );

        $fines = [];
        $balance = 0;
        foreach ($list as $current) {

            $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');

            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue'
            );
            $amount = (string)$tmp[0];
            $tmp = $current->xpath('ns1:AccrualDate');
            $date = (string)$tmp[0];
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:FiscalTransactionType'
            );
            $desc = (string)$tmp[0];
            /* This is an item ID, not a bib ID, so it's not actually useful:
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
                'ns1:ItemId/ns1:ItemIdentifierValue'
            );
            $id = (string)$tmp[0];
             */
            $id = '';
            $balance += $amount;
            $fines[] = [
                'amount' => $amount,
                'balance' => $balance,
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
     * @throws \VuFind\Exception\Date
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

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
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
            $expireDate = strtotime((string)$expireDate[0]);
            $expireDate = date("l, d-M-y", $expireDate);
            $requestType = (string)$requestType[0];
            // Only return requests of type Hold or Recall. Callslips/Stack
            // Retrieval requests are fetched using getMyStorageRetrievalRequests
            if ($requestType === "Hold" or $requestType === "Recall") {
                $retVal[] = [
                    'id' => (string)$id[0],
                    'create' => '',
                    'expire' => $expireDate,
                    'title' => (string)$title[0],
                    'position' => (string)$pos[0],
                    'requestId' => (string)$requestId[0],
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
            'address1' => isset($address[0]) ? $address[0] : '',
            'address2' => (isset($address[1]) ? $address[1] : '') .
                (isset($address[2]) ? ', ' . $address[2] : ''),
            'zip' => isset($address[3]) ? $address[3] : '',
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
        $locations = [];
        foreach (array_keys($this->agency) as $agency) {
            foreach ($this->pickupLocations[$agency] as $thisAgency) {
                $locations[]
                    = [
                        'locationID' => $thisAgency['locationID'],
                        'locationDisplay' => $thisAgency['locationDisplay'],
                    ];
            }
        }
        return $locations;
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

        $retVal = [];
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $cancelled = true;
            $id = $current->xpath(
                'ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier'
            );
            //$created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $pickupLocation = $current->xpath('ns1:PickupLocation');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $requestType = $current->xpath('ns1:RequestType');
            $requestType = (string)$requestType[0];
            $tmpStatus = $current->xpath('ns1:RequestStatusType');
            list($status, $created) = explode(" ", (string)$tmpStatus[0], 2);
            if ($status === "Accepted") {
                $cancelled = false;
            }
            // Only return requests of type Stack Retrieval/Callslip. Hold
            // and Recall requests are fetched using getMyHolds
            if ($requestType === 'Stack Retrieval') {
                $retVal[] = [
                    'id' => (string)$id[0],
                    'create' => $created,
                    'expire' => '',
                    'title' => (string)$title[0],
                    'position' => (string)$pos[0],
                    'requestId' => (string)$requestId[0],
                    'location' => 'test',
                    'canceled' => $cancelled,
                    'location' => (string)$pickupLocation[0],
                    'processed' => false,
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
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-' .
                substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";

        $request = $this->getRequest(
            $username, $password, $bibId, $itemId,
            $details['patron']['patron_agency_id'],
            $details['patron']['patron_agency_id'],
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
        $renewDetails = $checkOutDetails['item_id'];
        return $renewDetails;
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
        $holdType = $details['holdtype'];
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-'
            . substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";

        $request = $this->getRequest(
            $username, $password, $bibId, $itemId,
            $details['patron']['patron_agency_id'],
            $details['patron']['patron_agency_id'],
            $details['item_agency_id'],
            $holdType, "Item", $lastInterestDate, $pickUpLocation
        );
        $response = $this->sendRequest($request);
        $success = $response->xpath(
            'ns1:RequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue'
        );

        if ($success) {
            return [
                    'success' => true,
                    "sysMessage" => 'Request Successful.'
            ];
        } else {
            return [
                    'success' => false,
                    "sysMessage" => 'Request Not Successful.'
            ];
        }
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
        $details = $cancelDetails['details'];
        $response = [];

        foreach ($details as $cancelDetails) {
            list($itemId, $requestId) = explode("|", $cancelDetails);
            $request = $this->getCancelRequest(
                $username, $password, $requestId, "Hold"
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
                        'status' => 'hold_cancel_success',
                ];
            } else {
                $response[$itemId] = [
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                ];
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
        $cancelDetails = $holdDetails['id'] . "|" . $holdDetails['requestId'];
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
        $count = 0;
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $details = $cancelDetails['details'];
        $response = [];

        foreach ($details as $cancelDetails) {
            list($itemId, $requestId) = explode("|", $cancelDetails);
            $request = $this->getCancelRequest(
                $username, $password, $requestId, "Stack Retrieval"
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
        return $callslipDetails['id'] . "|" . $callslipDetails['requestId'];
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
        foreach ($renewDetails['details'] as $renewId) {
            $request = $this->getRenewRequest(
                $renewDetails['patron']['cat_username'],
                $renewDetails['patron']['cat_password'], $renewId
            );
            $response = $this->sendRequest($request);
            $dueDate = $response->xpath('ns1:RenewItemResponse/ns1:DateDue');
            if ($dueDate) {
                $tmp = $dueDate;
                $newDueDate = (string)$tmp[0];
                $tmp = split("T", $newDueDate);
                $splitDate = $tmp[0];
                $splitTime = $tmp[1];
                $details[$renewId] = [
                    "success" => true,
                    "new_date" => $splitDate,
                    "new_time" => rtrim($splitTime, "Z"),
                    "item_id" => $renewId,
                ];

            } else {
                $details[$renewId] = [
                    "success" => false,
                    "item_id" => $renewId,
                ];
            }
        }

        return [null, "details" => $details];
    }

    /**
     * Helper function to build the request XML to cancel a request:
     *
     * @param string $username  Username for login
     * @param string $password  Password for login
     * @param string $requestId Id of the request to cancel
     * @param string $type      The type of request to cancel (Hold, etc)
     *
     * @return string           NCIP request XML
     */
    protected function getCancelRequest($username, $password, $requestId, $type)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:CancelRequestItem>' .
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
                    '<ns1:RequestId>' .
                        '<ns1:RequestIdentifierValue>' .
                            htmlspecialchars($requestId) .
                        '</ns1:RequestIdentifierValue>' .
                    '</ns1:RequestId>' .
                    '<ns1:RequestType>' .
                        htmlspecialchars($type) .
                    '</ns1:RequestType>' .
                '</ns1:CancelRequestItem>' .
            '</ns1:NCIPMessage>';
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
                                htmlspecialchars($patron_agency_id) .
                            '</ns1:AgencyId>' .
                        '</ns1:FromAgencyId>' .
                        '<ns1:ToAgencyId>' .
                            '<ns1:AgencyId>' .
                                htmlspecialchars($pickup_agency_id) .
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
     * @param string $username Username for login
     * @param string $password Password for login
     * @param string $itemId   Id of item to renew
     *
     * @return string          NCIP request XML
     */
    protected function getRenewRequest($username, $password, $itemId)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:RenewItem>' .
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

        if (!is_null($patron_agency_id)) {
            $ret .=
                   '<ns1:InitiationHeader>' .
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
}

