<?php
/**
 * "Get Item Status" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\Holds;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Item Status" AJAX handler
 *
 * This is responsible for printing the holdings information for a
 * collection of records in JSON format.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetItemStatuses extends AbstractBase implements TranslatorAwareInterface,
    \VuFind\I18n\HasSorterInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\I18n\HasSorterTrait;

    /**
     * Top-level configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Holds logic
     *
     * @var Holds
     */
    protected $holdLogic;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss        Session settings
     * @param Config            $config    Top-level configuration
     * @param Connection        $ils       ILS connection
     * @param RendererInterface $renderer  View renderer
     * @param Holds             $holdLogic Holds logic
     */
    public function __construct(
        SessionSettings $ss,
        Config $config,
        Connection $ils,
        RendererInterface $renderer,
        Holds $holdLogic
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->ils = $ils;
        $this->renderer = $renderer;
        $this->holdLogic = $holdLogic;
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
            $transList[] = $this->translateWithPrefix($transPrefix, $current);
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
     * @param string $transPrefix Translator prefix to apply to values (false to
     * omit translation of values)
     *
     * @return string
     */
    protected function pickValue($rawList, $mode, $msg, $transPrefix = false)
    {
        // Make sure array contains only unique values:
        $list = array_unique($rawList);

        // If there is only one value in the list, or if we're in "first" mode,
        // send back the first list value:
        if ($mode == 'first' || count($list) == 1) {
            if ($transPrefix) {
                return $this->translateWithPrefix($transPrefix, $list[0]);
            }
            return $list[0];
        } elseif (count($list) == 0) {
            // Empty list?  Return a blank string:
            return '';
        } elseif ($mode == 'all') {
            // All values mode?  Return comma-separated values:
            return implode(
                ",\t",
                $transPrefix ? $this->translateList($transPrefix, $list) : $list
            );
        } else {
            // Message mode?  Return the specified message, translated to the
            // appropriate language.
            return $this->translate($msg);
        }
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
     * @param array $rawServices Names of available services.
     *
     * @return string
     */
    protected function reduceServices(array $rawServices)
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

        return $this->renderer->render(
            'ajax/status-available-services.phtml',
            ['services' => $services]
        );
    }

    /**
     * Create a delimited version of the call number to allow the Javascript code
     * to handle the prefix appropriately.
     *
     * @param string $prefix     Callnumber prefix or empty string.
     * @param string $callnumber Main call number.
     *
     * @return string
     */
    protected function formatCallNo($prefix, $callnumber)
    {
        return !empty($prefix) ? $prefix . '::::' . $callnumber : $callnumber;
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @param array  $record            Information on items linked to a single bib
     *                                  record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $locationSetting   The location mode setting used for
     *                                  pickValue()
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatus(
        $record,
        $messages,
        $locationSetting,
        $callnumberSetting
    ) {
        // Summarize call number, location and availability info across all items:
        $callNumbers = $locations = [];
        $use_unknown_status = $available = false;
        $services = [];

        foreach ($record as $info) {
            // Find an available copy
            if ($info['availability']) {
                $available = true;
            }
            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message'])
                && $info['use_unknown_message'] == true
            ) {
                $use_unknown_status = true;
            }
            // Store call number/location info:
            $callNumbers[] = $this->formatCallNo(
                $info['callnumber_prefix'] ?? '',
                $info['callnumber']
            );

            $locations[] = $info['location'];
            // Store all available services
            if (isset($info['services'])) {
                $services = array_merge($services, $info['services']);
            }
        }

        $callnumberHandler = $this->getCallnumberHandler(
            $callNumbers,
            $callnumberSetting
        );

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

        if (!empty($services)) {
            $availability_message = $this->reduceServices($services);
        } else {
            $availability_message = $use_unknown_status
                ? $messages['unknown']
                : $messages[$available ? 'available' : 'unavailable'];
        }

        // Send back the collected details:
        return [
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => htmlentities($location, ENT_COMPAT, 'UTF-8'),
            'locationList' => false,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? $this->translate('on_reserve')
                : $this->translate('Not On Reserve'),
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8'),
            'callnumber_handler' => $callnumberHandler
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for "group" location setting.
     *
     * @param array  $record            Information on items linked to a single
     *                                  bib record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatusGroup($record, $messages, $callnumberSetting)
    {
        // Summarize call number, location and availability info across all items:
        $locations = [];
        $use_unknown_status = $available = false;
        foreach ($record as $info) {
            // Find an available copy
            if ($info['availability']) {
                $available = $locations[$info['location']]['available'] = true;
            }
            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message'])
                && $info['use_unknown_message'] == true
            ) {
                $use_unknown_status = true;
                $locations[$info['location']]['status_unknown'] = true;
            }
            // Store call number/location info:
            $locations[$info['location']]['callnumbers'][] = $this->formatCallNo(
                $info['callnumber_prefix'] ?? '',
                $info['callnumber']
            );
        }

        // Build list split out by location:
        $locationList = [];
        foreach ($locations as $location => $details) {
            $locationCallnumbers = array_unique($details['callnumbers']);
            // Determine call number string based on findings:
            $callnumberHandler = $this->getCallnumberHandler(
                $locationCallnumbers,
                $callnumberSetting
            );
            $locationCallnumbers = $this->pickValue(
                $locationCallnumbers,
                $callnumberSetting,
                'Multiple Call Numbers'
            );
            $locationInfo = [
                'availability' =>
                    $details['available'] ?? false,
                'location' => htmlentities(
                    $this->translateWithPrefix('location_', $location),
                    ENT_COMPAT,
                    'UTF-8'
                ),
                'callnumbers' =>
                    htmlentities($locationCallnumbers, ENT_COMPAT, 'UTF-8'),
                'status_unknown' => $details['status_unknown'] ?? false,
                'callnumber_handler' => $callnumberHandler
            ];
            $locationList[] = $locationInfo;
        }

        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        // Send back the collected details:
        return [
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => false,
            'locationList' => $locationList,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? $this->translate('on_reserve')
                : $this->translate('Not On Reserve'),
            'callnumber' => false
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a failed record.
     *
     * @param array  $record Information on items linked to a single bib record
     * @param string $msg    Availability message
     *
     * @return array Summarized availability information
     */
    protected function getItemStatusError($record, $msg = '')
    {
        return [
            'id' => $record[0]['id'],
            'error' => $this->translate($record[0]['error']),
            'availability' => false,
            'availability_message' => $msg,
            'location' => false,
            'locationList' => [],
            'reserve' => false,
            'reserve_message' => '',
            'callnumber' => false
        ];
    }

    /**
     * Render full item status.
     *
     * @param array $record Record
     * @param array $values Additional values for the template
     *
     * @return string
     */
    protected function renderFullStatus($record, array $values = [])
    {
        $values = array_merge(
            [
                'statusItems' => $record,
                'callnumberHandler' => $this->getCallnumberHandler()
            ],
            $values
        );
        return $this->renderer->render('ajax/status-full.phtml', $values);
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $results = [];
        $this->disableSessionWrites();  // avoid session write timing bug
        $ids = $params->fromPost('id') ?? $params->fromQuery('id', []);
        $searchId = $params->fromPost('sid') ?? $params->fromQuery('sid');
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
                        'error' => 'An error has occurred'
                    ]
                ];
            }
        }

        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load messages for response:
        $messages = [
            'available' => $this->renderer->render('ajax/status-available.phtml'),
            'unavailable' =>
                $this->renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $this->renderer->render('ajax/status-unknown.phtml')
        ];

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
            if (count($record)) {
                // Check for errors
                if (!empty($record[0]['error'])) {
                    $current = $this
                        ->getItemStatusError($record, $messages['unknown']);
                } elseif ($locationSetting === 'group') {
                    $current = $this->getItemStatusGroup(
                        $record,
                        $messages,
                        $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record,
                        $messages,
                        $locationSetting,
                        $callnumberSetting
                    );
                }
                // If a full status display has been requested and no errors were
                // encountered, append the HTML:
                if ($showFullStatus && empty($record[0]['error'])) {
                    $current['full_status'] = $this->renderFullStatus(
                        $record,
                        compact('searchId')
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = [
                'id'                   => $missingId,
                'availability'         => 'false',
                'availability_message' => $messages['unavailable'],
                'location'             => $this->translate('Unknown'),
                'locationList'         => false,
                'reserve'              => 'false',
                'reserve_message'      => $this->translate('Not On Reserve'),
                'callnumber'           => '',
                'missing_data'         => true,
                'record_number'        => $recordNumber
            ];
        }

        // Done
        return $this->formatResponse(compact('statuses'));
    }
}
