<?php
/**
 * PAIA ILS Driver for VuFind to get patron information
 *
 * PHP version 7
 *
 * Copyright (C) Oliver Goldschmidt, Magda Roos, Till Kinstler, André Lahmann 2013,
 * 2014, 2015.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * PAIA ILS Driver for VuFind to get patron information
 *
 * Holding information is obtained by DAIA, so it's not necessary to implement those
 * functions here; we just need to extend the DAIA driver.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class PAIA extends DAIA
{
    /**
     * URL of PAIA service
     *
     * @var
     */
    protected $paiaURL;

    /**
     * Timeout in seconds to be used for PAIA http requests
     *
     * @var
     */
    protected $paiaTimeout = null;

    /**
     * Flag to switch on/off caching for PAIA items
     *
     * @var bool
     */
    protected $paiaCacheEnabled = false;

    /**
     * Session containing PAIA login information
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * SessionManager
     *
     * @var \Zend\Session\SessionManager
     */
    protected $sessionManager;

    /**
     * PAIA status strings
     *
     * @var array
     */
    protected static $statusStrings = [
        '0' => 'no relation',
        '1' => 'reserved',
        '2' => 'ordered',
        '3' => 'held',
        '4' => 'provided',
        '5' => 'rejected',
    ];

    /**
     * PAIA constructor.
     *
     * @param \VuFind\Date\Converter       $converter      Date converter
     * @param \Zend\Session\SessionManager $sessionManager Session Manager
     */
    public function __construct(\VuFind\Date\Converter $converter,
        \Zend\Session\SessionManager $sessionManager
    ) {
        parent::__construct($converter);
        $this->sessionManager = $sessionManager;
    }

    /**
     * PAIA specific override of method to ensure uniform cache keys for cached
     * VuFind objects.
     *
     * @param string|null $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     *
     * @return string
     */
    protected function getCacheKey($suffix = null)
    {
        return $this->getBaseCacheKey(
            md5($this->baseUrl . $this->paiaURL) . $suffix
        );
    }

    /**
     * Get the session container (constructing it on demand if not already present)
     *
     * @return SessionContainer
     */
    protected function getSession()
    {
        // SessionContainer not defined yet? Build it now:
        if (null === $this->session) {
            $this->session = new \Zend\Session\Container(
                'PAIA', $this->sessionManager
            );
        }
        return $this->session;
    }

    /**
     * Get the session scope
     *
     * @return array Array of the Session scope
     */
    protected function getScope()
    {
        return $this->getSession()->scope;
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
        parent::init();

        if (!(isset($this->config['PAIA']['baseUrl']))) {
            throw new ILSException('PAIA/baseUrl configuration needs to be set.');
        }
        $this->paiaURL = $this->config['PAIA']['baseUrl'];

        // use PAIA specific timeout setting for http requests if configured
        if ((isset($this->config['PAIA']['timeout']))) {
            $this->paiaTimeout = $this->config['PAIA']['timeout'];
        }

        // do we have caching enabled for PAIA
        if (isset($this->config['PAIA']['paiaCache'])) {
            $this->paiaCacheEnabled = $this->config['PAIA']['paiaCache'];
        } else {
            $this->debug('Caching not enabled, disabling it by default.');
        }
    }

    // public functions implemented to satisfy Driver Interface

    /*
    These methods are not implemented in the PAIA driver as they are probably
    not necessary in PAIA context:
    - findReserves
    - getCancelHoldLink
    - getConsortialHoldings
    - getCourses
    - getDepartments
    - getHoldDefaultRequiredDate
    - getInstructors
    - getOfflineMode
    - getSuppressedAuthorityRecords
    - getSuppressedRecords
    - hasHoldings
    - loginIsHidden
    - renewMyItemsLink
    - supportsMethod
    */

    /**
     * This method cancels a list of holds for a specific patron.
     *
     * @param array $cancelDetails An associative array with two keys:
     *      patron   array returned by the driver's patronLogin method
     *      details  an array of strings returned by the driver's
     *               getCancelHoldDetails method
     *
     * @return array Associative array containing:
     *      count   The number of items successfully cancelled
     *      items   Associative array where key matches one of the item_id
     *              values returned by getMyHolds and the value is an
     *              associative array with these keys:
     *                success    Boolean true or false
     *                status     A status message from the language file
     *                           (required – VuFind-specific message,
     *                           subject to translation)
     *                sysMessage A system supplied failure message
     */
    public function cancelHolds($cancelDetails)
    {
        $it = $cancelDetails['details'];
        $items = [];
        foreach ($it as $item) {
            $items[] = ['item' => stripslashes($item)];
        }
        $patron = $cancelDetails['patron'];
        $post_data = ["doc" => $items];

        try {
            $array_response = $this->paiaPostAsArray(
                'core/' . $patron['cat_username'] . '/cancel', $post_data
            );
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
            return [
                'success' => false,
                'status' => $e->getMessage(),
            ];
        }

        $details = [];

        if (isset($array_response['error'])) {
            $details[] = [
                'success' => false,
                'status' => $array_response['error_description'],
                'sysMessage' => $array_response['error']
            ];
        } else {
            $count = 0;
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                $item_id = $element['item'];
                if ($element['error']) {
                    $details[$item_id] = [
                        'success' => false,
                        'status' => $element['error'],
                        'sysMessage' => 'Cancel request rejected'
                    ];
                } else {
                    $details[$item_id] = [
                        'success' => true,
                        'status' => 'Success',
                        'sysMessage' => 'Successfully cancelled'
                    ];
                    $count++;

                    // DAIA cache cannot be cleared for particular item as PAIA only
                    // operates with specific item URIs and the DAIA cache is setup
                    // by doc URIs (containing items with URIs)
                }
            }

            // If caching is enabled for PAIA clear the cache as at least for one
            // item cancel was successfull and therefore the status changed.
            // Otherwise the changed status will not be shown before the cache
            // expires.
            if ($this->paiaCacheEnabled) {
                $this->removeCachedData($patron['cat_username']);
            }
        }
        $returnArray = ['count' => $count, 'items' => $details];

        return $returnArray;
    }

    /**
     * Public Function which changes the password in the library system
     * (not supported prior to VuFind 2.4)
     *
     * @param array $details Array with patron information, newPassword and
     *                       oldPassword.
     *
     * @return array An array with patron information.
     */
    public function changePassword($details)
    {
        $post_data = [
            "patron"       => $details['patron']['cat_username'],
            "username"     => $details['patron']['cat_username'],
            "old_password" => $details['oldPassword'],
            "new_password" => $details['newPassword']
        ];

        try {
            $array_response = $this->paiaPostAsArray(
                'auth/change', $post_data
            );
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
            return [
                'success' => false,
                'status' => $e->getMessage(),
            ];
        }

        $details = [];

        if (isset($array_response['error'])) {
            // on error
            $details = [
                'success'    => false,
                'status'     => $array_response['error'],
                'sysMessage' =>
                    $array_response['error'] ?? ' ' .
                    $array_response['error_description'] ?? ' '
            ];
        } elseif (isset($array_response['patron'])
            && $array_response['patron'] === $post_data['patron']
        ) {
            // on success patron_id is returned
            $details = [
                'success' => true,
                'status' => 'Successfully changed'
            ];
        } else {
            $details = [
                'success' => false,
                'status' => 'Failure changing password',
                'sysMessage' => serialize($array_response)
            ];
        }
        return $details;
    }

    /**
     * This method returns a string to use as the input form value for
     * cancelling each hold item. (optional, but required if you
     * implement cancelHolds). Not supported prior to VuFind 1.2
     *
     * @param array $checkOutDetails One of the individual item arrays returned by
     *                               the getMyHolds method
     *
     * @return string  A string to use as the input form value for cancelling
     *                 each hold item; you can pass any data that is needed
     *                 by your ILS to identify the hold – the output of this
     *                 method will be used as part of the input to the
     *                 cancelHolds method.
     */
    public function getCancelHoldDetails($checkOutDetails)
    {
        return $checkOutDetails['cancel_details'];
    }

    /**
     * Get Default Pick Up Location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron = null, $holdDetails = null)
    {
        return false;
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        // If you do not want or support such limits, just return an empty
        // array here and the limit control on the new item search screen
        // will disappear.
        return [];
    }

    /**
     * Cancel Storage Retrieval Request
     *
     * Attempts to Cancel a Storage Retrieval Request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelStorageRetrievalRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        // Not yet implemented
        return [];
    }

    /**
     * Get Cancel Storage Retrieval Request Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        // Not yet implemented
        return '';
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's ILL requests
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyILLRequests($patron)
    {
        // Not yet implemented
        return [];
    }

    /**
     * Check if ILL request available
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
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        // Not yet implemented
        return false;
    }

    /**
     * Place ILL Request
     *
     * Attempts to place an ILL request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeILLRequest($details)
    {
        // Not yet implemented
        return [];
    }

    /**
     * Get ILL Pickup Libraries
     *
     * This is responsible for getting information on the possible pickup libraries
     *
     * @param string $id     Record ID
     * @param array  $patron Patron
     *
     * @return bool|array False if request not allowed, or an array of associative
     * arrays with libraries.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLibraries($id, $patron)
    {
        // Not yet implemented
        return false;
    }

    /**
     * Get ILL Pickup Locations
     *
     * This is responsible for getting a list of possible pickup locations for a
     * library
     *
     * @param string $id        Record ID
     * @param string $pickupLib Pickup library ID
     * @param array  $patron    Patron
     *
     * @return bool|array False if request not allowed, or an array of locations.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        // Not yet implemented
        return false;
    }

    /**
     * Cancel ILL Request
     *
     * Attempts to Cancel an ILL request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelILLRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelILLRequests($cancelDetails)
    {
        // Not yet implemented
        return [];
    }

    /**
     * Get Cancel ILL Request Details
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelILLRequestDetails($details)
    {
        // Not yet implemented
        return '';
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed Array of the patron's fines on success
     */
    public function getMyFines($patron)
    {
        $fees = $this->paiaGetAsArray(
            'core/' . $patron['cat_username'] . '/fees'
        );

        // PAIA simple data type money: a monetary value with currency (format
        // [0-9]+\.[0-9][0-9] [A-Z][A-Z][A-Z]), for instance 0.80 USD.
        $feeConverter = function ($fee) {
            $paiaCurrencyPattern = "/^([0-9]+\.[0-9][0-9]) ([A-Z][A-Z][A-Z])$/";
            if (preg_match($paiaCurrencyPattern, $fee, $feeMatches)) {
                // VuFind expects fees in PENNIES
                return $feeMatches[1] * 100;
            }
            return $fee;
        };

        $results = [];
        if (isset($fees['fee'])) {
            foreach ($fees['fee'] as $fee) {
                $result = [
                    // fee.amount    1..1   money    amount of a single fee
                    'amount'      => $feeConverter($fee['amount']),
                    'checkout'    => '',
                    // fee.feetype   0..1   string   textual description of the type
                    // of service that caused the fee
                    'fine'    => ($fee['feetype'] ?? null),
                    'balance' => $feeConverter($fee['amount']),
                    // fee.date      0..1   date     date when the fee was claimed
                    'createdate'  => (isset($fee['date'])
                        ? $this->convertDate($fee['date']) : null),
                    'duedate' => '',
                    // fee.edition   0..1   URI      edition that caused the fee
                    'id' => (isset($fee['edition'])
                        ? $this->getAlternativeItemId($fee['edition']) : ''),
                ];
                // custom PAIA fields can get added in getAdditionalFeeData
                $results[] = $result + $this->getAdditionalFeeData($fee, $patron);
            }
        }
        return $results;
    }

    /**
     * Gets additional array fields for the item.
     * Override this method in your custom PAIA driver if necessary.
     *
     * @param array $fee    The fee array from PAIA
     * @param array $patron The patron array from patronLogin
     *
     * @return array Additional fee data for the item
     */
    protected function getAdditionalFeeData($fee, $patron = null)
    {
        $additionalData = [];
        // The title is always displayed to the user in fines view if no record can
        // be found for current fee. So always populate the title with content of
        // about field.
        if (isset($fee['about'])) {
            $additionalData['title'] = $fee['about'];
        }

        // custom PAIA fields
        // fee.about     0..1     string    textual information about the fee
        // fee.item      0..1     URI       item that caused the fee
        // fee.feeid     0..1     URI       URI of the type of service that
        // caused the fee
        $additionalData['feeid']      = ($fee['feeid'] ?? null);
        $additionalData['about']      = ($fee['about'] ?? null);
        $additionalData['item']       = ($fee['item'] ?? null);

        return $additionalData;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        // filters for getMyHolds are:
        // status = 1 - reserved (the document is not accessible for the patron yet,
        //              but it will be)
        //          4 - provided (the document is ready to be used by the patron)
        $filter = ['status' => [1, 4]];
        // get items-docs for given filters
        $items = $this->paiaGetItems($patron, $filter);

        return $this->mapPaiaItems($items, 'myHoldsMapping');
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array Array of the patron's profile data on success,
     */
    public function getMyProfile($patron)
    {
        //todo: read VCard if avaiable in patron info
        //todo: make fields more configurable
        if (is_array($patron)) {
            return [
                'firstname'  => $patron['firstname'],
                'lastname'   => $patron['lastname'],
                'address1'   => null,
                'address2'   => null,
                'city'       => null,
                'country'    => null,
                'zip'        => null,
                'phone'      => null,
                'group'      => null,
                // PAIA specific custom values
                'expires'    => isset($patron['expires'])
                    ? $this->convertDate($patron['expires']) : null,
                'statuscode' => $patron['status'] ?? null,
                'canWrite'   => in_array('write_items', $this->getScope()),
            ];
        }
        return [];
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array Array of the patron's transactions on success,
     */
    public function getMyTransactions($patron)
    {
        // filters for getMyTransactions are:
        // status = 3 - held (the document is on loan by the patron)
        $filter = ['status' => [3]];
        // get items-docs for given filters
        $items = $this->paiaGetItems($patron, $filter);

        return $this->mapPaiaItems($items, 'myTransactionsMapping');
    }

    /**
     * Get Patron StorageRetrievalRequests
     *
     * This is responsible for retrieving all storage retrieval requests
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array Array of the patron's storage retrieval requests on success,
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        // filters for getMyStorageRetrievalRequests are:
        // status = 2 - ordered (the document is ordered by the patron)
        $filter = ['status' => [2]];
        // get items-docs for given filters
        $items = $this->paiaGetItems($patron, $filter);

        return $this->mapPaiaItems($items, 'myStorageRetrievalRequestsMapping');
    }

    /**
     * This method queries the ILS for new items
     *
     * @param string $page    page number of results to retrieve (counting starts @1)
     * @param string $limit   the size of each page of results to retrieve
     * @param string $daysOld the maximum age of records to retrieve in days (max 30)
     * @param string $fundID  optional fund ID to use for limiting results
     *
     * @return array An associative array with two keys: 'count' (the number of items
     * in the 'results' array) and 'results' (an array of associative arrays, each
     * with a single key: 'id', a record ID).
     */
    public function getNewItems($page, $limit, $daysOld, $fundID)
    {
        return [];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     *                           method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron = null, $holdDetails = null)
    {
        // How to get valid PickupLocations for a PICA LBS?
        return [];
    }

    /**
     * This method returns a string to use as the input form value for renewing
     * each hold item. (optional, but required if you implement the
     * renewMyItems method) Not supported prior to VuFind 1.2
     *
     * @param array $checkOutDetails One of the individual item arrays returned by
     *                               the getMyTransactions method
     *
     * @return string A string to use as the input form value for renewing
     *                each item; you can pass any data that is needed by your
     *                ILS to identify the transaction to renew – the output
     *                of this method will be used as part of the input to the
     *                renewMyItems method.
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['renew_details'];
    }

    /**
     * Get the callnumber of this item
     *
     * @param array $doc Array of PAIA item.
     *
     * @return String
     */
    protected function getCallNumber($doc)
    {
        return $doc['label'] ?? null;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron's username
     * @param string $password The patron's login password
     *
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @throws ILSException
     */
    public function patronLogin($username, $password)
    {
        if ($username == '' || $password == '') {
            throw new ILSException('Invalid Login, Please try again.');
        }

        $session = $this->getSession();

        // if we already have a session with access_token and patron id, try to get
        // patron info with session data
        if (isset($session->expires) && $session->expires > time()) {
            try {
                return $this->enrichUserDetails(
                    $this->paiaGetUserDetails($session->patron),
                    $password
                );
            } catch (ILSException $e) {
                $this->debug('Session expired, login again', ['info' => 'info']);
            }
        }
        try {
            if ($this->paiaLogin($username, $password)) {
                return $this->enrichUserDetails(
                    $this->paiaGetUserDetails($session->patron),
                    $password
                );
            }
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * PAIA helper function to map session data to return value of patronLogin()
     *
     * @param array  $details  Patron details returned by patronLogin
     * @param string $password Patron cataloge password
     *
     * @return mixed
     */
    protected function enrichUserDetails($details, $password)
    {
        $session = $this->getSession();

        $details['cat_username'] = $session->patron;
        $details['cat_password'] = $password;
        return $details;
    }

    /**
     * Returns an array with PAIA confirmations based on the given holdDetails which
     * will be used for a request.
     * Currently two condition types are supported:
     *  - http://purl.org/ontology/paia#StorageCondition to select a document
     *    location -- mapped to pickUpLocation
     *  - http://purl.org/ontology/paia#FeeCondition to confirm or select a document
     *    service causing a fee -- not mapped yet
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return array
     */
    protected function getConfirmations($holdDetails)
    {
        $confirmations = [];
        if (isset($holdDetails['pickUpLocation'])) {
            $confirmations['http://purl.org/ontology/paia#StorageCondition']
                = [$holdDetails['pickUpLocation']];
        }
        return $confirmations;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details
     *
     * Make a request on a specific record
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $item = $holdDetails['item_id'];
        $patron = $holdDetails['patron'];

        $doc = [];
        $doc['item'] = stripslashes($item);
        if ($confirm = $this->getConfirmations($holdDetails)) {
            $doc["confirm"] = $confirm;
        }
        $post_data['doc'][] = $doc;

        try {
            $array_response = $this->paiaPostAsArray(
                'core/' . $patron['cat_username'] . '/request', $post_data
            );
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
            return [
                'success' => false,
                'sysMessage' => $e->getMessage(),
            ];
        }

        $details = [];
        if (isset($array_response['error'])) {
            $details = [
                'success' => false,
                'sysMessage' => $array_response['error_description']
            ];
        } else {
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                if (isset($element['error'])) {
                    $details = [
                        'success' => false,
                        'sysMessage' => $element['error']
                    ];
                } else {
                    $details = [
                        'success' => true,
                        'sysMessage' => 'Successfully requested'
                    ];
                    // if caching is enabled for DAIA remove the cached data for the
                    // current item otherwise the changed status will not be shown
                    // before the cache expires
                    if ($this->daiaCacheEnabled) {
                        $this->removeCachedData($holdDetails['doc_id']);
                    }
                }
            }
        }
        return $details;
    }

    /**
     * Place a Storage Retrieval Request
     *
     * Attempts to place a request on a particular item and returns
     * an array with result details.
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        // Making a storage retrieval request is the same in PAIA as placing a Hold
        return $this->placeHold($details);
    }

    /**
     * This method renews a list of items for a specific patron.
     *
     * @param array $details - An associative array with two keys:
     *      patron - array returned by patronLogin method
     *      details - array of values returned by the getRenewDetails method
     *                identifying which items to renew
     *
     * @return array - An associative array with two keys:
     *     blocks - An array of strings specifying why a user is blocked from
     *              renewing (false if no blocks)
     *     details - Not set when blocks exist; otherwise, an array of
     *               associative arrays (keyed by item ID) with each subarray
     *               containing these keys:
     *                  success – Boolean true or false
     *                  new_date – string – A new due date
     *                  new_time – string – A new due time
     *                  item_id – The item id of the renewed item
     *                  sysMessage – A system supplied renewal message (optional)
     */
    public function renewMyItems($details)
    {
        $it = $details['details'];
        $items = [];
        foreach ($it as $item) {
            $items[] = ['item' => stripslashes($item)];
        }
        $patron = $details['patron'];
        $post_data = ["doc" => $items];

        try {
            $array_response = $this->paiaPostAsArray(
                'core/' . $patron['cat_username'] . '/renew', $post_data
            );
        } catch (ILSException $e) {
            $this->debug($e->getMessage());
            return [
                'success' => false,
                'sysMessage' => $e->getMessage(),
            ];
        }

        $details = [];

        if (isset($array_response['error'])) {
            $details[] = [
                'success' => false,
                'sysMessage' => $array_response['error_description']
            ];
        } else {
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                // VuFind can only assign the response to an id - if none is given
                // (which is possible) simply skip this response element
                if (isset($element['item'])) {
                    if (isset($element['error'])) {
                        $details[$element['item']] = [
                            'success' => false,
                            'sysMessage' => $element['error']
                        ];
                    } elseif ($element['status'] == '3') {
                        $details[$element['item']] = [
                            'success'  => true,
                            'new_date' => isset($element['endtime'])
                                ? $this->convertDatetime($element['endtime']) : '',
                            'item_id'  => 0,
                            'sysMessage' => 'Successfully renewed'
                        ];
                    } else {
                        $details[$element['item']] = [
                            'success'  => false,
                            'item_id'  => 0,
                            'new_date' => isset($element['endtime'])
                                ? $this->convertDatetime($element['endtime']) : '',
                            'sysMessage' => 'Request rejected'
                        ];
                    }
                }

                // DAIA cache cannot be cleared for particular item as PAIA only
                // operates with specific item URIs and the DAIA cache is setup
                // by doc URIs (containing items with URIs)
            }

            // If caching is enabled for PAIA clear the cache as at least for one
            // item renew was successfull and therefore the status changed. Otherwise
            // the changed status will not be shown before the cache expires.
            if ($this->paiaCacheEnabled) {
                $this->removeCachedData($patron['cat_username']);
            }
        }
        $returnArray = ['blocks' => false, 'details' => $details];
        return $returnArray;
    }

    /*
     * PAIA functions
     */

    /**
     * PAIA support method to return strings for PAIA service status values
     *
     * @param string $status PAIA service status
     *
     * @return string Describing PAIA service status
     */
    protected function paiaStatusString($status)
    {
        return isset(self::$statusStrings[$status])
            ? self::$statusStrings[$status] : '';
    }

    /**
     * PAIA support method for PAIA core method 'items' returning only those
     * documents containing the given service status.
     *
     * @param array $patron Array with patron information
     * @param array $filter Array of properties identifying the wanted items
     *
     * @return array|mixed Array of documents containing the given filter properties
     */
    protected function paiaGetItems($patron, $filter = [])
    {
        // check for existing data in cache
        if ($this->paiaCacheEnabled) {
            $itemsResponse = $this->getCachedData($patron['cat_username']);
        }

        if (!isset($itemsResponse) || $itemsResponse == null) {
            $itemsResponse = $this->paiaGetAsArray(
                'core/' . $patron['cat_username'] . '/items'
            );
            if ($this->paiaCacheEnabled) {
                $this->putCachedData($patron['cat_username'], $itemsResponse);
            }
        }

        if (isset($itemsResponse['doc'])) {
            if (count($filter)) {
                $filteredItems = [];
                foreach ($itemsResponse['doc'] as $doc) {
                    $filterCounter = 0;
                    foreach ($filter as $filterKey => $filterValue) {
                        if (isset($doc[$filterKey])
                            && in_array($doc[$filterKey], (array)$filterValue)
                        ) {
                            $filterCounter++;
                        }
                    }
                    if ($filterCounter == count($filter)) {
                        $filteredItems[] = $doc;
                    }
                }
                return $filteredItems;
            } else {
                return $itemsResponse;
            }
        } else {
            $this->debug(
                "No documents found in PAIA response. Returning empty array."
            );
        }
        return [];
    }

    /**
     * PAIA support method to retrieve needed ItemId in case PAIA-response does not
     * contain it
     *
     * @param string $id itemId
     *
     * @return string $id
     */
    protected function getAlternativeItemId($id)
    {
        return $id;
    }

    /**
     * PAIA support function to implement ILS specific parsing of user_details
     *
     * @param string $patron        User id
     * @param array  $user_response Array with PAIA response data
     *
     * @return array
     */
    protected function paiaParseUserDetails($patron, $user_response)
    {
        $username = trim($user_response['name']);
        if (count(explode(',', $username)) == 2) {
            $nameArr = explode(',', $username);
            $firstname = $nameArr[1];
            $lastname = $nameArr[0];
        } else {
            $nameArr = explode(' ', $username);
            $firstname = $nameArr[0];
            $lastname = '';
            array_shift($nameArr);
            foreach ($nameArr as $value) {
                $lastname .= ' ' . $value;
            }
            $lastname = trim($lastname);
        }

        // TODO: implement parsing of user details according to types set
        // (cf. https://github.com/gbv/paia/issues/29)

        $user = [];
        $user['id']        = $patron;
        $user['firstname'] = $firstname;
        $user['lastname']  = $lastname;
        $user['email']     = ($user_response['email'] ?? '');
        $user['major']     = null;
        $user['college']   = null;
        // add other information from PAIA - we don't want anything to get lost
        // while parsing
        foreach ($user_response as $key => $value) {
            if (!isset($user[$key])) {
                $user[$key] = $value;
            }
        }
        return $user;
    }

    /**
     * PAIA helper function to allow customization of mapping from PAIA response to
     * VuFind ILS-method return values.
     *
     * @param array  $items   Array of PAIA items to be mapped
     * @param string $mapping String identifying a custom mapping-method
     *
     * @return array
     */
    protected function mapPaiaItems($items, $mapping)
    {
        if (is_callable([$this, $mapping])) {
            return $this->$mapping($items);
        }

        $this->debug('Could not call method: ' . $mapping . '() .');
        return [];
    }

    /**
     * Map a PAIA document to an array for use in generating a VuFind request
     * (holds, storage retrieval, etc).
     *
     * @param array $doc Array of PAIA document to be mapped.
     *
     * @return array
     */
    protected function getBasicDetails($doc)
    {
        $result = [];

        // item (0..1) URI of a particular copy
        $result['item_id'] = ($doc['item'] ?? '');

        $result['cancel_details']
            = (isset($doc['cancancel']) && $doc['cancancel'])
            ? $result['item_id'] : '';

        // edition (0..1) URI of a the document (no particular copy)
        // hook for retrieving alternative ItemId in case PAIA does not
        // the needed id
        $result['id'] = (isset($doc['edition'])
            ? $this->getAlternativeItemId($doc['edition']) : '');

        $result['type'] = $this->paiaStatusString($doc['status']);

        // storage (0..1) textual description of location of the document
        $result['location'] = ($doc['storage'] ?? null);

        // queue (0..1) number of waiting requests for the document or item
        $result['position'] =  ($doc['queue'] ?? null);

        // only true if status == 4
        $result['available'] = false;

        // about (0..1) textual description of the document
        $result['title'] = ($doc['about'] ?? null);

        // PAIA custom field
        // label (0..1) call number, shelf mark or similar item label
        $result['callnumber'] = $this->getCallNumber($doc);

        /*
         * meaning of starttime and endtime depends on status:
         *
         * status | starttime
         *        | endtime
         * -------+--------------------------------
         * 0      | -
         *        | -
         * 1      | when the document was reserved
         *        | when the reserved document is expected to be available
         * 2      | when the document was ordered
         *        | when the ordered document is expected to be available
         * 3      | when the document was lend
         *        | when the loan period ends or ended (due)
         * 4      | when the document is provided
         *        | when the provision will expire
         * 5      | when the request was rejected
         *        | -
         */

        $result['create'] = (isset($doc['starttime'])
            ? $this->convertDatetime($doc['starttime']) : '');

        // Optional VuFind fields
        /*
        $result['reqnum'] = null;
        $result['volume'] =  null;
        $result['publication_year'] = null;
        $result['isbn'] = null;
        $result['issn'] = null;
        $result['oclc'] = null;
        $result['upc'] = null;
        */

        return $result;
    }

    /**
     * This PAIA helper function allows custom overrides for mapping of PAIA response
     * to getMyHolds data structure.
     *
     * @param array $items Array of PAIA items to be mapped.
     *
     * @return array
     */
    protected function myHoldsMapping($items)
    {
        $results = [];

        foreach ($items as $doc) {
            $result = $this->getBasicDetails($doc);

            if ($doc['status'] == '4') {
                $result['expire'] = (isset($doc['endtime'])
                    ? $this->convertDatetime($doc['endtime']) : '');
            } else {
                $result['duedate'] = (isset($doc['endtime'])
                    ? $this->convertDatetime($doc['endtime']) : '');
            }

            // status: provided (the document is ready to be used by the patron)
            $result['available'] = $doc['status'] == 4 ? true : false;

            $results[] = $result;
        }
        return $results;
    }

    /**
     * This PAIA helper function allows custom overrides for mapping of PAIA response
     * to getMyStorageRetrievalRequests data structure.
     *
     * @param array $items Array of PAIA items to be mapped.
     *
     * @return array
     */
    protected function myStorageRetrievalRequestsMapping($items)
    {
        $results = [];

        foreach ($items as $doc) {
            $result = $this->getBasicDetails($doc);

            $results[] = $result;
        }
        return $results;
    }

    /**
     * This PAIA helper function allows custom overrides for mapping of PAIA response
     * to getMyTransactions data structure.
     *
     * @param array $items Array of PAIA items to be mapped.
     *
     * @return array
     */
    protected function myTransactionsMapping($items)
    {
        $results = [];

        foreach ($items as $doc) {
            $result = [];
            // canrenew (0..1) whether a document can be renewed (bool)
            $result['renewable'] = ($doc['canrenew'] ?? false);

            // item (0..1) URI of a particular copy
            $result['item_id'] = ($doc['item'] ?? '');

            $result['renew_details']
                = (isset($doc['canrenew']) && $doc['canrenew'])
                ? $result['item_id'] : '';

            // edition (0..1)  URI of a the document (no particular copy)
            // hook for retrieving alternative ItemId in case PAIA does not
            // the needed id
            $result['id'] = (isset($doc['edition'])
                ? $this->getAlternativeItemId($doc['edition']) : '');

            // requested (0..1) URI that was originally requested

            // about (0..1) textual description of the document
            $result['title'] = ($doc['about'] ?? null);

            // queue (0..1) number of waiting requests for the document or item
            $result['request'] = ($doc['queue'] ?? null);

            // renewals (0..1) number of times the document has been renewed
            $result['renew'] = ($doc['renewals'] ?? null);

            // reminder (0..1) number of times the patron has been reminded
            $result['reminder'] = (
                $doc['reminder'] ?? null
            );

            // custom PAIA field
            // starttime (0..1) date and time when the status began
            $result['startTime'] = (isset($doc['starttime'])
                ? $this->convertDatetime($doc['starttime']) : '');

            // endtime (0..1) date and time when the status will expire
            $result['dueTime'] = (isset($doc['endtime'])
                ? $this->convertDatetime($doc['endtime']) : '');

            // duedate (0..1) date when the current status will expire (deprecated)
            $result['duedate'] = (isset($doc['duedate'])
                ? $this->convertDate($doc['duedate']) : '');

            // cancancel (0..1) whether an ordered or provided document can be
            // canceled

            // error (0..1) error message, for instance if a request was rejected
            $result['message'] = ($doc['error'] ?? '');

            // storage (0..1) textual description of location of the document
            $result['borrowingLocation'] = ($doc['storage'] ?? '');

            // storageid (0..1) location URI

            // PAIA custom field
            // label (0..1) call number, shelf mark or similar item label
            $result['callnumber'] = $this->getCallNumber($doc);

            // Optional VuFind fields
            /*
            $result['barcode'] = null;
            $result['dueStatus'] = null;
            $result['renewLimit'] = "1";
            $result['volume'] = null;
            $result['publication_year'] = null;
            $result['isbn'] = null;
            $result['issn'] = null;
            $result['oclc'] = null;
            $result['upc'] = null;
            $result['institution_name'] = null;
            */

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Post something to a foreign host
     *
     * @param string $file         POST target URL
     * @param string $data_to_send POST data
     * @param string $access_token PAIA access token for current session
     *
     * @return string POST response
     * @throws ILSException
     */
    protected function paiaPostRequest($file, $data_to_send, $access_token = null)
    {
        // json-encoding
        $postData = json_encode($data_to_send);

        $http_headers = [];
        if (isset($access_token)) {
            $http_headers['Authorization'] = 'Bearer ' . $access_token;
        }

        try {
            $result = $this->httpService->post(
                $this->paiaURL . $file,
                $postData,
                'application/json; charset=UTF-8',
                $this->paiaTimeout,
                $http_headers
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            // log error for debugging
            $this->debug(
                'HTTP status ' . $result->getStatusCode() .
                ' received'
            );
        }
        // return any result as error-handling is done elsewhere
        return $result->getBody();
    }

    /**
     * GET data from foreign host
     *
     * @param string $file         GET target URL
     * @param string $access_token PAIA access token for current session
     *
     * @return bool|string
     * @throws ILSException
     */
    protected function paiaGetRequest($file, $access_token)
    {
        $http_headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-type' => 'application/json; charset=UTF-8',
        ];

        try {
            $result = $this->httpService->get(
                $this->paiaURL . $file,
                [], $this->paiaTimeout, $http_headers
            );
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            // log error for debugging
            $this->debug(
                'HTTP status ' . $result->getStatusCode() .
                ' received'
            );
        }
        // return any result as error-handling is done elsewhere
        return $result->getBody();
    }

    /**
     * Helper function for PAIA to uniformely parse JSON
     *
     * @param string $file JSON data
     *
     * @return mixed
     * @throws ILSException
     */
    protected function paiaParseJsonAsArray($file)
    {
        $responseArray = json_decode($file, true);

        if (isset($responseArray['error'])) {
            throw new ILSException(
                $responseArray['error'],
                $responseArray['code']
            );
        }

        return $responseArray;
    }

    /**
     * Retrieve file at given URL and return it as json_decoded array
     *
     * @param string $file GET target URL
     *
     * @return array|mixed
     * @throws ILSException
     */
    protected function paiaGetAsArray($file)
    {
        $responseJson = $this->paiaGetRequest(
            $file,
            $this->getSession()->access_token
        );

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (ILSException $e) {
            $this->debug($e->getCode() . ':' . $e->getMessage());
            return [];
        }

        return $responseArray;
    }

    /**
     * Post something at given URL and return it as json_decoded array
     *
     * @param string $file POST target URL
     * @param array  $data POST data
     *
     * @return array|mixed
     * @throws ILSException
     */
    protected function paiaPostAsArray($file, $data)
    {
        $responseJson = $this->paiaPostRequest(
            $file,
            $data,
            $this->getSession()->access_token
        );

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (ILSException $e) {
            $this->debug($e->getCode() . ':' . $e->getMessage());
            /* TODO: do not return empty array, this causes eventually confusion */
            return [];
        }

        return $responseArray;
    }

    /**
     * PAIA authentication function
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login, PEAR_Error on error.
     * @throws ILSException
     */
    protected function paiaLogin($username, $password)
    {
        // perform full PAIA auth and get patron info
        $post_data = [
            "username"   => $username,
            "password"   => $password,
            "grant_type" => "password",
            "scope"      => "read_patron read_fees read_items write_items " .
                "change_password"
        ];
        $responseJson = $this->paiaPostRequest('auth/login', $post_data);

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (ILSException $e) {
            if ($e->getMessage() === 'access_denied') {
                return false;
            }
            throw new ILSException(
                $e->getCode() . ':' . $e->getMessage()
            );
        }

        if (!isset($responseArray['access_token'])) {
            throw new ILSException(
                'Unknown error! Access denied.'
            );
        } elseif (!isset($responseArray['patron'])) {
            throw new ILSException(
                'Login credentials accepted, but got no patron ID?!?'
            );
        } else {
            // at least access_token and patron got returned which is sufficient for
            // us, now save all to session
            $session = $this->getSession();

            $session->patron
                = $responseArray['patron'] ?? null;
            $session->access_token
                = $responseArray['access_token'] ?? null;
            $session->scope
                = isset($responseArray['scope'])
                ? explode(' ', $responseArray['scope']) : null;
            $session->expires
                = isset($responseArray['expires_in'])
                ? (time() + ($responseArray['expires_in'])) : null;

            return true;
        }
    }

    /**
     * Support method for paiaLogin() -- load user details into session and return
     * array of basic user data.
     *
     * @param array $patron patron ID
     *
     * @return array
     * @throws ILSException
     */
    protected function paiaGetUserDetails($patron)
    {
        $responseJson = $this->paiaGetRequest(
            'core/' . $patron, $this->getSession()->access_token
        );

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (ILSException $e) {
            throw new ILSException(
                $e->getMessage(), $e->getCode()
            );
        }
        return $this->paiaParseUserDetails($patron, $responseArray);
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
        return $this->checkRequestIsValid($id, $data, $patron);
    }

    /**
     * Check if hold or recall available
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
    public function checkRequestIsValid($id, $data, $patron)
    {
        // TODO: make this more configurable
        if (isset($patron['status']) && $patron['status'] == 0
            && isset($patron['expires']) && $patron['expires'] > date('Y-m-d')
            && in_array('write_items', $this->getScope())
        ) {
            return true;
        }
        return false;
    }
}
