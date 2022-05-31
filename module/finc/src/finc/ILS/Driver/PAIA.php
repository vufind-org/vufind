<?php
/**
 * PAIA ILS Driver for VuFind to get patron information
 *
 * PHP version 5
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

namespace finc\ILS\Driver;

use VuFind\Exception\Auth as AuthException;
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
class PAIA extends \VuFind\ILS\Driver\PAIA
{
    /**
     * PAIA scopes as defined in http://gbv.github.io/paia/paia.html#access-tokens-and-scopes
     */
    const SCOPE_READ_PATRON = 'read_patron';
    const SCOPE_UPDATE_PATRON = 'update_patron';
    const SCOPE_UPDATE_PATRON_NAME = 'update_patron_name';
    const SCOPE_UPDATE_PATRON_EMAIL = 'update_patron_email';
    const SCOPE_UPDATE_PATRON_ADDRESS = 'update_patron_address';
    const SCOPE_READ_FEES = 'read_fees';
    const SCOPE_READ_ITEMS = 'read_items';
    const SCOPE_WRITE_ITEMS = 'write_items';
    const SCOPE_CHANGE_PASSWORD = 'change_password';
    const SCOPE_READ_NOTIFICATIOS = 'read_notifications';
    const SCOPE_DELETE_NOTIFICATIONS = 'delete_notifications';

    protected $last_error = null;

    protected $notificationsPrefix;

    protected $grantType;

    /**
     * @var array
     */
    protected $conditions;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     * @param \Zend\Session\SessionManager $sessionManager Session Manager
     */
    public function __construct(
        \VuFind\Date\Converter $converter,
        \Zend\Session\SessionManager $sessionManager
    ) {
        parent::__construct($converter, $sessionManager);
        $this->sessionManager = $sessionManager;
    }

    /**
     * {@inheritdoc}
     * Additionally sets notificationsPrefix config
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        if (isset($this->config['PAIA']['grantType'])) {
            $this->grantType = $this->config['PAIA']['grantType'];
        }

        if (isset($this->config['PAIA']['paiaNotificationsPrefix'])) {
            $this->notificationsPrefix = $this->config['PAIA']['paiaNotificationsPrefix'];
        }

        if (isset($this->config['PAIA']['paiaConditions'])) {
            $this->conditions = $this->config['PAIA']['paiaConditions'];
        }
    }

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
     * @throws \Exception
     * @throws ILSException You are not entitled to write items.
     */
    public function cancelHolds($cancelDetails)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)) {
            throw new ILSException('You are not entitled to write items.');
        }

        $it = $cancelDetails['details'];
        $items = [];
        $count = 0;
        foreach ($it as $item) {
            $items[] = ['item' => stripslashes($item)];
            //we count how many items shall be cancelled
            $count++;
        }
        $patron = $cancelDetails['patron'];
        $post_data = ["doc" => $items];

        try {
            $array_response = $this->paiaPostAsArray(
                'core/'.$patron['cat_username'].'/cancel',
                $post_data
            );
        } catch (\Exception $e) {
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
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                $item_id = $element['item'];
                if (isset($element['error']) && $element['error']) {
                    $details[$item_id] = [
                        'success' => false,
                        'status' => $element['error'],
                        'sysMessage' => 'Cancel request rejected'
                    ];
                    //if an item could not be cancelled it must not be counted
                    $count--;
                } else {
                    $details[$item_id] = [
                        'success' => true,
                        'status' => 'Success',
                        'sysMessage' => 'Successfully cancelled'
                    ];

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
     * @throws \Exception
     * @throws ILSException You are not entitled to write items.
     */
    public function changePassword($details)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_CHANGE_PASSWORD)) {
            throw new ILSException('You are not entitled to change password.');
        }

        $post_data = [
            "patron"       => $details['patron']['cat_username'],
            "username"     => $details['patron']['cat_username'],
            "old_password" => $details['oldPassword'],
            "new_password" => $details['newPassword']
        ];

        try {
            $array_response = $this->paiaPostAsArray(
                'auth/change',
                $post_data
            );
        } catch (AuthException $e) {
            return [
                'success' => false,
                'status' => 'errorcode_old_password_validation_error'
            ];
        } catch (\Exception $e) {
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
                    isset($array_response['error'])
                        ? $array_response['error'] : ' ' .
                    isset($array_response['error_description'])
                        ? $array_response['error_description'] : ' '
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
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed Array of the patron's fines on success
     * @throws \Exception
     * @throws ILSException You are not entitled to read fees
     */
    public function getMyFines($patron)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_READ_FEES)) {
            throw new ILSException('You are not entitled to read fees.');
        }

        try {
            $fees = $this->paiaGetAsArray(
                'core/'.$patron['cat_username'].'/fees'
            );
        } catch (\Exception $e) {
            // all error handling is done in paiaHandleErrors so pass on the excpetion
            throw $e;
        }

        // PAIA simple data type money: a monetary value with currency (format
        // [0-9]+\.[0-9][0-9] [A-Z][A-Z][A-Z]), for instance 0.80 USD.
        $feeConverter = function ($fee) {
            $paiaCurrencyPattern = "/^(\-?[0-9]+\.[0-9][0-9]) ([A-Z][A-Z][A-Z])$/";
            if (preg_match($paiaCurrencyPattern, $fee, $feeMatches)) {
                // VuFind expects fees in PENNIES
                return ($feeMatches[1]*100);
            }
            return null;
        };

        $results = [];
        if (isset($fees['fee'])) {
            foreach ($fees['fee'] as $fee) {
                // won't show this fee if there is a formatting error
                // see #11128-45, DM
                if ($amount = $feeConverter($fee['amount'])) {
                    $result = [
                        // fee.amount  1..1  money  amount of a single fee
                        'amount' => $amount,
                        'checkout' => '',
                        // fee.feetype 0..1  string  textual description of the type
                        // of service that caused the fee
                        'fine' => (isset($fee['feetype']) ? $fee['feetype'] : null),
                        'balance' => $amount,
                        // fee.date  0..1  date  date when the fee was claimed
                        'createdate' => (isset($fee['date'])
                            ? $this->convertDate($fee['date']) : null),
                        'duedate' => '',
                        // fee.edition 0..1  URI edition that caused the fee
                        'id' => (isset($fee['edition'])
                            ? $this->getAlternativeItemId($fee['edition']) : ''),
                    ];
                    // custom PAIA fields can get added in getAdditionalFeeData
                    $results[] = $result + $this->getAdditionalFeeData($fee, $patron);
                }
            }
        }
        return $results;
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
                'firstname'    => $patron['firstname'],
                'lastname'     => $patron['lastname'],
                'address1'     => null,
                'address2'     => null,
                'city'         => null,
                'country'      => null,
                'zip'          => null,
                'phone'        => null,
                'group'        => null,
                'home_library' => null,
                // PAIA specific custom values
                'expires'    => isset($patron['expires'])
                    ? $this->convertDate($patron['expires']) : null,
                'statuscode' => isset($patron['status'])
                    ? $patron['status'] : null,
                'note' => isset($patron['note'])
                    ? $patron['note'] : null,
                'canWrite' =>
                    in_array(self::SCOPE_WRITE_ITEMS, $this->getScope()),
            ];
        }
        return [];
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
     * @throws \Exception
     * @throws ILSException Invalid Login, Please try again
     */
    public function patronLogin($username, $password)
    {
        if ($username == '' || $password == '') {
            throw new ILSException('Invalid Login, Please try again.');
        }

        $session = $this->getSession();

        try {
            if (isset($session->expires) && $session->expires > time()) {
                return $this->enrichUserDetails(
                    $this->paiaGetUserDetails($session->patron),
                    $password
                );
            }
            if ($this->paiaLogin($username, $password)) {
                return $this->enrichUserDetails(
                    $this->paiaGetUserDetails($session->patron),
                    $password
                );
            }
        } catch (AuthException $e) {
            // Swallow auth exceptions and return null compliant to spec at:
            // https://vufind.org/wiki/development:plugins:ils_drivers#patronlogin
            return null;
        }
    }

    /**
     * Handle PAIA request errors and throw appropriate exception.
     *
     * @param array $array Array containing error messages
     *
     * @return void
     * @throws AuthException
     * @throws ILSException
     */
    protected function paiaHandleErrors($array)
    {
        // TODO: also have exception contain content of 'error' as for at least
        //       error code 403 two differing errors are possible
        //       (cf.  http://gbv.github.io/paia/paia.html#request-errors)
        if (isset($array['error'])) {
            $this->last_error = $array;
            switch ($array['error']) {
                // cf. http://gbv.github.io/paia/paia.html#request-errors
                // error  code  error_description
                // access_denied  403  Wrong or missing credentials to get an access token
                case 'access_denied':
                    throw new AuthException(
                        isset($array['error_description'])
                            ? $array['error_description'] : $array['error'],
                        isset($array['code']) ? $array['code'] : ''
                    );
                // not_found 404
                // Unknown request URL or unknown patron. Implementations SHOULD
                // first check authentication and prefer error invalid_grant or
                // access_denied to prevent leaking patron identifiers.
                case 'not_found':
                // not_implemented 501
                // Known but unsupported request URL (for instance a PAIA auth
                // server server may not implement http://example.org/core/change)
                case 'not_implemented':
                // invalid_request 405 Unexpected HTTP verb
                // invalid_request 400 Malformed request (for instance error parsing
                // JSON, unsupported request content type, etc.)
                // invalid_request 422 The request parameters could be parsed but
                // they don’t match the request method (for instance missing fields,
                // invalid values, etc.)
                case 'invalid_request':
                // invalid_grant 401
                // The access token was missing, invalid, or expired
                case 'invalid_grant':
                // insufficient_scope 403
                // The access token was accepted but it lacks permission for the request
                case 'insufficient_scope':
                // internal_error 500
                // An unexpected error occurred. This error corresponds to a bug in
                // the implementation of a PAIA auth/core server
                case 'internal_error':
                // service_unavailable 503
                // The request couldn’t be serviced because of a temporary failure
                case 'service_unavailable':
                // bad_gateway 502
                // The request couldn’t be serviced because of a backend failure
                // (for instance the library system’s database)
                case 'bad_gateway':
                // gateway_timeout 504
                // The request couldn’t be serviced because of a backend failure'
                case 'gateway_timeout':
                default:
                    throw new ILSException(
                        isset($array['error_description'])
                            ? $array['error_description'] : $array['error']
                        . ' : ' .
                        isset($array['code']) ? $array['code'] : ''
                    );
            }
        }
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
     * @throws \Exception
     * @throws ILSException You are not entitled to write items
     */
    public function placeHold($holdDetails)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)) {
            throw new ILSException('You are not entitled to write items.');
        }

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
                'core/'.$patron['cat_username'].'/request',
                $post_data
            );
        } catch (\Exception $e) {
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
     * This method renews a list of items for a specific patron.
     *
     * @param array $details - An associative array with two keys:
     *      patron - array returned by patronLogin method
     *      details - array of values returned by the getRenewDetails method
     *                identifying which items to renew
     *
     * @return  array - An associative array with two keys:
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
     * @throws \Exception
     * @throws ILSException You are not entitled to write items
     */
    public function renewMyItems($details)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)) {
            throw new ILSException('You are not entitled to write items.');
        }

        $it = $details['details'];
        $items = [];
        foreach ($it as $item) {
            $items[] = ['item' => stripslashes($item)];
        }
        $patron = $details['patron'];
        $post_data = ["doc" => $items];

        try {
            $array_response = $this->paiaPostAsArray(
                'core/'.$patron['cat_username'].'/renew',
                $post_data
            );
        } catch (\Exception $e) {
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
     * PAIA support method for PAIA core method 'items' returning only those
     * documents containing the given service status.
     *
     * @param array $patron Array with patron information
     * @param array $filter Array of properties identifying the wanted items
     *
     * @return array|mixed Array of documents containing the given filter properties
     * @throws \Exception
     * @throws ILSException You are not entitled to read items
     */
    protected function paiaGetItems($patron, $filter = [])
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_READ_ITEMS)) {
            throw new ILSException('You are not entitled to read items.');
        }

        // check for existing data in cache
        if ($this->paiaCacheEnabled) {
            $itemsResponse = $this->getCachedData($patron['cat_username']);
        }

        if (!isset($itemsResponse) || $itemsResponse == null) {
            try {
                $itemsResponse = $this->paiaGetAsArray(
                    'core/'.$patron['cat_username'].'/items'
                );
            } catch (\Exception $e) {
                // all error handling is done in paiaHandleErrors so pass on the excpetion
                throw $e;
            }
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
     * Helper function to generate cache key
     * @param array $patron
     * @return string
     */
    private function notificationsCacheKey($patron)
    {
        return $patron['cat_username'].'_notifications';
    }

    /**
     * Helper function to retrieve notification ID
     * @param string $messageId
     * @return string
     */
    protected function getPaiaNotificationsId($messageId)
    {
        if (isset($this->notificationsPrefix)
            &&
            strpos($messageId, $this->notificationsPrefix) === 0
        ) {
            return substr($messageId, strlen($this->notificationsPrefix));
        }
        return $messageId;
    }

    /**
     * PAIA support method for PAIA core method 'notifications'
     *
     * @param array $patron Array with patron information
     *
     * @return array|mixed Array of system notifications for the patron
     * @throws \Exception
     * @throws ILSException You are not entitled to read notifications
     */
    protected function paiaGetSystemMessages($patron)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_READ_NOTIFICATIOS)) {
            throw new ILSException('You are not entitled to read notifications.');
        }

        if ($this->paiaCacheEnabled) {
            $response = $this->getCachedData($this->notificationsCacheKey($patron));
            if (!empty($response)) {
                return $response;
            }
        }

        try {
            $response = $this->paiaGetAsArray(
                'core/'.$patron['cat_username'].'/notifications'
            );
        } catch (\Exception $e) {
            // all error handling is done in paiaHandleErrors so pass on the excpetion
            throw $e;
        }
        //$response = array_slice($response,0,20);
        foreach ($response as &$message) {
            //Fernleihmedium erhalten.[Barcode]7016265550[Titel: *]König von Deutschland
            if (preg_match('/\[Barcode\](\w\d*)/', $message['about'], $matches)) {
                $message['barcode'] = $matches[1];
            }
            if (preg_match('/\[Titel(.*)\](.+)/', $message['about'], $matches)) {
                $message['title'] = $matches[2];
            }
            $message['message'] = $message['about'];
            //2017-08-10T16:59:00+02:00
            $date = date_create_from_format(\DATE_RFC3339, $message['date']);
            if ($date !== false) {
                $message['datetime'] = $date->format('d.m.Y H:i');
            } else {
                //$errs = date_get_last_errors();
                $message['datetime'] = $message['date'];
            }
            $message['messageid'] = $message['item'];
        }

        if ($this->paiaCacheEnabled) {
            $this->putCachedData($this->notificationsCacheKey($patron), $response);
        }

        return $response;
    }

    /**
     * PAIA support method for PAIA core method DELETE 'notifications'
     *
     * @param array $patron Array with patron information
     * @param string $messageId
     * @param bool $keepCache decides whether to keep the cache or flush it, TRUE or FALSE, resp.
     * @return array|mixed Array of system notifications for the patron
     * @throws \Exception
     * @throws ILSException You are not entitled to read notifications
     */
    protected function paiaRemoveSystemMessage($patron, $messageId, $keepCache = false)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_DELETE_NOTIFICATIONS)) {
            throw new ILSException('You are not entitled to delete notifications.');
        }

        try {
            $response = $this->paiaDeleteRequest(
                'core/'.$patron['cat_username'].'/notifications/'.$this->getPaiaNotificationsId($messageId)
            );
        } catch (\Exception $e) {
            // all error handling is done in paiaHandleErrors so pass on the excpetion
            throw $e;
        }

        if (!$keepCache && $this->paiaCacheEnabled) {
            $this->removeCachedData($this->notificationsCacheKey($patron));
        }

        return $response;
    }

    /**
     * Removes patron's system notifcations
     * @param array $patron
     * @param array $messageIds
     * @return bool
     */
    protected function paiaRemoveSystemMessages($patron, array $messageIds)
    {
        foreach ($messageIds as $messageId) {
            if (!$this->paiaRemoveSystemMessage($patron, $messageId, true)) {
                return false;
            }
        }

        if ($this->paiaCacheEnabled) {
            $this->removeCachedData($this->notificationsCacheKey($patron));
        }

        return true;
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
            $result = [];

            // item (0..1) URI of a particular copy
            $result['item_id'] = (isset($doc['item']) ? $doc['item'] : '');

            $result['cancel_details']
                = (
                    isset($doc['cancancel'])
                    && $doc['cancancel']
                    && $this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)
                  ) ? $result['item_id'] : '';

            // edition (0..1) URI of a the document (no particular copy)
            // hook for retrieving alternative ItemId in case PAIA does not
            // the needed id
            $result['id'] = (isset($doc['edition'])
                ? $this->getAlternativeItemId($doc['edition']) : '');

            $result['type'] = $this->paiaStatusString($doc['status']);

            // storage (0..1) textual description of location of the document
            $result['location'] = (isset($doc['storage']) ? $doc['storage'] : null);

            // queue (0..1) number of waiting requests for the document or item
            $result['position'] =  (isset($doc['queue']) ? $doc['queue'] : null);

            // only true if status == 4
            $result['available'] = false;

            // about (0..1) textual description of the document
            $result['title'] = (isset($doc['about']) ? $doc['about'] : null);

            // PAIA custom field
            // label (0..1) call number, shelf mark or similar item label
            $result['callnumber'] = $this->getCallNumber($doc);

            // label (0..1) call number, shelf mark or similar item label
            $result['status'] = $doc['label'] ?? null;

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

            if ($doc['status'] == '4') {
                $result['expire'] = (isset($doc['endtime'])
                    ? $this->convertDatetime($doc['endtime']) : '');
            } else {
                $result['duedate'] = (isset($doc['endtime'])
                    ? $this->convertDatetime($doc['endtime']) : '');
            }

            // status: provided (the document is ready to be used by the patron)
            $result['available'] = $doc['status'] == 4 ? true : false;

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

            /* #17508 read optional PAIA information like pickupbranch */
            $this->mapOptions($doc, $result);

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
            $result = [];

            // item (0..1) URI of a particular copy
            $result['item_id'] = (isset($doc['item']) ? $doc['item'] : '');

            $result['cancel_details']
                = (
                    isset($doc['cancancel'])
                    && $doc['cancancel']
                    && $this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)
                  ) ? $result['item_id'] : '';

            // edition (0..1) URI of a the document (no particular copy)
            // hook for retrieving alternative ItemId in case PAIA does not
            // the needed id
            $result['id'] = (isset($doc['edition'])
                ? $this->getAlternativeItemId($doc['edition']) : '');

            $result['type'] = $this->paiaStatusString($doc['status']);

            // storage (0..1) textual description of location of the document
            $result['location'] = (isset($doc['storage']) ? $doc['storage'] : null);

            // queue (0..1) number of waiting requests for the document or item
            $result['position'] =  (isset($doc['queue']) ? $doc['queue'] : null);

            // only true if status == 4
            $result['available'] = false;

            // about (0..1) textual description of the document
            $result['title'] = (isset($doc['about']) ? $doc['about'] : null);

            // PAIA custom field
            // label (0..1) call number, shelf mark or similar item label
            $result['callnumber'] = $this->getCallNumber($doc);

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
            $result['renewable'] = (
                isset($doc['canrenew'])
                && $this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)
                ) ? $doc['canrenew'] : false;

            // item (0..1) URI of a particular copy
            $result['item_id'] = (isset($doc['item']) ? $doc['item'] : '');

            $result['renew_details']
                = (
                    isset($doc['canrenew'])
                    && $doc['canrenew']
                    && $this->paiaCheckScope(self::SCOPE_WRITE_ITEMS)
                  ) ? $result['item_id'] : '';

            // edition (0..1)  URI of a the document (no particular copy)
            // hook for retrieving alternative ItemId in case PAIA does not
            // the needed id
            $result['id'] = (isset($doc['edition'])
                ? $this->getAlternativeItemId($doc['edition']) : '');

            // requested (0..1) URI that was originally requested

            // about (0..1) textual description of the document
            $result['title'] = (isset($doc['about']) ? $doc['about'] : null);

            // queue (0..1) number of waiting requests for the document or item
            $result['request'] = (isset($doc['queue']) ? $doc['queue'] : null);

            // renewals (0..1) number of times the document has been renewed
            $result['renew'] = (isset($doc['renewals']) ? $doc['renewals'] : null);

            // reminder (0..1) number of times the patron has been reminded
            $result['reminder'] = (
                isset($doc['reminder']) ? $doc['reminder'] : null
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
            $result['message'] = (isset($doc['error']) ? $doc['error'] : '');

            // storage (0..1) textual description of location of the document
            $result['borrowingLocation'] = (isset($doc['storage'])
                ? $doc['storage'] : '');

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
     * Helper function for PAIA to uniformely parse JSON
     *
     * @param string $file JSON data
     *
     * @return mixed
     * @throws \Exception
     */
    protected function paiaParseJsonAsArray($file)
    {
        $responseArray = json_decode($file, true);

        // if we have an error response handle it accordingly (any will throw an
        // exception at the moment) and pass on the resulting exception
        if (isset($responseArray['error'])) {
            try {
                $this->paiaHandleErrors($responseArray);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $responseArray;
    }

    /**
     * Retrieve file at given URL and return it as json_decoded array
     *
     * @param string $file GET target URL
     *
     * @return array|mixed
     * @throws \Exception
     */
    protected function paiaGetAsArray($file)
    {
        $responseJson = $this->paiaGetRequest(
            $file,
            $this->getSession()->access_token
        );

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (\Exception $e) {
            // all error handling is done in paiaHandleErrors so pass on the
            // excpetion
            throw $e;
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
            // all error handling is done in paiaHandleErrors so pass on the
            // excpetion
            throw $e;
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
     * @throws \Exception
     * @throws ILSException
     */
    protected function paiaLogin($username, $password)
    {
        // as PAIA supports two authentication methods (defined as grant_type:
        // password or client_credentials), check which one is configured
        if (!in_array($this->grantType, ['password', 'client_credentials'])) {
            throw new ILSException(
                'Unsupported PAIA grantType configured.'
            );
        }

        // prepare http header
        $header_data = [];

        // prepare post data depending on configured grant type
        switch ($this->grantType) {
            case 'password' :
                $post_data = [
                    "username"   => $username,
                    "password"   => $password
                ];
                break;
            case 'client_credentials':
                // client_credentials only works if we have client_credentials
                // username and password
                if (isset($this->config['PAIA']['client_username']) &&
                    isset($this->config['PAIA']['client_password'])) {
                    $header_data["Authorization"] = 'Basic ' .
                        base64_encode(
                            $this->config['PAIA']['client_username'] . ':' .
                            $this->config['PAIA']['client_password']
                        );
                    $post_data = [
                        "patron" => $username // actual patron identifier
                    ];
                }
                break;
        }

        // post data is the same for all grant types
        $post_data['grant_type'] = $this->grantType;
        $post_data['scope'] = self::SCOPE_READ_PATRON . " " .
            self::SCOPE_READ_FEES . " " .
            self::SCOPE_READ_ITEMS . " " .
            self::SCOPE_WRITE_ITEMS . " " .
            self::SCOPE_CHANGE_PASSWORD;

        // perform full PAIA auth and get patron info
        try {
            $result = $this->httpService->post(
                $this->paiaURL . 'auth/login',
                json_encode($post_data),
                'application/json; charset=UTF-8',
                $this->paiaTimeout,
                $header_data
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

        // continue with result data
        $responseJson = $result->getBody();

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (\Exception $e) {
            // all error handling is done in paiaHandleErrors so pass on the
            // excpetion
            throw $e;
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
                = isset($responseArray['patron'])
                    ? $responseArray['patron'] : null;
            $session->access_token
                = isset($responseArray['access_token'])
                    ? $responseArray['access_token'] : null;
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
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_READ_PATRON)) {
            throw new ILSException('You are not entitled to read patron.');
        }

        $responseJson = $this->paiaGetRequest(
            'core/' . $patron,
            $this->getSession()->access_token
        );

        try {
            $responseArray = $this->paiaParseJsonAsArray($responseJson);
        } catch (ILSException $e) {
            // all error handling is done in paiaHandleErrors so pass on the
            // excpetion
            throw $e;
        }
        return $this->paiaParseUserDetails($patron, $responseArray);
    }


    /**
     * Get valid patron keys
     *
     * @return array
     * @access public
     */
    public function getValidPatronUpdateKeys()
    {
        return [
            'name'=>'name',
            'email'=>'email',
            'address'=>'address'
        ];
    }


    /**
     * update patron information using the PAIA function 'update patron'
     * see https://gbv.github.io/paia/paia.html#update-patron
     * @param array $values associative array containing strings for:
     * <ul>
     *   <li>name: new full name of the patron</li>
     *   <li>email: new email address of the patron</li>
     *   <li>address: new freeform address of the patron</li>
     * </ul>
     * All of those are optional. Any other key will be ignored. If additional
     * information shall be saved, this can be done via the freeform address field.
     * This function here does not make any assumptions on the syntax of the address
     * field, it should be field with correctly encoded data.
     *
     * @param array $patron current patron profile
     * @return array <ul><li>success: (bool) TRUE on success,
     *                                       FALSE on fail</li><li>details:
     *                                       (string) error details on fail</li></ul>
     * @throws ILSException
     */
    protected function paiaUpdatePatron($values, $patron)
    {
        // check if user has appropriate scope
        if (!$this->paiaCheckScope(self::SCOPE_UPDATE_PATRON)) {
            if ((isset($values['name']) && !$this->paiaCheckScope(self::SCOPE_UPDATE_PATRON_NAME))
                ||
                (isset($values['email']) && !$this->paiaCheckScope(self::SCOPE_UPDATE_PATRON_EMAIL))
                ||
                (isset($values['address']) && !$this->paiaCheckScope(self::SCOPE_UPDATE_PATRON_ADDRESS))
            ) {
                throw new ILSException(
                    'You are not allowed to update the desired patron information.'
                );
            }
        }

        try {
            $array_response = $this->paiaPostAsArray(
                'core/' . $patron['cat_username'],
                $values
            );
        } catch (\Exception $e) {
            $this->debug($e->getMessage());
            return [
                'success' => false,
                'sysMessage' => $e->getMessage(),
            ];
        }

        $details = [];
        if (array_key_exists('error', $array_response)) {
            $details = [
                'success' => false,
                'sysMessage' => $array_response['error_description']
            ];
        } else {
            $details = [
                'success' => true,
                'sysMessage' => 'Successfully requested'
            ];
        }
        return $details;
    }

    /**
     * Checks if the current scope is set for active session.
     *
     * @param string $scope Scope of paia
     *
     * @return boolean
     */
    protected function paiaCheckScope($scope)
    {
        return (!empty($scope) && is_array($this->getSession()->scope))
            ? in_array($scope, $this->getSession()->scope) : false;
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
        return $this->checkRequestIsValid($id, $data, $patron);
    }

    /**
     * Check if hold or recall available
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
    public function checkRequestIsValid($id, $data, $patron)
    {
        // TODO: make this more configurable
        if (isset($patron['status']) && $patron['status']  == 0
            && isset($patron['expires']) && $patron['expires'] > date('Y-m-d')
            && in_array(self::SCOPE_WRITE_ITEMS, $this->getScope())
        ) {
            return true;
        }
        return false;
    }

    /**
     * Get last error
     *
     * @return null
     * @access public
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * DELETE data on foreign host
     *
     * @param string $file         DELETE target URL
     * @param string $access_token PAIA access token for current session
     *
     * @return bool|string
     * @throws ILSException
     */
    protected function paiaDeleteRequest($file, $access_token = null)
    {
        if (is_null($access_token)) {
            $access_token = $this->getSession()->access_token;
        }

        $http_headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-type' => 'application/json; charset=UTF-8',
        ];

        try {
            $client = $this->httpService->createClient(
                $this->paiaURL . $file,
                \Zend\Http\Request::METHOD_DELETE,
                $this->paiaTimeout
            );
            $client->setHeaders($http_headers);
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            // log error for debugging
            $this->debug(
                'HTTP status ' . $result->getStatusCode() .
                ' received'
            );
            return false;
        }
        // return TRUE on success
        return true;
    }

    /***
     * finds conditions in PAIA items array and adds it as options in finc converted items array
     *
     * Conditions according to PAIA default are only intended for request, renew or cancel
     * BUT here used for items too, therefore should be only one option per condition
     * see: http://gbv.github.io/paia/paia.html#conditions-and-confirmations
     *
     * @param array         $doc  Array of PAIA input to be mapped
     * @param array         $result Array of PAIA output - called by reference
     * @param array|null    $configConditions conditions to be considered (optional, if null use config)
     *
     * @return bool True if any option was found, otherwise false.
     */
    protected function mapOptions(array $doc, array &$result, array $configConditions = null): bool
    {
        $configConditions = $configConditions ?? $this->conditions ?? [];

        foreach ($configConditions as $configCondition) {
            if ($optionsValues = $doc["condition"][$configCondition]["option"] ?? []) {
                $found = $result["options"][$configCondition] = $optionsValues;
            }
        }

        return !empty($found);
    }

    /**
     * Get (first) option for given condition and valid options from finc converted PAIA result
     *
     * @param string    $condition
     * @param array     $result
     * @param boolean   $onlyFirst
     * @param array     $validOptions
     *
     * @return array of option values: each either valid id or label or null if none set
     */
    public function getOptions(string $condition, array $result, bool $onlyFirst, array $validOptions = null): array
    {
        $retval = [];
        if (in_array($condition, $this->conditions)) {
            $options = $result['options'][$condition] ?? [];
            foreach ($options as $option) {
                if (!$validOptions) {
                    $retval[] = $option['about'] ?? $option['id'] ?? null;
                    if ($onlyFirst) {
                        break;
                    }
                } else {
                    foreach ($validOptions as $validOption) {
                        if (array_intersect($validOption, $option)) {
                            $retval[] = $option['id'] ?? $option['about'] ?? null;
                            if ($onlyFirst) {
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        return $retval;
    }
}
