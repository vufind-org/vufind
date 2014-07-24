<?php
/**
 * ILS Driver for VuFind to get information from PAIA
 *
 * Authentication in this driver is handled via LDAP, not via normal PICA!
 * First check local vufind database, and if no user is found, check LDAP.
 * LDAP configuration settings are taken from vufinds config.ini
 *
 * PHP version 5
 *
 * Copyright (C) Oliver Goldschmidt, Magda Roos, Till Kinstler 2013, 2014.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

namespace VZG\ILS\Driver;
use DOMDocument, VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Driver\DAIA as DAIA;

/**
 * ILS Driver for VuFind to get information from PICA
 *
 * Holding information is obtained by DAIA, so it's not necessary to implement those
 * functions here; we just need to extend the DAIA driver.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class PAIA extends DAIA
{
    private $_username;
    private $_password;
    private $_ldapConfigurationParameter;

    protected $baseURL;
    protected $paiaURL;

    /**
     * Constructor
     *
     * @access public
     */
    public function init()
    {
        parent::init();

        if (!isset($this->config['Catalog']['URL']) || !isset($this->config['PAIA']['URL'])) {
            throw new ILSException('Catalog/URL and PAIA/URL configuration needs to be set.');
        }

        $this->catalogURL = $this->config['Catalog']['URL'];

        $this->paiaURL = $this->config['PAIA']['URL'];

    }

    // public functions implemented to satisfy Driver Interface

