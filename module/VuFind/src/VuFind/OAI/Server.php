<?php
/**
 * OAI Server class
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
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/tracking_record_changes Wiki
 */
namespace VuFind\OAI;
use SimpleXMLElement, VuFind\Config\Reader as ConfigReader,
    VuFind\Db\Table\ChangeTracker as ChangeTrackerTable,
    VuFind\Db\Table\OaiResumption as OaiResumptionTable,
    VuFind\Exception\RecordMissing as RecordMissingException, VuFind\SimpleXML;

/**
 * OAI Server class
 *
 * This class provides OAI server functionality.
 *
 * @category VuFind2
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/tracking_record_changes Wiki
 */
class Server
{
    protected $baseURL;                         // Repository base URL
    protected $params;                          // Incoming request parameters
    protected $searchClassId = 'Solr';          // Search object class to use
    protected $core = 'biblio';                 // What Solr core are we serving up?
    protected $iso8601 = 'Y-m-d\TH:i:s\Z';      // ISO-8601 date format
    protected $pageSize = 100;                  // Records per page in lists
    protected $setField = null;                 // Solr field for set membership

    // Supported metadata formats:
    protected $metadataFormats = array();

    // Namespace used for ID prefixing (if any):
    protected $idNamespace = null;

    // Values used in "Identify" response:
    protected $repositoryName = 'VuFind';
    protected $earliestDatestamp = '2000-01-01T00:00:00Z';
    protected $adminEmail;

    // Search manager:
    protected $searchManager;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Manager $sm      Search manager for retrieving records
     * @param string                 $baseURL The base URL for the OAI server
     * @param array                  $params  The incoming OAI-PMH parameters
     * (i.e. $_GET)
     */
    public function __construct(\VuFind\Search\Manager $sm, $baseURL, $params)
    {
        $this->searchManager = $sm;
        $this->baseURL = $baseURL;
        $this->params = isset($params) && is_array($params) ? $params : array();
        $this->initializeMetadataFormats(); // Load details on supported formats
        $this->initializeSettings();        // Load config.ini settings
    }

    /**
     * Respond to the OAI-PMH request.
     *
     * @return string
     */
    public function getResponse()
    {
        if (!$this->hasParam('verb')) {
            return $this->showError('badArgument', 'Missing Verb Argument');
        } else {
            switch($this->params['verb']) {
            case 'GetRecord':
                return $this->getRecord();
            case 'Identify':
                return $this->identify();
            case 'ListIdentifiers':
            case 'ListRecords':
                return $this->listRecords($this->params['verb']);
            case 'ListMetadataFormats':
                return $this->listMetadataFormats();
            case 'ListSets':
                return $this->listSets();
            default:
                return $this->showError('badVerb', 'Illegal OAI Verb');
            }
        }
    }

    /**
     * Assign necessary interface variables to display a deleted record.
     *
     * @param SimpleXMLElement $xml        XML to update
     * @param object           $tracker    A change_tracker DB row object
     * @param bool             $headerOnly Only attach the header?
     *
     * @return void
     */
    protected function attachDeleted($xml, $tracker, $headerOnly = false)
    {
        // Deleted records only have a header, no metadata.  However, depending
        // on the context we are attaching them, they may or may not need a
        // <record> tag wrapping the header.
        $record = $headerOnly ? $xml : $xml->addChild('record');
        $this->attachRecordHeader(
            $record, $this->prefixID($tracker->id),
            date($this->iso8601, $this->normalizeDate($tracker->deleted)),
            array(),
            'deleted'
        );
    }

    /**
     * Attach a record header to an XML document.
     *
     * @param SimpleXMLElement $xml    XML to update
     * @param string           $id     Record id
     * @param string           $date   Record modification date
     * @param array            $sets   Set(s) containing record
     * @param string           $status Record status code
     *
     * @return void
     */
    protected function attachRecordHeader($xml, $id, $date, $sets = array(), 
        $status = ''
    ) {
        $header = $xml->addChild('header');
        if (!empty($status)) {
            $header['status'] = $status;
        }
        $header->identifier = $id;
        $header->datestamp = $date;
        foreach ($sets as $set) {
            $header->addChild('setSpec', htmlspecialchars($set));
        }
    }

