<?php
/**
 * ILS Driver for VuFind to get information from PICA
 *
 * Authentication in this driver is handled via LDAP, not via normal PICA!
 * First check local vufind database, and if no user is found, check LDAP.
 * LDAP configuration settings are taken from vufinds config.ini
 *
 * PHP version 5
 *
 * Copyright (C) Oliver Goldschmidt 2010.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use DOMDocument, VuFind\Config\Reader as ConfigReader,
    VuFind\Exception\ILS as ILSException;

/**
 * ILS Driver for VuFind to get information from PICA
 *
 * Holding information is obtained by DAIA, so it's not necessary to implement those
 * functions here; we just need to extend the DAIA driver.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class PICA extends DAIA
{
    protected $username;
    protected $password;
    protected $ldapConfigurationParameter;

    protected $catalogHost;
    protected $renewalsScript;
    protected $dbsid;

    /**
     * Constructor
     *
     * @param string $configFile The location of an alternative config file
     */
    public function __construct($configFile = false)
    {
        parent::__construct();  // do not pass $configFile to parent

        // Load configuration file:
        if (!$configFile) {
            $configFile = 'PICA.ini';
        }
        $configFilePath = ConfigReader::getConfigPath($configFile);
        if (!file_exists($configFilePath)) {
            throw new ILSException(
                'Cannot access config file - ' . $configFilePath
            );
        }
        $configArray = parse_ini_file($configFilePath, true);

        $this->catalogHost = $configArray['Catalog']['Host'];
        $this->renewalsScript = $configArray['Catalog']['renewalsScript'];
        $this->dbsid = isset($configArray['Catalog']['DB'])
            ? $configArray['Catalog']['DB'] : 1;
    }

    // public functions implemented to satisfy Driver Interface

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode  The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $password)
    {
        // Build request:
        $request = new \Zend\Http\Request();
        $request->getPost()
            ->set('username', $barcode)
            ->set('password', $password);

        // First try local database:
        $db = new \VuFind\Auth\Database();
        try {
            $user = $db->authenticate($request);
        } catch (\VuFind\Exception\Auth $e) {
            // Next try LDAP:
            $ldap = new \VuFind\Auth\LDAP();
            $user = $ldap->authenticate($request);
        }

        $_SESSION['picauser'] = $user;
        return array(
            'id' => $user->id,
            'firstname' =>  $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'username' => $barcode,
            'password' => $password,
            'cat_username' => $barcode,
            'cat_password' => $password
        );
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfile($user)
    {
        // TODO: this object probably doesn't have enough fields; it may be necessary
        // to subclass VuFind\Auth\LDAP with a different processLDAPUser() method for
        // loading the additional required properties.
        $userinfo = & $_SESSION['picauser'];
        // firstname
        $recordList['firstname'] = $userinfo->firstname;
        // lastname
        $recordList['lastname'] = $userinfo->lastname;
        // email
        $recordList['email'] = $userinfo->email;
        //Street and Number $ City $ Zip
        if ($userinfo->address) {
            $address = explode("\$", $userinfo->address);
            // address1
            $recordList['address1'] = $address[1];
            // address2
            $recordList['address2'] = $address[2];
            // zip (Post Code)
            $recordList['zip'] = $address[3];
        } else if ($userinfo->homeaddress) {
            $address = explode("\$", $userinfo->homeaddress);
            $recordList['address2'] = $address[0];
            $recordList['zip'] = $address[1];
        }
        // phone
        $recordList['phone'] = $userinfo->phone;
        // group
        $recordList['group'] = $userinfo->group;
        if ($recordList['firstname'] === null) {
            $recordList = $user;
            // add a group
            $recordList['group'] = 'No library account';
        }
        $recordList['expiration'] = $userinfo->libExpire;
        $recordList['status'] = $userinfo->borrowerStatus;
        // Get the LOANS-Page to extract a message for the user
        $URL = "/loan/DB={$this->dbsid}/USERINFO";
        $POST = array(
            "ACT" => "UI_DATA",
            "LNG" => "DU",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit = $this->postit($URL, $POST);
        // How many messages are there?
        $messages = substr_count($postit, '<strong class="alert">');
        $position = 0;
        if ($messages === 2) {
            // ignore the first message (its only the message to close the window
            // after finishing)
            for ($n = 0; $n<2; $n++) {
                $pos = strpos($postit, '<strong class="alert">', $position);
                $pos_close = strpos($postit, '</strong>', $pos);
                $value = substr($postit, $pos+22, ($pos_close-$pos-22));
                $position = $pos + 1;
            }
            $recordList['message'] = $value;
        }
        return $recordList;
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
        $URL = "/loan/DB={$this->dbsid}/USERINFO";
        $POST = array(
            "ACT" => "UI_LOL",
            "LNG" => "DU",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit = $this->postit($URL, $POST);
        // How many items are there?
        $holds = substr_count($postit, 'input type="checkbox" name="VB"');
        $iframes = $holdsByIframe = substr_count($postit, '<iframe');
        $ppns = array();
        $expiration = array();
        $transList = array();
        $barcode = array();
        $reservations = array();
        $titles = array();
        if ($holdsByIframe >= $holds) {
            $position = strpos($postit, '<iframe');
            for ($i = 0; $i < $iframes; $i++) {
                $pos = strpos($postit, 'VBAR=', $position);
                $value = substr($postit, $pos+9, 8);
                $completeValue = substr($postit, $pos+5, 12);
                $barcode[] = $completeValue;
                $bc = $this->getPpnByBarcode($value);
                $ppns[] = $bc;
                $position = $pos + 1;
                $current_position = $position;
                $position_state = null;
                for ($n = 0; $n<6; $n++) {
                    $current_position = $this->strposBackwards(
                        $postit, '<td class="value-small">', $current_position-1
                    );
                    if ($n === 1) {
                        $position_reservations = $current_position;
                    }
                    if ($n === 2) {
                        $position_expire = $current_position;
                    }
                    if ($n === 4) {
                        $position_state = $current_position;
                    }
                    if ($n === 5) {
                        $position_title = $current_position;
                    }
                }
                if ($position_state !== null
                    && substr($postit, $position_state+24, 8) !== 'bestellt'
                ) {
                    $reservations[] = substr($postit, $position_reservations+24, 1);
                    $expiration[] = substr($postit, $position_expire+24, 10);
                    $renewals[] = $this->getRenewals($completeValue);
                    $closing_title = strpos($postit, '</td>', $position_title);
                    $titles[] = $completeValue." ".substr(
                        $postit, $position_title+24,
                        ($closing_title-$position_title-24)
                    );
                } else {
                    $holdsByIframe--;
                    array_pop($ppns);
                    array_pop($barcode);
                }
            }
            $holds = $holdsByIframe;
        } else {
            // no iframes in PICA catalog, use checkboxes instead
            // Warning: reserved items have no checkbox in OPC! They wont appear
            // in this list
            $position = strpos($postit, 'input type="checkbox" name="VB"');
            for ($i = 0; $i < $holds; $i++) {
                $pos = strpos($postit, 'value=', $position);
                $value = substr($postit, $pos+11, 8);
                $completeValue = substr($postit, $pos+7, 12);
                $barcode[] = $completeValue;
                $ppns[] = $this->getPpnByBarcode($value);
                $position = $pos + 1;
                $position_expire = $position;
                for ($n = 0; $n<4; $n++) {
                    $position_expire = strpos(
                        $postit, '<td class="value-small">', $position_expire+1
                    );
                }
                $expiration[] = substr($postit, $position_expire+24, 10);
                $renewals[] = $this->getRenewals($completeValue);
            }
        }
        for ($i = 0; $i < $holds; $i++) {
            if ($ppns[$i] !== false) {
                $transList[] = array(
                    'id'      => $ppns[$i],
                    'duedate' => $expiration[$i],
                    'renewals' => $renewals[$i],
                    'reservations' => $reservations[$i],
                    'vb'      => $barcode[$i],
                    'title'   => $titles[$i]
                );
            } else {
                // There is a problem: no PPN found for this item... lets take id 0
                // to avoid serious error (that will just return an empty title)
                $transList[] = array(
                    'id'      => 0,
                    'duedate' => $expiration[$i],
                    'renewals' => $renewals[$i],
                    'reservations' => $reservations[$i],
                    'vb'      => $barcode[$i],
                    'title'   => $titles[$i]
                );
            }
        }
        return $transList;
    }

    /**
     * Support method - reverse strpos.
     *
     * @param string $haystack String to search within
     * @param string $needle   String to search for
     * @param int    $offset   Search offset
     *
     * @return int             Offset of $needle in $haystack
     */
    protected function strposBackwards($haystack, $needle, $offset = 0)
    {
        if ($offset === 0) {
            $haystack_reverse = strrev($haystack);
        } else {
            $haystack_reverse = strrev(substr($haystack, 0, $offset));
        }
        $needle_reverse = strrev($needle);
        $position_brutto = strpos($haystack_reverse, $needle_reverse);
        if ($offset === 0) {
            $position_netto = strlen($haystack)-$position_brutto-strlen($needle);
        } else {
            $position_netto = $offset-$position_brutto-strlen($needle);
        }
        return $position_netto;
    }

    /**
     * get the number of renewals
     *
     * @param string $barcode Barcode of the medium
     *
     * @return int number of renewals, if renewals script has not been set, return
     * false
     */
    protected function getRenewals($barcode)
    {
        $renewals = false;
        if (isset($this->renewalsScript) === true) {
            $POST = array(
                "DB" => '1',
                "VBAR" => $barcode,
                "U" => $_SESSION['picauser']->username
            );
            $URL = $this->renewalsScript;
            $postit = $this->postit($URL, $POST);

            $renewalsString = $postit;
            $pos = strpos($postit, '<span');
            $renewals = strip_tags(substr($renewalsString, $pos));
        }
        return $renewals;
    }
    /**
     * Renew item(s)
     *
     * @param string $recordId Record identifier
     *
     * @return bool            True on success
     */
    public function renew($recordId)
    {
        // TODO: rewrite this to use VuFind's standard renewMyItems() mechanism.
        $URL = "/loan/DB={$this->dbsid}/LNG=DU/USERINFO";
        $POST = array(
            "ACT" => "UI_RENEWLOAN",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        if (is_array($recordId) === true) {
            foreach ($recordId as $rid) {
                array_push($POST['VB'], $recordId);
            }
        } else {
            $POST['VB'] = $recordId;
        }
        $postit = $this->postit($URL, $POST);

        return true;
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
        // The patron comes as an array...
        $p = $patron[0];
        $URL = "/loan/DB={$this->dbsid}/LNG=DU/USERINFO";
        $POST = array(
            "ACT" => "UI_LOC",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit = $this->postit($URL, $POST);

        // How many items are there?
        $holds = substr_count($postit, '<td class="plain"')/3;
        $ppns = array();
        $fineDate = array();
        $description = array();
        $fine = array();
        $position = strpos($postit, '<td class="infotab2" align="left">Betrag<td>');
        for ($i = 0; $i < $holds; $i++) {
            $pos = strpos($postit, '<td class="plain"', $position);
            // first class=plain => description
            // length = position of next </td> - startposition
            $nextClosingTd = strpos($postit, '</td>', $pos);
            $description[$i] = substr($postit, $pos+18, ($nextClosingTd-$pos-18));
            $position = $pos + 1;
            // next class=plain => date of fee creation
            $pos = strpos($postit, '<td class="plain"', $position);
            $nextClosingTd = strpos($postit, '</td>', $pos);
            $fineDate[$i] = substr($postit, $pos+18, ($nextClosingTd-$pos-18));
            $position = $pos + 1;
            // next class=plain => amount of fee
            $pos = strpos($postit, '<td class="plain"', $position);
            $nextClosingTd = strpos($postit, '</td>', $pos);
            $fineString = substr($postit, $pos+32, ($nextClosingTd-$pos-32));
            $feeString = explode(',', $fineString);
            $feeString[1] = substr($feeString[1], 0, 2);
            $fine[$i] = (double) implode('', $feeString);
            $position = $pos + 1;
        }

        $fineList = array();
        for ($i = 0; $i < $holds; $i++) {
            $fineList[] = array(
                "amount"   => $fine[$i],
                "checkout" => "",
                "fine"     => $fineDate[$i] . ': ' .
                    utf8_encode(html_entity_decode($description[$i])),
                "duedate"  => ""
            );
            // id should be the ppn of the book resulting the fine but there's
            // currently no way to find out the PPN (we have neither barcode nor
            // signature...)
        }
        return $fineList;
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
        $URL = "/loan/DB={$this->dbsid}/LNG=DU/USERINFO";
        $POST = array(
            "ACT" => "UI_LOR",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit = $this->postit($URL, $POST);

        // How many items are there?
        $holds = substr_count($postit, 'input type="checkbox" name="VB"');
        $ppns = array();
        $creation = array();
        $position = strpos($postit, 'input type="checkbox" name="VB"');
        for ($i = 0; $i < $holds; $i++) {
            $pos = strpos($postit, 'value=', $position);
            $value = substr($postit, $pos+11, 8);
            $ppns[] = $this->getPpnByBarcode($value);
            $position = $pos + 1;
            $position_create = $position;
            for ($n = 0; $n<3; $n++) {
                $position_create = strpos(
                    $postit, '<td class="value-small">', $position_create+1
                );
            }
            $creation[]
                = str_replace('-', '.', substr($postit, $position_create+24, 10));
        }
        /* items, which are ordered and have no signature yet, are not included in
         * the for-loop getthem by checkbox PPN
         */
        $moreholds = substr_count($postit, 'input type="checkbox" name="PPN"');
        $position = strpos($postit, 'input type="checkbox" name="PPN"');
        for ($i = 0; $i < $moreholds; $i++) {
            $pos = strpos($postit, 'value=', $position);
            // get the length of PPN
               $x = strpos($postit, '"', $pos+7);
            $value = substr($postit, $pos+7, $x-$pos-7);
            // problem: the value presented here does not contain the checksum!
            // so its not a valid identifier
            // we need to calculate the checksum
            $checksum = 0;
            for ($i=0; $i<strlen($value);$i++) {
                $checksum += $value[$i]*(9-$i);
            }
            if ($checksum%11 === 1) {
                $checksum = 'X';
            } else if ($checksum%11 === 0) {
                $checksum = 0;
            } else {
                $checksum = 11 - $checksum%11;
            }
            $ppns[] = $value.$checksum;
            $position = $pos + 1;
            $position_create = $position;
            for ($n = 0; $n<3; $n++) {
                $position_create = strpos(
                    $postit, '<td class="value-small">', $position_create+1
                );
            }
            $creation[]
                = str_replace('-', '.', substr($postit, $position_create+24, 10));
        }

        /* media ordered from closed stack is not visible on the UI_LOR page
         * requested above... we need to do another request and filter the
         * UI_LOL-page for requests
         */
        $POST_LOL = array(
            "ACT" => "UI_LOL",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit_lol = $this->postit($URL, $POST_LOL);

        $requests = substr_count(
            $postit_lol, '<td class="value-small">bestellt</td>'
        );
        $position = 0;
        for ($i = 0; $i < $requests; $i++) {
            $position = strpos(
                $postit_lol, '<td class="value-small">bestellt</td>', $position+1
            );
            $pos = strpos($postit_lol, '<td class="value-small">', ($position-100));
            $nextClosingTd = strpos($postit_lol, '</td>', $pos);
            $value = substr($postit_lol, $pos+27, ($nextClosingTd-$pos-27));
            $ppns[] = $this->getPpnByBarcode($value);
            $creation[] = date('d.m.Y');
        }

        for ($i = 0; $i < ($holds+$moreholds+$requests); $i++) {
            $holdList[] = array(
                "id"       => $ppns[$i],
                "create"   => $creation[$i]
            );
        }
        return $holdList;
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

    //public function placeHold($holdDetails)
    //{
    //}


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


    // protected functions to connect to PICA

    /**
     * post something to a foreign host
     *
     * @param string $file         POST target URL
     * @param string $data_to_send POST data
     *
     * @return string              POST response
     */
    protected function postit($file, $data_to_send)
    {
        // Parameter verarbeiten
        foreach ($data_to_send as $key => $dat) {
            $data_to_send[$key]
                = "$key=".rawurlencode(utf8_encode(stripslashes($dat)));
        }
        $postData = implode("&", $data_to_send);

        // HTTP-Header vorbereiten
        $out  = "POST $file HTTP/1.1\r\n";
        $out .= "Host: " . $this->catalogHost . "\r\n";
        $out .= "Content-type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-length: ". strlen($postData) ."\r\n";
        $out .= "User-Agent: ".$_SERVER["HTTP_USER_AGENT"]."\r\n";
        $out .= "Connection: Close\r\n";
        $out .= "\r\n";
        $out .= $postData;
        if (!$conex = @fsockopen($this->catalogHost, "80", $errno, $errstr, 10)) {
            return 0;
        }
        fwrite($conex, $out);
        $data = '';
        while (!feof($conex)) {
            $data .= fgets($conex, 512);
        }
        fclose($conex);
        return $data;
    }


    /**
     * gets a PPN by its barcode
     *
     * @param string $barcode Barcode to use for lookup
     *
     * @return string         PPN
     */
    protected function getPpnByBarcode($barcode)
    {
        $searchUrl = "http://" . $this->catalogHost .
            "/DB={$this->dbsid}/XML=1.0/CMD?ACT=SRCHA&IKT=1016&SRT=YOP&TRM=sgn+" .
            $barcode;
        $doc = new DOMDocument();
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
     */
    public function getJournalHoldings($ppn)
    {
        $searchUrl = "http://" . $this->catalogHost .
            "/DB={$this->dbsid}/XML=1.0/SET=1/TTL=1/FAM?PPN=" . $ppn .
            "&SHRTST=10000";
        $doc = new DOMDocument();
        $doc->load($searchUrl);
        $itemlist = $doc->getElementsByTagName('SHORTTITLE');
        $ppn = array();
        for ($n = 0; $itemlist->item($n); $n++) {
            if (count($itemlist->item($n)->attributes) > 0) {
                $ppn[] = $itemlist->item($n)->attributes->getNamedItem('PPN')
                    ->nodeValue;
            }
        }
        return $ppn;
    }
}
