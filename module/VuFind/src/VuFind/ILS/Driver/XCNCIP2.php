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
use VuFind\Exception\ILS as ILSException;

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
    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    protected $consortium = false;
    protected $agency = array();
    protected $agency_url = array();
    
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
        
        if ($this->config['Catalog']['consortium']) {
            $this->consortium = true;
            foreach ($this->config['Catalog']['agency'] as $agency) {
                $this->agency[] = $agency;
                $this->agency_url[$agency] = $this->config['Agency_' . $agency]['url'];
            }
        } else {
            $this->consortium = false;
            $this->agency[] = $this->config['Catalog']['agency'];
            $this->agency_url[$this->config['Catalog']['agency']] = $this->config['Catalog']['url'];
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
            $client = $this->httpService
                ->createClient($this->config['Catalog']['url']);
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
     * @param array $current Current XCItemAvailability chunk.
     *
     * @return array
     */
    protected function getHoldingsForChunk($current)
    {
        // Maintain an internal static count of line numbers:
        static $number = 1;

        $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
        
        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];

        $id = $current->xpath(
            'ns1:BibliographicId/ns1:BibliographicRecordId/' .
            'ns1:BibliographicRecordIdentifier'
        );

        $itemId = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemId/ns1:ItemIdentifierValue'
        );
        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