    /**
     * Attach a non-deleted record to an XML document.
     *
     * @param SimpleXMLElement $container  XML container for new record
     * @param object           $record     A record driver object
     * @param string           $format     Metadata format to obtain (false for none)
     * @param bool             $headerOnly Only attach the header?
     *
     * @return bool
     */
    protected function attachNonDeleted($container, $record, $format,
        $headerOnly = false
    ) {
        // Get the XML (and display an error if it is unsupported):
        if ($format === false) {
            $xml = '';      // no metadata if in header-only mode!
        } else {
            $xml = $record->getXML($format);
            if ($xml === false) {
                return false;
            }
        }

        // Check for sets:
        $fields = $record->getAllFields();
        if (!is_null($this->setField) && !empty($fields[$this->setField])) {
            $sets = $fields[$this->setField];
        } else {
            $sets = array();
        }

        // Get modification date:
        $date = $record->getLastIndexed();
        if (empty($date)) {
            $date = date($this->iso8601);
        }

        // Set up header (inside or outside a <record> container depending on
        // preferences):
        $recXml = $headerOnly ? $container : $container->addChild('record');
        $this->attachRecordHeader(
            $recXml, $this->prefixID($record->getUniqueID()), $date, $sets
        );

        // Inject metadata if necessary:
        if (!$headerOnly) {
            $metadata = $recXml->addChild('metadata');
            SimpleXML::appendElement($metadata, simplexml_load_string($xml));
        }

        return true;
    }

    /**
     * Respond to a GetRecord request.
     *
     * @return string
     */
    protected function getRecord()
    {
        // Validate parameters
        if (!$this->hasParam('metadataPrefix')) {
            return $this->showError('badArgument', 'Missing Metadata Prefix');
        }
        if (!$this->hasParam('identifier')) {
            return $this->showError('badArgument', 'Missing Identifier');
        }

        // Start building response
        $xml = new SimpleXMLElement('<GetRecord />');

        // Retrieve the record from the index
        if ($record = $this->loadRecord($this->params['identifier'])) {
            if (!$this->attachNonDeleted(
                $xml, $record, $this->params['metadataPrefix']
            )) {
                return $this->showError('cannotDisseminateFormat', 'Unknown Format');
            }
        } else {
            // No record in index -- is this deleted?
            $tracker = new ChangeTrackerTable();
            $row = $tracker->retrieve(
                $this->core, $this->stripID($this->params['identifier'])
            );
            if (!empty($row) && !empty($row->deleted)) {
                $this->attachDeleted($xml, $row);
            } else {
                // Not deleted and not found in index -- error!
                return $this->showError('idDoesNotExist', 'Unknown Record');
            }
        }

        // Display the record:
        return $this->showResponse($xml);
    }

    /**
     * Was the specified parameter provided?
     *
     * @param string $param Name of the parameter to check.
     *
     * @return bool         True if parameter is set and non-empty.
     */
    protected function hasParam($param)
    {
        return (isset($this->params[$param]) && !empty($this->params[$param]));
    }

    /**
     * Respond to an Identify request:
     *
     * @return string
     */
    protected function identify()
    {
        $xml = new SimpleXMLElement('<Identify />');
        $xml->repositoryName = $this->repositoryName;
        $xml->baseURL = $this->baseURL;
        $xml->protocolVersion = '2.0';
        $xml->earliestDatestamp = $this->earliestDatestamp;
        $xml->deletedRecord = 'transient';
        $xml->granularity = 'YYYY-MM-DDThh:mm:ssZ';
        $xml->adminEmail = $this->adminEmail;
        if (!empty($this->idNamespace)) {
            $xml->addChild('description');
            $id = $xml->description->addChild(
                'oai-identifier', null,
                'http://www.openarchives.org/OAI/2.0/oai-identifier'
            );
            $id->addAttribute(
                'xsi:schemaLocation',
                'http://www.openarchives.org/OAI/2.0/oai-identifier '
                . 'http://www.openarchives.org/OAI/2.0/oai-identifier.xsd',
                'http://www.w3.org/2001/XMLSchema-instance'
            );
            $id->scheme = 'oai';
            $id->repositoryIdentifier = $this->idNamespace;
            $id->delimiter = ':';
            $id->sampleIdentifier = 'oai:' . $this->idNamespace . ':123456';
        }

        return $this->showResponse($xml);
    }

