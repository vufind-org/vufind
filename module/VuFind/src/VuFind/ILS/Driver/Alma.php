<?php
/**
 * Alma ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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

/**
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends Demo implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Alma API base URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Alma API key.
     *
     * @var string
     */
    protected $apiKey;

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
        // TODO: remove parent line when we unhook from demo driver
        parent::init();
        $this->baseUrl = $this->config['Catalog']['apiBaseUrl'];
        $this->apiKey = $this->config['Catalog']['apiKey'];
    }

    /**
     * Make an HTTP request against Alma
     *
     * @param string $path Path to retrieve from API (excluding base URL/API key)
     *
     * @return \SimpleXMLElement
     */
    protected function makeRequest($path)
    {
        // TODO: Support requests of different methods
        $client = $this->httpService->createClient(
            $this->baseUrl . $path . '?apiKey=' . urlencode($this->apiKey)
        );
        $result = $client->send();
        if ($result->isSuccess()) {
            return simplexml_load_string($result->getBody());
        } else {
            // TODO: Throw an error
            error_log($this->baseUrl . $path);
            error_log($result->getBody());
        }
        return null;
    }

    /**
     * Given an item, return the availability status.
     *
     * @param \SimpleXMLElement $item Item data
     *
     * @return bool
     */
    protected function getAvailabilityFromItem($item)
    {
        return (string)$item->item_data->base_status === '1';
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
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $results = [];
        $copyCount = 0;
        $bibPath = '/bibs/' . urlencode($id) . '/holdings';
        if ($holdings = $this->makeRequest($bibPath)) {
            foreach ($holdings->holding as $holding) {
                $holdingId = (string)$holding->holding_id;
                $itemPath = $bibPath . '/' . urlencode($holdingId) . '/items';
                if ($currentItems = $this->makeRequest($itemPath)) {
                    foreach ($currentItems->item as $item) {
                        $barcode = (string)$item->item_data->barcode;
                        $results[] = [
                            'id' => $id,
                            'source' => 'Solr',
                            'availability' => $this->getAvailabilityFromItem($item),
                            'status' => (string)$item->item_data->base_status[0]
                                ->attributes()['desc'],
                            'location' => (string)$holding->library[0]
                                ->attributes()['desc'],
                            'reserve' => 'N',   // TODO: support reserve status
                            'callnumber' => (string)$item->holding_data->call_number,
                            'duedate' => null, // TODO: support due dates
                            'returnDate' => false, // TODO: support recent returns
                            'number' => ++$copyCount,
                            'barcode' => empty($barcode) ? 'n/a' : $barcode,
                            'item_id' => (string)$item->item_data->pid,
                        ];
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode  The patron barcode
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $password)
    {
        $client = $this->httpService->createClient(
            $this->baseUrl . '/users/' . $barcode
            . '?apiKey=' . urlencode($this->apiKey)
        );
        $client->setMethod(\Zend\Http\Request::METHOD_POST);
        $client->setParameterPost(['op' => 'auth', 'password' => trim($password)]);
        $response = $client->send();
        // TODO: DO NOT FAKE SUCCESS
        if (true || $response->isSuccess()) {
            return [
                'cat_username' => trim($barcode),
                'cat_password' => trim($password)
            ];
        }
        return null;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $xml = $this->makeRequest('/users/' . $patron['cat_username']);
        $profile = [
            'firstname' => $xml->first_name,
            'lastname'  => $xml->last_name,
            'group'     => $xml->user_group['desc']
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = $contact->addresses[0]->address;
                $profile['address1'] = $address->line1;
                $profile['address2'] = $address->line2;
                $profile['address3'] = $address->line3;
                $profile['zip']      = $address->postal_code;
                $profile['city']     = $address->city;
                $profile['country']  = $address->country;
            }
            if ($contact->phones) {
                $profile['phone'] = $contact->phones[0]->phone->phone_number;
            }
        }
        return $profile;
    }
}
