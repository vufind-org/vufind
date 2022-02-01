<?php
/**
 * GeniePlus API driver
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * GeniePlus API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class GeniePlus extends AbstractAPI
{
    /**
     * Access token
     *
     * @var string
     */
    protected $token = null;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @return void
     */
    public function init()
    {
    }

    protected function renewAccessToken()
    {
        $params = [
            'client_id' => $this->config['API']['oauth_id'],
            'grant_type' => 'password',
            'database' => $this->config['API']['database'],
            'username' => $this->config['API']['username'],
            'password' => $this->config['API']['password'],
        ];
        $headers = [
            'Accept: application/json',
        ];
        $response = $this->makeRequest('POST', '/_oauth/token', $params, $headers);
        $result = json_decode($response->getBody());
        if (!isset($result->access_token)) {
            // TODO: retry loop? Smarter status checks?
            throw new \Exception('Unable to obtain access token.');
        }
        $this->token = $result->access_token;
    }

    protected function callApiWithToken(
        $method = "GET",
        $path = "/",
        $params = [],
        $headers = []
    ) {
        $headers[] = "Accept: application/json";
        if (null === $this->token) {
            $this->renewAccessToken();
        }
        try {
            $authHeader = "Authorization: Bearer {$this->token}";
            return $this
                ->makeRequest($method, $path, $params, $headers + [$authHeader]);
        } catch (\Exception $e) {
            // TODO: catch more specific exception
            $this->renewAccessToken();
            $authHeader = "Authorization: Bearer {$this->token}";
            return $this
                ->makeRequest($method, $path, $params, $headers + [$authHeader]);
        }
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        // TODO: add real data
        $database = $this->config['API']['database'];
        $template = $this->config['API']['catalog_template'];
        $path = "/_rest/databases/$database/templates/$template/search-result";
        $idField = $this->config['API']['field']['id'];
        $params = [
            'page-size' => 100, // TODO: configurable?
            'page' => 0,
            'fields' => implode(',', $this->config['API']['field']),
            'command' => "$idField == '$id'", // TODO: escape/sanitize
        ];
        $response = $this->callApiWithToken('GET', $path, $params);
        var_dump($response->getBody());
        return [
            [
                'id' => $id,
                'availability' => 1,
                'status' => 'Available',
                'location' => '3rd Floor Main Library',
                'reserve' => 'N',
                'callnumber' => 'A1234.567',
                'duedate' => '',
                'number' => 1,
                'barcode' => '1234567890',
            ]
        ];
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getStatus($id);
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
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
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
     * @return mixed     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // Not supported here:
        return [];
    }
}