    /**
     * Load data about metadata formats.  (This is called by the constructor
     * and is only a separate method to allow easy override by child classes).
     *
     * @return void
     */
    protected function initializeMetadataFormats()
    {
        $this->metadataFormats['oai_dc'] = array(
            'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $this->metadataFormats['marc21'] = array(
            'schema' => 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
            'namespace' => 'http://www.loc.gov/MARC21/slim');
    }

    /**
     * Load data from the OAI section of config.ini.  (This is called by the
     * constructor and is only a separate method to allow easy override by child
     * classes).
     *
     * @return void
     */
    protected function initializeSettings()
    {
        $config = ConfigReader::getConfig();

        // Override default repository name if configured:
        if (isset($config->OAI->repository_name)) {
            $this->repositoryName = $config->OAI->repository_name;
        }

        // Override default ID namespace if configured:
        if (isset($config->OAI->identifier)) {
            $this->idNamespace = $config->OAI->identifier;
        }

        // Use either OAI-specific or general email address; we must have SOMETHING.
        $this->adminEmail = isset($config->OAI->admin_email) ?
            $config->OAI->admin_email : $config->Site->email;

        // Use a Solr field to determine sets, if configured:
        if (isset($config->OAI->set_field)) {
            $this->setField = $config->OAI->set_field;
        }
    }

    /**
     * Respond to a ListMetadataFormats request.
     *
     * @return string
     */
    protected function listMetadataFormats()
    {
        // If a specific ID was provided, try to load the related record; otherwise,
        // set $record to false so we know it is a generic request.
        if (isset($this->params['identifier'])) {
            if (!($record = $this->loadRecord($this->params['identifier']))) {
                return $this->showError('idDoesNotExist', 'Unknown Record');
            }
        } else {
            $record = false;
        }

        // Loop through all available metadata formats and see if they apply in
        // the current context (all apply if $record is false, since that
        // means that no specific record ID was requested; otherwise, they only
        // apply if the current record driver supports them):
        $xml = new SimpleXMLElement('<ListMetadataFormats />');
        foreach ($this->metadataFormats as $prefix => $details) {
            if ($record === false
                || $record->getXML($prefix) !== false
            ) {
                $node = $xml->addChild('metadataFormat');
                $node->metadataPrefix = $prefix;
                if (isset($details['schema'])) {
                    $node->schema = $details['schema'];
                }
                if (isset($details['namespace'])) {
                    $node->metadataNamespace = $details['namespace'];
                }
            }
        }

        // Display the response:
        return $this->showResponse($xml);
    }