//         $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
//         $location = '';
//         foreach ($locationNodes as $curLoc) {
//             $type = $curLoc->xpath('ns1:LocationType');
//             if ((string)$type[0] == 'Permanent') {
//                 $tmp = $curLoc->xpath(
//                     'ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue'
//                 );
//                 $location = (string)$tmp[0];
//             }
//         }

        $tmp = $current->xpath('//ns1:LocationNameValue');
        $location = (string)$tmp[0];

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
        $holdCallNo = (string)$holdCallNo[0];
        $itemCallNo = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        
        $itemCallNo = (string)$itemCallNo[0];

        if ($status === "Not Charged") {
            $holdType = "hold";
        } else {
            $holdType = "recall";
        }
        
        $item_id = (string)$itemId[0];
        // Build return array:
        return array(
            'id' => empty($id) ? '' : (string)$id[0],
            'item_id' => (string)$itemId[0],
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => empty($itemCallNo) ? $holdCallNo : $itemCallNo,
            'duedate' => '',        // not supported
            'number' => $number++,
            // XC NCIP does not support barcode, but we need a placeholder here
            // to display anything on the record screen:
            'barcode' => 'placeholder' . $number,
            'is_holdable'  => true,
            'addLink' => true,
            'holdtype' => $holdType,
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => 'true',
        );
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
     *
     * @return string            XML request
     */
    protected function getStatusRequest($idList, $resumption = null, $agency = null)
    {
        if (is_null($agency)) $agency = "LOCAL";

        // Build a list of the types of information we want to retrieve:
        $desiredParts = array(
            'Bibliographic Description',
            'Circulation Status',
            'Electronic Resource',
            'Hold Queue Length',
            'Item Description',
            'Item Use Restriction Type',
            'Location'
        );

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
        $status = array();
        $resumption = null;
        do {
            $request = $this->getStatusRequest($idList, $resumption);
            $response = $this->sendRequest($request);
            $avail = $response->xpath(
                'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
            );

            // Build the array of statuses:
            foreach ($avail as $current) {
                // Get data on the current chunk of data:
                $chunk = $this->getHoldingsForChunk($current);

                // Each bibliographic ID has its own key in the $status array; make
                // sure we initialize new arrays when necessary and then add the
                // current chunk to the right place:
                $id = $chunk['id'];
                if (!isset($status[$id])) {
                    $status[$id] = array();
                }
                $status[$id][] = $chunk;
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
    public function getHolding($id, $patron = false)
    {
        if (is_array($id)) $ids = $id;
        else $ids = array($id);
        
        $agency_id = array();
        if ($this->consortium) {
            // Need to parse out the 035$a, e.g., (Agency)ID
            foreach ($ids as $id) { 
                if (preg_match('/\(([^\)]+)\)\s*([0-9]+)/', $id, $matches)) {
                    $matched_agency = $matches[1];
                    $matched_id = $matches[2];
                    if ($this->agency_url[$matched_agency]) {
                        $agency_id[$matched_agency] = $matched_id;
                    }
                }
            }
        } else {
            $agency_id['LOCAL'] = $ids;
        }

        $holdings = array();
        foreach ($agency_id as $_agency => $_id) {
            $request = $this->getStatusRequest(array($_id), null, $_agency);
            $response = $this->sendRequest($request);
            $avail = $response->xpath(
                'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
            );

            // Build the array of holdings:
            //$holdings = array();
            foreach ($avail as $current) {
                $holdings[] = $this->getHoldingsForChunk($current);
            }
            
        }

        return $holdings;
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
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
        $request = $this->getLookupUserRequest($username, $password);
        $response = $this->sendRequest($request);
        $id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
        if (!empty($id)) {
            // Fill in basic patron details:
            $patron = array(
                'id' => (string)$id[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => null,
                'major' => null,
                'college' => null
            );

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
        $extras = array('<ns1:LoanedItemsDesired/>');
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->sendRequest($request);

        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');
        
        foreach ($list as $current) {
            $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
            $tmp = $current->xpath('ns1:DateDue');
            $due = (string)$tmp[0];
            $due = str_replace("T", " ", $due);
            $due = str_replace("Z", "", $due);
            $title = $current->xpath('ns1:Title');
            $item_id = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $bib_id = $current->xpath('ns1:Ext/ns1:BibliographicDescription/' .
                'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier');
            // Hack to account for bibs from other non-local institutions
            // temporarily until consortial functionality is enabled.
            if ((string)$bib_id[0]) {
                $tmp = (string)$bib_id[0];
            } else {
                $tmp = "1";
            }
            $retVal[] = array(
                'id' => $tmp,
                'duedate' => $due,
                'title' => (string)$title[0],
                'item_id' => (string)$item_id[0],
                'renewable' => true,
            );
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
        $extras = array('<ns1:UserFiscalAccountDesired/>');
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->sendRequest($request);

        $list = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserFiscalAccount/ns1:AccountDetails'
        );

        $fines = array();
        $balance = 0;
        foreach ($list as $current) {
            //pzurek
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
            $fines[] = array(
                'amount' => $amount,
                'balance' => $balance,
                'checkout' => '',
                'fine' => $desc,
                'duedate' => '',
                'createdate' => $date,
                'id' => $id
            );
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
        $extras = array('<ns1:RequestedItemsDesired/>');
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->sendRequest($request);

        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $id = $current->xpath('ns1:Ext/ns1:BibliographicDescription/' .
                    'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier');
            $created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $requestType = $current->xpath('ns1:RequestType');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $itemId = $current->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            $requestType = (string)$requestType[0];
            // Only return requests of type Hold or Recall. Callslips/Stack
            // Retrieval requests are fetched using getMyStorageRetrievalRequests
            if ($requestType === "Hold" or $requestType === "Recall") {
                $retVal[] = array(
                    'id' => (string)$id[0],
                    'create' => (string)$created[0],
                    'expire' => '',
                    'title' => (string)$title[0],
                    'position' => (string)$pos[0],
                    'requestId' => (string)$requestId[0],
                    'item_id' => (string)$itemId[0],
                );
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
        $extras = array(
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'User Address Information' .
            '</ns1:UserElementType>',
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'Name Information' .
            '</ns1:UserElementType>'
        );
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
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
        return array(
            'firstname' => (string)$first[0],
            'lastname' => (string)$last[0],
            'address1' => isset($address[0]) ? $address[0] : '',
            'address2' => (isset($address[1]) ? $address[1] : '') .
                (isset($address[2]) ? ', ' . $address[2] : ''),
            'zip' => isset($address[3]) ? $address[3] : '',
            'phone' => '',  // TODO: phone number support
            'group' => ''
        );
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        return array();
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
        return array();
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
        return array();
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
        return array();
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
        return array();
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return array();
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
        return array();
    }

    public function getConfig($function)
    {
        if ($function == 'Holds') {
            return array(
                'HMACKeys' => 'item_id:holdtype',
                'extraHoldFields' => 'comments:pickUpLocation:requiredByDate',
                'defaultRequiredDate' => '0:2:0',
            );
        }
        if ($function == 'StorageRetrievalRequests') {
            return array(
                'HMACKeys' => 'id:item_id',
                'extraFields' => 'comments:pickUpLocation:requiredByDate:item-issue',
                'helpText' => 'This is a storage retrieval request help text' .
                    ' with some <span style="color: red">styling</span>.',
                'defaultRequiredDate' => '0:2:0',
            );
        }
        return array();
    }
    
    // TODO: Figure out how we're going to get this data into VuFind2 via NCIP
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return "12";
    }

    // TODO: Figure out how we're going to get this data into VuFind2 via NCIP
    public function getPickUpLocations($patron) 
    {
        return array(
                array(
                        'locationID' => '3',
                        'locationDisplay' => 'Main Circ Desk'
                ),
                array(
                        'locationID' => '115',
                        'locationDisplay' => 'An invalid location (testing)'
                ),
        );
        
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
    public function getMyStorageRetrievalRequests($patron = false) 
    {
        $extras = array('<ns1:RequestedItemsDesired/>');
        $request = $this->getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->sendRequest($request);

        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $id = $current->xpath('ns1:Ext/ns1:BibliographicDescription/' .
                    'ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier');
            $created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $requestId = $current->xpath('ns1:RequestId/ns1:RequestIdentifierValue');
            $requestType = $current->xpath('ns1:RequestType');
            $requestType = (string)$requestType[0];
            $requestStatus = $current->xpath('ns1:RequestStatusType');
            $requestStatus = (string)$requestStatus[0];
            // Only return requests of type Stack Retrieval/Callslip. Hold
            // and Recall requests are fetched using getMyHolds
            if ($requestType === 'Stack Retrieval' and 
                substr($requestStatus, 0, 8) !== 'Canceled')
            {
                $retVal[] = array(
                    'id' => (string)$id[0],
                    'create' => (string)$created[0],
                    'expire' => '',
                    'title' => (string)$title[0],
                    'position' => (string)$pos[0], 
                    'requestId' => (string)$requestId[0],
                    'location' => 'test',
                    'canceled' => false,
                    'processed' => false,
                );
            }
        }

        return $retVal;
    }

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
        $bibId = $details['id'];
        $itemId = $details['item_id'];
        $pickUpLocation = $details['pickUpLocation'];
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-' .
                substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";

        $request = $this->getRequest($username, $password, $bibId, $itemId, 
                "Stack Retrieval", "Item", $lastInterestDate, $pickUpLocation);
        $response = $this->sendRequest($request);
        $success = $response->xpath(
                'ns1:RequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue');

        if ($success) {
            return array(
                    'success' => true, 
                    "sysMessage" => 'Storage Retrieval Request Successful.'
            );
        } else {
            return array(
                    'success' => false, 
                    "sysMessage" => 'Storage Retrieval Request Not Successful.'
            );
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
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful
     */
    public function placeHold($details) 
    {
        $username = $details['patron']['cat_username'];
        $password = $details['patron']['cat_password'];
        $bibId = $details['id'];
        $itemId = $details['item_id'];
        $pickUpLocation = $details['pickUpLocation'];
        $holdType = $details['holdtype'];
        $lastInterestDate = $details['requiredBy'];
        $lastInterestDate = substr($lastInterestDate, 6, 10) . '-' .
                 substr($lastInterestDate, 0, 5);
        $lastInterestDate = $lastInterestDate . "T00:00:00.000Z";
       
        $request = $this->getRequest($username, $password, $bibId, $itemId, 
                 $holdType, "Item", $lastInterestDate, $pickUpLocation);
        $response = $this->sendRequest($request);
        $success = $response->xpath(
                'ns1:RequestItemResponse/ns1:ItemId/ns1:ItemIdentifierValue');
        
        if ($success) {
            return array(
                    'success' => true, 
                    "sysMessage" => 'Request Successful.'
            );
        } else {
            return array(
                    'success' => false, 
                    "sysMessage" => 'Request Not Successful.'
            );
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
        $response = array();

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
            if($userId) {
                $count++;
                $response[$itemId] = array(
                        'success' => true,
                        'status' => 'hold_cancel_success',
                );
            } else {
                $response[$itemId] = array(
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                );
            }
        }
        $result = array('count' => $count, 'items' => $response);
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
        $cancelDetails = $holdDetails['id']."|".$holdDetails['requestId'];
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
        $response = array();

        foreach ($details as $cancelDetails) {
            list($itemId, $requestId) = explode("|", $cancelDetails);
            $request = $this->getCancelRequest(
                $username, $password, $requestId, "Stack Retrieval"
            );
            $cancelRequestResponse = $this->sendRequest($request);
            $userId = $cancelRequestResponse->xpath(
                'ns1:CancelRequestItemResponse/'. 
                'ns1:UserId/ns1:UserIdentifierValue'
            );
            $itemId = (string)$itemId;
            if($userId) {
                $count++;
                $response[$itemId] = array(
                        'success' => true,
                        'status' => 'storage_retrieval_request_cancel_success',
                );
            } else {
                $response[$itemId] = array(
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                );
            }
        }
        $result = array('count' => $count, 'items' => $response);
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
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($callslipDetails)
    {
        $cancelDetails = $callslipDetails['id']."|".$callslipDetails['requestId'];
        return $cancelDetails;
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
        $details = array();
        foreach ($renewDetails['details'] as $renewId) {
            $request = $this->getRenewRequest(
                    $renewDetails['patron']['cat_username'], 
                    $renewDetails['patron']['cat_password'], $renewId);
            $response = $this->sendRequest($request);
            $dueDate = $response->xpath('ns1:RenewItemResponse/ns1:DateDue');
            if ($dueDate) {
                $tmp = $dueDate;
                $newDueDate = (string)$tmp[0];
                $tmp = split("T", $newDueDate);
                $splitDate = $tmp[0];
                $splitTime = $tmp[1];
                $details[$renewId] = array(
                    "success" => true,
                    "new_date" => $splitDate,
                    "new_time" => rtrim($splitTime, "Z"),
                    "item_id" => $renewId,
                );

            } else {
                $details[$renewId] = array(
                    "success" => false,
                    "item_id" => $renewId,
                );
            }
        }

        return array(null, "details" => $details);
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
     * @param string $requestType      Type of the request (Hold, Callslip, etc)
     * @param string $requestScope     Level of request (title, item, etc)
     * @param string $lastInterestDate Last date interested in item
     * @param string $pickupLocation   Code of location to pickup request
     *
     * @return string          NCIP request XML
     */
    protected function getRequest($username, $password, $bibId, $itemId,
            $requestType, $requestScope, $lastInterestDate, $pickupLocation = null)
    {
    	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:RequestItem>' .
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
     * @param string $username Username for login
     * @param string $password Password for login
     * @param string $extras   Extra elements to include in the request
     *
     * @return string          NCIP request XML
     */
    protected function getLookupUserRequest($username, $password, $extras = array())
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:LookupUser>' .
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
    }
}
    
