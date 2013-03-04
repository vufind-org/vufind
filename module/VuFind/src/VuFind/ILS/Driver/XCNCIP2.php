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

        // Extract details from the XML:
        $status = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:CirculationStatus'
        );
        $status = empty($status) ? '' : (string)$status[0];

        $id = $current->xpath(
            'ns1:BibliographicId/ns1:BibliographicItemId/' .
            'ns1:BibliographicItemIdentifier'
        );

        // Pick out the permanent location (TODO: better smarts for dealing with
        // temporary locations and multi-level location names):
        $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
        $location = '';
        foreach ($locationNodes as $curLoc) {
            $type = $curLoc->xpath('ns1:LocationType');
            if ((string)$type[0] == 'Permanent') {
                $tmp = $curLoc->xpath(
                    'ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue'
                );
                $location = (string)$tmp[0];
            }
        }

        // Get both holdings and item level call numbers; we'll pick the most
        // specific available value below.
        $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
        $holdCallNo = (string)$holdCallNo[0];
        $itemCallNo = $current->xpath(
            'ns1:HoldingsSet/ns1:ItemInformation/' .
            'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
        );
        $itemCallNo = (string)$itemCallNo[0];

        // Build return array:
        return array(
            'id' => empty($id) ? '' : (string)$id[0],
            'availability' => ($status == 'Not Charged'),
            'status' => $status,
            'location' => $location,
            'reserve' => 'N',       // not supported
            'callnumber' => empty($itemCallNo) ? $holdCallNo : $itemCallNo,
            'duedate' => '',        // not supported
            'number' => $number++,
            // XC NCIP does not support barcode, but we need a placeholder here
            // to display anything on the record screen:
            'barcode' => 'placeholder' . $number
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
    protected function getStatusRequest($idList, $resumption = null)
    {
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
                    '<ns1:BibliographicItemId>' .
                        '<ns1:BibliographicItemIdentifier>' .
                            htmlspecialchars($id) .
                        '</ns1:BibliographicItemIdentifier>' .
                        '<ns1:AgencyId>LOCAL</ns1:AgencyId>' .
                    '</ns1:BibliographicItemId>' .
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
        $request = $this->getStatusRequest(array($id));
        $response = $this->sendRequest($request);
        $avail = $response->xpath(
            'ns1:Ext/ns1:LookupItemSetResponse/ns1:BibInformation'
        );

        // Build the array of holdings:
        $holdings = array();
        foreach ($avail as $current) {
            $holdings[] = $this->getHoldingsForChunk($current);
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
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Build the request XML to log in a user:
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
            $due = $current->xpath('ns1:DateDue');
            $title = $current->xpath('ns1:Title');
            $retVal[] = array(
                'id' => false,
                'duedate' => (string)$due[0],
                'title' => (string)$title[0]
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
        foreach ($list as $current) {
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
            $fines[] = array(
                'amount' => $amount,
                'balance' => $amount,
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
            $created = $current->xpath('ns1:DatePlaced');
            $title = $current->xpath('ns1:Title');
            $pos = $current->xpath('ns1:HoldQueuePosition');
            $retVal[] = array(
                'id' => false,
                'create' => (string)$created[0],
                'expire' => '',
                'title' => (string)$title[0],
                'position' => (string)$pos[0]
            );
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
     * This version of findReserves was contributed by Matthew Hooper and includes
     * support for electronic reserves (though eReserve support is still a work in
     * progress).
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
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
}