    /**
     * Respond to a ListIdentifiers or ListRecords request (the $verb parameter
     * determines the exact format of the response).
     *
     * @param string $verb 'ListIdentifiers' or 'ListRecords'
     *
     * @return string
     */
    protected function listRecords($verb = 'ListRecords')
    {
        // Load and validate parameters; if an Exception is thrown, we need to parse
        // and output an appropriate error.
        try {
            $params = $this->listRecordsGetParams();
        } catch (\Exception $e) {
            $parts = explode(':', $e->getMessage(), 2);
            if (count($parts) != 2) {
                throw $e;
            }
            return $this->showError($parts[0], $parts[1]);
        }

        // Normalize the provided dates into Unix timestamps.  Depending on whether
        // they come from the OAI-PMH request or the database, the format may be
        // slightly different; this ensures they are reduced to a consistent value!
        $from = $this->normalizeDate($params['from']);
        $until = $this->normalizeDate($params['until']);
        if (!$this->listRecordsValidateDates($from, $until)) {
            return;
        }

        // Initialize the array of XML chunks to include in our response:
        $xmlParts = array();

        // Copy the cursor from the parameters so we can track our current position
        // separately from our initial position!
        $currentCursor = $params['cursor'];

        // The template for displaying a single record varies based on the verb:
        $xml = new SimpleXMLElement("<{$verb} />");

        // The verb determines whether we're returning headers only or full records:
        $headersOnly = ($verb != 'ListRecords');

        // Get deleted records in the requested range (if applicable):
        $deleted = $this->listRecordsGetDeleted($from, $until);
        $deletedCount = count($deleted);
        if ($currentCursor < $deletedCount) {
            $limit = $currentCursor + $this->pageSize;
            $limit = $limit > $deletedCount ? $deletedCount : $limit;
            for ($i = $currentCursor; $i < $limit; $i++) {
                $deleted->seek($i);
                $this->attachDeleted($xml, $deleted->current(), $headersOnly);
                $currentCursor++;
            }
        }

        // Figure out how many Solr records we need to display (and where to start):
        if ($currentCursor >= $deletedCount) {
            $solrOffset = $currentCursor - $deletedCount;
        } else {
            $solrOffset = 0;
        }
        $solrLimit = ($params['cursor'] + $this->pageSize) - $currentCursor;

        // Get non-deleted records from the Solr index:
        $result = $this->listRecordsGetNonDeleted(
            $from, $until, $solrOffset, $solrLimit, $params['set']
        );
        $nonDeletedCount = $result->getResultTotal();
        $format = $verb == 'ListIdentifiers' ? false : $params['metadataPrefix'];
        foreach ($result->getResults() as $doc) {
            if (!$this->attachNonDeleted($xml, $doc, $format, $headersOnly)) {
                $this->unexpectedError('Cannot load document');
            }
            $currentCursor++;
        }

        // If our cursor didn't reach the last record, we need a resumption token!
        $listSize = $deletedCount + $nonDeletedCount;
        if ($listSize > $currentCursor) {
            $this->saveResumptionToken($xml, $params, $currentCursor, $listSize);
        } else if ($solrOffset > 0) {
            // If we reached the end of the list but there is more than one page, we
            // still need to display an empty <resumptionToken> tag:
            $token = $xml->addChild('resumptionToken');
            $token->addAttribute('completeListSize', $listSize);
            $token->addAttribute('cursor', $params['cursor']);
        }

        return $this->showResponse($xml);
    }

    /**
     * Respond to a ListSets request.
     *
     * @return string
     */
    protected function listSets()
    {
        // Resumption tokens are not currently supported for this verb:
        if ($this->hasParam('resumptionToken')) {
            return $this->showError(
                'badResumptionToken', 'Invalid resumption token'
            );
        }

        // If no set field is enabled, we can't provide a set list:
        if (is_null($this->setField)) {
            return $this->showError('noSetHierarchy', 'Sets not supported');
        }

        // If we got this far, we can load all available set values.  For now,
        // we'll assume that this list is short enough to load in a single response;
        // it may be necessary to implement a resumption token mechanism if this
        // proves not to be the case:
        $this->searchManager->setSearchClassId($this->searchClassId);
        $params = $this->searchManager->getParams();
        $results = $this->searchManager->getResults($params);
        try {
            $facets = $results->getFullFieldFacets(array($this->setField));
        } catch (\Exception $e) {
            $facets = null;
        }
        if (empty($facets) || !isset($facets[$this->setField]['data']['list'])) {
            $this->unexpectedError('Cannot find sets');
        }

        // Extract facet values from the Solr response:
        $xml = new SimpleXMLElement('<ListSets />');
        foreach ($facets[$this->setField]['data']['list'] as $x) {
            $set = $xml->addChild('set');
            $set->setSpec = $x['value'];
            $set->setName = $x['displayText'];
        }

        // Display the list:
        return $this->showResponse($xml);
    }