/*

    cancelHolds
    checkRequestIsValid
    findReserves
    getCancelHoldDetails
    getCancelHoldLink
    getConfig
    getCourses
    getDefaultPickUpLocation
    getDepartments
    getFunds
    getHolding
    getHoldings -- DEPRECATED
    getHoldLink
    getInstructors
    getMyFines
    getMyHolds
    getMyProfile
    getMyTransactions
    getNewItems
    getOfflineMode
    getPickUpLocations
    getPurchaseHistory
    getRenewDetails
    getStatus
    getStatuses
    getSuppressedAuthorityRecords
    getSuppressedRecords
    hasHoldings
    loginIsHidden
    patronLogin
    placeHold
    renewMyItems
    renewMyItemsLink

*/

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode The patron barcode
     * @param string $login   The patron's last name or PIN (depending on config)
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $password)
    {
        if ($barcode == '' || $password == '') {
            return new PEAR_Error('Invalid Login, Please try again.');
        }
        $this->_username = $barcode;
        $this->_password = $password;

        try {
            return $this->_paiaLogin($barcode, $password);
        } catch (ILSException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @return mixed      Array of the patron's profile data on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyProfile($user)
    {
        // we are already having all possible PAIA user data in $user
        $userinfo['firstname'] = $user['firstname'];
        $userinfo['lastname'] = $user['lastname'];
        // fill up all possible return values
        $userinfo['address1'] = null;
        $userinfo['address2'] = null;
        $userinfo['city'] = null;
        $userinfo['country'] = null;
        $userinfo['zip'] = null;
        $userinfo['phone'] = null;
        $userinfo['group'] = null;
        return $userinfo;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyTransactions($patron)
    {
        $loans_response = $this->_getAsArray('/core/'.$patron['cat_username'].'/items');
        $holds = count($loans_response['doc']);
        for ($i = 0; $i < $holds; $i++) {
            if ($loans_response['doc'][$i]['status'] == '3') {
                // TODO: set renewable dynamically (not yet supported by PAIA)
                $renewable = false;
                $renew_details = $loans_response['doc'][$i]['item'];
                if ($loans_response['doc'][$i]['cancancel'] == 1) {
                    $renewable = true;
                    $renew_details = $loans_response['doc'][$i]['item'];
                }
                // get PPN from PICA catalog since it is not part of PAIA
                $ppn = $this->_getPpnByBarcode($loans_response['doc'][$i]['label']);

                $transList[] = array(
                    'duedate'        => $loans_response['doc'][$i]['duedate'],
                    'id'             => $ppn ? $ppn : $loans_response['doc'][$i]['item'],
                    'barcode'        => $loans_response['doc'][$i]['item'],
                    'renew'          => $loans_response['doc'][$i]['renewals'],
                    'renewLimit'     => null,
                    'request'        => $loans_response['doc'][$i]['queue'],
                    'volume'         => null,
                    'publication_year' => null,
                    'renewable'      => $renewable,
                    'message'        => $loans_response['doc'][$i]['label'],             
                    'title'          => $loans_response['doc'][$i]['about'],
                    'item_id'        => $loans_response['doc'][$i]['item'],
                    'institution_name' => null,
                    'callnumber'     => $loans_response['doc'][$i]['label'],
                    'location'       => $loans_response['doc'][$i]['storage'],
                );
            }
        }
        //print_r($transList);
        return $transList;
    }

    /**
     * Renew item(s)
     *
     * @param string $recordId Record identifier
     *
     * @return bool            True on success
     * @access public
     */
    public function renewMyItems($details)
    {
        $it = $details['details'];
        $items = array();
        foreach ($it as $item) {
            $items[] = array('item' => stripslashes($item));
        }
        $patron = $details['patron'];
        $post_data = array("doc" => $items);
        $array_response = $this->_postAsArray('/core/'.$patron['cat_username'].'/renew', $post_data);

        $details = array();

        if (array_key_exists('error', $array_response)) {
            $details[] = array('success' => false, 'sysMessage' => $array_response['error_description']);
        }
        else {
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                if ($element['status'] == '3') {
                    $details[] = array('success' => true, 'new_date' => $element['duedate'], 'new_time' => '23:59:59', 'item_id' => 0, 'sysMessage' => 'Successfully renewed');
                }
                else {
                    $details[] = array('success' => false, 'new_date' => $element['duedate'], 'new_time' => '23:59:59', 'item_id' => 0, 'sysMessage' => 'Request rejected');
                }
            }
        }
        $returnArray = array('blocks' => false, 'details' => $details);

        return $returnArray;
    }

    public function getRenewDetails($checkOutDetails) {
        return($checkOutDetails['renew_details']);
    }

    /**
     * Cancel item(s)
     *
     * @param string $recordId Record identifier
     *
     * @return bool            True on success
     * @access public
     */
    public function cancelHolds($cancelDetails)
    {
        $it = $cancelDetails['details'];
        $items = array();
        foreach ($it as $item) {
            $items[] = array('item' => stripslashes($item));
        }
        $patron = $cancelDetails['patron'];
        $post_data = array("doc" => $items);

        $array_response = $this->_postAsArray('/core/'.$patron['cat_username'].'/cancel', $post_data);
        $details = array();

        if (array_key_exists('error', $array_response)) {
            $details[] = array('success' => false, 'status' => $array_response['error_description'], 'sysMessage' => $array_response['error']);
        }
        else {
            $count = 0;
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                if ($element['error']) {
                    $details[] = array('success' => false, 'status' => $element['error'], 'sysMessage' => 'Cancel request rejected');
                }
                else {
                    $details[] = array('success' => true, 'status' => 'Success', 'sysMessage' => 'Successfully cancelled');
                    $count++;
                }
            }
        }
        $returnArray = array('count' => $count, 'items' => $details);

        return $returnArray;
    }

    public function getCancelHoldDetails($checkOutDetails) {
        return($checkOutDetails['cancel_details']);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyFines($patron)
    {

        $fees_response = $this->_getAsArray('/core/'.$patron['cat_username'].'/fees');

        $fineList = array();
        foreach ($fees_response['fee'] as $fine) {
            $ppn = $this->_getPpnByBarcode(substr($fine['item'], -8));
            $fineList[] = array(
                "id"       => $ppn,
                "amount"   => $fine['amount'],
                "checkout" => "",
                "title"    => $fine['about'],
                "feedate"  => $fine['date'],
                "duedate"  => "",
                "fine"     => $fine['feetype']
            );
            // id should be the ppn of the book resulting the fine but there's
            // currently no way to find out the PPN (we have neither barcode nor
            // signature...)
        }
        $fineList[] = array(
            "balance"  => $fees_response['amount']
        );

        return $fineList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyHolds($patron)
    {
        $loans_response = $this->_getAsArray('/core/'.$patron['cat_username'].'/items');
        $holds = count($loans_response['doc']);
        for ($i = 0; $i < $holds; $i++) {
            // TODO: get date of creation from a reservation
            // this is not yet supported by PAIA
            if ($loans_response['doc'][$i]['status'] == '1' || $loans_response['doc'][$i]['status'] == '2') {
                // get PPN from PICA catalog since it is not part of PAIA
                $ppn = $this->_getPpnByBarcode($loans_response['doc'][$i]['label']);
                $cancel_details = false;
                if ($loans_response['doc'][$i]['cancancel'] == 1) {
                    $cancel_details = $loans_response['doc'][$i]['item'];
                }
          
                $transList[] = array(
                    'type'           => $loans_response['doc'][$i]['status'],
                    'id'             => $ppn ? $ppn : $loans_response['doc'][$i]['item'],
                    'location'       => $loans_response['doc'][$i]['storage'],
                    'reqnum'         => null,
                    'expire'         => $loans_response['doc'][$i]['duedate'],
                    'create'         => $loans_response['doc'][$i]['create'],
                    'position'       => null,
                    'available'      => null,
                    'item_id'        => $loans_response['doc'][$i]['item'],
                    'volume'         => null,
                    'publication_year' => null,
                    'title'          => $loans_response['doc'][$i]['about'],
                    'message'        => $loans_response['doc'][$i]['label'],             
                    'callnumber'     => $loans_response['doc'][$i]['label'],
                    'cancel_details' => $cancel_details,
                );
            }
        }
        //print_r($transList);
        return $transList;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or a PEAR error on failure of support classes
     *
     * Make a request on a specific record
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available) or a
     * PEAR error on failure of support classes
     * @access public
     */
    public function placeHold($holdDetails)
    {
        $item = $holdDetails['item_id'];

        $items = array();
        $items[] = array('item' => stripslashes($item));
        $patron = $holdDetails['patron'];
        $post_data = array("doc" => $items);
        $array_response = $this->_postAsArray('/core/'.$patron['cat_username'].'/request', $post_data);
        $details = array();

        if (array_key_exists('error', $array_response)) {
            $details = array('success' => false, 'sysMessage' => $array_response['error_description']);
        }
        else {
            $elements = $array_response['doc'];
            foreach ($elements as $element) {
                if (array_key_exists('error', $element)) {
                    $details = array('success' => false, 'sysMessage' => $element['error']);
                }
                else {
                    $details = array('success' => true, 'sysMessage' => 'Successfully requested');
                }
            }
        }
        $returnArray = $details;
        return $returnArray;
    }


    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * TODO: implement it for PICA
     *
     * @return array An associative array with key = fund ID, value = fund name.
     * @access public
     */
    public function getFunds()
    {
        return null;
    }


    // private functions to connect to PAIA

    /**
     * post something to a foreign host
     *
     * @param string $file         POST target URL
     * @param string $data_to_send POST data
     *
     * @return string              POST response
     * @access private
     */
    private function _postit($file, $data_to_send, $access_token = null)
    {
        // json-encoding
        $postData = stripslashes(json_encode($data_to_send));

        $http = curl_init();
        curl_setopt($http, CURLOPT_URL, $this->paiaURL . $file);
        curl_setopt($http, CURLOPT_POST, true);
        curl_setopt($http, CURLOPT_POSTFIELDS, $postData);
        if (isset($access_token)) {
            curl_setopt($http, CURLOPT_HTTPHEADER, array('Content-type: application/json; charset=UTF-8', 'Authorization: Bearer ' .$access_token));
        } else {
            curl_setopt($http, CURLOPT_HTTPHEADER, array('Content-type: application/json; charset=UTF-8'));
        }
        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($http);

        curl_close($http);

        return $data;
    }

    private function _getit($file, $access_token)
    {

        $http = curl_init();
        curl_setopt($http, CURLOPT_URL, $this->paiaURL . $file);
        curl_setopt($http, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' .$access_token, 'Content-type: application/json; charset=UTF-8'));
        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($http);
        curl_close($http);
        return $data;
    }

    private function _getAsArray($file) {

        $pure_response = $this->_getit($file, $_SESSION['paiaToken']);
        $json_start = strpos($pure_response, '{');
        $json_response = substr($pure_response, $json_start);
        $loans_response = json_decode($json_response, true);

        // if the login auth token is invalid, renew it (this is possible unless the session is expired)
        if ($loans_response['error'] && $loans_response['code'] == '401') {
            $sessionuser = $_SESSION['picauser'];
            $this->_paiaLogin($sessionuser->username, $sessionuser->cat_password);

            $pure_response = $this->_getit($file, $_SESSION['paiaToken']);
            $json_start = strpos($pure_response, '{');
            $json_response = substr($pure_response, $json_start);
            $loans_response = json_decode($json_response, true);
        }

        return $loans_response;
    }

    private function _postAsArray($file, $data) {
        $pure_response = $this->_postit($file, $data, $_SESSION['paiaToken']);
        $json_start = strpos($pure_response, '{');
        $json_response = substr($pure_response, $json_start);
        $loans_response = json_decode($json_response, true);

        // if the login auth token is invalid, renew it (this is possible unless the session is expired)
        if ($loans_response['error'] && $loans_response['code'] == '401') {
            $sessionuser = $_SESSION['picauser'];
            $this->_paiaLogin($sessionuser->username, $sessionuser->cat_password);

            $pure_response = $this->_postit($file, $data, $_SESSION['paiaToken']);
            $json_start = strpos($pure_response, '{');
            $json_response = substr($pure_response, $json_start);
            $loans_response = json_decode($json_response, true);
        }

        return $loans_response;
    }

    /**
     * gets a PPN by its barcode
     *
     * @param string $barcode Barcode to use for lookup
     *
     * @return string         PPN
     * @access private
     */
    private function _getPpnByBarcode($barcode)
    {
        $barcode = str_replace("/"," ",$barcode);
        $searchUrl = $this->catalogURL .
            "XML=1.0/CMD?ACT=SRCHA&IKT=1016&SRT=YOP&TRM=sgn+$barcode";

        $doc = new DomDocument();
        $doc->load($searchUrl);
        // get Availability information from DAIA
        $itemlist = $doc->getElementsByTagName('SHORTTITLE');
        if (count($itemlist->item(0)->attributes) > 0) {
            $ppn = $itemlist->item(0)->attributes->getNamedItem('PPN')->nodeValue;
        } else {
            return false;
        }
        return $ppn;
    }

    /**
     * gets holdings of magazine and journal exemplars
     *
     * @param string $ppn PPN identifier
     *
     * @return array
     * @access public
     */
    public function getJournalHoldings($ppn)
    {
        $searchUrl = $this->catalogURL .
            "XML=1.0/SET=1/TTL=1/FAM?PPN=" . $ppn . "&SHRTST=100";
        $doc = new DomDocument();
        $doc->load($searchUrl);
        $itemlist = $doc->getElementsByTagName('SHORTTITLE');
        $ppn = array();
        for ($n = 0; $itemlist->item($n); $n++) {
            if (count($itemlist->item($n)->attributes) > 0) {
                $ppn[] = $itemlist->item($n)->attributes->getNamedItem('PPN')->nodeValue;
            }
        }
        return $ppn;
    }

    /**
     * private authentication function
     * use PAIA for authentication
     *
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login, PEAR_Error on error.
     * @access private
     */
    private function _paiaLogin($username, $password)
    {
        $post_data = array("username" => $username, "password" => $password, "grant_type" => "password", "scope" => "read_patron read_fees read_items write_items change_password");
        $login_response = $this->_postit('/auth/login', $post_data);

        $json_start = strpos($login_response, '{');
        $json_response = substr($login_response, $json_start);
        $array_response = json_decode($json_response, true);

        if (array_key_exists('access_token', $array_response)) {
            $_SESSION['paiaToken'] = $array_response['access_token'];
            if (array_key_exists('patron', $array_response)) {
                $user = $this->_getUserDetails($array_response['patron']);
                $user['cat_username'] = $array_response['patron'];
                $user['cat_password'] = $password;
                return $user;
            }
            else {
                 throw new ILSException('Login credentials accepted, but got no patron ID?!?');
            }
        }
        else if (array_key_exists('error', $array_response)) {
            throw new ILSException($array_response['error'].": ".$array_response['error_description']);
        }
        else throw new ILSException('Unknown error! Access denied.');
    }

    /**
     * Support method for _paiaLogin() -- load user details into session and return
     * array of basic user data.
     *
     * @param array $patron                    patron ID
     *
     * @return array
     * @access private
     */
    private function _getUserDetails($patron)
    {
        $pure_response = $this->_getit('/core/' . $patron, $_SESSION['paiaToken']);
        $json_start = strpos($pure_response, '{');
        $json_response = substr($pure_response, $json_start);
        $user_response = json_decode($json_response, true);

        // if the login auth token is invalid, renew it (this is possible unless the session is expired)
        if ($user_response['error'] && $user_response['code'] == '401') {
            $this->_paiaLogin($sessionuser->username, $sessionuser->cat_password);

            $pure_response = $this->_getit('/core/'.$data, $_SESSION['paiaToken']);
            $json_start = strpos($pure_response, '{');
            $json_response = substr($pure_response, $json_start);
            $user_response = json_decode($json_response, true);
        }

        $username = $user_response['name'];
        $nameArr = explode(',', $username);
        $firstname = $nameArr[1];
        $lastname = $nameArr[0];

        $user = array();
        $user['id'] = $patron;
        $user['firstname'] = $firstname;
        $user['lastname'] = $lastname;
        $user['email'] = $user_response['email'];
        $user['major'] = null;
        $user['college'] = null;

        // do not store cat_password into database, but assign it to Session user
        /*
        $sessionuser = new User();
        $sessionuser->username = $this->_username;
        $sessionuser->cat_password = $this->_password;
        */
        return $user;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     * @access public
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function]) ) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }

    /**
     * Public Function which changes the password in the library system
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with patron information.
     * @access public
     */
    public function changePassword($patron, $newpassword)
    {
        $sessionuser = $_SESSION['picauser'];

        $post_data = array("patron"       => $patron['username'],
                           "username"     => $patron['firstname']." ".$patron['lastname'],
                           "old_password" => $sessionuser->cat_password,
                           "new_password" => $newpassword);

        $array_response = $this->_postAsArray('/auth/change', $post_data);

        $details = array();

        if (array_key_exists('error', $array_response)) {
            $details = array('success' => false, 'status' => $array_response['error'], 'sysMessage' => $array_response['error_description']);
        }
        else {
            $element = $array_response['patron'];
            if (array_key_exists('error', $element)) {
                $details = array('success' => false, 'status' => 'Failure changing password', 'sysMessage' => $element['error']);
            }
            else {
                $details = array('success' => true, 'status' => 'Successfully changed');

                // TODO: push password also to LDAP (but make that configurable since this is non-standard)

                // replace password for currently logged in user with the new one
                $sessionuser->password = $newsecret;
                $sessionuser->cat_password = $newsecret;
                $_SESSION['picauser'] = $sessionuser;
            }
        }
        $returnArray = $details;
        return $returnArray;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        // How to get valid PickupLocations for a PICA LBS?
        return array();
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return false;
    }

}
?>
