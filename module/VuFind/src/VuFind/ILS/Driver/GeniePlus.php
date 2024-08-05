<?php

/**
 * GeniePlus API driver
 *
 * PHP version 8
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

use VuFind\Exception\ILS as ILSException;

use function count;
use function in_array;

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
     * Status messages indicating available items
     *
     * @var string[]
     */
    protected $availableStatuses;

    /**
     * Access token
     *
     * @var string
     */
    protected $token = null;

    /**
     * Factory function for constructing the SessionContainer.
     *
     * @var callable
     */
    protected $sessionFactory;

    /**
     * Session cache
     *
     * @var \Laminas\Session\Container
     */
    protected $sessionCache;

    /**
     * Constructor
     *
     * @param callable $sessionFactory Factory function returning SessionContainer
     * object
     */
    public function __construct(callable $sessionFactory)
    {
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * Support method for init(): make sure we have a valid configuration.
     *
     * @return void
     * @throws ILSException
     */
    protected function validateConfiguration(): void
    {
        $missingConfigs = [];
        $requiredApiSettings = [
            'base_url',
            'catalog_template',
            'database',
            'loan_template',
            'oauth_id',
            'username',
            'password',
            'patron_template',
        ];
        foreach ($requiredApiSettings as $setting) {
            if (!isset($this->config['API'][$setting])) {
                $missingConfigs[] = "API/$setting";
            }
        }
        if (!isset($this->config['Patron']['field']['cat_password'])) {
            $missingConfigs[] = 'Patron/field/cat_password';
        }
        if (!empty($missingConfigs)) {
            throw new ILSException(
                'Missing required GeniePlus.ini configuration setting(s): '
                . implode(', ', $missingConfigs)
            );
        }
    }

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
        $this->validateConfiguration();
        $this->availableStatuses
            = (array)($this->config['Item']['available_statuses'] ?? []);
        $cacheNamespace = md5(
            $this->config['API']['database'] . '|' . $this->config['API']['base_url']
        );
        $this->sessionCache = ($this->sessionFactory)($cacheNamespace);
        if ($this->sessionCache->genieplus_token ?? false) {
            $this->token = $this->sessionCache->genieplus_token;
            $this->debug(
                'Token taken from cache: ' . substr($this->token, 0, 30) . '...'
            );
        }
    }

    /**
     * Renew the OAuth access token needed by the API.
     *
     * @return void
     * @throws ILSException
     */
    protected function renewAccessToken(): void
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
            throw new ILSException('No access token in API response.');
        }
        $this->token = $this->sessionCache->genieplus_token = $result->access_token;
    }

    /**
     * Call the API, with an access token added to the headers; renew token as
     * needed.
     *
     * @param string $method  GET/POST/PUT/DELETE/etc
     * @param string $path    API path (with a leading /)
     * @param array  $params  Parameters object to be sent as data
     * @param array  $headers Additional headers
     *
     * @return \Laminas\Http\Response
     */
    protected function callApiWithToken(
        $method = 'GET',
        $path = '/',
        $params = [],
        $headers = []
    ) {
        $headers[] = 'Accept: application/json';
        if (null === $this->token) {
            $this->renewAccessToken();
        }
        $authHeader = "Authorization: Bearer {$this->token}";
        $response = $this->makeRequest(
            $method,
            $path,
            $params,
            array_merge($headers, [$authHeader]),
            [401, 403]
        );
        if ($response->getStatusCode() > 400) {
            $this->renewAccessToken();
            $authHeader = "Authorization: Bearer {$this->token}";
            $response = $this->makeRequest(
                $method,
                $path,
                $params,
                array_merge($headers, [$authHeader])
            );
        }
        return $response;
    }

    /**
     * Extract a field from an API response.
     *
     * @param array  $record Record containing field
     * @param string $field  Name of field to extract
     * @param string $type   Type of field being looked up (e.g. Item, Patron)
     *
     * @return array
     */
    protected function getFieldFromApiRecord($record, $field, $type = 'Item')
    {
        $fieldName = $this->config[$type]['field'][$field] ?? '';
        return $record[$fieldName] ?? [];
    }

    /**
     * Extract display values from an API response field.
     *
     * @param array $field Array of values from API
     *
     * @return array
     */
    protected function extractDisplayValues($field): array
    {
        $callback = function ($value) {
            return $value['display'];
        };
        return array_map($callback, $field);
    }

    /**
     * Extract holdings data from an API response. Return an array of arrays
     * representing 852 fields (indexed by subfield code).
     *
     * @param array $record Record from API response
     *
     * @return array
     */
    protected function apiStatusRecordToArray($record): array
    {
        $bibId = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($record, 'id')
            )
        );
        $barcodes = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'barcode')
        );
        $callNos = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'callnumber')
        );
        $dueDates = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'duedate')
        );
        $locations = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'location')
        );
        $statuses = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'status')
        );
        $volumes = $this->extractDisplayValues(
            $this->getFieldFromApiRecord($record, 'volume')
        );
        $total = max(
            [
                count($barcodes),
                count($callNos),
                count($dueDates),
                count($locations),
                count($statuses),
                count($volumes),
            ]
        );
        $result = [];
        for ($i = 0; $i < $total; $i++) {
            $availability = in_array($statuses[$i] ?? '', $this->availableStatuses)
                ? 1 : 0;
            $result[] = [
                'id' => $bibId,
                'availability' => $availability,
                'status' => $statuses[$i] ?? '',
                'location' => $locations[$i] ?? '',
                'reserve' => 'N', // not supported
                'callnumber' => $callNos[$i] ?? '',
                'duedate' => $dueDates[$i] ?? '',
                'number' => $volumes[$i] ?? ($i + 1),
                'barcode' => $barcodes[$i] ?? '',
            ];
        }
        $sortParts = array_map(
            'trim',
            explode(
                ' ',
                strtolower($this->config['Item']['sort'] ?? 'none')
            )
        );
        $sortField = $sortParts[0];
        if ($sortField !== 'none') {
            $sortDirection = ($sortParts[1] ?? 'asc') === 'asc' ? 1 : -1;
            $callback = function ($a, $b) use ($sortField, $sortDirection) {
                return strnatcmp($a[$sortField] ?? '', $b[$sortField] ?? '')
                    * $sortDirection;
            };
            usort($result, $callback);
        }
        return $result;
    }

    /**
     * Get the search path to query a template.
     *
     * @param string $template Name of template to query
     *
     * @return string
     */
    protected function getTemplateQueryPath(string $template): string
    {
        $database = $this->config['API']['database'];
        return "/_rest/databases/$database/templates/$template/search-result";
    }

    /**
     * Sanitize a value for inclusion as a single-quoted value in a query string.
     *
     * @param string $value Value to sanitize
     *
     * @return string       Sanitized value
     */
    protected function sanitizeQueryParam(string $value): string
    {
        // The query language used by GeniePlus doubles quotes to escape them.
        return str_replace("'", "''", $value);
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
        $template = $this->config['API']['catalog_template'];
        $path = $this->getTemplateQueryPath($template);
        $idField = $this->config['Item']['field']['id'] ?? 'UniqRecNum';
        $safeId = $this->sanitizeQueryParam($id);
        $params = [
            'page-size' => 100,
            'page' => 0,
            'fields' => implode(',', $this->config['Item']['field'] ?? []),
            'command' => "$idField == '$safeId'",
        ];
        $json = $this->callApiWithToken('GET', $path, $params)->getBody();
        $response = json_decode($json, true);
        return $this->apiStatusRecordToArray($response['records'][0] ?? []);
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
        return array_map([$this, 'getStatus'], $ids);
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

    /**
     * Public Function which retrieves feature-specific settings from the
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
        if ('getMyTransactions' === $function) {
            return $this->config['Transactions'] ?? [
                'max_results' => 100,
            ];
        }

        return false;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $template = $this->config['API']['patron_template'];
        $path = $this->getTemplateQueryPath($template);
        $userField = $this->config['Patron']['field']['cat_username'] ?? 'Email';
        $passField = $this->config['Patron']['field']['cat_password'];
        $safeUser = $this->sanitizeQueryParam($username);
        $safePass = $this->sanitizeQueryParam($password);
        $idField = $this->config['Patron']['field']['id'] ?? 'ID';
        $nameField = $this->config['Patron']['field']['name'] ?? 'Name';
        $emailField = $this->config['Patron']['field']['email'] ?? 'Email';
        $params = [
            'page-size' => 1,
            'page' => 0,
            'fields' => implode(',', [$idField, $nameField, $emailField]),
            'command' => "$userField == '$safeUser' AND $passField == '$safePass'",
        ];
        $json = $this->callApiWithToken('GET', $path, $params)->getBody();
        $response = json_decode($json, true);
        $user = $response['records'][0] ?? [];
        if (empty($user)) {
            return null;
        }
        $id = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'id', 'Patron')
            )
        );
        $email = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'email', 'Patron')
            )
        );
        $name = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'name', 'Patron')
            )
        );
        [$last, $first] = explode(',', $name, 2);
        return [
            'id'           => $id,
            'firstname'    => trim($first),
            'lastname'     => trim($last),
            'cat_username' => trim($username),
            'cat_password' => trim($password),
            'email'        => $email,
            'major'        => null,
            'college'      => null,
        ];
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
        $template = $this->config['API']['patron_template'];
        $path = $this->getTemplateQueryPath($template);
        $idField = $this->config['Patron']['field']['id'] ?? 'ID';
        $safeId = $this->sanitizeQueryParam($patron['id']);
        $fields = [
            $this->config['Patron']['field']['address1'] ?? 'Address1',
            $this->config['Patron']['field']['address2'] ?? 'Address2',
            $this->config['Patron']['field']['zip'] ?? 'ZipCode',
            $this->config['Patron']['field']['city'] ?? 'City',
            $this->config['Patron']['field']['state'] ?? 'StateProv.CodeDesc',
            $this->config['Patron']['field']['country'] ?? 'Country.CodeDesc',
            $this->config['Patron']['field']['phone'] ?? 'PhoneNumber',
            $this->config['Patron']['field']['expiration_date'] ?? 'ExpiryDate',
        ];
        $params = [
            'page-size' => 1,
            'page' => 0,
            'fields' => implode(',', $fields),
            'command' => "$idField == '$safeId'",
        ];
        $json = $this->callApiWithToken('GET', $path, $params)->getBody();
        $response = json_decode($json, true);
        $user = $response['records'][0] ?? [];
        if (empty($user)) {
            throw new \Exception("Unable to fetch patron $safeId");
        }
        $addr1 = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'address1', 'Patron')
            )
        );
        $addr2 = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'address2', 'Patron')
            )
        );
        $zip = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'zip', 'Patron')
            )
        );
        $city = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'city', 'Patron')
            )
        );
        $state = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'state', 'Patron')
            )
        );
        $country = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'country', 'Patron')
            )
        );
        $phone = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'phone', 'Patron')
            )
        );
        $expirationDate = current(
            $this->extractDisplayValues(
                $this->getFieldFromApiRecord($user, 'expiration_date', 'Patron')
            )
        );
        $cityAndState = trim($city . (!empty($city) ? ', ' : '') . $state);
        return [
            'firstname'       => $patron['firstname'],
            'lastname'        => $patron['lastname'],
            'address1'        => empty($addr1) ? null : $addr1,
            'address2'        => empty($addr2) ? null : $addr2,
            'zip'             => empty($zip) ? null : $zip,
            'city'            => empty($city) ? null : $cityAndState,
            'country'         => empty($country) ? null : $country,
            'phone'           => empty($phone) ? null : $phone,
            'expiration_date' => empty($expirationDate) ? null : $expirationDate,
        ];
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return mixed        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron, $params = [])
    {
        $patronTemplate = $this->config['API']['patron_template'];
        $loanTemplate = $this->config['API']['loan_template'];
        $path = $this->getTemplateQueryPath($loanTemplate);
        $idField = $patronTemplate . '.'
            . ($this->config['Patron']['field']['id'] ?? 'ID');
        $safeId = $this->sanitizeQueryParam($patron['id']);
        $barcodeField = $this->config['Item']['field']['barcode']
            ?? 'Inventory.Barcode';
        $bibIdField = $this->config['Loan']['field']['bib_id']
            ?? 'Inventory.Inventory@Catalog.UniqRecNum';
        $dueField = $this->config['Loan']['field']['duedate'] ?? 'ClaimDate';
        $archiveField = $this->config['Loan']['field']['archive'] ?? 'Archive';
        $fields = [$barcodeField, $bibIdField, $dueField];
        $params = [
            'page-size' => $params['limit'] ?? 100,
            'page' => ($params['page'] ?? 1) - 1,
            'fields' => implode(',', $fields),
            'command' => "$idField == '$safeId' AND $archiveField == 'No'",
        ];
        $json = $this->callApiWithToken('GET', $path, $params)->getBody();
        $response = json_decode($json, true);
        $callback = function ($entry) use ($barcodeField, $bibIdField, $dueField) {
            return [
                'id' => $entry[$bibIdField][0]['display'] ?? null,
                'item_id' => $entry[$barcodeField][0]['display'] ?? null,
                'duedate' => $entry[$dueField][0]['display'] ?? null,
            ];
        };
        return [
            'count' => $response['total'] ?? 0,
            'records' => array_map($callback, $response['records'] ?? []),
        ];
    }
}
