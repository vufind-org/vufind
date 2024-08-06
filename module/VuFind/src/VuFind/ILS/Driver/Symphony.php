<?php

/**
 * Symphony Web Services (symws) ILS Driver
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
 * @author   Steven Hild <sjhild@wm.edu>
 * @author   Michael Gillen <mlgillen@sfasu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use Laminas\Log\LoggerAwareInterface;
use SoapClient;
use SoapFault;
use SoapHeader;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Exception\ILS as ILSException;
use VuFind\Record\Loader;

use function count;
use function in_array;
use function is_array;

/**
 * Symphony Web Services (symws) ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Steven Hild <sjhild@wm.edu>
 * @author   Michael Gillen <mlgillen@sfasu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Symphony extends AbstractBase implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Cache for policy information
     *
     * @var object
     */
    protected $policyCache = false;

    /**
     * Policy information
     *
     * @var array
     */
    protected $policies;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param Loader       $loader       Record loader
     * @param CacheManager $cacheManager Cache manager (optional)
     */
    public function __construct(Loader $loader, CacheManager $cacheManager = null)
    {
        $this->recordLoader = $loader;
        $this->cacheManager = $cacheManager;
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
        // Merge in defaults.
        $this->config += [
            'WebServices' => [],
            'PolicyCache' => [],
            'LibraryFilter' => [],
            'MarcHoldings' => [],
            '999Holdings' => [],
            'Behaviors' => [],
        ];

        $this->config['WebServices'] += [
            'clientID' => 'VuFind',
            'baseURL' => 'http://localhost:8080/symws',
            'soapOptions' => [],
        ];

        $this->config['PolicyCache'] += [
            'backend' => 'file',
            'backendOptions' => [],
            'frontendOptions' => [],
        ];

        $this->config['PolicyCache']['frontendOptions'] += [
            'automatic_serialization' => true,
            'lifetime' => null,
        ];

        $this->config['LibraryFilter'] += [
            'include_only' => [],
            'exclude' => [],
        ];

        $this->config['999Holdings'] += [
            'entry_number' => 999,
            'mode' => 'off', // also off, failover
        ];

        $this->config['Behaviors'] += [
            'showBaseCallNumber' => true,
            'showAccountLogin' => true,
            'showStaffNotes' => true,
            'showFeeType' => 'ALL_FEES',
            'usernameField' => 'userID',
            'userProfileGroupField' => 'USER_PROFILE_ID',
        ];

        // Initialize cache manager.
        if (
            isset($this->config['PolicyCache']['type'])
            && $this->cacheManager
        ) {
            $this->policyCache = $this->cacheManager
                ->getCache($this->config['PolicyCache']['type']);
        }
    }

    /**
     * Return a SoapClient for the specified SymWS service.
     *
     * SoapClient instantiation fetches and parses remote files,
     * so this method instantiates SoapClients lazily and keeps them around
     * so that they can be reused for multiple requests.
     *
     * @param string $service The name of the SymWS service
     *
     * @return object The SoapClient object for the specified service
     */
    protected function getSoapClient($service)
    {
        static $soapClients = [];

        if (!isset($soapClients[$service])) {
            try {
                $soapClients[$service] = new SoapClient(
                    $this->config['WebServices']['baseURL'] . "/soap/$service?wsdl",
                    $this->config['WebServices']['soapOptions']
                );
            } catch (SoapFault $e) {
                // This SoapFault may have happened because, e.g., PHP's
                // SoapClient won't load SymWS 3.1's Patron service WSDL.
                // However, we can't check the SymWS version if this fault
                // happened with the Standard service (which contains the
                // 'version' operation).
                if ($service != 'standard') {
                    $this->checkSymwsVersion();
                }

                throw $e;
            }
        }

        return $soapClients[$service];
    }

    /**
     * Return a SoapHeader for the specified login and password.
     *
     * @param mixed $login    The login account name if logging in, otherwise null
     * @param mixed $password The login password if logging in, otherwise null
     * @param bool  $reset    Whether or not the session token should be reset
     *
     * @return object The SoapHeader object
     */
    protected function getSoapHeader(
        $login = null,
        $password = null,
        $reset = false
    ) {
        $data = ['clientID' => $this->config['WebServices']['clientID']];
        if (null !== $login) {
            $data['sessionToken']
                = $this->getSessionToken($login, $password, $reset);
        }
        return new SoapHeader(
            'http://www.sirsidynix.com/xmlns/common/header',
            'SdHeader',
            $data
        );
    }

    /**
     * Return a SymWS session token for given credentials.
     *
     * To avoid needing to repeatedly log in the same user,
     * cache acquired session tokens by the credentials provided.
     * If the cached session token is expired or otherwise defective,
     * the caller can use the $reset parameter.
     *
     * @param string  $login    The login account name
     * @param ?string $password The login password, or null for no password
     * @param bool    $reset    If true, replace any currently cached token
     *
     * @return string The session token
     */
    protected function getSessionToken(
        string $login,
        ?string $password = null,
        bool $reset = false
    ) {
        static $sessionTokens = [];

        // If we keyed only by $login, we might mistakenly retrieve a valid
        // session token when provided with an invalid password.
        // We hash the credentials to reduce the potential for
        // incompatibilities with key limitations of whatever cache backend
        // an administrator might elect to use for session tokens,
        // and though more expensive, we use a secure hash because
        // what we're hashing contains a password.
        $key = hash('sha256', "$login:$password");

        if (!isset($sessionTokens[$key]) || $reset) {
            if (!$reset && $token = $_SESSION['symws']['session'][$key]) {
                $sessionTokens[$key] = $token;
            } else {
                $params = ['login' => $login];
                if (isset($password)) {
                    $params['password'] = $password;
                }

                $response = $this->makeRequest('security', 'loginUser', $params);
                $sessionTokens[$key] = $response->sessionToken;
                $_SESSION['symws']['session'] = $sessionTokens;
            }
        }

        return $sessionTokens[$key];
    }

    /**
     * Make a request to Symphony Web Services using the SOAP protocol.
     *
     * @param string $service    the SymWS service name
     * @param string $operation  the SymWS operation name
     * @param array  $parameters the request parameters for the operation
     * @param array  $options    An associative array of additional options:
     * - 'login': login to use for the operation; omit for configured default
     * credentials or anonymous
     * - 'password': password associated with login; omit for no password
     * - 'header': SoapHeader to use for the request; omit to handle automatically
     *
     * @return mixed the result of the SOAP call
     */
    protected function makeRequest(
        $service,
        $operation,
        $parameters = [],
        $options = []
    ) {
        // If provided, use the SoapHeader and skip the rest of makeRequest().
        if (isset($options['header'])) {
            return $this->getSoapClient($service)->soapCall(
                $operation,
                $parameters,
                null,
                [$options['header']]
            );
        }

        /* Determine what credentials, if any, to use for the SymWS request.
         *
         * If a login and password are specified in $options, use them.
         * If not, for any operation not exempted from SymWS'
         * "Always Require Authentication" option, use the login and password
         * specified in the configuration. Otherwise, proceed anonymously.
         */
        if (isset($options['login'])) {
            $login    = $options['login'];
            $password = $options['password'] ?? null;
        } elseif (
            isset($options['WebServices']['login'])
            && !in_array(
                $operation,
                ['isRestrictedAccess', 'license', 'loginUser', 'version']
            )
        ) {
            $login    = $this->config['WebServices']['login'];
            $password = $this->config['WebServices']['password'] ?? null;
        } else {
            $login    = null;
            $password = null;
        }

        // Attempt the request.
        $soapClient = $this->getSoapClient($service);
        try {
            $header = $this->getSoapHeader($login, $password);
            $soapClient->__setSoapHeaders($header);
            return $soapClient->$operation($parameters);
        } catch (SoapFault $e) {
            $timeoutException = 'ns0:com.sirsidynix.symws.service.'
                . 'exceptions.SecurityServiceException.sessionTimedOut';
            if ($e->faultcode == $timeoutException) {
                // The SoapHeader's session has expired. Tell
                // getSoapHeader() to have a new one established.
                $header = $this->getSoapHeader($login, $password, true);
                // Try the request again with the new SoapHeader.
                $soapClient->__setSoapHeaders($header);
                return $soapClient->$operation($parameters);
            } elseif ($operation == 'logoutUser') {
                return null;
            } elseif ($operation == 'lookupSessionInfo') {
                // lookupSessionInfo did not exist in SymWS 3.0.
                $this->checkSymwsVersion();
                throw $e;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Check the SymWS version, and throw an Exception if it's too old.
     *
     * Always checking at initialization would result in many unnecessary
     * roundtrips with the SymWS server, so this method is intended to be
     * called when an error happens that might be correctable by upgrading
     * SymWS. In such a case it will produce a potentially more helpful error
     * message than the original error would have.
     *
     * @throws \Exception if the SymWS version is too old
     * @return void
     */
    protected function checkSymwsVersion()
    {
        $resp = $this->makeRequest('standard', 'version', []);
        foreach ($resp->version as $v) {
            if ($v->product == 'SYM-WS') {
                if (version_compare($v->version, 'v3.2', '<')) {
                    // ILSException didn't seem to produce an error message
                    // when checkSymwsVersion() was called from the catch
                    // block in makeRequest().
                    throw new \Exception('SymWS version too old');
                }
                break;
            }
        }
    }

    /**
     * Get Statuses from 999 Holdings Marc Tag
     *
     * Protected support method for parsing status info from the marc record
     *
     * @param array $ids The array of record ids to retrieve the item info for
     *
     * @return array An associative array of items
     */
    protected function getStatuses999Holdings($ids)
    {
        $items   = [];
        $marcMap = [
            'call number'            => 'marc|a',
            'copy number'            => 'marc|c',
            'barcode number'         => 'marc|i',
            'library'                => 'marc|m',
            'current location'       => 'marc|k',
            'home location'          => 'marc|l',
            'item type'              => 'marc|t',
            'circulate flag'         => 'marc|r',
        ];

        $entryNumber = $this->config['999Holdings']['entry_number'];

        $records = $this->recordLoader->loadBatch($ids);
        foreach ($records as $record) {
            $results = $record->getFormattedMarcDetails($entryNumber, $marcMap);
            foreach ($results as $result) {
                $library  = $this->translatePolicyID('LIBR', $result['library']);
                $home_loc
                    = $this->translatePolicyID('LOCN', $result['home location']);

                $curr_loc = isset($result['current location']) ?
                    $this->translatePolicyID('LOCN', $result['current location']) :
                    $home_loc;

                $available = (empty($curr_loc) || $curr_loc == $home_loc)
                    || $result['circulate flag'] == 'Y';
                $callnumber = $result['call number'];
                $location   = $library . ' - ' . ($available && !empty($curr_loc)
                    ? $curr_loc : $home_loc);

                $material = $this->translatePolicyID('ITYP', $result['item type']);

                $items[$result['id']][] = [
                    'id' => $result['id'],
                    'availability' => $available,
                    'status' => $curr_loc,
                    'location' => $location,
                    'reserve' => null,
                    'callnumber' => $callnumber,
                    'duedate' => null,
                    'returnDate' => false,
                    'number' => $result['copy number'],
                    'barcode' => $result['barcode number'],
                    'item_id' => $result['barcode number'],
                    'library' => $library,
                    'material' => $material,
                ];
            }
        }
        return $items;
    }

    /**
     * Look up title info
     *
     * Protected support method for parsing the call info into items.
     *
     * @param array $ids The array of record ids to retrieve the item info for
     *
     * @return object Result of the "lookupTitleInfo" call to the standard service
     */
    protected function lookupTitleInfo($ids)
    {
        $ids = is_array($ids) ? $ids : [$ids];

        // SymWS ignores invalid titleIDs instead of rejecting them, so
        // checking ahead of time for obviously invalid titleIDs is a useful
        // sanity check (which has a good chance of catching, for example,
        // the use of something other than catkeys as record IDs).
        $invalid = preg_grep('/^[1-9][0-9]*$/', $ids, PREG_GREP_INVERT);
        if (count($invalid) > 0) {
            $titleIDs = count($invalid) == 1 ? 'titleID' : 'titleIDs';
            $msg = "Invalid $titleIDs: " . implode(', ', $invalid);
            throw new ILSException($msg);
        }

        // Prepare $params array for makeRequest().
        $params = [
            'titleID' => $ids,
            'includeAvailabilityInfo' => 'true',
            'includeItemInfo' => 'true',
            'includeBoundTogether' => 'true',
            'includeOrderInfo' => 'true',
        ];

        // If the driver is configured to populate holdings_text_fields
        // with MFHD, also request MARC holdings information from SymWS.
        if (count(array_filter($this->config['MarcHoldings'])) > 0) {
            $params['includeMarcHoldings'] = 'true';
            // With neither marcEntryFilter nor marcEntryID, or with
            // marcEntryFilter NONE, SymWS won't return MarcHoldingsInfo,
            // and there doesn't seem to be another option for marcEntryFilter
            // that returns just MarcHoldingsInfo without BibliographicInfo.
            // So we filter BibliographicInfo for an unlikely entry.
            $params['marcEntryID'] = '999';
        }

        // If only one library is being exclusively included,
        // filtering can be done within Web Services.
        if (count($this->config['LibraryFilter']['include_only']) == 1) {
            $params['libraryFilter']
                = $this->config['LibraryFilter']['include_only'][0];
        }

        return $this->makeRequest('standard', 'lookupTitleInfo', $params);
    }

    /**
     * Determine if a library is excluded by LibraryFilter configuration.
     *
     * @param string $libraryID the ID of the library in question
     *
     * @return bool             true if excluded, false if not
     */
    protected function libraryIsFilteredOut($libraryID)
    {
        $notIncluded = !empty($this->config['LibraryFilter']['include_only'])
            && !in_array(
                $libraryID,
                $this->config['LibraryFilter']['include_only']
            );
        $excluded = in_array(
            $libraryID,
            $this->config['LibraryFilter']['exclude']
        );
        return $notIncluded || $excluded;
    }

    /**
     * Parse Call Info
     *
     * Protected support method for parsing the call info into items.
     *
     * @param object $callInfos   The call info of the title
     * @param int    $titleID     The catalog key of the title in the catalog
     * @param bool   $is_holdable Whether or not the title is holdable
     * @param int    $bound_in    The ID of the parent title
     *
     * @return array An array of items, an empty array otherwise
     */
    protected function parseCallInfo(
        $callInfos,
        $titleID,
        $is_holdable = false,
        $bound_in = null
    ) {
        $items = [];

        $callInfos = is_array($callInfos) ? $callInfos : [$callInfos];

        foreach ($callInfos as $callInfo) {
            $libraryID = $callInfo->libraryID;

            if ($this->libraryIsFilteredOut($libraryID)) {
                continue;
            }

            if (!isset($callInfo->ItemInfo)) {
                continue; // no items!
            }

            $library = $this->translatePolicyID('LIBR', $libraryID);
            // ItemInfo does not include copy numbers, so we generate them under
            // the assumption that items are being listed in order.
            $copyNumber = 0;

            $itemInfos = is_array($callInfo->ItemInfo)
                ? $callInfo->ItemInfo
                : [$callInfo->ItemInfo];
            foreach ($itemInfos as $itemInfo) {
                $in_transit = isset($itemInfo->transitReason);
                $currentLocation = $this->translatePolicyID(
                    'LOCN',
                    $itemInfo->currentLocationID
                );
                $homeLocation = $this->translatePolicyID(
                    'LOCN',
                    $itemInfo->homeLocationID
                );

                /* I would like to be able to write
                 *      $available = $itemInfo->numberOfCharges == 0;
                 * but SymWS does not appear to provide that information.
                 *
                 * SymWS *will* tell me if an item is "chargeable",
                 * but this is inadequate because reference and internet
                 * materials may be available, but not chargeable.
                 *
                 * I can't rely on the presence of dueDate, because
                 * although "dueDate is only returned if the item is currently
                 * checked out", the converse is not true: due dates of NEVER
                 * are simply omitted.
                 *
                 * TitleAvailabilityInfo would be more helpful per item;
                 * as it is, it tells me only number available and library.
                 *
                 * Hence the following criterion: an available item must not
                 * be in-transit, and if it, like exhibits and reserves,
                 * is not in its home location, it must be chargeable.
                 */
                $available = !$in_transit &&
                    ($itemInfo->currentLocationID == $itemInfo->homeLocationID
                    || $itemInfo->chargeable);

                /* Statuses like "Checked out" and "Missing" are represented
                 * by an item's current location. */
                $status = $in_transit ? 'In transit' : $currentLocation;

                /* "$library - $location" may be misleading for items that are
                 * on reserve at a reserve desk in another library, so for
                 * items on reserve, report location as just the reserve desk.
                 */
                if (isset($itemInfo->reserveCollectionID)) {
                    $reserveDeskID = $itemInfo->reserveCollectionID;
                    $location = $this->translatePolicyID('RSRV', $reserveDeskID);
                } else {
                    /* If an item is available, its current location should be
                     * reported as its location. */
                    $location = $available ? $currentLocation : $homeLocation;

                    /* Locations may be shared among libraries, so unless
                     * holdings are being filtered to just one library,
                     * it is insufficient to provide just the location
                     * description as the "location."
                     */
                    if (count($this->config['LibraryFilter']['include_only']) != 1) {
                        $location = "$library - $location";
                    }
                }

                $material = $this->translatePolicyID('ITYP', $itemInfo->itemTypeID);

                $duedate = isset($itemInfo->dueDate) ?
                        date('F j, Y', strtotime($itemInfo->dueDate)) : null;
                $duedate = isset($itemInfo->recallDueDate) ?
                        date('F j, Y', strtotime($itemInfo->recallDueDate)) :
                        $duedate;

                $requests_placed = $itemInfo->numberOfHolds ?? 0;

                // Handle item notes
                $notes = [];

                if (isset($itemInfo->publicNote)) {
                    $notes[] = $itemInfo->publicNote;
                }

                if (
                    isset($itemInfo->staffNote)
                    && $this->config['Behaviors']['showStaffNotes']
                ) {
                    $notes[] = $itemInfo->staffNote;
                }

                $transitSourceLibrary
                    = isset($itemInfo->transitSourceLibraryID)
                    ? $this->translatePolicyID(
                        'LIBR',
                        $itemInfo->transitSourceLibraryID
                    )
                    : null;

                $transitDestinationLibrary
                    = isset($itemInfo->transitDestinationLibraryID)
                        ? $this->translatePolicyID(
                            'LIBR',
                            $itemInfo->transitDestinationLibraryID
                        )
                        : null;

                $transitReason = $itemInfo->transitReason ?? null;

                $transitDate = isset($itemInfo->transitDate) ?
                     date('F j, Y', strtotime($itemInfo->transitDate)) : null;

                $holdtype = $available ? 'hold' : 'recall';

                $items[] = [
                    'id' => $titleID,
                    'availability' => $available,
                    'status' => $status,
                    'location' => $location,
                    'reserve' => isset($itemInfo->reserveCollectionID)
                        ? 'Y' : 'N',
                    'callnumber' => $callInfo->callNumber,
                    'duedate' => $duedate,
                    'returnDate' => false, // Not returned by symws
                    'number' => ++$copyNumber,
                    'requests_placed' => $requests_placed,
                    'barcode' => $itemInfo->itemID,
                    'notes' => $notes,
                    'summary' => [],
                    'is_holdable' => $is_holdable,
                    'holdtype' => $holdtype,
                    'addLink' => $is_holdable,
                    'item_id' => $itemInfo->itemID,

                    // The fields below are non-standard and
                    // should be added to your holdings.tpl
                    // RecordDriver template to be utilized.
                    'library' => $library,
                    'material' => $material,
                    'bound_in' => $bound_in,
                    //'bound_in_title' => ,
                    'transit_source_library' =>
                        $transitSourceLibrary,
                    'transit_destination_library' =>
                        $transitDestinationLibrary,
                    'transit_reason' => $transitReason,
                    'transit_date' => $transitDate,
                ];
            }
        }
        return $items;
    }

    /**
     * Parse Bound With Link Info
     *
     * Protected support method for parsing bound with link information.
     *
     * @param object $boundwithLinkInfos The boundwithLinkInfos object of the title
     * @param int    $ckey               The catalog key of the title in the catalog
     *
     * @return array An array of parseCallInfo() return values on success,
     * an empty array otherwise.
     */
    protected function parseBoundwithLinkInfo($boundwithLinkInfos, $ckey)
    {
        $items = [];

        $boundwithLinkInfos = is_array($boundwithLinkInfos)
            ? $boundwithLinkInfos
            : [$boundwithLinkInfos];

        foreach ($boundwithLinkInfos as $boundwithLinkInfo) {
            // Ignore BoundwithLinkInfos which do not refer to parents
            // or which refer to the record we're already looking at.
            if (
                !$boundwithLinkInfo->linkedAsParent
                || $boundwithLinkInfo->linkedTitle->titleID == $ckey
            ) {
                continue;
            }

            // Fetch the record that contains the parent CallInfo,
            // identify the CallInfo by matching itemIDs,
            // and parse that CallInfo in the items array.
            $parent_ckey   = $boundwithLinkInfo->linkedTitle->titleID;
            $linked_itemID = $boundwithLinkInfo->itemID;
            $resp          = $this->lookupTitleInfo($parent_ckey);
            $is_holdable   = $resp->TitleInfo->TitleAvailabilityInfo->holdable;

            $callInfos = is_array($resp->TitleInfo->CallInfo)
                ? $resp->TitleInfo->CallInfo
                : [$resp->TitleInfo->CallInfo];

            foreach ($callInfos as $callInfo) {
                $itemInfos = is_array($callInfo->ItemInfo)
                    ? $callInfo->ItemInfo
                    : [$callInfo->ItemInfo];
                foreach ($itemInfos as $itemInfo) {
                    if ($itemInfo->itemID == $linked_itemID) {
                        $items += $this->parseCallInfo(
                            $callInfo,
                            $ckey,
                            $is_holdable,
                            $parent_ckey
                        );
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Parse Title Order Info
     *
     * Protected support method for parsing order info.
     *
     * @param object $titleOrderInfos The titleOrderInfo object of the title
     * @param int    $titleID         The ID of the title in the catalog
     *
     * @return array An array of items that are on order, an empty array otherwise.
     */
    protected function parseTitleOrderInfo($titleOrderInfos, $titleID)
    {
        $items = [];

        $titleOrderInfos = is_array($titleOrderInfos)
            ? $titleOrderInfos : [$titleOrderInfos];

        foreach ($titleOrderInfos as $titleOrderInfo) {
            $library_id = $titleOrderInfo->orderLibraryID;

            /* Allow returned holdings information to be
             * limited to a specified list of library names. */
            if (
                isset($this->config['holdings']['include_libraries'])
                && !in_array(
                    $library_id,
                    $this->config['holdings']['include_libraries']
                )
            ) {
                continue;
            }

            /* Allow libraries to be excluded by name
             * from returned holdings information. */
            if (
                isset($this->config['holdings']['exclude_libraries'])
                && in_array(
                    $library_id,
                    $this->config['holdings']['exclude_libraries']
                )
            ) {
                continue;
            }

            $nr_copies = $titleOrderInfo->copiesOrdered;
            $library   = $this->translatePolicyID('LIBR', $library_id);

            $statuses = [];
            if (!empty($titleOrderInfo->orderDateReceived)) {
                $statuses[] = "Received $titleOrderInfo->orderDateReceived";
            }

            if (!empty($titleOrderInfo->orderNote)) {
                $statuses[] = $titleOrderInfo->orderNote;
            }

            if (!empty($titleOrderInfo->volumesOrdered)) {
                $statuses[] = $titleOrderInfo->volumesOrdered;
            }

            for ($i = 1; $i <= $nr_copies; ++$i) {
                $items[] = [
                    'id' => $titleID,
                    'availability' => false,
                    'status' => implode('; ', $statuses),
                    'location' => "On order for $library",
                    'callnumber' => null,
                    'duedate' => null,
                    'reserve' => 'N',
                    'number' => $i,
                    'barcode' => true,
                    'offsite' => $library_id == 'OFFSITE',
                ];
            }
        }
        return $items;
    }

    /**
     * Parse MarcHoldingInfo into VuFind items.
     *
     * @param object $marcHoldingsInfos MarcHoldingInfo, from TitleInfo
     * @param int    $titleID           The catalog key of the title record
     *
     * @return array  an array (possibly empty) of VuFind items
     */
    protected function parseMarcHoldingsInfo($marcHoldingsInfos, $titleID)
    {
        $items = [];
        $marcHoldingsInfos = is_array($marcHoldingsInfos)
            ? $marcHoldingsInfos
            : [$marcHoldingsInfos];

        foreach ($marcHoldingsInfos as $marcHoldingsInfo) {
            $libraryID = $marcHoldingsInfo->holdingLibraryID;
            if ($this->libraryIsFilteredOut($libraryID)) {
                continue;
            }

            $marcEntryInfos = is_array($marcHoldingsInfo->MarcEntryInfo)
                ? $marcHoldingsInfo->MarcEntryInfo
                : [$marcHoldingsInfo->MarcEntryInfo];
            $item = [];

            foreach ($marcEntryInfos as $marcEntryInfo) {
                foreach ($this->config['MarcHoldings'] as $textfield => $spec) {
                    if (in_array($marcEntryInfo->entryID, $spec)) {
                        $item[$textfield][] = $marcEntryInfo->text;
                    }
                }
            }

            if (!empty($item)) {
                $items[] = $item + [
                    'id' => $titleID,
                    'location' => $this->translatePolicyID('LIBR', $libraryID),
                ];
            }
        }

        return $items;
    }

    /**
     * Get Live Statuses
     *
     * Protected support method for retrieving a list of item statuses from symws.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array An array of parseCallInfo() return values on success,
     * an empty array otherwise.
     */
    protected function getLiveStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[$id] = [];
        }

        /* In Symphony, a title record has at least one "callnum" record,
         * to which are attached zero or more item records. This structure
         * is reflected in the LookupTitleInfoResponse, which contains
         * one or more TitleInfo elements, which contain one or more
         * CallInfo elements, which contain zero or more ItemInfo elements.
         */
        $response   = $this->lookupTitleInfo($ids);
        $titleInfos = is_array($response->TitleInfo)
            ? $response->TitleInfo
            : [$response->TitleInfo];

        foreach ($titleInfos as $titleInfo) {
            $ckey        = $titleInfo->titleID;
            $is_holdable = $titleInfo->TitleAvailabilityInfo->holdable;

            /* In order to have only one item record per item regardless of
             * how many titles are bound within, Symphony handles titles bound
             * with others by linking callnum records in parent-children
             * relationships, where only the parent callnum has item records
             * attached to it. The CallInfo element of a child callnum
             * does not contain any ItemInfo elements, so we must locate the
             * parent CallInfo using BoundwithLinkInfo, in order to parse
             * the ItemInfo.
             */
            if (isset($titleInfo->BoundwithLinkInfo)) {
                $items[$ckey] = $this->parseBoundwithLinkInfo(
                    $titleInfo->BoundwithLinkInfo,
                    $ckey
                );
            }

            /* Callnums that are not bound-with, or are bound-with parents,
             * have item records and can be parsed directly. Since bound-with
             * children do not have item records, parsing them should have no
             * effect. */
            if (isset($titleInfo->CallInfo)) {
                $items[$ckey] = array_merge(
                    $items[$ckey],
                    $this->parseCallInfo($titleInfo->CallInfo, $ckey, $is_holdable)
                );
            }

            /* Copies on order do not have item records,
             * so we make some pseudo-items for VuFind. */
            if (isset($titleInfo->TitleOrderInfo)) {
                $items[$ckey] = array_merge(
                    $items[$ckey],
                    $this->parseTitleOrderInfo($titleInfo->TitleOrderInfo, $ckey)
                );
            }

            /* MARC holdings records are associated with title records rather
             * than item records, so we make pseudo-items for VuFind. */
            if (isset($titleInfo->MarcHoldingsInfo)) {
                $items[$ckey] = array_merge(
                    $items[$ckey],
                    $this->parseMarcHoldingsInfo($titleInfo->MarcHoldingsInfo, $ckey)
                );
            }
        }
        return $items;
    }

    /**
     * Translate a Symphony policy ID into a policy description
     * (e.g. VIDEO-COLL => Videorecording Collection).
     *
     * In order to minimize roundtrips with the SymWS server,
     * we fetch more than was requested and cache the results.
     * At time of writing, SymWS did not appear to
     * support retrieving policies of multiple types simultaneously,
     * so we currently fetch only all policies of one type at a time.
     *
     * @param string $policyType The policy type, e.g. LOCN or LIBR.
     * @param string $policyID   The policy ID, e.g. VIDEO-COLL or SWEM.
     *
     * @return string The policy description, if found, or the policy ID, if not.
     *
     * @todo policy description override
     */
    protected function translatePolicyID($policyType, $policyID)
    {
        $policyType = strtoupper($policyType);
        $policyID   = strtoupper($policyID);
        $policyList = $this->getPolicyList($policyType);

        return $policyList[$policyID] ?? $policyID;
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
        $statuses = $this->getStatuses([$id]);
        return $statuses[$id] ?? [];
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
        if ($this->config['999Holdings']['mode']) {
            return $this->getStatuses999Holdings($ids);
        } else {
            return $this->getLiveStatuses($ids);
        }
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
     */
    public function getPurchaseHistory($id)
    {
        return [];
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
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $usernameField = $this->config['Behaviors']['usernameField'];

        $patron = [
            'cat_username' => $username,
            'cat_password' => $password,
        ];

        try {
            $resp = $this->makeRequest(
                'patron',
                'lookupMyAccountInfo',
                [
                    'includePatronInfo' => 'true',
                    'includePatronAddressInfo' => 'true',
                ],
                [
                    'login' => $username,
                    'password' => $password,
                ]
            );
        } catch (SoapFault $e) {
            $unableToLogin = 'ns0:com.sirsidynix.symws.service.'
                . 'exceptions.SecurityServiceException.unableToLogin';
            if ($e->faultcode == $unableToLogin) {
                return null;
            } else {
                throw $e;
            }
        }

        $patron['id']      = $resp->patronInfo->$usernameField;
        $patron['library'] = $resp->patronInfo->patronLibraryID;

        $regEx = '/([^,]*),\s([^\s]*)/';
        if (preg_match($regEx, $resp->patronInfo->displayName, $matches)) {
            $patron['firstname'] = $matches[2];
            $patron['lastname']  = $matches[1];
        }

        // There may be an email address in any of three numbered addresses,
        // so we search each one until we find an email address,
        // starting with the one marked primary.
        $addrinfo_check_order = ['1','2','3'];
        if (isset($resp->patronAddressInfo->primaryAddress)) {
            $primary_addr_n = $resp->patronAddressInfo->primaryAddress;
            array_unshift($addrinfo_check_order, $primary_addr_n);
        }
        foreach ($addrinfo_check_order as $n) {
            $AddressNInfo = "Address{$n}Info";
            if (isset($resp->patronAddressInfo->$AddressNInfo)) {
                $addrinfos = is_array($resp->patronAddressInfo->$AddressNInfo)
                    ? $resp->patronAddressInfo->$AddressNInfo
                    : [$resp->patronAddressInfo->$AddressNInfo];
                foreach ($addrinfos as $addrinfo) {
                    if (
                        $addrinfo->addressPolicyID == 'EMAIL'
                        && !empty($addrinfo->addressValue)
                    ) {
                        $patron['email'] = $addrinfo->addressValue;
                        break;
                    }
                }
            }
        }

        // @TODO: major, college

        return $patron;
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
        try {
            $userProfileGroupField
                = $this->config['Behaviors']['userProfileGroupField'];

            $options = [
                'includePatronInfo' => 'true',
                'includePatronAddressInfo' => 'true',
                'includePatronStatusInfo' => 'true',
                'includeUserGroupInfo'     => 'true',
            ];

            $result = $this->makeRequest(
                'patron',
                'lookupMyAccountInfo',
                $options,
                [
                    'login' => $patron['cat_username'],
                    'password' => $patron['cat_password'],
                ]
            );

            $primaryAddress = $result->patronAddressInfo->primaryAddress;

            $primaryAddressInfo = 'Address' . $primaryAddress . 'Info';

            $addressInfo = $result->patronAddressInfo->$primaryAddressInfo;
            $address1    = $addressInfo[0]->addressValue;
            $address2    = $addressInfo[1]->addressValue;
            $zip         = $addressInfo[2]->addressValue;
            $phone       = $addressInfo[3]->addressValue;

            if (strcmp($userProfileGroupField, 'GROUP_ID') == 0) {
                $group = $result->patronInfo->groupID;
            } elseif (strcmp($userProfileGroupField, 'USER_PROFILE_ID') == 0) {
                $group = $this->makeRequest(
                    'security',
                    'lookupSessionInfo',
                    $options,
                    [
                        'login' => $patron['cat_username'],
                        'password' => $patron['cat_password'],
                    ]
                )->userProfileID;
            } elseif (strcmp($userProfileGroupField, 'PATRON_LIBRARY_ID') == 0) {
                $group = $result->patronInfo->patronLibraryID;
            } elseif (strcmp($userProfileGroupField, 'DEPARTMENT') == 0) {
                $group = $result->patronInfo->department;
            } else {
                $group = null;
            }

            [$lastname, $firstname]
                = explode(', ', $result->patronInfo->displayName);

            $profile = [
                'lastname' => $lastname,
                'firstname' => $firstname,
                'address1' => $address1,
                'address2' => $address2,
                'zip' => $zip,
                'phone' => $phone,
                'group' => $group,
            ];
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $profile;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        try {
            $transList = [];
            $options   = ['includePatronCheckoutInfo' => 'ALL'];

            $result = $this->makeRequest(
                'patron',
                'lookupMyAccountInfo',
                $options,
                [
                    'login' => $patron['cat_username'],
                    'password' => $patron['cat_password'],
                ]
            );

            if (isset($result->patronCheckoutInfo)) {
                $transactions = $result->patronCheckoutInfo;
                $transactions = !is_array($transactions) ? [$transactions] :
                    $transactions;

                foreach ($transactions as $transaction) {
                    $urr = !empty($transaction->unseenRenewalsRemaining)
                        || !empty($transaction->unseenRenewalsRemainingUnlimited);
                    $rr = !empty($transaction->renewalsRemaining)
                        || !empty($transaction->renewalsRemainingUnlimited);
                    $renewable = ($urr && $rr);

                    $transList[] = [
                        'duedate' =>
                            date('F j, Y', strtotime($transaction->dueDate)),
                        'id' => $transaction->titleKey,
                        'barcode' => $transaction->itemID,
                        'renew' => $transaction->renewals,
                        'request' => $transaction->recallNoticesSent,
                        //'volume' => null,
                        //'publication_year' => null,
                        'renewable' => $renewable,
                        //'message' => null,
                        'title' => $transaction->title,
                        'item_id' => $transaction->itemID,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $transList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        try {
            $holdList = [];
            $options  = ['includePatronHoldInfo' => 'ACTIVE'];

            $result = $this->makeRequest(
                'patron',
                'lookupMyAccountInfo',
                $options,
                [
                    'login' => $patron['cat_username'],
                    'password' => $patron['cat_password'],
                ]
            );

            if (!property_exists($result, 'patronHoldInfo')) {
                return null;
            }

            $holds = $result->patronHoldInfo;
            $holds = !is_array($holds) ? [$holds] : $holds;

            foreach ($holds as $hold) {
                $holdList[] = [
                    'id' => $hold->titleKey,
                    //'type' => ,
                    'location' => $hold->pickupLibraryID,
                    'reqnum' => $hold->holdKey,
                    'expire' => date('F j, Y', strtotime($hold->expiresDate)),
                    'create' => date('F j, Y', strtotime($hold->placedDate)),
                    'position' => $hold->queuePosition,
                    'available' => $hold->available,
                    'item_id' => $hold->itemID,
                    //'volume' => null,
                    //'publication_year' => null,
                    'title' => $hold->title,
                ];
            }
        } catch (SoapFault $e) {
            return null;
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $holdList;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        try {
            $fineList = [];
            $feeType  = $this->config['Behaviors']['showFeeType'];
            $options  = ['includeFeeInfo' => $feeType];

            $result = $this->makeRequest(
                'patron',
                'lookupMyAccountInfo',
                $options,
                [
                    'login' => $patron['cat_username'],
                    'password' => $patron['cat_password'],
                ]
            );

            if (isset($result->feeInfo)) {
                $fees = $result->feeInfo;
                $fees = !is_array($fees) ? [$fees] : $fees;

                foreach ($fees as $fee) {
                    $fineList[] = [
                        'amount' => $fee->amount->_ * 100,
                        'checkout' => $fee->feeItemInfo->checkoutDate ?? null,
                        'fine' => $fee->billReasonDescription,
                        'balance' => $fee->amountOutstanding->_ * 100,
                        'createdate' => $fee->dateBilled ?? null,
                        'duedate' => $fee->feeItemInfo->dueDate ?? null,
                        'id' => $fee->feeItemInfo->titleKey ?? null,
                    ];
                }
            }

            return $fineList;
        } catch (SoapFault | \Exception $e) {
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Get Cancel Hold Form
     *
     * Supplies the form details required to cancel a hold
     *
     * @param array $holdDetails A single hold array from getMyHolds
     * @param array $patron      Patron information from patronLogin
     *
     * @return string  Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelHoldDetails($holdDetails, $patron = [])
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold on a particular item
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return mixed  An array of data on each request including
     * whether or not it was successful and a system message (if available)
     * or boolean false on failure
     */
    public function cancelHolds($cancelDetails)
    {
        $count  = 0;
        $items  = [];
        $patron = $cancelDetails['patron'];

        foreach ($cancelDetails['details'] as $holdKey) {
            try {
                $options = ['holdKey' => $holdKey];

                $this->makeRequest(
                    'patron',
                    'cancelMyHold',
                    $options,
                    [
                        'login' => $patron['cat_username'],
                        'password' => $patron['cat_password'],
                    ]
                );

                $count++;
                $items[$holdKey] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                ];
            } catch (\Exception $e) {
                $items[$holdKey] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $e->getMessage(),
                ];
            }
        }
        $result = ['count' => $count, 'items' => $items];
        return $result;
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
     * Get Renew Details
     *
     * In order to renew an item, Symphony requires the patron details and an item
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
        $renewDetails = $checkOutDetails['barcode'];
        return $renewDetails;
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
        $details = [];
        $patron  = $renewDetails['patron'];

        foreach ($renewDetails['details'] as $barcode) {
            try {
                $options = ['itemID' => $barcode];

                $renewal = $this->makeRequest(
                    'patron',
                    'renewMyCheckout',
                    $options,
                    [
                        'login' => $patron['cat_username'],
                        'password' => $patron['cat_password'],
                    ]
                );

                $details[$barcode] = [
                    'success' => true,
                    'new_date' => date('j-M-y', strtotime($renewal->dueDate)),
                    'new_time' => date('g:i a', strtotime($renewal->dueDate)),
                    'item_id' => $renewal->itemID,
                    'sysMessage' => $renewal->message,
                ];
            } catch (\Exception $e) {
                $details[$barcode] = [
                    'success' => false,
                    'new_date' => false,
                    'new_time' => false,
                    'sysMessage' =>
                        'We could not renew this item: ' . $e->getMessage(),
                ];
            }
        }

        $result = ['details' => $details];
        return $result;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return array  An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        try {
            $options = [];
            $patron  = $holdDetails['patron'];

            if ($holdDetails['item_id'] != null) {
                $options['itemID'] = $holdDetails['item_id'];
            }

            if ($holdDetails['id'] != null) {
                $options['titleKey'] = $holdDetails['id'];
            }

            if ($holdDetails['pickUpLocation'] != null) {
                $options['pickupLibraryID'] = $holdDetails['pickUpLocation'];
            }

            if ($holdDetails['requiredBy'] != null) {
                $options['expiresDate'] = $holdDetails['requiredBy'];
            }

            if ($holdDetails['comment'] != null) {
                $options['comment'] = $holdDetails['comment'];
            }

            $this->makeRequest(
                'patron',
                'createMyHold',
                $options,
                [
                    'login' => $patron['cat_username'],
                    'password' => $patron['cat_password'],
                ]
            );

            $result = [
                'success' => true,
                'sysMessage' => 'Your hold has been placed.',
            ];
            return $result;
        } catch (SoapFault $e) {
            $result = [
                'success' => false,
                'sysMessage' =>
                    'We could not place the hold: ' . $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * Get Policy List
     *
     * Protected support method for getting a list of policies.
     *
     * @param string $policyType Symphony policy code for type of policy
     *
     * @return array An associative array of policy codes and descriptions.
     */
    protected function getPolicyList($policyType)
    {
        try {
            $cacheKey = 'symphony' . hash('sha256', "{$policyType}");

            if (isset($this->policies[$policyType])) {
                return $this->policies[$policyType];
            } elseif (
                $this->policyCache
                && ($policyList = $this->policyCache->getItem($cacheKey))
            ) {
                $this->policies[$policyType] = $policyList;
                return $policyList;
            } else {
                $policyList = [];
                $options    = ['policyType' => $policyType];
                $policies   = $this->makeRequest(
                    'admin',
                    'lookupPolicyList',
                    $options
                );

                foreach ($policies->policyInfo as $policyInfo) {
                    $policyList[$policyInfo->policyID]
                        = $policyInfo->policyDescription;
                }

                if ($this->policyCache) {
                    $this->policyCache->setItem($cacheKey, $policyList);
                }

                return $policyList;
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Pick Up Locations
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $libraries = [];

        foreach ($this->getPolicyList('LIBR') as $key => $library) {
            $libraries[] = [
                'locationID' => $key,
                'locationDisplay' => $library,
            ];
        }

        return $libraries;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in Symphony.ini
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
        if (isset($patron['library'])) {
            // Check for library in patron info
            return $patron['library'];
        } elseif (isset($this->config['Holds']['defaultPickUpLocation'])) {
            // If no library returned in patron info, check config file
            return $this->config['Holds']['defaultPickUpLocation'];
        } else {
            // Default to first library in the list if none specified
            // in patron info or config file
            $libraries = $this->getPickUpLocations();
            return $libraries[0]['locationID'];
        }
    }
}
