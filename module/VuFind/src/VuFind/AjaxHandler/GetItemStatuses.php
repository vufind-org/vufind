<?php

/**
 * "Get Item Status" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Throwable;
use VuFind\Config\Config;
use VuFind\Exception\ILS as ILSException;
use VuFind\GetThis\GetThisLoader;
use VuFind\Http\RouteHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFind\ILS\Logic\AvailabilityStatusManager;
use VuFind\ILS\Logic\Holds;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Search\Memory;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

use function array_map;
use function array_unique;
use function call_user_func;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * "Get Item Status" AJAX handler.
 *
 * This is responsible for printing the holdings information for a
 * collection of records in JSON format.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetItemStatuses extends AbstractBase implements
    TranslatorAwareInterface,
    \VuFind\I18n\HasSorterInterface,
    LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\I18n\HasSorterTrait;
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param SessionSettings           $ss                        Session settings
     * @param Config                    $config                    Top-level configuration
     * @param Connection                $ils                       ILS connection
     * @param TemplateRendererInterface $renderer                  Template renderer
     * @param Holds                     $holdLogic                 Holds logic
     * @param AvailabilityStatusManager $availabilityStatusManager Availability status manager
     * @param ?GetThisLoader            $getThisLoader             Get This loader or null if not enabled
     * @param RouteHelper               $routeHelper               Route helper
     * @param ?Memory                   $searchMemory              Search memory to be able to get user selected filters
     */
    public function __construct(
        SessionSettings $ss,
        protected Config $config,
        protected Connection $ils,
        protected TemplateRendererInterface $renderer,
        protected Holds $holdLogic,
        protected AvailabilityStatusManager $availabilityStatusManager,
        protected ?GetThisLoader $getThisLoader,
        protected RouteHelper $routeHelper,
        protected ?Memory $searchMemory,
    ) {
        parent::__construct($ss);
    }

    /**
     * Support method for getItemStatuses() -- filter suppressed locations from the
     * array of item information for a particular bib record.
     *
     * @param array $record Information on items linked to a single bib record
     *
     * @return array        Filtered version of $record
     */
    protected function filterSuppressedLocations($record)
    {
        static $hideHoldings = false;
        if ($hideHoldings === false) {
            $hideHoldings = $this->holdLogic->getSuppressedLocations();
        }

        $filtered = [];
        foreach ($record as $current) {
            if (!in_array($current['location'] ?? null, $hideHoldings)) {
                $filtered[] = $current;
            }
        }
        return $filtered;
    }

    /**
     * Translate an array of strings using a prefix.
     *
     * @param string $transPrefix Translation prefix
     * @param array  $list        List of values to translate
     *
     * @return array
     */
    protected function translateList($transPrefix, $list)
    {
        $transList = [];
        foreach ($list as $current) {
            // $current can be an array if pickValue() is called with callnumbers
            $transList[] = is_string($current) ? $this->translateWithPrefix($transPrefix, $current) : $current;
        }
        return $transList;
    }

    /**
     * Support method for getItemStatuses() -- when presented with multiple values,
     * pick which one(s) to send back via AJAX.
     *
     * @param array  $rawList     Array of values to choose from.
     * @param string $mode        config.ini setting -- first, all or msg
     * @param string $msg         Message to display if $mode == "msg"
     * @param string $transPrefix Translator prefix to apply to values (false to omit translation of values)
     *
     * @return array
     */
    protected function pickValue($rawList, $mode, $msg, $transPrefix = false)
    {
        // Make sure array contains only unique values:
        // array unique for multidimensional arrays due to callnumber array,
        // can be slow for larger/more complex arrays
        $list = array_map('unserialize', array_unique(array_map('serialize', $rawList)));

        // If we're in "first" mode, reduce list to first list value:
        if ($mode == 'first' && count($list) > 0) {
            $list = [$list[0]];
        } elseif ($mode == 'msg' && count($list) > 1) {
            // Message mode?  Return the specified message, translated to the
            // appropriate language.
            return [$this->translate($msg)];
        }

        return $transPrefix ? $this->translateList($transPrefix, $list) : $list;
    }

    /**
     * Based on settings and the number of callnumbers, return callnumber handler
     * Use callnumbers before pickValue is run.
     *
     * @param array  $list           Array of callnumbers.
     * @param string $displaySetting config.ini setting -- first, all or msg
     *
     * @return string
     */
    protected function getCallnumberHandler($list = null, $displaySetting = null)
    {
        if ($displaySetting == 'msg' && count($list) > 1) {
            return false;
        }
        return $this->config->Item_Status->callnumber_handler ?? false;
    }

    /**
     * Reduce an array of service names to a human-readable string.
     *
     * @param array                  $rawServices Names of available services
     *
     * @return array
     */
    protected function reduceServices(array $rawServices): array
    {
        // Normalize, dedup and sort available services
        $normalize = function ($in) {
            return strtolower(preg_replace('/[^A-Za-z]/', '', $in));
        };
        $services = array_map($normalize, array_unique($rawServices));
        $this->getSorter()->sort($services);

        // Do we need to deal with a preferred service?
        $preferred = isset($this->config->Item_Status->preferred_service)
            ? $normalize($this->config->Item_Status->preferred_service) : false;
        if (false !== $preferred && in_array($preferred, $services)) {
            $services = [$preferred];
        }

        return $services;
    }

    /**
     * Create an array with the callnumber and prefix of the given item.
     *
     * @param array $item Item's holding data.
     *
     * @return array{
     *     prefix: string,
     *     callnumber: string,
     * } Associative array with the keys 'prefix' and 'callnumber'
     */
    protected function getCallNumberArray(array $item): array
    {
        return [
            'prefix' => $item['callnumber_prefix'] ?? '',
            'callnumber' => $item['callnumber'] ?? '',
        ];
    }

    /**
     * Render the callnumber HTML.
     *
     * @param ServerRequestInterface $request           Request
     * @param string                 $callnumberSetting The callnumber mode setting
     * @param array                  $callnumbers       Callnumbers to render
     *
     * @return string
     */
    protected function renderCallnumbers(
        ServerRequestInterface $request,
        string $callnumberSetting,
        array $callnumbers
    ): string {
        $html = [];

        $callnumberHandler = $this->getCallnumberHandler($callnumbers, $callnumberSetting);
        foreach ($callnumbers as $number) {
            // Call number is usually an array, but it could be a flat string if we're in "msg" mode:
            if (is_array($number)) {
                $displayCallnumber = $actualCallnumber = $number['callnumber'];

                if (!empty($number['prefix'])) {
                    $displayCallnumber = $number['prefix'] . ' ' . $displayCallnumber;
                }
            } else {
                $displayCallnumber = $actualCallnumber = $number;
            }

            $html[] = $this->renderer->renderTemplateAsString(
                $request,
                'ajax/itemCallnumber',
                compact('actualCallnumber', 'displayCallnumber', 'callnumberHandler')
            );
        }

        return implode(",\t", $html);
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @param array                  $record            Information on items linked to a single bib record
     * @param string                 $locationSetting   The location mode setting used for pickValue()
     * @param string                 $callnumberSetting The callnumber mode setting used for pickValue()
     *
     * @return array{
     *     id: string,
     *     availability: string,
     *     combinedAvailability: AvailabilityStatus,
     *     services: array,
     *     location: string,
     *     locationList: bool,
     *     reserve: string,
     *     reserve_message: string,
     *     callnumber: string,
     *     getThisURL: string,
     * } Summarized availability information
     */
    protected function getItemStatus(
        $record,
        $locationSetting,
        $callnumberSetting
    ): array {
        if (isset($this->getThisLoader)) {
            $itemIdParams = empty($record[0]['item_id']) ? [] : ['item_id' => $record[0]['item_id']];
            $getThisURL = $this->routeHelper->getUrlFromRoute(
                'record-getthis',
                ['id' => $record[0]['id'] ?? null],
                $itemIdParams
            );
        } else {
            $getThisURL = '';
        }
        // Summarize call number, location and availability info across all items:
        $callNumbers = $locations = [];
        $services = [];
        foreach ($record as $info) {
            // Store call number/location info:
            $callNumbers[] = $this->getCallNumberArray($info);

            if (!empty($info['location'])) {
                $locations[] = $info['location'];
            }
            // Store all available services
            if (isset($info['services'])) {
                $services = array_merge($services, $info['services']);
            }
        }

        // Determine call number string based on findings:
        $callNumber = $this->pickValue(
            $callNumbers,
            $callnumberSetting,
            'Multiple Call Numbers'
        );

        // Determine location string based on findings:
        $location = $this->pickValue(
            $locations,
            $locationSetting,
            'Multiple Locations',
            'location_'
        );

        // Get combined availability
        $combinedInfo = $this->availabilityStatusManager->combine($record);
        $combinedAvailability = $combinedInfo['availability'];

        $reserve = ($record[0]['reserve'] ?? 'N') === 'Y';

        // Send back the collected details:
        return [
            'id' => $record[0]['id'] ?? '',
            'availability' => $combinedAvailability->availabilityAsString(),
            'combinedAvailability' => $combinedAvailability,
            'services' => $services,
            'location' => implode(",\t", $location),
            'locationList' => false,
            'reserve' => $reserve ? 'true' : 'false',
            'reserve_message'
                => $this->translate($reserve ? 'on_reserve' : 'Not On Reserve'),
            'callnumber' => $callNumber,
            'getThisURL' => $getThisURL,
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for "group" location setting.
     *
     * @param array  $record            Information on items linked to a single bib record
     * @param string $callnumberSetting The callnumber mode setting used for pickValue()
     *
     * @return array{
     *      id: string,
     *      availability: string,
     *      combinedAvailability: AvailabilityStatus,
     *      location: string,
     *      locationList: bool,
     *      reserve: string,
     *      reserve_message: string,
     *      callnumber: bool,
     *  } Summarized availability information
     */
    protected function getItemStatusGroup($record, $callnumberSetting): array
    {
        // Summarize call number, location and availability info across all items:
        $locations = [];
        foreach ($record as $info) {
            // Store call number/location info:
            $locations[$info['location']]['callnumbers'][] = $this->getCallNumberArray($info);
            $locations[$info['location']]['items'][] = $info;
        }

        if (isset($this->getThisLoader)) {
            $this->getThisLoader->setItems($record);
        }

        $locationList = $this->getLocationList($locations, $callnumberSetting, $record[0]['id']);

        // Get combined availability
        $combinedInfo = $this->availabilityStatusManager->combine($record);
        $combinedAvailability = $combinedInfo['availability'];

        $reserve = ($record[0]['reserve'] ?? 'N') === 'Y';

        // Send back the collected details:
        return [
            'id' => $record[0]['id'],
            'availability' => $combinedAvailability->availabilityAsString(),
            'combinedAvailability' => $combinedAvailability,
            'location' => false,
            'locationList' => $locationList,
            'reserve' => $reserve ? 'true' : 'false',
            'reserve_message'
                => $this->translate($reserve ? 'on_reserve' : 'Not On Reserve'),
            'callnumber' => false,
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a failed record.
     *
     * @param array $record Information on items linked to a single bib record
     *
     * @return array{
     *       id: string,
     *       error: string,
     *       availability: string,
     *       location: string,
     *       locationList: bool,
     *       reserve: string,
     *       reserve_message: string,
     *       callnumber: bool,
     *   } Summarized availability information
     */
    protected function getItemStatusError($record)
    {
        return [
            'id' => $record[0]['id'],
            'error' => $this->translate($record[0]['error']),
            'availability' => false,
            'location' => false,
            'locationList' => [],
            'reserve' => false,
            'reserve_message' => '',
            'callnumber' => false,
        ];
    }

    /**
     * Get a message for availability status.
     *
     * @param ServerRequestInterface      $request      Request
     * @param AvailabilityStatusInterface $availability Availability Status
     *
     * @return string
     */
    protected function renderAvailabilityMessage(
        ServerRequestInterface $request,
        AvailabilityStatusInterface $availability
    ): string {
        return $this->renderer->renderTemplateAsString(
            $request,
            'ajax/status.phtml',
            ['availabilityStatus' => $availability]
        );
    }

    /**
     * Get full item status data to pass for rendering.
     *
     * @param array                  $record       Record
     * @param array                  $simpleStatus Simple status result
     * @param array                  $values       Additional values for the template
     *
     * @return array
     */
    protected function getFullStatusData($record, $simpleStatus, array $values = []): array
    {
        // Default case: no extra holdings fields are shown
        $holdingsTextFieldsToShow = [];

        if ($this->config->Item_Status->include_holdings_text_fields ?? false) {
            // If we are showing additional holdings text fields, the set of fields shown is
            // either config.ini's displayed_holdings_text_fields[] (if set), or the set of
            // all fields reported by the ILS driver otherwise.
            $holdingsTextFieldsToShow = $this->config?->Item_Status?->displayed_holdings_text_fields?->toArray()
                ?? $this->ils->getHoldingsTextFieldNames();
        }

        return array_merge(
            [
                'getThisLoader' => $this->getThisLoader,
                'statusItems' => $record,
                'simpleStatus' => $simpleStatus,
                'callnumberHandler' => $this->getCallnumberHandler(),
                'holdingsTextFieldNames' => $holdingsTextFieldsToShow,
            ],
            $values
        );
    }

    /**
     * Build list split out by location
     *
     * @param array   $locations         Available locations
     * @param string  $callnumberSetting Callnumber setting
     * @param ?string $id                Holding id
     *
     * @return array
     */
    public function getLocationList(array $locations, string $callnumberSetting, ?string $id): array
    {
        $locationList = [];
        foreach ($locations as $location => $details) {
            // Determine call number string based on findings:
            $locationCallnumbers = $this->pickValue(
                $details['callnumbers'],
                $callnumberSetting,
                'Multiple Call Numbers'
            );

            // Get combined availability for location
            $locationStatus = $this->availabilityStatusManager->combine($details['items']);
            if (
                isset($this->getThisLoader)
                && !$this->getThisLoader->isOnlineResource($locationStatus['item_id'] ?? null)
            ) {
                $itemIdParams = empty($locationStatus['item_id']) ? []
                    : ['item_id' => $locationStatus['item_id']];
                $getThisURL = $this->routeHelper->getUrlFromRoute(
                    'record-getthis',
                    ['id' => $id ?? null],
                    $itemIdParams
                );
            } else {
                $getThisURL = '';
            }

            $locationInfo = [
                'availability' => $locationStatus['availability'],
                'location' => $this->translateWithPrefix('location_', $location),
                'locationCallnumbers' => $locationCallnumbers,
                'getThisURL' => $getThisURL,
            ];
            $locationList[] = $locationInfo;
        }
        return $locationList;
    }

    /**
     * A comparison function to be used in sortStatuses.
     *
     * @param array $a      The first holding to compare
     * @param array $b      The second holding to compare
     * @param array $extras Extras parameters, filters are used in this function
     *
     * @return int -1,0,1
     */
    protected function compareLocationFilters(array $a, array $b, array $extras): int
    {
        foreach ($extras['filters']['Location'] as $locationFilter) {
            $locations = explode('/', $locationFilter['displayText']);
            $aLocation = true;
            $bLocation = true;
            foreach ($locations as $location) {
                $aLocation = $aLocation && str_contains($a['location'], $location);
                $bLocation = $bLocation && str_contains($b['location'], $location);
            }
            if ($aLocation && !$bLocation) {
                return -1;
            } elseif (!$aLocation && $bLocation) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * Sort statuses according to given config (by default it come from config.ini).
     *
     * @param array[]             $holdings      The holdings to sort
     * @param array<string,mixed> $sortingFields Config on how to sort the fields (first values are prioritized for sorting)
     * @param array               $filters       Filters from the user search
     *
     * @return void
     */
    protected function sortStatuses(array &$holdings, array $sortingFields, array $filters): void
    {
        usort($holdings, function ($a, $b) use ($sortingFields, $filters) {
            foreach ($sortingFields as $field => $order) {
                if (isset($a[$field], $b[$field])) {
                    $result = $a[$field] <=> $b[$field];
                } elseif (method_exists($this, $field)) {
                    try {
                        $result = call_user_func([$this, $field], $a, $b, ['filters' => $filters]);
                    } catch (Throwable $e) {
                        $this->logError(
                            'An error happened during call to function "' . $field . '" : '
                            . $e->getMessage() . ' line ' . $e->getLine() . ' of file ' . $e->getFile(),
                            $e->getTrace()
                        );
                        continue;
                    }
                } else {
                    continue;
                }
                if ($result === 0) {
                    continue;
                }
                return $order !== 'reversed' ? $result : -$result;
            }
            return 0;
        });
    }

    /**
     * @param array                  $record            Record to parse
     * @param mixed                  $locationSetting   Setting for location
     * @param string                 $callnumberSetting Setting for callnumber
     * @param array                  $ids               Request ids
     *
     * @return array
     */
    protected function parseRecordStatusFromIlsData(
        array $record,
        string $locationSetting,
        string $callnumberSetting,
        array $ids,
    ): array {
        if (
            isset($this->searchMemory)
            && ($this->config->Record->getStatusesSorting ?? 'false') !== 'false'
        ) {
            $filters = $this->searchMemory->getCurrentSearch()->getParams()->getFilterList() ?? [];
            $this->sortStatuses(
                $record,
                $this->config->Record->getStatusesSorting->toArray(),
                $filters
            );
        }

        // Check for errors
        if (!empty($record[0]['error'])) {
            $current = $this->getItemStatusError($record);
        } elseif ($locationSetting === 'group') {
            $current = $this->getItemStatusGroup(
                $record,
                $callnumberSetting
            );
        } else {
            $current = $this->getItemStatus(
                $record,
                $locationSetting,
                $callnumberSetting
            );
        }

        $current['record_number'] = array_search($current['id'], $ids);
        return $current;
    }

    /**
     * @param array                  $ids      Ids from the request
     * @param array                  $results  Results from the ILS
     * @param ServerRequestInterface $request  Request
     * @param ?string                $searchId SearchId
     *
     * @return array
     */
    public function parseRecordsStatusesFromIlsData(
        array $ids,
        array $results,
        ServerRequestInterface $request,
        ?string $searchId
    ): array {
        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID. We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load callnumber and location settings:
        $callnumberSetting = $this->config->Item_Status->multiple_call_nos ?? 'msg';
        $locationSetting = $this->config->Item_Status->multiple_locations ?? 'msg';
        $showFullStatus = $this->config->Item_Status->show_full_status ?? false;

        // Loop through all the status information that came back
        $statuses = [];
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (empty($record)) {
                continue;
            }
            $current = $this->parseRecordStatusFromIlsData(
                $record,
                $locationSetting,
                $callnumberSetting,
                $ids
            );
            // If a full status display has been requested and no errors were
            // encountered, append the HTML:
            if ($showFullStatus && empty($record[0]['error'])) {
                $fullStatusData = $this->getFullStatusData(
                    $record,
                    $current,
                    compact('searchId', 'current'),
                );
                $current['full_status'] = $this->renderer->renderTemplateAsString(
                    $request,
                    'ajax/status-full.phtml',
                    $fullStatusData
                );
            }
            if (!empty($current['services'])) {
                $current['availability_message'] = $this->renderer->renderTemplateAsString(
                    $request,
                    'ajax/status-available-services.phtml',
                    ['services' => $this->reduceServices($current['services'])]
                );
            } else {
                if (!empty($current['combinedAvailability'])) {
                    $availabilityMessageData =  ['availabilityStatus' => $current['combinedAvailability']];
                } else {
                    $unknownStatus = $this->availabilityStatusManager->createAvailabilityStatus(
                        AvailabilityStatusInterface::STATUS_UNKNOWN
                    );
                    $availabilityMessageData = ['availabilityStatus' => $unknownStatus];
                }
                $current['availability_message'] = $this->renderer->renderTemplateAsString(
                    $request,
                    'ajax/status.phtml',
                    $availabilityMessageData
                );
            }
            if (!empty($current['locationList'])) {
                foreach ($current['locationList'] as $location => $value) {
                    $current['locationList'][$location]['callnumberHtml'] = $this->renderCallnumbers(
                        $request,
                        $callnumberSetting,
                        $current['locationList'][$location]['locationCallnumbers']
                    );
                }
                $current['locationList'] = $this->renderer->renderTemplateAsString(
                    $request,
                    'ajax/itemLocationList',
                    ['locationList' => $current['locationList']]
                );
            }
            $current['callnumberHtml'] = empty($current['callnumber'])
                ? false
                : $this->renderCallnumbers(
                    $request,
                    $callnumberSetting,
                    $current['callnumber']
            );
            $statuses[] = $current;

            // The current ID is not missing -- remove it from the missing list.
            unset($missingIds[$current['id']]);
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $availabilityStatus = $this->availabilityStatusManager->createAvailabilityStatus(false);
            $statuses[] = [
                'id' => (string) $missingId, // array_flip may have converted to int
                'availability' => 'false',
                'availability_message' => $this->renderAvailabilityMessage(
                    $request,
                    $availabilityStatus
                ),
                'location' => $this->translate('Unknown'),
                'locationList' => false,
                'reserve' => 'false',
                'reserve_message' => $this->translate('Not On Reserve'),
                'callnumber' => '',
                'missing_data' => true,
                'record_number' => $recordNumber,
            ];
        }
        return $statuses;
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $results = [];
        $this->disableSessionWrites();  // avoid session write timing bug
        $ids = $this->getPostOrQueryParam($request, 'id', []);
        $searchId = $this->getPostOrQueryParam($request, 'sid');
        try {
            $results = $this->ils->getStatuses($ids);
        } catch (ILSException $e) {
            // If the ILS fails, send an error response instead of a fatal
            // error; we don't want to confuse the end user unnecessarily.
            error_log($e->getMessage());
            foreach ($ids as $id) {
                $results[] = [
                    [
                        'id' => $id,
                        'error' => 'An error has occurred',
                    ],
                ];
            }
        }

        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }
        $statuses = $this->parseRecordsStatusesFromIlsData($ids, $results, $request, $searchId);

        // Done
        return $this->formatResponse(compact('statuses'));
    }
}
