<?php

/**
 * SirsiDynix Unicorn ILS Driver (VuFind side)
 *
 * PHP version 8
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
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Drew Farrugia <vufind-unicorn-l@lists.lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/vufind-unicorn/ vufind-unicorn project
 */

namespace VuFind\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Marc\MarcCollection;
use VuFind\Marc\MarcReader;

use function array_key_exists;
use function array_slice;
use function count;
use function floatval;
use function in_array;
use function sprintf;
use function strlen;

/**
 * SirsiDynix Unicorn ILS Driver (VuFind side)
 *
 * IMPORTANT: To use this driver you need to download the SirsiDynix API driver.pl
 * from http://code.google.com/p/vufind-unicorn/ and install it on your Sirsi
 * Unicorn/Symphony server. Please note: currently you will need to download
 * the driver.pl in the yorku branch on google code to use this driver.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Drew Farrugia <vufind-unicorn-l@lists.lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/vufind-unicorn/ vufind-unicorn project
 **/
class Unicorn extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\HasSorterInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\HasSorterTrait;

    /**
     * Host
     *
     * @var string
     */
    protected $host;

    /**
     * Port
     *
     * @var string
     */
    protected $port;

    /**
     * Name of API program
     *
     * @var string
     */
    protected $search_prog;

    /**
     * Full URL to API (alternative to host/port/search_prog)
     *
     * @var string
     */
    protected $url;

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

        // allow user to specify the full url to the Sirsi side perl script
        $this->url = $this->config['Catalog']['url'];

        // host/port/search_prog kept for backward compatibility
        if (
            isset($this->config['Catalog']['host'])
            && isset($this->config['Catalog']['port'])
            && isset($this->config['Catalog']['search_prog'])
        ) {
            $this->host = $this->config['Catalog']['host'];
            $this->port = $this->config['Catalog']['port'];
            $this->search_prog = $this->config['Catalog']['search_prog'];
        }
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
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for getting a list of valid library locations for
     * holds / recall retrieval
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
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $params = ['query' => 'libraries'];
        $response = $this->querySirsi($params);
        $response = rtrim($response);
        $lines = explode("\n", $response);
        $libraries = [];

        foreach ($lines as $line) {
            [$code, $name] = explode('|', $line);
            $libraries[] = [
                'locationID' => $code,
                'locationDisplay' => empty($name) ? $code : $name,
            ];
        }
        return $libraries;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        if ($patron && isset($patron['library'])) {
            return $patron['library'];
        }
        return $this->config['Holds']['defaultPickupLocation'];
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Voyager requires the patron details and an item
     * id. This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['item_id'];
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
        $patron = $renewDetails['patron'];
        $details = $renewDetails['details'];

        $chargeKeys = implode(',', $details);
        $params = [
          'query' => 'renew_items', 'chargeKeys' => $chargeKeys,
          'patronId' => $patron['cat_username'], 'pin' => $patron['cat_password'],
          'library' => $patron['library'],
        ];
        $response = $this->querySirsi($params);

        // process the API response
        if ($response == 'invalid_login') {
            return ['blocks' => ['authentication_error_admin']];
        }

        $results = [];
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            [$chargeKey, $result] = explode('-----API_RESULT-----', $line);
            $results[$chargeKey] = ['item_id' => $chargeKey];
            $matches = [];
            preg_match('/\^MN([0-9][0-9][0-9])/', $result, $matches);
            if (isset($matches[1])) {
                $status = $matches[1];
                if ($status == '214') {
                    $results[$chargeKey]['success'] = true;
                } else {
                    $results[$chargeKey]['success'] = false;
                    $results[$chargeKey]['sysMessage']
                        = $this->config['ApiMessages'][$status];
                }
            }
            preg_match('/\^CI([^\^]+)\^/', $result, $matches);
            if (isset($matches[1])) {
                [$newDate, $newTime] = explode(',', $matches[1]);
                $results[$chargeKey]['new_date'] = $newDate;
                $results[$chargeKey]['new_time'] = $newTime;
            }
        }
        return ['details' => $results];
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
        $params = ['query' => 'single', 'id' => $id];
        $response = $this->querySirsi($params);
        if (empty($response)) {
            return [];
        }

        // separate the item lines and the MARC holdings records
        $marc_marker = '-----BEGIN MARC-----';
        $marc_marker_pos = strpos($response, $marc_marker);
        $lines = ($marc_marker_pos !== false)
            ? substr($response, 0, $marc_marker_pos) : '';
        $marc = ($marc_marker_pos !== false)
            ? substr($response, $marc_marker_pos + strlen($marc_marker)) : '';

        // Initialize item holdings the ones received in MARC holding
        // records
        $items = $this->getMarcHoldings($marc);

        // Then add the ones from bibliographic records
        $lines = explode("\n", rtrim($lines));
        foreach ($lines as $line) {
            $item = $this->parseStatusLine($line);
            $items[] = $item;
        }

        // sort the items by shelving key in descending order, then ascending by
        // copy number
        $cmp = function ($a, $b) {
            if ($a['shelving_key'] == $b['shelving_key']) {
                return $a['number'] - $b['number'];
            }
            return $a['shelving_key'] < $b['shelving_key'] ? 1 : -1;
        };
        usort($items, $cmp);

        return $items;
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
        $statuses = [];
        $params = [
            'query' => 'multiple', 'ids' => implode('|', array_unique($idList)),
        ];
        $response = $this->querySirsi($params);
        if (empty($response)) {
            return [];
        }
        $lines = explode("\n", $response);

        $currentId = null;
        $group = -1;
        foreach ($lines as $line) {
            $item = $this->parseStatusLine($line);
            if ($item['id'] != $currentId) {
                $currentId = $item['id'];
                $statuses[] = [];
                $group++;
            }
            $statuses[$group][] = $item;
        }
        return $statuses;
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
        return $this->getStatus($id);
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
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];

        // convert expire date from display format
        // to the format Symphony/Unicorn expects
        $expire = $holdDetails['requiredBy'];
        $expire = $this->dateConverter->convertFromDisplayDate(
            $this->config['Catalog']['server_date_format'],
            $expire
        );

        // query sirsi
        $params = [
            'query' => 'hold',
            'itemId' => $holdDetails['item_id'],
            'patronId' => $patron['cat_username'],
            'pin' => $patron['cat_password'],
            'pickup' => $holdDetails['pickUpLocation'],
            'expire' => $expire,
            'comments' => $holdDetails['comment'],
            'holdType' => $holdDetails['level'],
            'callnumber' => $holdDetails['callnumber'],
            'override' => $holdDetails['override'],
        ];
        $response = $this->querySirsi($params);

        // process the API response
        if ($response == 'invalid_login') {
            return [
              'success' => false,
              'sysMessage' => 'authentication_error_admin'];
        }

        $matches = [];
        preg_match('/\^MN([0-9][0-9][0-9])/', $response, $matches);
        if (isset($matches[1])) {
            $status = $matches[1];
            if ($status == '209') {
                return ['success' => true];
            } else {
                return [
                  'success' => false,
                  'sysMessage' => $this->config['ApiMessages'][$status]];
            }
        }

        return ['success' => false];
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
        //query sirsi
        $params = [
            'query' => 'login', 'patronId' => $username, 'pin' => $password,
        ];
        $response = $this->querySirsi($params);

        if (empty($response)) {
            return null;
        }

        [$user_key, $alt_id, $barcode, $name, $library, $profile, $cat1, $cat2,
            $cat3, $cat4, $cat5, $expiry, $holds, $status] = explode('|', $response);

        [$last, $first] = explode(',', $name);
        $first = rtrim($first, ' ');

        if ($expiry != '0') {
            $expiry = $this->parseDateTime(trim($expiry));
        }
        $expired = ($expiry == '0') ? false : $expiry < time();
        return [
            'id' => $username,
            'firstname' => $first,
            'lastname' =>  $last,
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => null,
            'major' => null,
            'college' => null,
            'library' => $library,
            'barcode' => $barcode,
            'alt_id' => $alt_id,
            'cat1' => $cat1,
            'cat2' => $cat2,
            'cat3' => $cat3,
            'cat4' => $cat4,
            'cat5' => $cat5,
            'profile' => $profile,
            'expiry_date' => $this->formatDateTime($expiry),
            'expired' => $expired,
            'number_of_holds' => $holds,
            'status' => $status,
            'user_key' => $user_key,
        ];
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        //query sirsi
        $params = [
            'query' => 'profile', 'patronId' => $username, 'pin' => $password,
        ];
        $response = $this->querySirsi($params);

        [, , , , $library, $profile, , , , , , , , $email, $address1, $zip, $phone,
            $address2] = explode('|', $response);

        return [
            'firstname' => $patron['firstname'],
            'lastname' => $patron['lastname'],
            'address1' => $address1,
            'address2' => $address2,
            'zip' => $zip,
            'phone' => $phone,
            'email' => $email,
            'group' => $profile,
            'library' => $library,
        ];
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $params = [
            'query' => 'fines', 'patronId' => $username, 'pin' => $password,
        ];
        $response = $this->querySirsi($params);
        if (empty($response)) {
            return [];
        }
        $lines = explode("\n", $response);
        $items = [];
        foreach ($lines as $item) {
            [$catkey, $amount, $balance, $date_billed, $number_of_payments,
                $with_items, $reason, $date_charged, $duedate, $date_recalled]
                    = explode('|', $item);

            // the amount and balance are in cents, so we need to turn them into
            // dollars if configured
            if (!$this->config['Catalog']['leaveFinesAmountsInCents']) {
                $amount = (floatval($amount) / 100.00);
                $balance = (floatval($balance) / 100.00);
            }

            $date_billed = $this->parseDateTime($date_billed);
            $date_charged = $this->parseDateTime($date_charged);
            $duedate = $this->parseDateTime($duedate);
            $date_recalled = $this->parseDateTime($date_recalled);
            $items[] = [
                'id' => $catkey,
                'amount' => $amount,
                'balance' => $balance,
                'date_billed' => $this->formatDateTime($date_billed),
                'number_of_payments' => $number_of_payments,
                'with_items' => $with_items,
                'fine' => $reason,
                'checkout' => $this->formatDateTime($date_charged),
                'duedate' => $this->formatDateTime($duedate),
                'date_recalled' => $this->formatDateTime($date_recalled),
            ];
        }

        return $items;
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $params = [
            'query' => 'getholds', 'patronId' => $username, 'pin' => $password,
        ];
        $response = $this->querySirsi($params);
        if (empty($response)) {
            return [];
        }
        $lines = explode("\n", $response);
        $items = [];
        foreach ($lines as $item) {
            [$catkey, $holdkey, $available, , $date_expires, , $date_created, ,
                $type, $pickup_library, , , , , , , $barcode] = explode('|', $item);

            $date_created = $this->parseDateTime($date_created);
            $date_expires = $this->parseDateTime($date_expires);
            $items[] = [
                'id' => $catkey,
                'reqnum' => $holdkey,
                'available' => ($available == 'Y') ? true : false,
                'expire' => $this->formatDateTime($date_expires),
                'create' => $this->formatDateTime($date_created),
                'type' => $type,
                'location' => $pickup_library,
                'item_id' => $holdkey,
                'barcode' => trim($barcode),
            ];
        }

        return $items;
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
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
        return $holdDetails['item_id'];
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
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $patron = $cancelDetails['patron'];
        $details = $cancelDetails['details'];
        $params = [
            'query' => 'cancelHolds',
            'patronId' => $patron['cat_username'], 'pin' => $patron['cat_password'],
            'holdId' => implode('|', $details),
        ];
        $response = $this->querySirsi($params);

        // process response
        if (empty($response) || $response == 'invalid_login') {
            return false;
        }

        // break the response into separate lines
        $lines = explode("\n", $response);

        // if there are more than 1 lines, then there is at least 1 failure
        $failures = [];
        if (count($lines) > 1) {
            // extract the failed IDs.
            foreach ($lines as $line) {
                // error lines start with '**'
                if (str_starts_with(trim($line), '**')) {
                    [, $holdKey] = explode(':', $line);
                    $failures[] = trim($holdKey, '()');
                }
            }
        }

        $count = 0;
        $items = [];
        foreach ($details as $holdKey) {
            if (in_array($holdKey, $failures)) {
                $items[$holdKey] = [
                    'success' => false, 'status' => 'hold_cancel_fail',
                ];
            } else {
                $count++;
                $items[$holdKey] = [
                  'success' => true, 'status' => 'hold_cancel_success',
                ];
            }
        }
        $result = ['count' => $count, 'items' => $items];
        return $result;
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $params = [
            'query' => 'transactions', 'patronId' => $username, 'pin' => $password,
        ];
        $response = $this->querySirsi($params);
        if (empty($response)) {
            return [];
        }
        $item_lines = explode("\n", $response);
        $items = [];
        foreach ($item_lines as $item) {
            [$catkey, $date_charged, $duedate, $date_renewed, $accrued_fine,
                $overdue, $number_of_renewals, $date_recalled, $charge_key1,
                $charge_key2, $charge_key3, $charge_key4, $recall_period, $callnum]
                    = explode('|', $item);

            $duedate = $original_duedate = $this->parseDateTime($duedate);
            $recall_duedate = false;
            $date_recalled = $this->parseDateTime($date_recalled);
            if ($date_recalled) {
                $duedate = $recall_duedate = $this->calculateRecallDueDate(
                    $date_recalled,
                    $recall_period,
                    $original_duedate
                );
            }
            $charge_key = "$charge_key1|$charge_key2|$charge_key3|$charge_key4";
            $items[] = [
                'id' => $catkey,
                'date_charged' =>
                    $this->formatDateTime($this->parseDateTime($date_charged)),
                'duedate' => $this->formatDateTime($duedate),
                'duedate_raw' => $duedate, // unformatted duedate used for sorting
                'date_renewed' =>
                    $this->formatDateTime($this->parseDateTime($date_renewed)),
                'accrued_fine' => $accrued_fine,
                'overdue' => $overdue,
                'number_of_renewals' => $number_of_renewals,
                'date_recalled' => $this->formatDateTime($date_recalled),
                'recall_duedate' => $this->formatDateTime($recall_duedate),
                'original_duedate' => $this->formatDateTime($original_duedate),
                'renewable' => true,
                'charge_key' => $charge_key,
                'item_id' => $charge_key,
                'callnum' => $callnum,
                'dueStatus' => $overdue == 'Y' ? 'overdue' : '',
            ];
        }

        // sort the items by due date
        $cmp = function ($a, $b) {
            if ($a['duedate_raw'] == $b['duedate_raw']) {
                return $a['id'] < $b['id'] ? -1 : 1;
            }
            return $a['duedate_raw'] < $b['duedate_raw'] ? -1 : 1;
        };
        usort($items, $cmp);

        return $items;
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
        //query sirsi
        $params = ['query' => 'courses'];
        $response = $this->querySirsi($params);

        $response = rtrim($response);
        $course_lines = explode("\n", $response);
        $courses = [];

        foreach ($course_lines as $course) {
            [$id, $code, $name] = explode('|', $course);
            $name = ($code == $name) ? $name : $code . ' - ' . $name;
            $courses[$id] = $name;
        }
        $this->getSorter()->asort($courses);
        return $courses;
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
        //query sirsi
        $params = ['query' => 'instructors'];
        $response = $this->querySirsi($params);

        $response = rtrim($response);
        $user_lines = explode("\n", $response);
        $users = [];

        foreach ($user_lines as $user) {
            [$id, $name] = explode('|', $user);
            $users[$id] = $name;
        }
        $this->getSorter()->asort($users);
        return $users;
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
        //query sirsi
        $params = ['query' => 'desks'];
        $response = $this->querySirsi($params);

        $response = rtrim($response);
        $dept_lines = explode("\n", $response);
        $depts = [];

        foreach ($dept_lines as $dept) {
            [$id, $name] = explode('|', $dept);
            $depts[$id] = $name;
        }
        $this->getSorter()->asort($depts);
        return $depts;
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $courseId     ID from getCourses (empty string to match all)
     * @param string $instructorId ID from getInstructors (empty string to match all)
     * @param string $departmentId ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array               An array of associative arrays representing
     * reserve items.
     */
    public function findReserves($courseId, $instructorId, $departmentId)
    {
        //query sirsi
        if ($courseId) {
            $params = [
                'query' => 'reserves', 'course' => $courseId, 'instructor' => '',
                'desk' => '',
            ];
        } elseif ($instructorId) {
            $params = [
                'query' => 'reserves', 'course' => '', 'instructor' => $instructorId,
                'desk' => '',
            ];
        } elseif ($departmentId) {
            $params = [
                'query' => 'reserves', 'course' => '', 'instructor' => '',
                'desk' => $departmentId,
            ];
        } else {
            $params = [
                'query' => 'reserves', 'course' => '', 'instructor' => '',
                'desk' => '',
            ];
        }

        $response = $this->querySirsi($params);

        $item_lines = explode("\n", $response);
        $items = [];
        foreach ($item_lines as $item) {
            [$instructor_id, $course_id, $dept_id, $bib_id]
                = explode('|', $item);
            if (
                $bib_id && (empty($instructorId) || $instructorId == $instructor_id)
                && (empty($courseId) || $courseId == $course_id)
                && (empty($departmentId) || $departmentId == $dept_id)
            ) {
                $items[] = [
                    'BIB_ID' => $bib_id,
                    'INSTRUCTOR_ID' => $instructor_id,
                    'COURSE_ID' => $course_id,
                    'DEPARTMENT_ID' => $dept_id,
                ];
            }
        }
        return $items;
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
        //query sirsi
        //  isset($lib)
        // ? $params = array('query' => 'newItems',
        // 'lib' => array_search($lib, $config['Libraries']))
        // : $params = array('query' => 'newItems');
        $params = ['query' => 'newitems', 'lib' => 'PPL'];
        $response = $this->querySirsi($params);

        $item_lines = explode("\n", rtrim($response));

        $rescount = 0;
        foreach ($item_lines as $item) {
            $item = rtrim($item, '|');
            $items[$item] = [
                'id' => $item,
            ];
            $rescount++;
        }

        $results = array_slice($items, ($page - 1) * $limit, ($page * $limit) - 1);
        return ['count' => $rescount, 'results' => $results];
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $params = ['query' => 'shadowed'];
        $response = $this->querySirsi($params);

        $record_lines = explode("\n", rtrim($response));
        $records = [];
        foreach ($record_lines as $record) {
            $record = rtrim($record, '|');
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Parse a pipe-delimited status line received from the script on the
     * Unicorn/Symphony server.
     *
     * @param string $line The pipe-delimited status line to parse.
     *
     * @return array       Associative array of holding information
     */
    protected function parseStatusLine($line)
    {
        [$catkey, $shelving_key, $callnum, $itemkey1, $itemkey2, $itemkey3,
            $barcode, $reserve, $number_of_charges, $item_type, $recirculate_flag,
            $holdcount, $library_code, $library, $location_code, $location,
            $currLocCode, $current_location, $holdable, $circulation_rule, $duedate,
            $date_recalled, $recall_period, $format, $title_holds]
                = explode('|', $line);

        // availability
        $availability = ($number_of_charges == 0) ? 1 : 0;

        // due date (if checked out)
        $duedate = $this->parseDateTime(trim($duedate));

        // date recalled
        $date_recalled = $this->parseDateTime(trim($date_recalled));

        // a recalled item has a new due date, we have to calculate that new due date
        if ($date_recalled !== false) {
            $duedate = $this->calculateRecallDueDate(
                $date_recalled,
                $recall_period,
                $duedate
            );
        }

        // item status
        $status = ($availability) ? 'Available' : 'Checked Out';

        // even though item is NOT checked out, it still may not be "Available"
        // the following are the special cases
        if (
            isset($this->config['UnavailableItemTypes'])
            && isset($this->config['UnavailableItemTypes'][$item_type])
        ) {
            $availability = 0;
            $status = $this->config['UnavailableItemTypes'][$item_type];
        } elseif (
            isset($this->config['UnavailableLocations'])
            && isset($this->config['UnavailableLocations'][$currLocCode])
        ) {
            $availability = 0;
            $status = $this->config['UnavailableLocations'][$currLocCode];
        }

        $item = [
            'status' => $status,
            'availability' => $availability,
            'id' => $catkey,
            'number' => $itemkey3, // copy number
            'duedate' => $this->formatDateTime($duedate),
            'callnumber' => $callnum,
            'reserve' => ($reserve == '0') ? 'N' : 'Y',
            'location_code' => $location_code,
            'location' => $location,
            'home_location_code' => $location_code,
            'home_location' => $location,
            'library_code' => $library_code,
            'library' => ($library) ? $library : $library_code,
            'barcode' => trim($barcode),
            'item_id' => trim($barcode),
            'is_holdable' => $holdable,
            'requests_placed' => $holdcount + $title_holds,
            'current_location_code' => $currLocCode,
            'current_location' => $current_location,
            'item_type' => $item_type,
            'recirculate_flag' => $recirculate_flag,
            'shelving_key' => $shelving_key,
            'circulation_rule' => $circulation_rule,
            'date_recalled' => $this->formatDateTime($date_recalled),
            'item_key' => $itemkey1 . '|' . $itemkey2 . '|' . $itemkey3 . '|',
            'format' => $format,
            ];

        return $item;
    }

    /**
     * Map the location code to friendly name.
     *
     * @param string $code The location code from Unicorn/Symphony
     *
     * @return string      The friendly name if defined, otherwise the code is
     * returned.
     */
    protected function mapLocation($code)
    {
        if (
            isset($this->config['Locations'])
            && isset($this->config['Locations'][$code])
        ) {
            return $this->config['Locations'][$code];
        }
        return $code;
    }

    /**
     * Maps the library code to friendly library name.
     *
     * @param string $code The library code from Unicorn/Symphony
     *
     * @return string      The library friendly name if defined, otherwise the code
     * is returned.
     */
    protected function mapLibrary($code)
    {
        if (
            isset($this->config['Libraries'])
            && isset($this->config['Libraries'][$code])
        ) {
            return $this->config['Libraries'][$code];
        }
        return $code;
    }

    /**
     * Send a request to the SIRSI side API script and returns the response.
     *
     * @param array $params Associative array of query parameters to send.
     *
     * @return string
     */
    protected function querySirsi($params)
    {
        // make sure null parameters are sent as empty strings instead or else the
        // driver.pl may choke on null parameter values
        foreach ($params as $key => $value) {
            if ($value == null) {
                $params[$key] = '';
            }
        }

        $url = $this->url;
        if (empty($url)) {
            $url = $this->host;
            if ($this->port) {
                $url = 'http://' . $url . ':' . $this->port . '/' .
                    $this->search_prog;
            } else {
                $url = 'http://' . $url . '/' . $this->search_prog;
            }
        }

        $httpClient = $this->httpService->createClient($url, 'POST');
        $httpClient->setRawBody(http_build_query($params));
        $httpClient->setEncType('application/x-www-form-urlencoded');
        // use HTTP POST so parameters like user id and PIN are NOT logged by web
        // servers
        $result = $httpClient->send();

        // Even if we get a response, make sure it's a 'good' one.
        if (!$result->isSuccess()) {
            throw new ILSException("Error response code received from $url");
        }

        // get the response data
        $response = $result->getBody();

        return rtrim($response);
    }

    /**
     * Given the date recalled, calculate the new due date based on circulation
     * policy.
     *
     * @param int $dateRecalled Unix time stamp of when the recall was issued.
     * @param int $recallPeriod Number of days to due date (from date recalled).
     * @param int $duedate      Original duedate.
     *
     * @return int              New due date as unix time stamp.
     */
    protected function calculateRecallDueDate($dateRecalled, $recallPeriod, $duedate)
    {
        // FIXME: There must be a better way of getting recall due date
        if ($dateRecalled) {
            $recallDue = $dateRecalled
                + (($recallPeriod + 1) * 24 * 60 * 60) - 60;
            return ($recallDue < $duedate) ? $recallDue : $duedate;
        }
        return false;
    }

    /**
     * Take a date/time string from SIRSI seltool and convert it into unix time
     * stamp.
     *
     * @param string $date The input date string. Expected format YYYYMMDDHHMM.
     *
     * @return int         Unix time stamp if successful, false otherwise.
     */
    protected function parseDateTime($date)
    {
        if (strlen($date) >= 8) {
            // format is MM/DD/YYYY HH:MI so it can be passed to strtotime
            $formatted_date = substr($date, 4, 2) . '/' . substr($date, 6, 2) .
                    '/' . substr($date, 0, 4);
            if (strlen($date) > 8) {
                $formatted_date .= ' ' . substr($date, 8, 2) . ':' .
                substr($date, 10);
            }
            return strtotime($formatted_date);
        }
        return false;
    }

    /**
     * Format the given unix time stamp to a human readable format. The format is
     * configurable in Unicorn.ini
     *
     * @param int $time Unix time stamp.
     *
     * @return string Formatted date/time.
     */
    protected function formatDateTime($time)
    {
        $dateTimeString = '';
        if ($time) {
            $dateTimeString = $this->dateConverter->convertToDisplayDate('U', $time);
        }
        return $dateTimeString;
    }

    /**
     * Given a location field, return the values relevant to VuFind.
     *
     * This method is meant to be overridden in inheriting classes to
     * reflect local policies regarding interpretation of the a, b and
     * c subfields of  852.
     *
     * @param MarcReader $record MARC record.
     * @param array      $field  Location field to be processed.
     *
     * @return array Location information.
     */
    protected function processMarcHoldingLocation(MarcReader $record, $field)
    {
        $library_code  = $record->getSubfield($field, 'b');
        $location_code = $record->getSubfield($field, 'c');
        $location = [
            'library_code'  => $library_code,
            'library'       => $this->mapLibrary($library_code),
            'location_code' => $location_code,
            'location'      => $this->mapLocation($location_code),
            'notes'         => $record->getSubfields($field, 'z'),
            'marc852'       => $field,
        ];
        return $location;
    }

    /**
     * Decode a MARC holding record.
     *
     * @param MarcReader $record Holding record to decode..
     *
     * @return array Has two elements: the first is the list of
     *               locations found in the record, the second are the
     *               decoded holdings per se.
     *
     * @todo Check if is OK to print multiple times textual holdings
     *       that had more than one $8.
     */
    protected function decodeMarcHoldingRecord(MarcReader $record)
    {
        $locations = [];
        $holdings = [];
        // First pass:
        //  - process locations
        //
        //  - collect textual holdings indexed by linking number to be
        //    able to easily check later what fields from enumeration
        //    and chronology they override.
        $textuals = [];
        $fields = array_merge($record->getFields('852'), $record->getFields('866'));
        foreach ($fields as $field) {
            switch ($field['tag']) {
                case '852':
                    $locations[]
                        = $this->processMarcHoldingLocation($record, $field);
                    break;
                case '866':
                    $linking_fields = $record->getSubfields($field, '8');
                    if ($linking_fields === false) {
                        // Skip textual holdings fields with no linking
                        continue 2;
                    }
                    foreach ($linking_fields as $linking_field) {
                        $linking = explode('.', $linking_field);
                        // Only the linking part is used in textual
                        // holdings...
                        $linking = $linking[0];
                        // and it should be an int.
                        $textuals[(int)($linking)] = &$field;
                    }
                    break;
            }
        }

        // Second pass: enumeration and chronology, biblio

        // Digits to use to build a combined index with linking number
        // and sequence number.
        // PS: Does this make this implementation year-3K safe?
        $link_digits = floor(strlen((string)PHP_INT_MAX) / 2);

        $data863 = array_key_exists(0, $textuals) ? [] : $record->getFields('863');
        foreach ($data863 as $field) {
            $linking_field = $record->getSubfield($field, '8');

            if ($linking_field === false) {
                // Skip record if there is no linking number
                continue;
            }

            $linking = explode('.', $linking_field);
            if (1 < count($linking)) {
                $sequence = explode('\\', $linking[1]);
                // Lets ignore the link type, as we only care for \x
                $sequence = $sequence[0];
            } else {
                $sequence = 0;
            }
            $linking = $linking[0];

            if (array_key_exists((int)$linking, $textuals)) {
                // Skip coded holdings overridden by textual
                // holdings
                continue;
            }

            $decoded_holding = '';
            foreach ($field['subfields'] as $subfield) {
                if (str_contains('68x', $subfield['code'])) {
                    continue;
                }
                $decoded_holding .= ' ' . $subfield['data'];
            }

            $ndx = (int)($linking
                          . sprintf("%0{$link_digits}u", $sequence));
            $holdings[$ndx] = trim($decoded_holding);
        }

        foreach ($textuals as $linking => $field) {
            $textual_holding = $record->getSubfield($field, 'a');
            foreach ($record->getSubfields($field, 'z') as $note) {
                $textual_holding .= ' ' . $note;
            }

            $ndx = (int)($linking . sprintf("%0{$link_digits}u", 0));
            $holdings[$ndx] = trim($textual_holding);
        }

        return [$locations, $holdings];
    }

    /**
     * Get textual holdings summary.
     *
     * @param string $marc Raw marc holdings records.
     *
     * @return array   Array of holdings data similar to the one returned by
     *                 getHolding.
     */
    protected function getMarcHoldings($marc)
    {
        $holdings = [];
        $collection = new MarcCollection($marc);
        foreach ($collection as $record) {
            [$locations, $record_holdings]
                = $this->decodeMarcHoldingRecord($record);
            // Flatten locations with corresponding holdings as VuFind
            // expects it.
            foreach ($locations as $location) {
                $holdings[] = array_merge_recursive(
                    $location,
                    ['summary' => $record_holdings]
                );
            }
        }
        return $holdings;
    }
}
