<?php
/**
 * Voyager ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;
use PDO, PDOException;

/**
 * Voyager Restful ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{
    use VoyagerFinna;

    /**
     * Configuration Reader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader = null;

    /**
     * Set the config reader
     *
     * @param \VuFind\Config\PluginManager $configReader Configuration reader
     *
     * @return void
     */
    public function setConfigReader(\VuFind\Config\PluginManager $configReader)
    {
        $this->configReader = $configReader;
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
        $profile = parent::getMyProfile($patron);
        $profile['blocks'] = $this->checkAccountBlocks($patron['id']);
        return $profile;
    }

    /**
     * Get ILL (UB) Pickup Locations
     *
     * This is responsible for getting a list of possible pickup locations for a
     * library
     *
     * @param string $id        Record ID
     * @param string $pickupLib Pickup library ID
     * @param array  $patron    Patron
     *
     * @return bool|array False if request not allowed, or an array of
     * locations.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        $result = parent::getILLPickupLocations($id, $pickupLib, $patron);
        if (is_array($result)) {
            $result = $this->filterAllowedUBPickupLocations($result, $patron);
        }
        return $result;
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $pickupByCallSlipGroup
            = isset(
                $this->config['StorageRetrievalRequests']['pickupByCallSlipGroup']
            )
            && $this->config['StorageRetrievalRequests']['pickupByCallSlipGroup'];
        if (isset($holdDetails['requestType'])
            && $holdDetails['requestType'] == 'StorageRetrievalRequest'
            && $pickupByCallSlipGroup
        ) {
            $savePickupLocations = $this->ws_pickUpLocations;
            try {
                $this->ws_pickUpLocations = [];
                $sql = "SELECT CALL_SLIP_PRINT_GROUP.LOCATION_ID as location_id, " .
                    "NVL(LOCATION.LOCATION_DISPLAY_NAME, LOCATION.LOCATION_NAME) " .
                    "as location_name from " .
                    "$this->dbName.CALL_SLIP_PRINT_GROUP, $this->dbName.LOCATION, " .
                    "$this->dbName.CALL_SLIP_GROUP_LOCATION, $this->dbName.ITEM " .
                    "where CALL_SLIP_PRINT_GROUP.GROUP_ID = " .
                    "CALL_SLIP_GROUP_LOCATION.GROUP_ID " .
                    "and CALL_SLIP_PRINT_GROUP.LOCATION_ID = LOCATION.LOCATION_ID " .
                    "and CALL_SLIP_GROUP_LOCATION.LOCATION_ID = " .
                    "case when ITEM.TEMP_LOCATION = 0 " .
                    "then ITEM.PERM_LOCATION else ITEM.TEMP_LOCATION end " .
                    "and ITEM.ITEM_ID = :item_id";
                $params = ['item_id' => $holdDetails['item_id']];
                try {
                    $this->debugSQL(__FUNCTION__, $sql, $params);
                    $sqlStmt = $this->db->prepare($sql);
                    $sqlStmt->execute($params);

                    while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                        $this->ws_pickUpLocations[$row['LOCATION_ID']]
                            = utf8_encode($row['LOCATION_NAME']);
                    }
                } catch (PDOException $e) {
                    throw new ILSException($e->getMessage());
                }

                $result = parent::getPickupLocations($patron, $holdDetails);
            } catch (\Exception $e) {
                $this->ws_pickUpLocations = $savePickupLocations;
                throw $e;
            }
            $this->ws_pickUpLocations = $savePickupLocations;
            return $result;
        }
        return parent::getPickupLocations($patron, $holdDetails);
    }

    /**
     * Check Account Blocks
     *
     * Checks if a user has any blocks against their account which may prevent them
     * performing certain operations
     *
     * @param string $patronId A Patron ID
     *
     * @return mixed           A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function checkAccountBlocks($patronId)
    {
        $cacheKey = "blocks_$patronId";
        $blockReason = $this->getCachedData($cacheKey);
        if (null === $blockReason) {
            // Build Hierarchy
            $hierarchy = [
                "patron" =>  $patronId,
                "patronStatus" => "blocks"
            ];

            // Add Required Params
            $params = [
                "patron_homedb" => $this->ws_patronHomeUbId,
                "view" => "full"
            ];

            $blockReason = [];

            $blocks = $this->makeRequest($hierarchy, $params);
            if ($blocks) {
                $borrowingBlocks = $blocks->xpath(
                    "//blocks/institution[@id='LOCAL']/borrowingBlock"
                );
                if (count($borrowingBlocks)) {
                    $blockReason[] = $this->translate('Borrowing Block Message');
                }
                foreach ($borrowingBlocks as $borrowBlock) {
                    $code = (int)$borrowBlock->blockCode;
                    $reason = "Borrowing Block Voyager Reason $code";
                    $params = [];
                    if ($code == 19) {
                        $params = [
                            '%%blockCount%%' => $borrowBlock->blockCount,
                            '%%blockLimit%%' => $borrowBlock->blockLimit
                        ];
                    }
                    $translated = $this->translate($reason, $params);
                    if ($reason !== $translated) {
                        $reason = $translated;
                        $blockReason[] = $reason;
                    }
                }
            }
            $this->putCachedData($cacheKey, $blockReason);
        }
        return empty($blockReason) ? false : $blockReason;
    }

    /**
     * A helper function that retrieves UB request details for ILL and caches them
     * for a short while for faster access.
     *
     * @param string $id     BIB id
     * @param array  $patron Patron
     *
     * @return bool|array False if UB request is not available or an array
     * of details on success
     */
    protected function getUBRequestDetails($id, $patron)
    {
        $result = parent::getUBRequestDetails($id, $patron);
        if (is_array($result)) {
            $result['libraries'] = $this->filterAllowedUBPickupLibraries(
                $result['libraries'], $patron
            );
        }
        return $result;
    }

    /**
     * Utility function for filtering the given UB pickup libraries
     * based on allowed pickup locations within the users local library.
     * If allowed pickup locations are configured, only users local library
     * is returned. If not, no filtering is done.
     *
     * @param array $libraries Array of libraries
     * @param array $patron    Patron
     *
     * @return bool|array False if request not allowed, or an array of
     * allowed pickup libraries.
     */
    protected function filterAllowedUBPickupLibraries($libraries, $patron)
    {
        if (!$allowedIDs = $this->getAllowedUBPickupLocationIDs($patron)) {
            return $libraries;
        }

        if (!$patronHomeUBID = $this->getPatronHomeUBID($patron)) {
            return false;
        }

        $allowedLibraries = [];
        foreach ($libraries as $library) {
            if ($patronHomeUBID === $library['id']) {
                $allowedLibraries[] = $library;
            }
        }

        return $allowedLibraries;
    }

    /**
     * Utility function for filtering the given UB locations
     * based on allowed pickup locations within the users local library.
     * If allowed pickup locations are not configured, no filtering is done.
     *
     * @param array $locations Array of locations
     * @param array $patron    Patron
     *
     * @return array array of allowed pickup locations.
     */
    protected function filterAllowedUBPickupLocations($locations, $patron)
    {
        if (!$allowedIDs = $this->getAllowedUBPickupLocationIDs($patron)) {
            return $locations;
        }

        $allowedLocations = [];
        foreach ($locations as $location) {
            if (in_array($location['id'], $allowedIDs)) {
                $allowedLocations[] = $location;
            }
        }

        return $allowedLocations;
    }

    /**
     * Return list of allowed UB pickup locations
     * within the users home local library.
     *
     * @param array $patron Patron
     *
     * @return bool|array False if allowed pickup locations are
     * not configured, or array of location codes
     */
    protected function getAllowedUBPickupLocationIDs($patron)
    {
        if (!($config = $this->getPatronDriverConfig($patron))) {
            return false;
        }

        if (!isset($config['ILLRequests']['pickUpLocations'])) {
            return false;
        }

        return explode(':', $config['ILLRequests']['pickUpLocations']);
    }

    /**
     * Return configuration for the patron's active library card driver.
     *
     * @param array $patron Patron
     *
     * @return bool|array False if no driver configuration was found,
     * or configuration.
     */
    protected function getPatronDriverConfig($patron)
    {
        if (null === $this->configReader) {
            return false;
        }
        if (isset($patron['cat_username'])
            && ($pos = strpos($patron['cat_username'], '.')) > 0
        ) {
            $source = substr($patron['cat_username'], 0, $pos);

            $config = $this->configReader->get("VoyagerRestful_$source");
            if (!is_object($config) || $config->count() == 0) {
                $config = $this->configReader->get($source);
            }
            return is_object($config) ? $config->toArray() : [];
        }

        return false;
    }

    /**
     * Return patron's local library UB id.
     *
     * @param array $patron Patron
     *
     * @return bool|string False if request not allowed, or UB id
     */
    protected function getPatronHomeUBID($patron)
    {
        if (!$config = $this->getPatronDriverConfig($patron)) {
            return false;
        }

        if (!isset($config['WebServices']['patronHomeUbId'])) {
            return false;
        }

        return $config['WebServices']['patronHomeUbId'];
    }

    /**
     * Make Request
     *
     * Makes a request to the Voyager Restful API
     *
     * @param array  $hierarchy Array of key-value pairs to embed in the URL path of
     * the request (set value to false to inject a non-paired value).
     * @param array  $params    A keyed array of query data
     * @param string $mode      The http request method to use (Default of GET)
     * @param string $xml       An optional XML string to send to the API
     *
     * @throws ILSException
     * @return obj  A Simple XML Object loaded with the xml data returned by the API
     */
    protected function makeRequest($hierarchy, $params = false, $mode = "GET",
        $xml = false
    ) {
        $startTime = microtime(true);
        $result = parent::makeRequest($hierarchy, $params, $mode, $xml);
        if (!empty($this->config['Debug']['durationLogPrefix'])) {
            list(, $caller) = debug_backtrace(false);
            file_put_contents(
                $this->config['Debug']['durationLogPrefix'] . '_'
                . $caller['function'] . '_ws.log',
                date('Y-m-d H:i:s ') . round(microtime(true) - $startTime, 4) . "\n",
                FILE_APPEND
            );
        }
        return $result;
    }
}