    /**
     * Get an object to list deleted records in the specified range.
     *
     * @param int $from  Start date.
     * @param int $until End date.
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    protected function listRecordsGetDeleted($from, $until)
    {
        $tracker = new ChangeTrackerTable();
        return $tracker->retrieveDeleted(
            $this->core, date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $until)
        );
    }

    /**
     * Get an array of information on non-deleted records in the specified range.
     *
     * @param int    $from   Start date.
     * @param int    $until  End date.
     * @param int    $offset First record to obtain in full detail.
     * @param int    $limit  Max number of full records to return.
     * @param string $set    Set to limit to (empty string for none).
     *
     * @return \VuFind\Search\Base\Results Search result object.
     */
    protected function listRecordsGetNonDeleted($from, $until, $offset, $limit,
        $set = ''
    ) {
        // Set up search parameters:
        $this->searchManager->setSearchClassId($this->searchClassId);
        $params = $this->searchManager->getParams();
        $params->setLimit($limit);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $params->recommendationsEnabled(false);
        $params->setSort('last_indexed asc', true);

        // Construct a range query based on last indexed time:
        $params->setOverrideQuery(
            'last_indexed:[' . date($this->iso8601, $from) . ' TO '
            . date($this->iso8601, $until) . ']'
        );

        // Apply filters as needed.
        if (!empty($set) && !is_null($this->setField)) {
            $params->addFilter(
                $this->setField . ':"' . addcslashes($set, '"') . '"'
            );
        }

        // Perform a Solr search:
        $results = $this->searchManager->getResults($params);
        $results->overrideStartRecord($offset + 1);

        // Return our results:
        return $results;
    }

    /**
     * Get parameters for use in the listRecords method.
     *
     * @throws \Exception
     * @return mixed Array of parameters or false on error
     */
    protected function listRecordsGetParams()
    {
        // If we received a resumption token, use it to override any existing
        // parameters or fail if it is invalid.
        if (!empty($this->params['resumptionToken'])) {
            $params = $this->loadResumptionToken($this->params['resumptionToken']);
            if ($params === false) {
                throw new \Exception(
                    'badResumptionToken:Invalid or expired resumption token'
                );
            }

            // Merge restored parameters with incoming parameters:
            $params = array_merge($params, $this->params);
        } else {
            // No resumption token?  Use the provided parameters:
            $params = $this->params;

            // Make sure we don't act on any user-provided cursor settings; this
            // value should only be set in association with resumption tokens!
            $params['cursor'] = 0;

            // Set default date range if not already provided:
            if (empty($params['from'])) {
                $params['from'] = $this->earliestDatestamp;
            }
            if (empty($params['until'])) {
                $params['until'] = date($this->iso8601);
            }
        }

        // If no set field is configured and a set parameter comes in, we have a
        // problem:
        if (is_null($this->setField) && isset($params['set'])
            && !empty($params['set'])
        ) {
            throw new \Exception('noSetHierarchy:Sets not supported');
        }

        // Validate requested metadata format:
        $prefixes = array_keys($this->metadataFormats);
        if (!in_array($params['metadataPrefix'], $prefixes)) {
            throw new \Exception('cannotDisseminateFormat:Unknown Format');
        }

        return $params;
    }

    /**
     * Validate the from and until parameters for the listRecords method.
     *
     * @param int $from  Timestamp for start date.
     * @param int $until Timestamp for end date.
     *
     * @return bool      True if valid, false if not.
     */
    protected function listRecordsValidateDates($from, $until)
    {
        // Validate dates:
        if (!$from || !$until) {
            return $this->showError('badArgument', 'Bad Date Format');
        }
        if ($from > $until) {
            return $this->showError(
                'badArgument', 'End date must be after start date'
            );
        }
        if ($from < $this->normalizeDate($this->earliestDatestamp)) {
            return $this->showError(
                'badArgument', 'Start date must be after earliest date'
            );
        }

        // If we got this far, everything is valid!
        return true;
    }

