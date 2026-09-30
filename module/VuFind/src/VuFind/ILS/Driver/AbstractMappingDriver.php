<?php

/**
 * Multiple Backend Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2012-2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function call_user_func_array;
use function func_get_args;
use function in_array;
use function is_array;
use function is_callable;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
abstract class AbstractMappingDriver extends AbstractMultiDriver
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * An array of methods that should map the parameters and determine the source for a specific method.
     *
     * @var array
     */
    protected array $paramMapAndSourceCheckMethods = [
        'cancelHolds' => 'detailsWithPatronParamMap',
        'cancelILLRequests' => 'detailsWithPatronParamMap',
        'cancelStorageRetrievalRequests' => 'detailsWithPatronParamMap',
        'changePassword' => 'patronParamMap',
        'checkILLRequestIsValid' => 'recordIdAndDetailsAndPatronParamMap',
        'checkRequestIsValid' => 'recordIdAndDetailsAndPatronParamMap',
        'checkStorageRetrievalRequestIsValid' => 'recordIdAndDetailsAndPatronParamMap',
        'getAccountBlocks' => 'patronParamMap',
        'getCancelHoldDetails' => 'detailsAndPatronParamMap',
        'getCancelHoldLink' => 'detailsAndPatronParamMap',
        'getCancelILLRequestDetails' => 'detailsAndPatronParamMap',
        'getCancelStorageRetrievalRequestDetails' => 'detailsAndPatronParamMap',
        'getConsortialHoldings' => 'getConsortialHoldingsParamMap',
        'getDefaultPickUpLocation' => 'patronAndDetailsParamMap',
        'getDefaultRequestGroup' => 'patronAndDetailsParamMap',
        'getHoldDefaultRequiredDate' => 'patronAndDetailsParamMap',
        'getHolding' => 'recordIdAndPatronParamMap',
        'getHoldLink' => 'recordIdAndPatronParamMap',
        'getILLPickupLibraries' => 'recordIdAndPatronParamMap',
        'getILLPickupLocations' => 'getILLPickupLocationsParamMap',
        'getMyFines' => 'patronParamMap',
        'getMyHolds' => 'patronParamMap',
        'getMyILLRequests' => 'patronParamMap',
        'getMyProfile' => 'patronParamMap',
        'getMyStorageRetrievalRequests' => 'patronParamMap',
        'getMyTransactionHistory' => 'patronParamMap',
        'getMyTransactions' => 'patronParamMap',
        'getOnlinePaymentDetails' => 'patronParamMap',
        'getPickUpLocations' => 'patronAndDetailsParamMap',
        'getProxiedUsers' => 'patronParamMap',
        'getProxyingUsers' => 'patronParamMap',
        'getPurchaseHistory' => 'recordIdParamMap',
        'getRenewDetails' => 'detailsParamMap',
        'getRequestBlocks' => 'patronParamMap',
        'getRequestGroups' => 'recordIdAndPatronAndDetailsParamMap',
        'getStatus' => 'recordIdParamMap',
        'getUrlsForRecord' => 'recordIdParamMap',
        'hasHoldings' => 'recordIdParamMap',
        'patronLogin' => 'patronLoginParamMap',
        'placeHold' => 'detailsWithPatronParamMap',
        'placeILLRequest' => 'detailsWithPatronParamMap',
        'placeStorageRetrievalRequest' => 'detailsWithPatronParamMap',
        'purgeTransactionHistory' => 'patronParamMap',
        'renewMyItems' => 'detailsWithPatronParamMap',
        'renewMyItemsLink' => 'detailsWithPatronParamMap',
        'registerPayment' => 'patronParamMap',
        'updateHolds' => 'updateHoldsParamMap',
    ];

    protected array $resultMapMethods = [
        'renewMyItems' => 'mapIlsHoldToVuFindHold',
        'getMyTransactionHistory' => 'mapIlsHoldToVuFindHold',
        'getMyHolds' => 'mapIlsHoldToVuFindHold',
        'getMyTransactions' => 'mapIlsHoldToVuFindHold',
        'getConsortialHoldings' => 'mapIlsHoldToVuFindHold',
        'getRenewDetails' => 'mapIlsHoldToVuFindHold',
        'getMyFines' => 'mapIlsIdsToVuFindIds',
    ];

    /**
     * The default driver to use.
     *
     * @var string
     */
    protected $defaultDriver;

    protected function recordIdParamMap($params)
    {
        $recordId = $params[0] ?? '';
        $source = $this->getSourceForRecordId($recordId);
        $params[0] = $this->mapVuFindRecordIdToIlsRecordId($recordId, $source);
        return [$params, $source];
    }

    protected function patronParamMap($params)
    {
        $patron = $params[0] ?? [];
        $source = $this->getSourceForPatron($patron);
        $params[0] = $this->mapVuFindPatronToIlsPatron($patron, $source);
        return [$params, $source];
    }

    protected function recordIdAndPatronParamMap($params)
    {
        $id = $params[0] ?? '';
        $patron = $params[1] ?? [];
        $source = $this->getSourceForRecordId($id);
        $params[0] = $this->mapVuFindRecordIdToIlsRecordId($id, $source);
        $params[1] = $this->mapVuFindPatronToIlsPatron($patron, $source);
        return [$params, $source];
    }

    protected function detailsParamMap($params)
    {
        $details = $params[0] ?? [];
        $source = $this->getSourceForRecordId($details['id']);
        $params[0] = $this->mapVuFindHoldToIlsHold($details, $source);
        return [$params, $source];
    }

    protected function detailsWithPatronParamMap($params)
    {
        $details = $params[0] ?? [];
        $patron = $details['patron'];
        $source = $this->getSourceForPatron($patron);
        // remove patron from hold details for mapping of ids
        unset($details['patron']);
        $details = $this->mapVuFindHoldToIlsHold($details, $source);
        $details['patron'] = $this->mapVuFindPatronToIlsPatron($patron, $source);
        $params[0] = $details;
        return [$params, $source];
    }

    protected function detailsAndPatronParamMap($params)
    {
        $details = $params[0] ?? [];
        $patron = $params[1] ?? [];
        $source = '';
        $recordId = $details['id'] ?? null;
        $itemId = $details['item_id'] ?? null;
        if ($patron) {
            $source = $this->getSourceForPatron($patron);
        } elseif ($recordId) {
            $source = $this->getSourceForRecordId($recordId);
        } elseif ($itemId) {
            $source = $this->getSourceForItemId($itemId);
        }
        $params[0] = $this->mapVuFindHoldToIlsHold($details, $source);
        $params[1] = $this->mapVuFindPatronToIlsPatron($patron, $source);
        return [$params, $source];
    }

    protected function patronAndDetailsParamMap($params)
    {
        $mappedParams = [$params[1] ?? [], $params[0] ?? []];
        [$mappedParams, $source] = $this->detailsAndPatronParamMap($mappedParams);
        $params[0] = $mappedParams[1];
        $params[1] = $mappedParams[0];
        return [$params, $source];
    }

    protected function recordIdAndDetailsAndPatronParamMap($params)
    {
        $source = $this->getSourceForPatron($params[2]);
        $params[0] = $this->mapVuFindRecordIdToIlsRecordId($params[0] ?? '', $source);
        $params[1] = $this->mapVuFindHoldToIlsHold($params[1] ?? [], $source);
        $params[2] = $this->mapVuFindPatronToIlsPatron($params[2] ?? [], $source);
        return [$params, $source];
    }

    protected function recordIdAndPatronAndDetailsParamMap($params)
    {
        $source = $this->getSourceForPatron($params[1] ?? []);
        $params[0] = $this->mapVuFindRecordIdToIlsRecordId($params[0] ?? '', $source);
        $params[1] = $this->mapVuFindPatronToIlsPatron($params[1] ?? [], $source);
        $params[2] = $this->mapVuFindHoldToIlsHold($params[2] ?? [], $source);
        return [$params, $source];
    }

    protected function getConsortialHoldingsParamMap($params)
    {
        $id = $params[0] ?? '';
        $patron = $params[1] ?? [];
        $ids = $params[2] ?? [];
        $source = '';
        if ($patron) {
            $source = $this->getSourceForPatron($patron);
        } elseif ($id) {
            $source = $this->getSourceForRecordId($id);
        } elseif ($firstId = $ids[0] ?? null) {
            $source = $this->getSourceForRecordId($firstId);
        }
        return [
            [
                $this->mapVuFindRecordIdToIlsRecordId($id, $source),
                $this->mapVuFindHoldToIlsHold($patron, $source),
                array_map(fn ($id) => $this->mapVuFindRecordIdToIlsRecordId($id, $source), $ids),
            ],
            $source,
        ];
    }

    protected function getILLPickupLocationsParamMap($params)
    {
        $mappedParams = [$params[0] ?? '', $params[2] ?? []];
        [$mappedParams, $source] = $this->recordIdAndPatronParamMap($mappedParams);
        $params[0] = $mappedParams[0];
        $params[2] = $mappedParams[1];
        return [$params, $source];
    }

    protected function patronLoginParamMap($params)
    {
        $catUsername = $params[0] ?? '';
        $source = $this->getSourceForCatUsername($catUsername);
        $params[0] = $this->mapVuFindCatUsernameToIlsCatUsername($catUsername, $source);
        return [$params, $source];
    }

    protected function updateHoldsParamMap($params)
    {
        $mappedParams = [$params[0] ?? '', $params[2] ?? []];
        [$mappedParams, $source] = $this->detailsAndPatronParamMap($mappedParams);
        $params[0] = $mappedParams[0];
        $params[2] = $mappedParams[1];
        return [$params, $source];
    }

    protected function mapParamsAndGetSourceForMethod($function, $params)
    {
        if ($mappingMethod = $this->paramMapAndSourceCheckMethods[$function] ?? null) {
            return $this->$mappingMethod($params);
        }
        $source = $this->getSourceForMethod($function, $params);
        try {
            if (!$source && $patron = $this->ilsAuth->getStoredCatalogCredentials()) {
                $source = $this->getSourceForPatron($patron);
            }
        } catch (ILSException $e) {
        }
        $source ??= $this->defaultDriver ?? null;
        return [$params, $source];
    }

    protected function mapResultForMethod($source, $function, $result)
    {
        if ($mappingMethod = $this->resultMapMethods[$function] ?? null) {
            return $this->$mappingMethod($result, $source);
        }
        return $result;
    }

    /**
     * Map the patron array of the ILS to VuFind's patron array.
     *
     * @param array  $patron Patron
     * @param string $source Source code
     *
     * @return array
     */
    protected function mapIlsPatronToVuFindPatron($patron, $source)
    {
        return $this->mapVuFindIdsToIlsIds(
            $patron,
            $source,
            [
                'cat_username' => 'mapIlsCatUsernameToVuFindCatUsername',
            ]
        );
    }

    /**
     * Map the hold array of the ILS to VuFind's hold array.
     *
     * @param array  $hold   Hold
     * @param string $source Source code
     *
     * @return array
     */
    protected function mapIlsHoldToVuFindHold($hold, $source)
    {
        return $this->mapVuFindIdsToIlsIds(
            $hold,
            $source,
            [
                'id' => 'mapIlsRecordIdToVuFindRecordId',
                'item_id' => 'mapIlsItemIdToVuFindItemId',
            ]
        );
    }

    /**
     * Change local ID's to global ID's in the given array.
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function mapIlsIdsToVuFindIds(
        $data,
        $source,
        $modifyFields = ['id' => 'mapIlsRecordIdToVuFindRecordId'],
    ) {
        if (empty($source) || empty($data) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (null === $value) {
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->mapIlsIdsToVuFindIds(
                    $value,
                    $source,
                    $modifyFields
                );
            } else {
                if ($value !== '' && $mappingMethod = $modifyFields[$key] ?? null) {
                    $data[$key] = $this->$mappingMethod($value, $source);
                }
            }
        }
        return $data;
    }

    /**
     * Map VuFind's patron array to the patron array of the ILS.
     *
     * @param array  $patron Patron
     * @param string $source Source code
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function mapVuFindPatronToIlsPatron($patron, $source)
    {
        return $this->mapVuFindIdsToIlsIds(
            $patron,
            $source,
            [
                'id' => 'mapVuFindPatronIdToIlsPatronId',
                'cat_username' => 'mapVuFindCatUsernameToIlsCatUsername',
            ]
        );
    }

    /**
     * Map VuFind's hold array to the hold array of the ILS.
     *
     * @param array  $hold   Hold
     * @param string $source Source code
     *
     * @return array
     */
    protected function mapVuFindHoldToIlsHold($hold, $source)
    {
        return $this->mapVuFindIdsToIlsIds(
            $hold,
            $source,
            [
                'id' => 'mapVuFindRecordIdToIlsRecordId',
                'item_id' => 'mapVuFindItemIdToIlsItemId',
            ]
        );
    }

    /**
     * Change global ID's to local ID's in the given array.
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     * @param array  $ignoreFields Fields to be ignored during recursive processing
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function mapVuFindIdsToIlsIds(
        $data,
        $source,
        $modifyFields = ['id' => 'mapVuFindRecordIdToIlsRecordId'],
        $ignoreFields = []
    ) {
        if (!isset($data) || empty($data)) {
            return $data;
        }
        $array = is_array($data) ? $data : [$data];

        foreach ($array as $key => $value) {
            if (in_array($key, $ignoreFields) || null === $value) {
                continue;
            }
            if (is_array($value)) {
                $array[$key] = $this->mapVuFindIdsToIlsIds(
                    $value,
                    $source,
                    $modifyFields,
                    $ignoreFields
                );
            } else {
                if ($method = $modifyFields[$key] ?? null) {
                    $array[$key] = $this->$method($value, $source);
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }

    /**
     * Check if the given ILS driver supports the source for a given the patron.
     *
     * @param string $driverSource Driver's source identifier
     * @param array  $patron       Patron
     *
     * @return bool
     */
    protected function driverSupportsSourceForPatron(string $driverSource, array $patron): bool
    {
        // Same source is always ok:
        if ($this->getSourceForPatron($patron) === $driverSource) {
            return true;
        }
        // Demo driver supports any record source:
        $driver = $this->getDriver($driverSource);
        return $driver instanceof \VuFind\ILS\Driver\Demo;
    }

    /**
     * Check if the given ILS driver supports the source for a given record.
     *
     * @param string $driverSource Driver's source identifier
     * @param string $recordId     Record id
     *
     * @return bool
     */
    protected function driverSupportsSourceForRecordId(string $driverSource, string $recordId): bool
    {
        // Same source is always ok:
        if ($this->getSourceForRecordId($recordId) === $driverSource) {
            return true;
        }
        // Demo driver supports any record source:
        $driver = $this->getDriver($driverSource);
        return $driver instanceof \VuFind\ILS\Driver\Demo;
    }

    /**
     * Check if the given ILS driver supports the source for a given record.
     *
     * @param string $driverSource Driver's source identifier
     * @param string $itemId       Item id
     *
     * @return bool
     */
    protected function driverSupportsSourceForItemId(string $driverSource, string $itemId): bool
    {
        // Same source is always ok:
        if ($this->getSourceForItemId($itemId) === $driverSource) {
            return true;
        }
        // Demo driver supports any record source:
        $driver = $this->getDriver($driverSource);
        return $driver instanceof \VuFind\ILS\Driver\Demo;
    }

    /**
     * Get source for a method and parameters.
     *
     * @param string $method Method
     * @param array  $params Parameters
     *
     * @return ?string
     */
    protected function getSourceForMethod(string $method, array $params): ?string
    {
        return $params['__source'] ?? null;
    }

    /**
     * Check that the requested method is supported and call it.
     *
     * @param ?string $source Source ID or null to determine from parameters
     * @param string  $method Method name
     * @param array   $params Method parameters
     *
     * @return mixed
     * @throws ILSException
     */
    protected function callMethodIfSupported(
        ?string $source,
        string $method,
        array $params,
    ) {
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->driverSupportsMethod($driver, $method, $params)) {
                return call_user_func_array([$driver, $method], $params);
            }
        }
        throw new ILSException('No suitable backend driver found');
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
        $this->defaultDriver = $this->config['General']['default_driver'] ?? null;
    }

    /**
     * Get Status.
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
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            $status = $driver->getStatus(...$params);
            return $this->mapIlsHoldToVuFindHold($status, $source);
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
        return [];
    }

    /**
     * Get Statuses.
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
        // Group records by source and request statuses from the drivers
        $grouped = [];
        foreach ($ids as $id) {
            $source = $this->getSourceForRecordId($id);
            if (!isset($grouped[$source])) {
                $driver = $this->getDriver($source);
                $grouped[$source] = [
                    'driver' => $driver,
                    'ids' => [],
                ];
            }
            $grouped[$source]['ids'][] = $id;
        }

        // Process each group
        $results = [];
        foreach ($grouped as $source => $current) {
            // Get statuses only if a driver is configured for this source
            if ($current['driver']) {
                $ilsRecordIds = array_map(
                    function ($id) use ($source) {
                        return $this->mapVuFindRecordIdToIlsRecordId($id, $source);
                    },
                    $current['ids']
                );
                try {
                    $statuses = $current['driver']->getStatuses($ilsRecordIds);
                } catch (ILSException $e) {
                    $statuses = array_map(
                        function ($id) {
                            return [
                                ['id' => $id, 'error' => 'An error has occurred'],
                            ];
                        },
                        $ilsRecordIds
                    );
                }
                $statuses = array_map(
                    function ($status) use ($source) {
                        return $this->mapIlsHoldToVuFindHold($status, $source);
                    },
                    $statuses
                );
                $results = array_merge($results, $statuses);
            }
        }
        return $results;
    }

    /**
     * Get Holding.
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param ?array $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, ?array $patron = null, array $options = [])
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            // If the patron belongs to another source, just pass on an empty array
            // to indicate that the patron has logged in but is not available for the
            // current catalog.
            if ($patron && !$this->driverSupportsSourceForPatron($source, $patron)) {
                $params[1] = [];
            }
            $holdings = $driver->getHolding(...$params);
            return $this->mapIlsHoldToVuFindHold($holdings, $source);
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
        return [];
    }

    /**
     * Get Purchase History.
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
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            return $driver->getPurchaseHistory(...$params);
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
        return [];
    }

    /**
     * Get available login targets (drivers enabled for login).
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        return [$this->defaultDriver];
    }

    /**
     * Get default login driver.
     *
     * @return string Default login driver or empty string
     */
    public function getDefaultLoginDriver()
    {
        return $this->defaultDriver;
    }

    /**
     * Get Departments.
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getDepartments();
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Instructors.
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getInstructors();
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Courses.
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getCourses();
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Find Reserves.
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items
     */
    public function findReserves($course, $inst, $dept)
    {
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $this->mapIlsIdsToVuFindIds(
                $driver->findReserves($course, $inst, $dept),
                $this->defaultDriver,
                ['BIB_ID' => 'mapIlsRecordIdToVuFindRecordId']
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Profile.
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return mixed Array of the patron's profile data
     */
    public function getMyProfile($patron)
    {

        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            return $driver->getMyProfile(...$params);
        }
        // Return an empty array if driver is not available; cat_username can point
        // to an ILS that's not currently configured.
        return [];
    }

    /**
     * Get Patron Call Slips.
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyStorageRetrievalRequests(...$params);
            return $this->mapIlsHoldToVuFindHold($requests, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Check whether a hold or recall request is valid.
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if (!isset($patron['cat_username'])) {
            return false;
        }
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsSourceForRecordId($source, $id)) {
                return false;
            }
            return $driver->checkRequestIsValid(...$params);
        }
        return false;
    }

    /**
     * Check whether a storage retrieval request is valid.
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (
                !$this->driverSupportsSourceForRecordId($source, $id)
                || !is_callable([$driver, 'checkStorageRetrievalRequestIsValid'])
            ) {
                return false;
            }
            return $driver->checkStorageRetrievalRequestIsValid(
                ...$params
            );
        }
        return false;
    }

    /**
     * Get Pick Up Locations.
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
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
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            $recordId = $holdDetails['id'] ?? null;
            $itemId = $holdDetails['item_id'] ?? null;
            if (
                ($recordId && !$this->driverSupportsSourceForRecordId($source, $recordId)) ||
                (!$recordId && $itemId && !$this->driverSupportsSourceForItemId($source, $itemId))
            ) {
                // Return empty array since the sources don't match
                return [];
            }
            return $driver->getPickUpLocations(
                ...$params
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Default Pick Up Location.
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            $recordId = $holdDetails['id'] ?? null;
            if ($recordId && !$this->driverSupportsSourceForRecordId($source, $recordId)) {
                // Return false since the sources don't match
                return false;
            }
            return $driver->getDefaultPickUpLocation(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get request groups.
     *
     * @param int   $id          BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  An array of associative arrays with requestGroupId and
     * name keys
     */
    public function getRequestGroups($id, $patron, $holdDetails = null)
    {
        // Get source from patron as that will work also with the Demo driver:
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (
                !$this->driverSupportsSourceForRecordId($source, $id)
                || !$this->driverSupportsMethod($driver, __FUNCTION__, $params)
            ) {
                // Return empty array since the sources don't match or the method
                // isn't supported by the driver
                return [];
            }
            return $driver->getRequestGroups(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Default Request Group.
     *
     * Returns the default request group
     *
     * @param array  $patron      Patron information returned by the patronLogin method.
     * @param ?array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return false|string       The default request group for the patron.
     */
    public function getDefaultRequestGroup($patron, $holdDetails = null)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!empty($holdDetails)) {
                if (
                    !$this->driverSupportsSourceForRecordId($source, $holdDetails['id'])
                    || !$this->driverSupportsMethod($driver, __FUNCTION__, $params)
                ) {
                    // Return false since the sources don't match or the method
                    // isn't supported by the driver
                    return false;
                }
            }
            return $driver->getDefaultRequestGroup(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Place Hold.
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsSourceForRecordId($source, $holdDetails['id'])) {
                return [
                    'success' => false,
                    'sysMessage' => 'ILSMessages::hold_wrong_user_institution',
                ];
            }
            return $driver->placeHold(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Place Storage Retrieval Request.
     *
     * Attempts to place a storage retrieval request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        $driver = $this->getDriver($source);
        if (
            $driver
            && is_callable([$driver, 'placeStorageRetrievalRequest'])
        ) {
            if (!$this->driverSupportsSourceForRecordId($source, $details['id'])) {
                return [
                    'success' => false,
                    'sysMessage' => 'ILSMessages::storage_wrong_user_institution',
                ];
            }
            return $driver->placeStorageRetrievalRequest(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron ILL Requests.
     *
     * This is responsible for retrieving all ILL Requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's ILL requests
     */
    public function getMyILLRequests($patron)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyILLRequests(...$params);
            return $this->mapIlsHoldToVuFindHold($requests, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                return false;
            }
            return $driver->getRequestBlocks(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get suppressed authority records.
     *
     * @return array ID numbers of suppressed authority records in the system.
     */
    public function getSuppressedAuthorityRecords()
    {
        $suppressedRecords = [];
        foreach (array_keys($this->drivers) as $source) {
            if ($driver = $this->getDriver($source)) {
                $suppressedRecords = array_merge(
                    $suppressedRecords,
                    array_map(
                        fn ($id) => $this->mapIlsRecordIdToVuFindRecordId($id, $source),
                        $driver->getSuppressedAuthorityRecords()
                    )
                );
            }
        }
        return $suppressedRecords;
    }

    /**
     * Get suppressed records.
     *
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $suppressedRecords = [];
        foreach (array_keys($this->drivers) as $source) {
            if ($driver = $this->getDriver($source)) {
                $suppressedRecords = array_merge(
                    $suppressedRecords,
                    array_map(
                        fn ($id) => $this->mapIlsRecordIdToVuFindRecordId($id, $source),
                        $driver->getSuppressedRecords()
                    )
                );
            }
        }
        return $suppressedRecords;
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                return false;
            }
            return $driver->getAccountBlocks(...$params);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Function which specifies renew, hold and cancel settings.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig(string $function, array $params = []): array
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod($function, $params);

        $driver = $this->getDriver($source);

        // If we have resolved the needed driver, call getConfig and return.
        if ($driver && $this->driverSupportsMethod($driver, 'getConfig', $params)) {
            return $driver->getConfig($function, $params);
        }

        // If driver not available, return an empty array
        return [];
    }

    /**
     * Patron Login.
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return ?array          Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod(__FUNCTION__, func_get_args());
        $result = $this->callMethodIfSupported($source, __FUNCTION__, $params);
        if (is_array($result)) {
            $result['__source'] = $source;
        }
        return $this->mapIlsPatronToVuFindPatron($result, $source);
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters.
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     */
    public function supportsMethod(string $method, array $params)
    {
        if ($method == 'getLoginDrivers' || $method == 'getDefaultLoginDriver') {
            return true;
        }

        [$params, $source] = $this->mapParamsAndGetSourceForMethod($method, $params);

        if (!$source) {
            // If we can't determine the source, assume we are capable of handling
            // the request unless the method is one that doesn't have parameters that
            // allow the correct source to be determined.
            return !in_array($method, $this->methodsWithNoSourceSpecificParameters);
        }

        $driver = $this->getDriver($source);
        return $driver && $this->driverSupportsMethod($driver, $method, $params);
    }

    /**
     * Default method -- pass along calls to the driver if a source can be determined
     * and a driver is available. Throws ILSException otherwise.
     *
     * @param string $methodName The name of the called method
     * @param array  $params     Array of passed parameters
     *
     * @throws ILSException
     * @return mixed             Varies by method
     */
    public function __call($methodName, $params)
    {
        [$params, $source] = $this->mapParamsAndGetSourceForMethod($methodName, $params);
        $result = $this->callMethodIfSupported($source, $methodName, $params);
        return $this->mapResultForMethod($source, $methodName, $result);
    }

    /**
     * Find the correct driver for the correct configuration file for the
     * given source and cache an initialized copy of it.
     *
     * @param string $source The source name of the driver to get.
     *
     * @return mixed On success a driver object, otherwise null.
     */
    protected function getDriver($source)
    {
        if (!$source) {
            // Check for default driver
            if (!$this->defaultDriver) {
                return null;
            }
            $this->debug('Using default driver ' . $this->defaultDriver);
            $source = $this->defaultDriver;
        }
        return parent::getDriver($source);
    }
}
