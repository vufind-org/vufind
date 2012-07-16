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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Controller;
use VuFind\Cart, VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Auth as AuthException, VuFind\Export,
    VuFind\Record, VuFind\Translator\Translator;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class AjaxController extends AbstractBase
{
    // define some status constants
    const STATUS_OK = 'OK';                  // good
    const STATUS_ERROR = 'ERROR';            // bad
    const STATUS_NEED_AUTH = 'NEED_AUTH';    // must login first

    protected $outputMode;
    protected $account;
    protected static $php_errors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add notices to a key in the output
        set_error_handler(array("VuFind\\Controller\\AjaxController", "storeError"));
    }

    /**
     * Handles passing data to the class
     *
     * @return void
     */
    public function jsonAction()
    {
        // Set the output mode to JSON:
        $this->outputMode = 'json';

        // Call the method specified by the 'method' parameter as long as it is
        // valid and will not result in an infinite loop!
        $method = $this->params()->fromQuery('method');
        if ($method != 'init' && $method != '__construct' && $method != 'output'
            && method_exists($this, $method)
        ) {
            try {
                $this->$method();
            } catch (\Exception $e) {
                return $this->output(
                    Translator::translate('An error has occurred'),
                    self::STATUS_ERROR
                );
            }
        } else {
            return $this->output(
                Translator::translate('Invalid Method'), self::STATUS_ERROR
            );
        }
    }

    /**
     * Load a recommendation module via AJAX.
     *
     * @return void
     */
    public function recommendAction()
    {
        // Process recommendations -- for now, we assume Solr-based search objects,
        // since deferred recommendations work best for modules that don't care about
        // the details of the search objects anyway:
        $class = 'VuFind\\Recommend\\' . $this->params()->fromQuery('mod');
        $module = new $class($this->params()->fromQuery('params'));
        $params = new \VuFind\Search\Solr\Params();
        $module->init($params, $this->getRequest()->getQuery());
        $results = new \VuFind\Search\Solr\Results($params);
        $module->process($results);

        /* TODO
        // Set headers:
        $resp = $this->getResponse();
        $resp->setHeader('Content-type', 'text/html');
        $resp->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $resp->setHeader('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        // Render recommendations:
        $resp->appendBody($this->view->recommend($module));
         */
    }

    /**
     * Get the contents of a lightbox; note that unlike most methods, this
     * one actually returns HTML rather than JSON.
     *
     * @return void
     */
    public function getLightbox()
    {
        /* TODO
        // Turn layouts on for this action since we want to render the
        // page inside a lightbox:
        $this->_helper->layout->setLayout('lightbox');

        // Call the requested action:
        return $this->_forward(
            $this->params()->fromQuery('subaction'),
            $this->params()->fromQuery('submodule')
        );
         */
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
            $logic = new \VuFind\ILS\Logic\Holds($this->getAccount());
            $hideHoldings = $logic->getSuppressedLocations();
        }

        $filtered = array();
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
     * @return void
     * @author Chris Delis <cedelis@uillinois.edu>
     * @author Tuan Nguyen <tuan@yorku.ca>
     */
    public function getItemStatuses()
    {
        try {
            $catalog = ConnectionManager::connectToCatalog();
        } catch (\Exception $e) {
            return $this->output(
                Translator::translate('An error has occurred'), self::STATUS_ERROR
            );
        }
        $ids = $this->params()->fromQuery('id');
        try {
            $results = $catalog->getStatuses($ids);
        } catch (\Exception $e) {
            return $this->output($e->getMessage(), self::STATUS_ERROR);
        }
        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = array();
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        /* TODO
        // Load messages for response:
        $messages = array(
            'available' => $this->view->render('ajax/status-available.phtml'),
            'unavailable' => $this->view->render('ajax/status-unavailable.phtml'),
            'unknown' => $this->view->render('ajax/status-unknown.phtml')
        );
         */

        // Load callnumber and location settings:
        $config = ConfigReader::getConfig();
        $callnumberSetting = isset($config->Item_Status->multiple_call_nos)
            ? $config->Item_Status->multiple_call_nos : 'msg';
        $locationSetting = isset($config->Item_Status->multiple_locations)
            ? $config->Item_Status->multiple_locations : 'msg';
        $showFullStatus = isset($config->Item_Status->show_full_status)
            ? $config->Item_Status->show_full_status : false;

        // Loop through all the status information that came back
        $statuses = array();
        foreach ($results as $recordNumber=>$record) {
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
                    /* TODO
                    $this->view->statusItems = $record;
                    $current['full_status'] = $this->view->render(
                        'ajax/status-full.phtml'
                    );
                     */
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = array(
                'id'                   => $missingId,
                'availability'         => 'false',
                'availability_message' => $messages['unavailable'],
                'location'             => Translator::translate('Unknown'),
                'locationList'         => false,
                'reserve'              => 'false',
                'reserve_message'      => Translator::translate('Not On Reserve'),
                'callnumber'           => '',
                'missing_data'         => true,
                'record_number'        => $recordNumber
            );
        }

        // Done
        $this->output($statuses, self::STATUS_OK);
    }

    /**
     * Support method for getItemStatuses() -- when presented with multiple values,
     * pick which one(s) to send back via AJAX.
     *
     * @param array  $list Array of values to choose from.
     * @param string $mode config.ini setting -- first, all or msg
     * @param string $msg  Message to display if $mode == "msg"
     *
     * @return string
     */
    protected function pickValue($list, $mode, $msg)
    {
        // Make sure array contains only unique values:
        $list = array_unique($list);

        // If there is only one value in the list, or if we're in "first" mode,
        // send back the first list value:
        if ($mode == 'first' || count($list) == 1) {
            return $list[0];
        } else if (count($list) == 0) {
            // Empty list?  Return a blank string:
            return '';
        } else if ($mode == 'all') {
            // All values mode?  Return comma-separated values:
            return implode(', ', $list);
        } else {
            // Message mode?  Return the specified message, translated to the
            // appropriate language.
            return Translator::translate($msg);
        }
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
        $callNumbers = $locations = array();
        $use_unknown_status = $available = false;
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
        }

        // Determine call number string based on findings:
        $callNumber = $this->pickValue(
            $callNumbers, $callnumberSetting, 'Multiple Call Numbers'
        );

        // Determine location string based on findings:
        $location = $this->pickValue(
            $locations, $locationSetting, 'Multiple Locations'
        );

        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        // Send back the collected details:
        return array(
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => htmlentities($location, ENT_COMPAT, 'UTF-8'),
            'locationList' => false,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? Translator::translate('on_reserve')
                : Translator::translate('Not On Reserve'),
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8')
        );
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
        $locations =  array();
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
            }
            // Store call number/location info:
            $locations[$info['location']]['callnumbers'][] = $info['callnumber'];
        }

        // Build list split out by location:
        $locationList = false;
        foreach ($locations as $location => $details) {
            $locationCallnumbers = array_unique($details['callnumbers']);
            // Determine call number string based on findings:
            $locationCallnumbers = $this->pickValue(
                $locationCallnumbers, $callnumberSetting, 'Multiple Call Numbers'
            );
            $locationInfo = array(
                'availability' =>
                    isset($details['available']) ? $details['available'] : false,
                'location' => htmlentities($location, ENT_COMPAT, 'UTF-8'),
                'callnumbers' =>
                    htmlentities($locationCallnumbers, ENT_COMPAT, 'UTF-8')
            );
            $locationList[] = $locationInfo;
        }

        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        // Send back the collected details:
        return array(
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => false,
            'locationList' => $locationList,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? Translator::translate('on_reserve')
                : Translator::translate('Not On Reserve'),
            'callnumber' => false
        );
    }

    /**
     * Check one or more records to see if they are saved in one of the user's list.
     *
     * @return void
     */
    protected function getSaveStatuses()
    {
        // check if user is logged in
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        // loop through each ID check if it is saved to any of the user's lists
        $result = array();
        $ids = $this->params()->fromQuery('id', array());
        $sources = $this->params()->fromQuery('source', array());
        if (!is_array($ids) || !is_array($sources)) {
            return $this->output(
                Translator::translate('Argument must be array.'),
                self::STATUS_ERROR
            );
        }
        foreach ($ids as $i => $id) {
            $source = isset($sources[$i]) ? $sources[$i] : 'VuFind';
            $data = $user->getSavedData($id, null, $source);
            if ($data) {
                // if this item was saved, add it to the list of saved items.
                foreach ($data as $list) {
                    $result[] = array(
                        'record_id' => $id,
                        'record_source' => $source,
                        'resource_id' => $list->id,
                        'list_id' => $list->list_id,
                        'list_title' => $list->list_title,
                        'record_number' => $i
                    );
                }
            }
        }
        $this->output($result, self::STATUS_OK);
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data   The response data
     * @param string $status Status of the request
     *
     * @return void
     */
    protected function output($data, $status)
    {
        if ($this->outputMode == 'json') {
            $response = $this->getResponse();
            $headers = $response->getHeaders();
            $headers->addHeaderLine(
                'Content-type', 'application/javascript'
            );
            $headers->addHeaderLine(
                'Cache-Control', 'no-cache, must-revalidate'
            );
            $headers->addHeaderLine(
                'Expires', 'Mon, 26 Jul 1997 05:00:00 GMT'
            );
            $output = array('data'=>$data,'status'=>$status);
            if ('development' == APPLICATION_ENV && count(self::$php_errors) > 0) {
                $output['php_errors'] = self::$php_errors;
            }
            $response->setContent(json_encode($output));
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
     * @param string $errfile File where error occured
     * @param string $errline Line number of error
     *
     * @return true - to cancel default error handling
     */
    public static function storeError($errno, $errstr, $errfile, $errline)
    {
        self::$php_errors[] = "ERROR [$errno] - ".$errstr."<br />\n"
            . " Occurred in ".$errfile." on line ".$errline.".";
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
     * @return void
     */
    protected function getSalt()
    {
        $this->output($this->generateSalt(), self::STATUS_OK);
    }

    /**
     * Login with post'ed username and encrypted password.
     *
     * @return void
     */
    protected function login()
    {
        // Fetch Salt
        $salt = $this->generateSalt();

        // HexDecode Password
        $password = pack('H*', $this->params()->fromQuery('password'));

        // Decrypt Password
        $password = \VuFind\Crypt\RC4::encrypt($salt, $password);

        // Update the request with the decrypted password:
        $this->getRequest()->getPost()->set('password', $password);

        // Authenticate the user:
        try {
            $this->getAccount()->login($this->getRequest());
        } catch (AuthException $e) {
            return $this->output(
                Translator::translate($e->getMessage()),
                self::STATUS_ERROR
            );
        }

        $this->output(true, self::STATUS_OK);
    }

    /**
     * Tag a record.
     *
     * @return void
     */
    protected function tagRecord()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }
        // empty tag
        try {
            $driver = Record::load(
                $this->params()->fromQuery('id'),
                $this->params()->fromQuery('source', 'VuFind')
            );
            $tag = $this->params()->fromQuery('tag', '');
            if (strlen($tag) > 0) { // don't add empty tags
                $driver->addTags($user, $tag);
            }
        } catch (\Exception $e) {
            return $this->output(
                Translator::translate('Failed'),
                self::STATUS_ERROR
            );
        }

        $this->output(Translator::translate('Done'), self::STATUS_OK);
    }

    /**
     * Get all tags for a record.
     *
     * @return void
     */
    protected function getRecordTags()
    {
        // Retrieve from database:
        $tagTable = new VuFind_Model_Db_Tags();
        $tags = $tagTable->getForResource(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', 'VuFind')
        );

        // Build data structure for return:
        $tagList = array();
        foreach ($tags as $tag) {
            $tagList[] = array('tag'=>$tag->tag, 'cnt'=>$tag->cnt);
        }

        // If we don't have any tags, provide a user-appropriate message:
        if (empty($tagList)) {
            $msg = Translator::translate('No Tags') . ', ' .
                Translator::translate('Be the first to tag this record') . '!';
            return $this->output($msg, self::STATUS_ERROR);
        }

        return $this->output($tagList, self::STATUS_OK);
    }

    /**
     * Get map data on search results and output in JSON
     *
     * @param array $fields Solr fields to retrieve data from
     *
     * @author   Chris Hallberg <crhallberg@gmail.com>
     * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
     *
     * @return void
     */
    public function getMapData($fields = array('long_lat'))
    {
        $params = new \VuFind\Search\Solr\Params();
        $params->initFromRequest($this->getRequest->getQuery());
        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            $results = new \VuFind\Search\Solr\Results($params);

            $facets = $results->getFullFieldFacets($fields, false);

            $markers=array();
            $i = 0;
            $list = isset($facets['long_lat']['data']['list'])
                ? $facets['long_lat']['data']['list'] : array();
            foreach ($list as $location) {
                $longLat = explode(',', $location['value']);
                $markers[$i] = array(
                    'title' => (string)$location['count'], //needs to be a string
                    'location_facet' =>
                        $location['value'], //needed to load in the location
                    'lon' => $longLat[0],
                    'lat' => $longLat[1]
                );
                $i++;
            }
            $this->output($markers, self::STATUS_OK);
        } catch (\Exception $e) {
            echo $e;
            $this->output("", self::STATUS_ERROR);
        }
    }

    /**
     * Get entry information on entries tied to a specific map location
     *
     * @param array $fields Solr fields to retrieve data from
     *
     * @author   Chris Hallberg <crhallberg@gmail.com>
     * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
     *
     * @return void
     */
    public function resultgooglemapinfoAction($fields = array('long_lat'))
    {
        /* TODO
        // Turn layouts on for this action since we want to render the
        // page inside a lightbox:
        $this->_helper->layout->setLayout('lightbox');
        $this->_helper->viewRenderer->setNoRender(false);

        $params = new \VuFind\Search\Solr\Params();
        $params->initFromRequest($this->getRequest()->getQuery());
        $results = new \VuFind\Search\Solr\Results($params);
        $this->view->results = $results;
        $this->view->recordSet = $results->getResults();
        $this->view->recordCount = $results->getResultTotal();
        $this->view->completeListUrl = $results->getUrl()->getParams();
         */
    }

    /**
     * AJAX for timeline feature (PubDateVisAjax)
     *
     * @param array $fields Solr fields to retrieve data from
     *
     * @author   Chris Hallberg <crhallberg@gmail.com>
     * @author   Till Kinstler <kinstler@gbv.de>
     *
     * @return void (thru output)
     */
    public function getVisData($fields = array('publishDate'))
    {
        $params = new \VuFind\Search\Solr\Params();
        $params->initFromRequest($this->getRequest()->getQuery());
        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            $results = new \VuFind\Search\Solr\Results($params);
            $filters = $results->getFilters();
            $dateFacets = $this->params()->fromQuery('facetFields');
            $dateFacets = empty($dateFacets) ? array() : explode(':', $dateFacets);
            $fields = $this->processDateFacets($filters, $dateFacets, $results);
            $facets = $this->processFacetValues($fields, $results);
            foreach ($fields as $field => $val) {
                $facets[$field]['min'] = $val[0] > 0 ? $val[0] : 0;
                $facets[$field]['max'] = $val[1] > 0 ? $val[1] : 0;
                $facets[$field]['removalURL']
                    = $results->getUrl()->removeFacet(
                        $field,
                        isset($filters[$field][0]) ? $filters[$field][0] : null,
                        false
                    );
            }
            $this->output($facets, self::STATUS_OK);
        } catch (\Exception $e) {
            echo $e;
            $this->output("", self::STATUS_ERROR);
        }
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
        $result = array();
        foreach ($dateFacets as $current) {
            $from = $to = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if (preg_match('/\[\d+ TO \d+\]/', $filter)) {
                        $range = explode(' TO ', trim($filter, '[]'));
                        $from = $range[0] == '*' ? '' : $range[0];
                        $to = $range[1] == '*' ? '' : $range[1];
                        break;
                    }
                }
            }
            $result[$current] = array($from, $to);
            $result[$current]['label']
                = $results->getFacetLabel($current);
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
        $retVal = array();
        foreach ($facets as $field => $values) {
            $newValues = array('data' => array());
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match("/^[0-9]+$/", $current['value'])) {
                    $newValues['data'][]
                        = array($current['value'],$current['count']);
                }
            }
            $retVal[$field] = $newValues;
        }
        return $retVal;
    }

    /**
     * Save a record to a list.
     *
     * @return void
     */
    public function saveRecord()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $driver = Record::load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', 'VuFind')
        );
        $driver->saveToFavorites($this->getRequest()->getQuery(), $user);
        return $this->output('Done', self::STATUS_OK);
    }

    /**
     * Saves records to a User's favorites
     *
     * @return void
     * @access public
     */
    public function bulkSave()
    {
        /* TODO
        // Without IDs, we can't continue
        $ids = $this->params()->fromQuery('ids', array());
        if (empty($ids)) {
            return $this->output(
                array('result'=>Translator::translate('bulk_error_missing')),
                self::STATUS_ERROR
            );
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        try {
            $this->_helper->favorites->saveBulk(
                $this->getRequest()->getParams(), $user
            );
            return $this->output(
                array(
                    'result' => array('list' => $this->params()->fromQuery('list')),
                    'info' => Translator::translate("bulk_save_success")
                ), self::STATUS_OK
            );
        } catch (\Exception $e) {
            return $this->output(
                array('info' => Translator::translate('bulk_save_error')),
                self::STATUS_ERROR
            );
        }
         */
    }

    /**
     * Add a list.
     *
     * @return void
     */
    public function addList()
    {
        $user = $this->getUser();

        try {
            $list = VuFind_Model_Db_UserList::getNew($user);
            $id = $list->updateFromRequest($this->getRequest()->getQuery());
        } catch (\Exception $e) {
            switch(get_class($e)) {
            case 'VuFind\\Exception\\LoginRequired':
                return $this->output(
                    Translator::translate('You must be logged in first'),
                    self::STATUS_NEED_AUTH
                );
                break;
            case 'VuFind\\Exception\\ListPermission':
            case 'VuFind\\Exception\\MissingField':
                return $this->output(
                    Translator::translate($e->getMessage()), self::STATUS_ERROR
                );
            default:
                throw $e;
            }
        }

        return $this->output(array('id' => $id), self::STATUS_OK);
    }

    /**
     * Get Autocomplete suggestions.
     *
     * @return void
     */
    public function getACSuggestions()
    {
        /* TODO
        return $this->output(
            array_values(
                VF_Autocomplete_Factory::getSuggestions(
                    $this->getRequest()->getQuery()
                )
            ),
            self::STATUS_OK
        );
         */
    }

    /**
     * Text a record.
     *
     * @return void
     */
    public function smsRecord()
    {
        /* TODO
        // Attempt to send the email:
        try {
            $record = Record::load(
                $this->params()->fromQuery('id'),
                $this->params()->fromQuery('source', 'VuFind')
            );
            $mailer = new VF_Mailer_SMS();
            $mailer->textRecord(
                $this->params()->fromQuery('provider'),
                $this->params()->fromQuery('to'), $record, $this->view
            );
            return $this->output(
                Translator::translate('sms_success'), self::STATUS_OK
            );
        } catch (\Exception $e) {
            return $this->output(
                Translator::translate($e->getMessage()), self::STATUS_ERROR
            );
        }
         */
    }


    /**
     * Email a record.
     *
     * @return void
     */
    public function emailRecord()
    {
        /* TODO
        // Attempt to send the email:
        try {
            $record = Record::load(
                $this->params()->fromQuery('id'),
                $this->params()->fromQuery('source', 'VuFind')
            );
            $mailer = new VF_Mailer();
            $mailer->sendRecord(
                $this->params()->fromQuery('to'), $this->params()->fromQuery('from'),
                $this->params()->fromQuery('message'), $record, $this->view
            );
            return $this->output(
                Translator::translate('email_success'), self::STATUS_OK
            );
        } catch (\Exception $e) {
            return $this->output(
                Translator::translate($e->getMessage()), self::STATUS_ERROR
            );
        }
         */
    }

    /**
     * Email a search.
     *
     * @return void
     */
    public function emailSearch()
    {
        /* TODO
        // Make sure URL is properly formatted -- if no protocol is specified, run it
        // through the fullUrl helper:
        $url = $this->params()->fromQuery('url');
        if (substr($url, 0, 4) != 'http') {
            $url = $this->view->fullUrl($url);
        }

        // Attempt to send the email:
        try {
            $mailer = new VF_Mailer();
            $mailer->sendLink(
                $this->params()->fromQuery('to'), $this->params()->fromQuery('from'),
                $this->params()->fromQuery('message'),
                $url, $this->view, $this->params()->fromQuery('subject')
            );
            return $this->output(
                Translator::translate('email_success'), self::STATUS_OK
            );
        } catch (\Exception $e) {
            return $this->output(
                Translator::translate($e->getMessage()), self::STATUS_ERROR
            );
        }
         */
    }

    /**
     * Check Request is Valid
     *
     * @return void
     */
    public function checkRequestIsValid()
    {
        $id = $this->params()->fromQuery('id');
        $data = $this->params()->fromQuery('data');
        if (!empty($id) && !empty($data)) {
            // check if user is logged in
            $user = $this->getUser();
            if (!$user) {
                return $this->output(
                    Translator::translate('You must be logged in first'),
                    self::STATUS_NEED_AUTH
                );
            }

            try {
                $catalog = ConnectionManager::connectToCatalog();
                $patron = $this->getAccount()->storedCatalogLogin();
                if ($patron) {
                    $results = $catalog->checkRequestIsValid($id, $data, $patron);

                    $msg = $results
                        ? Translator::translate('request_place_text')
                        : Translator::translate('hold_error_blocked');
                    return $this->output(
                        array('status' => $results, 'msg' => $msg), self::STATUS_OK
                    );
                }
            } catch (\Exception $e) {
                // Do nothing -- just fail through to the error message below.
            }
        }

        return $this->output(
            Translator::translate('An error has occurred'), self::STATUS_ERROR
        );
    }

    /**
     * Comment on a record.
     *
     * @return void
     */
    public function commentRecord()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $id = $this->params()->fromQuery('id');
        $comment = $this->params()->fromQuery('comment');
        if (empty($id) || empty($comment)) {
            return $this->output(
                Translator::translate('An error has occurred'), self::STATUS_ERROR
            );
        }

        $table = new VuFind_Model_Db_Resource();
        $resource = $table->findResource(
            $id, $this->params()->fromQuery('source', 'VuFind')
        );
        $id = $resource->addComment($comment, $user);

        return $this->output($id, self::STATUS_OK);
    }

    /**
     * Delete a comment on a record.
     *
     * @return void
     */
    public function deleteRecordComment()
    {
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $id = $this->params()->fromQuery('id');
        $table = new VuFind_Model_Db_Comments();
        if (empty($id) || !$table->deleteIfOwnedByUser($id, $user)) {
            return $this->output(
                Translator::translate('An error has occurred'), self::STATUS_ERROR
            );
        }

        return $this->output(Translator::translate('Done'), self::STATUS_OK);
    }

    /**
     * Get list of comments for a record as HTML.
     *
     * @return void
     */
    public function getRecordCommentsAsHTML()
    {
        /* TODO
        $this->view->driver = Record::load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', 'VuFind')
        );
        $html = $this->view->render('record/comments-list.phtml');
        return $this->output($html, self::STATUS_OK);
         */
    }

    /**
     * Delete multiple items from favorites or a list.
     *
     * @return void
     */
    protected function deleteFavorites()
    {
        /* TODO
        $user = $this->getUser();
        if ($user === false) {
            return $this->output(
                Translator::translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $listID = $this->params()->fromQuery('listID');
        $ids = $this->params()->fromQuery('ids');

        if (!is_array($ids)) {
            return $this->output(
                Translator::translate('delete_missing'),
                self::STATUS_ERROR
            );
        }

        $this->_helper->favorites->delete($ids, $listID, $user);
        return $this->output(
            array('result' => Translator::translate('fav_delete_success')),
            self::STATUS_OK
        );
         */
    }

    /**
     * Delete records from a User's cart
     *
     * @return void
     */
    protected function removeItemsCart()
    {
        // Without IDs, we can't continue
        $ids = $this->params()->fromQuery('ids');
        if (empty($ids)) {
            return $this->output(
                array('result'=>Translator::translate('bulk_error_missing')),
                self::STATUS_ERROR
            );
        }
        Cart::getInstance()->removeItems($ids);
        return $this->output(array('delete' => true), self::STATUS_OK);
    }

    /**
     * Process an export request
     *
     * @return void
     */
    protected function exportFavorites()
    {
        /* TODO
        $format = $this->params()->fromQuery('format');
        $url = Export::getBulkUrl(
            $this->view, $format, $this->params()->fromQuery('ids', array())
        );
        $this->view->url = $url;
        $this->view->format = $format;
        $html = $this->view->render('ajax/export-favorites.phtml');
        return $this->output(
            array(
                'result' => Translator::translate('Done'),
                'result_additional' => $html
            ), self::STATUS_OK
        );
         */
    }

    /**
     * Fetch Links from resolver given an OpenURL and format as HTML
     * and output the HTML content in JSON object.
     *
     * @return void
     * @author Graham Seaman <Graham.Seaman@rhul.ac.uk>
     */
    protected function getResolverLinks()
    {
        /* TODO
        $openUrl = $this->params()->fromQuery('openurl', '');

        $config = ConfigReader::getConfig();
        $resolverType = isset($config->OpenURL->resolver)
            ? $config->OpenURL->resolver : 'other';
        $resolver = new VF_Resolver_Connection($resolverType);
        if (!$resolver->driverLoaded()) {
            return $this->output(
                Translator::translate("Could not load driver for $resolverType"),
                self::STATUS_ERROR
            );
        }

        $result = $resolver->fetchLinks($openUrl);

        // Sort the returned links into categories based on service type:
        $electronic = $print = $services = array();
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
                $link['title'] = Translator::translate('Get full text');
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
        $this->view->openUrlBase = $base;
        $this->view->openUrl = $openUrl;
        $this->view->print = $print;
        $this->view->electronic = $electronic;
        $this->view->services = $services;
        $html = $this->view->render('ajax/resolverLinks.phtml');

        // output HTML encoded in JSON object
        return $this->output($html, self::STATUS_OK);
         */
    }
}
