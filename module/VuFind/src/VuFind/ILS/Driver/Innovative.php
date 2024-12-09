<?php

/**
 * III ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Adam Brin <abrin@brynmawr.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function count;
use function strlen;

/**
 * VuFind Connector for Innovative
 *
 * This class uses screen scraping techniques to gather record holdings written
 * by Adam Bryn of the Tri-College consortium.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Adam Brin <abrin@brynmawr.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Innovative extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

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
     * Make an HTTP request
     *
     * @param string $url URL to request
     *
     * @return string
     */
    protected function sendRequest($url)
    {
        // Make the NCIP request:
        try {
            $result = $this->httpService->get($url);
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        return $result->getBody();
    }

    /**
     * Prepare ID
     *
     * This function returns the correct record id format as defined
     * in the Innovative.ini file.
     *
     * @param string $id ID to format
     *
     * @return string
     */
    protected function prepID($id)
    {
        // Get the ID format from config (default to use_full_id if unset):
        if (
            !isset($this->config['RecordID']['use_full_id'])
            || $this->config['RecordID']['use_full_id']
        ) {
            // Strip ID leading period and trailing check digit.
            $id_ = substr(str_replace('.b', '', $id), 0, -1);
        } else {
            // Return digits only.
            $id_ = substr($id, 1);
        }
        return $id_;
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
        // Strip ID
        $id_ = $this->prepID($id);

        // Load Record Page
        $host = rtrim($this->config['Catalog']['url'], '/');

        // Grab the full item list view
        //$result = $this->sendRequest($host . '/record=b' . $id_);
        $result = $this->sendRequest(
            $host . '/search/.b' . $id_ . '/.b' . $id_ .
            '/1%2C1%2C1%2CB/holdings~' . $id_ . '&FF=&1%2C0%2C'
        );

        // strip out html before the first occurrence of 'bibItems', should be
        // '<table class="bibItems" '
        $r = substr($result, stripos($result, 'bibItems'));
        // strip out the rest of the first table tag.
        $r = substr($r, strpos($r, '>') + 1);
        // strip out the next table closing tag and everything after it.
        $r = substr($r, 0, stripos($r, '</table'));

        // $r should only include the holdings table at this point

        // split up into strings that contain each table row, excluding the
        // beginning tr tag.
        $rows = preg_split('/<tr([^>]*)>/', $r);
        $count = 0;
        $keys = array_pad([], 10, '');

        $loc_col_name      = $this->config['OPAC']['location_column'];
        $call_col_name     = $this->config['OPAC']['call_no_column'];
        $status_col_name   = $this->config['OPAC']['status_column'];
        $reserves_col_name = $this->config['OPAC']['location_column'];
        $reserves_key_name = $this->config['OPAC']['reserves_key_name'];
        $stat_avail        = $this->config['OPAC']['status_avail'];
        $stat_due          = $this->config['OPAC']['status_due'];

        $ret = [];
        foreach ($rows as $row) {
            // Split up the contents of the row based on the th or td tag, excluding
            // the tags themselves.
            $cols = preg_split('/<t(h|d)([^>]*)>/', $row);

            // for each th or td section, do the following.
            for ($i = 0; $i < count($cols); $i++) {
                // replace non blocking space encodings with a space.
                $cols[$i] = str_replace('&nbsp;', ' ', $cols[$i]);
                // remove html comment tags
                $cols[$i] = preg_replace('/<!--([^(-->)]*)-->/', '', $cols[$i]);
                // Remove closing th or td tag, trim whitespace and decode html
                // entities
                $cols[$i] = html_entity_decode(
                    trim(substr($cols[$i], 0, stripos($cols[$i], '</t')))
                );

                // If this is the first row, it is the header row and has the column
                // names
                if ($count == 1) {
                    $keys[$i] = $cols[$i];
                } elseif ($count > 1) { // not the first row, has holding info
                    //look for location column
                    if (stripos($keys[$i], (string)$loc_col_name) > -1) {
                        $ret[$count - 2]['location'] = strip_tags($cols[$i]);
                    }
                    // Does column hold reserves information?
                    if (stripos($keys[$i], (string)$reserves_col_name) > -1) {
                        if (stripos($cols[$i], (string)$reserves_key_name) > -1) {
                            $ret[$count - 2]['reserve'] = 'Y';
                        } else {
                            $ret[$count - 2]['reserve'] = 'N';
                        }
                    }
                    // Does column hold call numbers?
                    if (stripos($keys[$i], (string)$call_col_name) > -1) {
                        $ret[$count - 2]['callnumber'] = strip_tags($cols[$i]);
                    }
                    // Look for status information.
                    if (stripos($keys[$i], (string)$status_col_name) > -1) {
                        if (stripos($cols[$i], (string)$stat_avail) > -1) {
                            $ret[$count - 2]['status'] = 'Available On Shelf';
                            $ret[$count - 2]['availability'] = 1;
                        } else {
                            $ret[$count - 2]['status'] = 'Available to request';
                            $ret[$count - 2]['availability'] = 0;
                        }
                        if (stripos($cols[$i], (string)$stat_due) > -1) {
                            $t = trim(
                                substr(
                                    $cols[$i],
                                    stripos($cols[$i], (string)$stat_due)
                                        + strlen($stat_due)
                                )
                            );
                            $t = substr($t, 0, stripos($t, ' '));
                            $ret[$count - 2]['duedate'] = $t;
                        }
                    }
                    //$ret[$count-2][$keys[$i]] = $cols[$i];
                    //$ret[$count-2]['id'] = $bibid;
                    $ret[$count - 2]['id'] = $id;
                    $ret[$count - 2]['number'] = ($count - 1);
                    // Return a fake barcode so hold link is enabled
                    // TODO: Should be dependent on settings variable, if bib level
                    // holds.
                    $ret[$count - 2]['barcode'] = '1234567890123';
                }
            }
            $count++;
        }
        return $ret;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        $count = 0;
        foreach ($ids as $id) {
            $items[$count] = $this->getStatus($id);
            $count++;
        }
        return $items;
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
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $id      The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string         URL to ILS's OPAC's place hold screen.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($id, $details)
    {
        // Strip ID
        $id_ = $this->prepID($id);

        //Build request link
        $link = $this->config['Catalog']['url'] . '/search?/.b' . $id_ . '/.b' .
            $id_ . '/1%2C1%2C1%2CB/request~b' . $id_;
        //$link = $this->config['Catalog']['url'] . '/record=b' . $id_;

        return $link;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $userinfo The patron array
     *
     * @throws ILSException
     * @return array          Array of the patron's profile data on success.
     */
    public function getMyProfile($userinfo)
    {
        return $userinfo;
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
        // TODO: if username is a barcode, test to make sure it fits proper format
        $enabled = $this->config['PATRONAPI']['enabled'] ?? false;
        if ($enabled && strtolower($enabled) !== 'false') {
            // use patronAPI to authenticate customer
            $url = $this->config['PATRONAPI']['url'];

            // build patronapi pin test request
            $result = $this->sendRequest(
                $url . urlencode($username) . '/' . urlencode($password) .
                '/pintest'
            );

            // search for successful response of "RETCOD=0"
            if (stripos($result, 'RETCOD=0') == -1) {
                // pin did not match, can look up specific error to return
                // more useful info.
                return null;
            }

            // Pin did match, get patron information
            $result = $this->sendRequest($url . urlencode($username) . '/dump');

            // The following is taken and modified from patronapi.php by John Blyberg
            // released under the GPL
            $api_contents = trim(strip_tags($result));
            $api_array_lines = explode("\n", $api_contents);
            $api_data = ['PBARCODE' => false];

            foreach ($api_array_lines as $api_line) {
                $api_line = str_replace('p=', 'peq', $api_line);
                $api_line_arr = explode('=', $api_line);
                $regex_match = ["/\[(.*?)\]/","/\s/",'/#/'];
                $regex_replace = ['','','NUM'];
                $key = trim(
                    preg_replace($regex_match, $regex_replace, $api_line_arr[0])
                );
                $api_data[$key] = trim($api_line_arr[1]);
            }

            if (!$api_data['PBARCODE']) {
                // No barcode found, can look up specific error to return more
                // useful info. This check needs to be modified to handle using
                // III patron ids also.
                return null;
            }

            // return patron info
            $ret = [];
            $ret['id'] = $api_data['PBARCODE']; // or should I return patron id num?
            $names = explode(',', $api_data['PATRNNAME']);
            $ret['firstname'] = $names[1];
            $ret['lastname'] = $names[0];
            $ret['cat_username'] = urlencode($username);
            $ret['cat_password'] = urlencode($password);
            $ret['email'] = $api_data['EMAILADDR'];
            $ret['major'] = null;
            $ret['college'] = $api_data['HOMELIBR'];
            $ret['homelib'] = $api_data['HOMELIBR'];
            // replace $ separator in III addresses with newline
            $ret['address1'] = str_replace('$', ', ', $api_data['ADDRESS']);
            $ret['address2'] = str_replace('$', ', ', $api_data['ADDRESS2']);
            preg_match(
                '/([0-9]{5}|[0-9]{5}-[0-9]{4})[ ]*$/',
                $api_data['ADDRESS'],
                $zipmatch
            );
            $ret['zip'] = $zipmatch[1]; //retrieve from address
            $ret['phone'] = $api_data['TELEPHONE'];
            $ret['phone2'] = $api_data['TELEPHONE2'];
            // Should probably have a translation table for patron type
            $ret['group'] = $api_data['PTYPE'];
            $ret['expiration'] = $api_data['EXPDATE'];
            // Only if agency module is enabled.
            $ret['region'] = $api_data['AGENCY'];
            return $ret;
        } else {
            // TODO: use screen scrape
            return null;
        }
    }
}
