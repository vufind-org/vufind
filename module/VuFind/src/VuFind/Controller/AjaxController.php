<?php
/**
 * Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFind\Controller;
use VuFind\Exception\Auth as AuthException;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AjaxController extends AbstractBase
{
    // define some status constants
    const STATUS_OK = 'OK';                  // good
    const STATUS_ERROR = 'ERROR';            // bad
    const STATUS_NEED_AUTH = 'NEED_AUTH';    // must login first

    /**
     * Type of output to use
     *
     * @var string
     */
    protected $outputMode;

    /**
     * Array of PHP errors captured during execution
     *
     * @var array
     */
    protected static $php_errors = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add notices to a key in the output
        set_error_handler(['VuFind\Controller\AjaxController', "storeError"]);
    }

    /**
     * Handles passing data to the class
     *
     * @return mixed
     */
    public function jsonAction()
    {
        // Set the output mode to JSON:
        $this->outputMode = 'json';

        // Call the method specified by the 'method' parameter; append Ajax to
        // the end to avoid access to arbitrary inappropriate methods.
        $callback = [$this, $this->params()->fromQuery('method') . 'Ajax'];
        if (is_callable($callback)) {
            try {
                return call_user_func($callback);
            } catch (\Exception $e) {
                $debugMsg = ('development' == APPLICATION_ENV)
                    ? ': ' . $e->getMessage() : '';
                return $this->output(
                    $this->translate('An error has occurred') . $debugMsg,
                    self::STATUS_ERROR,
                    500
                );
            }
        } else {
            return $this->output(
                $this->translate('Invalid Method'), self::STATUS_ERROR, 400
            );
        }
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return \Zend\Http\Response
     */
    public function recommendAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Process recommendations -- for now, we assume Solr-based search objects,
        // since deferred recommendations work best for modules that don't care about
        // the details of the search objects anyway:
        $rm = $this->getServiceLocator()->get('VuFind\RecommendPluginManager');
        $module = $rm->get($this->params()->fromQuery('mod'));
        $module->setConfig($this->params()->fromQuery('params'));
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $module->init($params, $this->getRequest()->getQuery());
        $module->process($results);

        // Set headers:
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/html');
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        // Render recommendations:
        $recommend = $this->getViewRenderer()->plugin('recommend');
        $response->setContent($recommend($module));
        return $response;
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
            $logic = $this->getServiceLocator()->get('VuFind\ILSHoldLogic');
            $hideHoldings = $logic->getSuppressedLocations();
        }

        $filtered = [];
        foreach ($record as $current) {
            if (!in_array($current['location'], $hideHoldings)) {
                $filtered[] = $current;
            }
        }
        return $filtered;
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for printing the holdings information for a
     * collection of records in JSON format.
     *
     * @return \Zend\Http\Response
     * @author Chris Delis <cedelis@uillinois.edu>
     * @author Tuan Nguyen <tuan@yorku.ca>
     */
    protected function getItemStatusesAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $catalog = $this->getILS();
        $ids = $this->params()->fromPost('id', $this->params()->fromQuery('id'));
        $results = $catalog->getStatuses($ids);

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

        // Get access to PHP template renderer for partials:
        $renderer = $this->getViewRenderer();

        // Load messages for response:
        $messages = [
            'available' => $renderer->render('ajax/status-available.phtml'),
            'unavailable' => $renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $renderer->render('ajax/status-unknown.phtml')
        ];

        // Load callnumber and location settings:
        $config = $this->getConfig();
        $callnumberSetting = isset($config->Item_Status->multiple_call_nos)
            ? $config->Item_Status->multiple_call_nos : 'msg';
        $locationSetting = isset($config->Item_Status->multiple_locations)
            ? $config->Item_Status->multiple_locations : 'msg';
        $showFullStatus = isset($config->Item_Status->show_full_status)
            ? $config->Item_Status->show_full_status : false;

        // Loop through all the status information that came back
        $statuses = [];
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                if ($locationSetting == "group") {
                    $current = $this->getItemStatusGroup(
                        $record, $messages, $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record, $messages, $locationSetting, $callnumberSetting
                    );
                }
                // If a full status display has been requested, append the HTML:
                if ($showFullStatus) {
                    $current['full_status'] = $renderer->render(
                        'ajax/status-full.phtml', [
                            'statusItems' => $record,
                            'callnumberHandler' => $this->getCallnumberHandler()
                         ]
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
        return $this->output($statuses, self::STATUS_OK);
    }

    /**
     * Support method for getItemStatuses() -- when presented with multiple values,
     * pick which one(s) to send back via AJAX.
     *
     * @param array  $list        Array of values to choose from.
     * @param string $mode        config.ini setting -- first, all or msg
     * @param string $msg         Message to display if $mode == "msg"
     * @param string $transPrefix Translator prefix to apply to values (false to
     * omit translation of values)
     *
     * @return string
     */
    protected function pickValue($list, $mode, $msg, $transPrefix = false)
    {
        // Make sure array contains only unique values:
        $list = array_unique($list);

        // If there is only one value in the list, or if we're in "first" mode,
        // send back the first list value:
        if ($mode == 'first' || count($list) == 1) {
            if (!$transPrefix) {
                return $list[0];
            } else {
                return $this->translate($transPrefix . $list[0], [], $list[0]);
            }
        } else if (count($list) == 0) {
            // Empty list?  Return a blank string:
            return '';
        } else if ($mode == 'all') {
            // Translate values if necessary:
            if ($transPrefix) {
                $transList = [];
                foreach ($list as $current) {
                    $transList[] = $this->translate(
                        $transPrefix . $current, [], $current
                    );
                }
                $list = $transList;
            }
            // All values mode?  Return comma-separated values:
            return implode(",\t", $list);
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
        $config = $this->getConfig();
        return isset($config->Item_Status->callnumber_handler)
            ? $config->Item_Status->callnumber_handler
            : false;
    }

    /**
     * Reduce an array of service names to a human-readable string.
     *
     * @param array $services Names of available services.
     *
     * @return string
     */
    protected function reduceServices(array $services)
    {
        // Normalize, dedup and sort available services
        $normalize = function ($in) {
            return strtolower(preg_replace('/[^A-Za-z]/', '', $in));
        };
        $services = array_map($normalize, array_unique($services));
        sort($services);

        // Do we need to deal with a preferred service?
        $config = $this->getConfig();
        $preferred = isset($config->Item_Status->preferred_service)
            ? $normalize($config->Item_Status->preferred_service) : false;
        if (false !== $preferred && in_array($preferred, $services)) {
            $services = [$preferred];
        }

        return $this->getViewRenderer()->render(
            'ajax/status-available-services.phtml',
            ['services' => $services]
        );
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
    protected function getItemStatus($record, $messages, $locationSetting,
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
            $callNumbers[] = $info['callnumber'];
            $locations[] = $info['location'];
            // Store all available services
            if (isset($info['services'])) {
                $services = array_merge($services, $info['services']);
            }
        }

        $callnumberHandler = $this->getCallnumberHandler(
            $callNumbers, $callnumberSetting
        );

        // Determine call number string based on findings:
        $callNumber = $this->pickValue(
            $callNumbers, $callnumberSetting, 'Multiple Call Numbers'
        );

        // Determine location string based on findings:
        $location = $this->pickValue(
            $locations, $locationSetting, 'Multiple Locations', 'location_'
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
        $locations =  [];
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
            $locations[$info['location']]['callnumbers'][] = $info['callnumber'];
        }

        // Build list split out by location:
        $locationList = false;
        foreach ($locations as $location => $details) {
            $locationCallnumbers = array_unique($details['callnumbers']);
            // Determine call number string based on findings:
            $callnumberHandler = $this->getCallnumberHandler(
                $locationCallnumbers, $callnumberSetting
            );
            $locationCallnumbers = $this->pickValue(
                $locationCallnumbers, $callnumberSetting, 'Multiple Call Numbers'
            );
            $locationInfo = [
                'availability' =>
                    isset($details['available']) ? $details['available'] : false,
                'location' => htmlentities(
                    $this->translate('location_' . $location, [], $location),
                    ENT_COMPAT, 'UTF-8'
                ),
                'callnumbers' =>
                    htmlentities($locationCallnumbers, ENT_COMPAT, 'UTF-8'),
                'status_unknown' => isset($details['status_unknown'])
                    ? $details['status_unknown'] : false,
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
     * Check one or more records to see if they are saved in one of the user's list.
     *
     * @return \Zend\Http\Response
     */
    protected function getSaveStatusesAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        // loop through each ID check if it is saved to any of the user's lists
        $ids = $this->params()->fromPost('id', $this->params()->fromQuery('id', []));
        $sources = $this->params()->fromPost(
            'source', $this->params()->fromQuery('source', [])
        );
        if (!is_array($ids) || !is_array($sources)) {
            return $this->output(
                $this->translate('Argument must be array.'),
                self::STATUS_ERROR,
                400
            );
        }
        $result = $checked = [];
        foreach ($ids as $i => $id) {
            $source = isset($sources[$i]) ? $sources[$i] : DEFAULT_SEARCH_BACKEND;
            $selector = $source . '|' . $id;

            // We don't want to bother checking the same ID more than once, so
            // use the $checked flag array to avoid duplicates:
            if (isset($checked[$selector])) {
                continue;
            }
            $checked[$selector] = true;

            $data = $user->getSavedData($id, null, $source);
            if ($data && count($data) > 0) {
                $result[$selector] = [];
                // if this item was saved, add it to the list of saved items.
                foreach ($data as $list) {
                    $result[$selector][] = [
                        'list_url' => $this->url()->fromRoute(
                            'userList',
                            ['id' => $list->list_id]
                        ),
                        'list_title' => $list->list_title
                    ];
                }
            }
        }
        return $this->output($result, self::STATUS_OK);
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $output = ['data' => $data, 'status' => $status];
            if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
                $output['php_errors'] = self::$php_errors;
            }
            $response->setContent(json_encode($output));
            return $response;
        } else if ($this->outputMode == 'plaintext') {
            $headers->addHeaderLine('Content-type', 'text/plain');
            $response->setContent($data ? $status . " $data" : $status);
            return $response;
        } else {
            throw new \Exception('Unsupported output mode: ' . $this->outputMode);
        }
    }

    /**
     * Store the errors for later, to be added to the output
     *
     * @param string $errno   Error code number
     * @param string $errstr  Error message
     * @param string $errfile File where error occurred
     * @param string $errline Line number of error
     *
     * @return bool           Always true to cancel default error handling
     */
    public static function storeError($errno, $errstr, $errfile, $errline)
    {
        self::$php_errors[] = "ERROR [$errno] - " . $errstr . "<br />\n"
            . " Occurred in " . $errfile . " on line " . $errline . ".";
        return true;
    }

    /**
     * Generate the "salt" used in the salt'ed login request.
     *
     * @return string
     */
    protected function generateSalt()
    {
        return str_replace(
            '.', '', $this->getRequest()->getServer()->get('REMOTE_ADDR')
        );
    }

    /**
     * Send the "salt" to be used in the salt'ed login request.
     *
     * @return \Zend\Http\Response
     */
    protected function getSaltAjax()
    {
        return $this->output($this->generateSalt(), self::STATUS_OK);
    }

    /**
     * Login with post'ed username and encrypted password.
     *
     * @return \Zend\Http\Response
     */
    protected function loginAjax()
    {
        // Fetch Salt
        $salt = $this->generateSalt();

        // HexDecode Password
        $password = pack('H*', $this->params()->fromPost('password'));

        // Decrypt Password
        $password = base64_decode(\VuFind\Crypt\RC4::encrypt($salt, $password));

        // Update the request with the decrypted password:
        $this->getRequest()->getPost()->set('password', $password);

        // Authenticate the user:
        try {
            $this->getAuthManager()->login($this->getRequest());
        } catch (AuthException $e) {
            return $this->output(
                $this->translate($e->getMessage()),
                self::STATUS_ERROR,
                401
            );
        }

        return $this->output(true, self::STATUS_OK);
    }

    /**
     * Tag a record.
     *
     * @return \Zend\Http\Response
     */
    protected function tagRecordAjax()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }
        // empty tag
        try {
            $driver = $this->getRecordLoader()->load(
                $this->params()->fromPost('id'),
                $this->params()->fromPost('source', DEFAULT_SEARCH_BACKEND)
            );
            $tag = $this->params()->fromPost('tag', '');
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            if (strlen($tag) > 0) { // don't add empty tags
                if ('false' === $this->params()->fromPost('remove', 'false')) {
                    $driver->addTags($user, $tagParser->parse($tag));
                } else {
                    $driver->deleteTags($user, $tagParser->parse($tag));
                }
            }
        } catch (\Exception $e) {
            return $this->output(
                ('development' == APPLICATION_ENV) ? $e->getMessage() : 'Failed',
                self::STATUS_ERROR,
                500
            );
        }

        return $this->output($this->translate('Done'), self::STATUS_OK);
    }

    /**
     * Get all tags for a record.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordTagsAjax()
    {
        $user = $this->getUser();
        $is_me_id = null === $user ? null : $user->id;
        // Retrieve from database:
        $tagTable = $this->getTable('Tags');
        $tags = $tagTable->getForResource(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND),
            0, null, null, 'count', $is_me_id
        );

        // Build data structure for return:
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[] = [
                'tag'   => $tag->tag,
                'cnt'   => $tag->cnt,
                'is_me' => $tag->is_me == 1 ? true : false
            ];
        }

        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');
        $view = $this->createViewModel(
            [
                'tagList' => $tagList,
                'loggedin' => null !== $user
            ]
        );
        $view->setTemplate('record/taglist');
        return $view;
    }

    /**
     * Get record for integrated list view.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordDetailsAjax()
    {
        $driver = $this->getRecordLoader()->load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source')
        );
        $viewtype = preg_replace(
            '/\W/', '',
            trim(strtolower($this->params()->fromQuery('type')))
        );
        $request = $this->getRequest();
        $config = $this->getServiceLocator()->get('Config');
        $sconfig = $this->getServiceLocator()->get('VuFind\Config')->get('searches');

        $recordTabPlugin = $this->getServiceLocator()
            ->get('VuFind\RecordTabPluginManager');
        $details = $recordTabPlugin
            ->getTabDetailsForRecord(
                $driver,
                $config['vufind']['recorddriver_tabs'],
                $request,
                'Information'
            );

        $rtpm = $this->getServiceLocator()->get('VuFind\RecordTabPluginManager');
        $html = $this->getViewRenderer()
            ->render(
                "record/ajaxview-" . $viewtype . ".phtml",
                [
                    'defaultTab' => $details['default'],
                    'driver' => $driver,
                    'tabs' => $details['tabs'],
                    'backgroundTabs' => $rtpm->getBackgroundTabNames(
                        $driver, $this->getRecordTabConfig()
                    )
                ]
            );
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Get map data on search results and output in JSON
     *
     * @param array $fields Solr fields to retrieve data from
     *
     * @author Chris Hallberg <crhallberg@gmail.com>
     * @author Lutz Biedinger <lutz.biedinger@gmail.com>
     *
     * @return \Zend\Http\Response
     */
    protected function getMapDataAjax($fields = ['long_lat'])
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());

        $facets = $results->getFullFieldFacets($fields, false);

        $markers = [];
        $i = 0;
        $list = isset($facets['long_lat']['data']['list'])
            ? $facets['long_lat']['data']['list'] : [];
        foreach ($list as $location) {
            $longLat = explode(',', $location['value']);
            $markers[$i] = [
                'title' => (string)$location['count'], //needs to be a string
                'location_facet' =>
                    $location['value'], //needed to load in the location
                'lon' => $longLat[0],
                'lat' => $longLat[1]
            ];
            $i++;
        }
        return $this->output($markers, self::STATUS_OK);
    }

    /**
     * Get entry information on entries tied to a specific map location
     *
     * @author Chris Hallberg <crhallberg@gmail.com>
     * @author Lutz Biedinger <lutz.biedinger@gmail.com>
     *
     * @return mixed
     */
    public function resultgooglemapinfoAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');

        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());

        return $this->createViewModel(
            [
                'results' => $results,
                'recordSet' => $results->getResults(),
                'recordCount' => $results->getResultTotal(),
                'completeListUrl' => $results->getUrlQuery()->getParams()
            ]
        );
    }

    /**
     * AJAX for timeline feature (PubDateVisAjax)
     *
     * @param array $fields Solr fields to retrieve data from
     *
     * @author Chris Hallberg <crhallberg@gmail.com>
     * @author Till Kinstler <kinstler@gbv.de>
     *
     * @return \Zend\Http\Response
     */
    protected function getVisDataAjax($fields = ['publishDate'])
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());
        foreach ($this->params()->fromQuery('hf', []) as $hf) {
            $params->addHiddenFilter($hf);
        }
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $filters = $params->getFilters();
        $dateFacets = $this->params()->fromQuery('facetFields');
        $dateFacets = empty($dateFacets) ? [] : explode(':', $dateFacets);
        $fields = $this->processDateFacets($filters, $dateFacets, $results);
        $facets = $this->processFacetValues($fields, $results);
        foreach ($fields as $field => $val) {
            $facets[$field]['min'] = $val[0] > 0 ? $val[0] : 0;
            $facets[$field]['max'] = $val[1] > 0 ? $val[1] : 0;
            $facets[$field]['removalURL']
                = $results->getUrlQuery()->removeFacet(
                    $field,
                    isset($filters[$field][0]) ? $filters[$field][0] : null,
                    false
                );
        }
        return $this->output($facets, self::STATUS_OK);
    }

    /**
     * Support method for getVisData() -- extract details from applied filters.
     *
     * @param array                       $filters    Current filter list
     * @param array                       $dateFacets Objects containing the date
     * ranges
     * @param \VuFind\Search\Solr\Results $results    Search results object
     *
     * @return array
     */
    protected function processDateFacets($filters, $dateFacets, $results)
    {
        $result = [];
        foreach ($dateFacets as $current) {
            $from = $to = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if (preg_match('/\[[\d\*]+ TO [\d\*]+\]/', $filter)) {
                        $range = explode(' TO ', trim($filter, '[]'));
                        $from = $range[0] == '*' ? '' : $range[0];
                        $to = $range[1] == '*' ? '' : $range[1];
                        break;
                    }
                }
            }
            $result[$current] = [$from, $to];
            $result[$current]['label']
                = $results->getParams()->getFacetLabel($current);
        }
        return $result;
    }

    /**
     * Support method for getVisData() -- filter bad values from facet lists.
     *
     * @param array                       $fields  Processed date information from
     * processDateFacets
     * @param \VuFind\Search\Solr\Results $results Search results object
     *
     * @return array
     */
    protected function processFacetValues($fields, $results)
    {
        $facets = $results->getFullFieldFacets(array_keys($fields));
        $retVal = [];
        foreach ($facets as $field => $values) {
            $newValues = ['data' => []];
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match("/^[0-9]+$/", $current['value'])) {
                    $newValues['data'][]
                        = [$current['value'], $current['count']];
                }
            }
            $retVal[$field] = $newValues;
        }
        return $retVal;
    }

    /**
     * Get Autocomplete suggestions.
     *
     * @return \Zend\Http\Response
     */
    protected function getACSuggestionsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $query = $this->getRequest()->getQuery();
        $autocompleteManager = $this->getServiceLocator()
            ->get('VuFind\AutocompletePluginManager');
        return $this->output(
            $autocompleteManager->getSuggestions($query), self::STATUS_OK
        );
    }

    /**
     * Check Request is Valid
     *
     * @return \Zend\Http\Response
     */
    protected function checkRequestIsValidAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $this->params()->fromQuery('id');
        $data = $this->params()->fromQuery('data');
        $requestType = $this->params()->fromQuery('requestType');
        if (empty($id) || empty($data)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();
            if ($patron) {
                switch ($requestType) {
                case 'ILLRequest':
                    $results = $catalog->checkILLRequestIsValid($id, $data, $patron);
                    $msg = $results
                        ? 'ill_request_place_text' : 'ill_request_error_blocked';
                    break;
                case 'StorageRetrievalRequest':
                    $results = $catalog->checkStorageRetrievalRequestIsValid(
                        $id, $data, $patron
                    );
                    $msg = $results ? 'storage_retrieval_request_place_text'
                        : 'storage_retrieval_request_error_blocked';
                    break;
                default:
                    $results = $catalog->checkRequestIsValid($id, $data, $patron);
                    $msg = $results ? 'request_place_text' : 'hold_error_blocked';
                    break;
                }
                return $this->output(
                    ['status' => $results, 'msg' => $this->translate($msg)],
                    self::STATUS_OK
                );
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function commentRecordAjax()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            return $this->output(
                $this->translate('Comments disabled'),
                self::STATUS_ERROR,
                403
            );
        }

        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $id = $this->params()->fromPost('id');
        $comment = $this->params()->fromPost('comment');
        if (empty($id) || empty($comment)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }

        $useCaptcha = $this->recaptcha()->active('userComments');
        $this->recaptcha()->setErrorMode('none');
        if (!$this->formWasSubmitted('comment', $useCaptcha)) {
            return $this->output(
                $this->translate('recaptcha_not_passed'),
                self::STATUS_ERROR,
                403
            );
        }

        $table = $this->getTable('Resource');
        $resource = $table->findResource(
            $id, $this->params()->fromPost('source', DEFAULT_SEARCH_BACKEND)
        );
        $id = $resource->addComment($comment, $user);

        return $this->output($id, self::STATUS_OK);
    }

    /**
     * Delete a comment on a record.
     *
     * @return \Zend\Http\Response
     */
    protected function deleteRecordCommentAjax()
    {
        // Make sure comments are enabled:
        if (!$this->commentsEnabled()) {
            return $this->output(
                $this->translate('Comments disabled'),
                self::STATUS_ERROR,
                403
            );
        }

        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        $id = $this->params()->fromQuery('id');
        if (empty($id)) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        $table = $this->getTable('Comments');
        if (!$table->deleteIfOwnedByUser($id, $user)) {
            return $this->output(
                $this->translate('edit_list_fail'),
                self::STATUS_ERROR,
                403
            );
        }

        return $this->output($this->translate('Done'), self::STATUS_OK);
    }

    /**
     * Get list of comments for a record as HTML.
     *
     * @return \Zend\Http\Response
     */
    protected function getRecordCommentsAsHTMLAjax()
    {
        $driver = $this->getRecordLoader()->load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND)
        );
        $html = $this->getViewRenderer()
            ->render('record/comments-list.phtml', ['driver' => $driver]);
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Process an export request
     *
     * @return \Zend\Http\Response
     */
    protected function exportFavoritesAjax()
    {
        $format = $this->params()->fromPost('format');
        $export = $this->getServiceLocator()->get('VuFind\Export');
        $url = $export->getBulkUrl(
            $this->getViewRenderer(), $format,
            $this->params()->fromPost('ids', [])
        );
        $html = $this->getViewRenderer()->render(
            'ajax/export-favorites.phtml',
            ['url' => $url, 'format' => $format]
        );
        return $this->output(
            [
                'result' => $this->translate('Done'),
                'result_additional' => $html,
                'needs_redirect' => $export->needsRedirect($format),
                'export_type' => $export->getBulkExportType($format),
                'result_url' => $url
            ], self::STATUS_OK
        );
    }

    /**
     * Fetch Links from resolver given an OpenURL and format as HTML
     * and output the HTML content in JSON object.
     *
     * @return \Zend\Http\Response
     * @author Graham Seaman <Graham.Seaman@rhul.ac.uk>
     */
    protected function getResolverLinksAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $openUrl = $this->params()->fromQuery('openurl', '');
        $searchClassId = $this->params()->fromQuery('searchClassId', '');

        $config = $this->getConfig();
        $resolverType = isset($config->OpenURL->resolver)
            ? $config->OpenURL->resolver : 'other';
        $pluginManager = $this->getServiceLocator()
            ->get('VuFind\ResolverDriverPluginManager');
        if (!$pluginManager->has($resolverType)) {
            return $this->output(
                $this->translate("Could not load driver for $resolverType"),
                self::STATUS_ERROR,
                500
            );
        }
        $resolver = new \VuFind\Resolver\Connection(
            $pluginManager->get($resolverType)
        );
        if (isset($config->OpenURL->resolver_cache)) {
            $resolver->enableCache($config->OpenURL->resolver_cache);
        }
        $result = $resolver->fetchLinks($openUrl);

        // Sort the returned links into categories based on service type:
        $electronic = $print = $services = [];
        foreach ($result as $link) {
            switch (isset($link['service_type']) ? $link['service_type'] : '') {
            case 'getHolding':
                $print[] = $link;
                break;
            case 'getWebService':
                $services[] = $link;
                break;
            case 'getDOI':
                // Special case -- modify DOI text for special display:
                $link['title'] = $this->translate('Get full text');
                $link['coverage'] = '';
            case 'getFullTxt':
            default:
                $electronic[] = $link;
                break;
            }
        }

        // Get the OpenURL base:
        if (isset($config->OpenURL) && isset($config->OpenURL->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $config->OpenURL->url);
        } else {
            $base = false;
        }

        // Render the links using the view:
        $view = [
            'openUrlBase' => $base, 'openUrl' => $openUrl, 'print' => $print,
            'electronic' => $electronic, 'services' => $services,
            'searchClassId' => $searchClassId
        ];
        $html = $this->getViewRenderer()->render('ajax/resolverLinks.phtml', $view);

        // output HTML encoded in JSON object
        return $this->output($html, self::STATUS_OK);
    }

    /**
     * Keep Alive
     *
     * This is responsible for keeping the session alive whenever called
     * (via JavaScript)
     *
     * @return \Zend\Http\Response
     */
    protected function keepAliveAjax()
    {
        // Request ID from session to mark it active
        $this->getServiceLocator()->get('VuFind\SessionManager')->getId();
        return $this->output(true, self::STATUS_OK);
    }

    /**
     * Get pick up locations for a library
     *
     * @return \Zend\Http\Response
     */
    protected function getLibraryPickupLocationsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $this->params()->fromQuery('id');
        $pickupLib = $this->params()->fromQuery('pickupLib');
        if (null === $id || null === $pickupLib) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();
            if ($patron) {
                $results = $catalog->getILLPickupLocations($id, $pickupLib, $patron);
                foreach ($results as &$result) {
                    if (isset($result['name'])) {
                        $result['name'] = $this->translate(
                            'location_' . $result['name'],
                            [],
                            $result['name']
                        );
                    }
                }
                return $this->output(['locations' => $results], self::STATUS_OK);
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Get pick up locations for a request group
     *
     * @return \Zend\Http\Response
     */
    protected function getRequestGroupPickupLocationsAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $id = $this->params()->fromQuery('id');
        $requestGroupId = $this->params()->fromQuery('requestGroupId');
        if (null === $id || null === $requestGroupId) {
            return $this->output(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH,
                401
            );
        }

        try {
            $catalog = $this->getILS();
            $patron = $this->getILSAuthenticator()->storedCatalogLogin();
            if ($patron) {
                $details = [
                    'id' => $id,
                    'requestGroupId' => $requestGroupId
                ];
                $results = $catalog->getPickupLocations($patron, $details);
                foreach ($results as &$result) {
                    if (isset($result['locationDisplay'])) {
                        $result['locationDisplay'] = $this->translate(
                            'location_' . $result['locationDisplay'],
                            [],
                            $result['locationDisplay']
                        );
                    }
                }
                return $this->output(['locations' => $results], self::STATUS_OK);
            }
        } catch (\Exception $e) {
            // Do nothing -- just fail through to the error message below.
        }

        return $this->output(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }

    /**
     * Get hierarchical facet data for jsTree
     *
     * Parameters:
     * facetName  The facet to retrieve
     * facetSort  By default all facets are sorted by count. Two values are available
     * for alternative sorting:
     *   top = sort the top level alphabetically, rest by count
     *   all = sort all levels alphabetically
     *
     * @return \Zend\Http\Response
     */
    protected function getFacetDataAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $facet = $this->params()->fromQuery('facetName');
        $sort = $this->params()->fromQuery('facetSort');
        $operator = $this->params()->fromQuery('facetOperator');

        $results = $this->getResultsManager()->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet, null, $operator === 'OR');
        $params->initFromRequest($this->getRequest()->getQuery());

        $facets = $results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            return $this->output([], self::STATUS_OK);
        }

        $facetList = $facets[$facet]['data']['list'];

        $facetHelper = $this->getServiceLocator()
            ->get('VuFind\HierarchicalFacetHelper');
        if (!empty($sort)) {
            $facetHelper->sortFacetList($facetList, $sort == 'top');
        }

        return $this->output(
            $facetHelper->buildFacetArray(
                $facet, $facetList, $results->getUrlQuery()
            ),
            self::STATUS_OK
        );
    }

    /**
     * Check status and return a status message for e.g. a load balancer.
     *
     * A simple OK as text/plain is returned if everything works properly.
     *
     * @return \Zend\Http\Response
     */
    protected function systemStatusAction()
    {
        $this->outputMode = 'plaintext';

        // Check system status
        $config = $this->getConfig();
        if (!empty($config->System->healthCheckFile)
            && file_exists($config->System->healthCheckFile)
        ) {
            return $this->output(
                'Health check file exists', self::STATUS_ERROR, 503
            );
        }

        // Test search index
        try {
            $results = $this->getResultsManager()->get('Solr');
            $params = $results->getParams();
            $params->setQueryIDs(['healthcheck']);
            $results->performAndProcessSearch();
        } catch (\Exception $e) {
            return $this->output(
                'Search index error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }

        // Test database connection
        try {
            $sessionTable = $this->getTable('Session');
            $sessionTable->getBySessionId('healthcheck', false);
        } catch (\Exception $e) {
            return $this->output(
                'Database error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }

        // This may be called frequently, don't leave sessions dangling
        $this->getServiceLocator()->get('VuFind\SessionManager')->destroy();

        return $this->output('', self::STATUS_OK);
    }

    /**
     * Convenience method for accessing results
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getResultsManager()
    {
        return $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager');
    }

    /**
     * Get Ils Status
     *
     * This will check the ILS for being online and will return the ils-offline
     * template upon failure.
     *
     * @return \Zend\Http\Response
     * @author André Lahmann <lahmann@ub.uni-leipzig.de>
     */
    protected function getIlsStatusAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $offlineModeMsg = $this->params()->fromPost(
            'offlineModeMsg',
            $this->params()->fromQuery('offlineModeMsg')
        );
        if ($this->getILS()->getOfflineMode() == 'ils-offline') {
            return $this->output(
                $this->getViewRenderer()->render(
                    'Helpers/ils-offline.phtml',
                    $offlineModeMsg
                ),
                self::STATUS_OK
            );
        }
        return $this->output('', self::STATUS_OK);
    }
}