    /**
     * Load a specific record from the index.
     *
     * @param string $id The record ID to load
     *
     * @return mixed     The record array (if successful) or false
     */
    protected function loadRecord($id)
    {
        // Strip the ID prefix, if necessary:
        $id = $this->stripID($id);
        if ($id !== false) {
            $resultsClass = 'VuFind\Search\\' . $this->searchClassId . '\Results';
            try {
                return $resultsClass::getRecord($id);
            } catch (RecordMissingException $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Load parameters associated with a resumption token.
     *
     * @param string $token The resumption token to look up
     *
     * @return array        Parameters associated with token
     */
    protected function loadResumptionToken($token)
    {
        // Create object for loading tokens:
        $search = new OaiResumptionTable();

        // Clean up expired records before doing our search:
        $search->removeExpired();

        // Load the requested token if it still exists:
        if ($row = $search->findToken($token)) {
            return $row->restoreParams();
        }

        // If we got this far, the token is invalid or expired:
        return false;
    }

    /**
     * Normalize a date to a Unix timestamp.
     *
     * @param string $date Date (ISO-8601 or YYYY-MM-DD HH:MM:SS)
     *
     * @return integer     Unix timestamp (or false if $date invalid)
     */
    protected function normalizeDate($date)
    {
        // Remove timezone markers -- we don't want PHP to outsmart us by adjusting
        // the time zone!
        $date = str_replace(array('T', 'Z'), array(' ', ''), $date);

        // Translate to a timestamp:
        return strtotime($date);
    }

    /**
     * Prepend the OAI prefix to the provided ID number.
     *
     * @param string $id The ID to update.
     *
     * @return string    The prefixed ID.
     */
    protected function prefixID($id)
    {
        $prefix = empty($this->idNamespace)
            ? '' : 'oai:' . $this->idNamespace . ':';
        return $prefix . $id;
    }

    /**
     * Generate a resumption token to continue the current operation.
     *
     * @param SimpleXMLElement $xml           XML document to update with token.
     * @param array            $params        Current operational parameters.
     * @param int              $currentCursor Current cursor position in search
     * results.
     * @param int              $listSize      Total size of search results.
     *
     * @return void
     */
    protected function saveResumptionToken($xml, $params, $currentCursor, $listSize)
    {
        // Save the old cursor position before overwriting it for storage in the
        // database!
        $oldCursor = $params['cursor'];
        $params['cursor'] = $currentCursor;

        // Save everything to the database:
        $search = new OaiResumptionTable();
        $expire = time() + 24 * 60 * 60;
        $token = $search->saveToken($params, $expire);

        // Add details to the xml:
        $token = $xml->addChild('resumptionToken', $token);
        $token->addAttribute('cursor', $oldCursor);
        $token->addAttribute('expirationDate', date($this->iso8601, $expire));
        $token->addAttribute('completeListSize', $listSize);
    }

    /**
     * Display an error response.
     *
     * @param string $code    The error code to display
     * @param string $message The error string to display
     *
     * @return string
     */
    protected function showError($code, $message)
    {
        $xml = new SimpleXMLElement(
            '<error>' . htmlspecialchars($message) . '</error>'
        );
        if (!empty($code)) {
            $xml['code'] = $code;
        }

        // Certain errors should not echo parameters:
        $echoParams = !($code == 'badVerb' || $code == 'badArgument');
        return $this->showResponse($xml, $echoParams);
    }

    /**
     * Display an OAI-PMH response (shared support method used by various
     * response-specific methods).
     *
     * @param SimpleXMLElement $body       Main body of response.
     * @param bool             $echoParams Include params in <request> tag?
     *
     * @return string
     */
    protected function showResponse($body, $echoParams = true)
    {
        // Set up standard response wrapper:
        $xml = simplexml_load_string(
            '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" />'
        );
        $xml->addAttribute(
            'xsi:schemaLocation',
            'http://www.openarchives.org/OAI/2.0/ '
            . 'http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $xml->responseDate = date($this->iso8601);
        $xml->request = $this->baseURL;
        if ($echoParams) {
            foreach ($this->params as $key => $value) {
                $xml->request[$key] = $value;
            }
        }

        // Attach main body:
        SimpleXML::appendElement($xml, $body);

        return $xml->asXml();
    }

    /**
     * Strip the OAI prefix from the provided ID number.
     *
     * @param string $id The ID to strip.
     *
     * @return string    The stripped ID (false if prefix invalid).
     */
    protected function stripID($id)
    {
        // No prefix?  No stripping!
        if (empty($this->idNamespace)) {
            return $id;
        }

        // Prefix?  Strip it off and return the stripped version if valid:
        $prefix = 'oai:' . $this->idNamespace . ':';
        $prefixLen = strlen($prefix);
        if (!empty($prefix) && substr($id, 0, $prefixLen) == $prefix) {
            return substr($id, $prefixLen);
        }

        // Invalid prefix -- unrecognized ID:
        return false;
    }

    /**
     * Die with an unexpected error code (when something outside the scope of
     * OAI-PMH fails).
     *
     * @param string $msg Error message
     *
     * @throws \Exception
     * @return void
     */
    protected function unexpectedError($msg)
    {
        throw new \Exception("Unexpected fatal error -- {$msg}.");
    }
}
?>