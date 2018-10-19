<?php
/**
 * OAI Server class
 *
 * PHP version 7
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
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\OAI;

use SimpleXMLElement;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\SimpleXML;

/**
 * OAI Server class
 *
 * This class provides OAI server functionality.
 *
 * @category VuFind
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Server
{
    /**
     * Repository base URL
     *
     * @var string
     */
    protected $baseURL;

    /**
     * Base URL of host containing VuFind.
     *
     * @var string
     */
    protected $baseHostURL;

    /**
     * Incoming request parameters
     *
     * @var array
     */
    protected $params;

    /**
     * Search object class to use
     *
     * @var string
     */
    protected $searchClassId = 'Solr';

    /**
     * What Solr core are we serving up?
     *
     * @var string
     */
    protected $core = 'biblio';

    /**
     * ISO-8601 date format
     *
     * @var string
     */
    protected $iso8601 = 'Y-m-d\TH:i:s\Z';

    /**
     * Records per page in lists
     *
     * @var int
     */
    protected $pageSize = 100;

    /**
     * Solr field for set membership
     *
     * @var string
     */
    protected $setField = null;

    /**
     * Supported metadata formats
     *
     * @var array
     */
    protected $metadataFormats = [];

    /**
     * Namespace used for ID prefixing (if any)
     *
     * @var string
     */
    protected $idNamespace = null;

    /**
     * Repository name used in "Identify" response
     *
     * @var string
     */
    protected $repositoryName = 'VuFind';

    /**
     * Earliest datestamp used in "Identify" response
     *
     * @var string
     */
    protected $earliestDatestamp = '2000-01-01T00:00:00Z';

    /**
     * Admin email used in "Identify" response
     *
     * @var string
     */
    protected $adminEmail;

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Table manager
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $tableManager;

    /**
     * Record link helper (optional)
     *
     * @var \VuFind\View\Helper\Root\RecordLink
     */
    protected $recordLinkHelper = null;

    /**
     * Set queries
     *
     * @var array
     */
    protected $setQueries = [];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Search manager for
     * retrieving records
     * @param \VuFind\Record\Loader                $loader  Record loader
     * @param \VuFind\Db\Table\PluginManager       $tables  Table manager
     * @param \Zend\Config\Config                  $config  VuFind configuration
     * @param string                               $baseURL The base URL for the OAI
     * server
     * @param array                                $params  The incoming OAI-PMH
     * parameters (i.e. $_GET)
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results,
        \VuFind\Record\Loader $loader, \VuFind\Db\Table\PluginManager $tables,
        \Zend\Config\Config $config, $baseURL, $params
    ) {
        $this->resultsManager = $results;
        $this->recordLoader = $loader;
        $this->tableManager = $tables;
        $this->baseURL = $baseURL;
        $parts = parse_url($baseURL);
        $this->baseHostURL = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $this->baseHostURL .= $parts['port'];
        }
        $this->params = isset($params) && is_array($params) ? $params : [];
        $this->initializeMetadataFormats(); // Load details on supported formats
        $this->initializeSettings($config); // Load config.ini settings
    }

    /**
     * Add a record link helper (optional -- allows enhancement of some metadata
     * with VuFind-specific links).
     *
     * @param \VuFind\View\Helper\Root\RecordLink $helper Helper to set
     *
     * @return void
     */
    public function setRecordLinkHelper($helper)
    {
        $this->recordLinkHelper = $helper;
    }

    /**
     * Respond to the OAI-PMH request.
     *
     * @return string
     */
    public function getResponse()
    {
        if (!$this->hasParam('verb')) {
            return $this->showError('badVerb', 'Missing Verb Argument');
        } else {
            switch ($this->params['verb']) {
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
     * @param array            $tracker    Array representing a change_tracker row
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
            $record, $this->prefixID($tracker['id']),
            date($this->iso8601, $this->normalizeDate($tracker['deleted'])),
            [],
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
    protected function attachRecordHeader($xml, $id, $date, $sets = [],
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
     * @param string           $set        Currently active set
     *
     * @return bool
     */
    protected function attachNonDeleted($container, $record, $format,
        $headerOnly = false, $set = ''
    ) {
        // Get the XML (and display an error if it is unsupported):
        if ($format === false) {
            $xml = '';      // no metadata if in header-only mode!
        } else {
            $xml = $record
                ->getXML($format, $this->baseHostURL, $this->recordLinkHelper);
            if ($xml === false) {
                return false;
            }
        }

        // Headers should be returned only if the metadata format matching
        // the supplied metadataPrefix is available.
        // If RecordDriver returns nothing, skip this record.
        if (empty($xml)) {
            return true;
        }

        // Check for sets:
        $fields = $record->getRawData();
        if (null !== $this->setField && !empty($fields[$this->setField])) {
            $sets = (array)$fields[$this->setField];
        } else {
            $sets = [];
        }
        if (!empty($set)) {
            $sets = array_unique(array_merge($sets, [$set]));
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
        if (!$headerOnly && !empty($xml)) {
            $metadata = $recXml->addChild('metadata');
            SimpleXML::appendElement($metadata, $xml);
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
            $success = $this->attachNonDeleted(
                $xml, $record, $this->params['metadataPrefix']
            );
            if (!$success) {
                return $this->showError('cannotDisseminateFormat', 'Unknown Format');
            }
        } else {
            // No record in index -- is this deleted?
            $tracker = $this->tableManager->get('ChangeTracker');
            $row = $tracker->retrieve(
                $this->core, $this->stripID($this->params['identifier'])
            );
            if (!empty($row) && !empty($row->deleted)) {
                $this->attachDeleted($xml, $row->toArray());
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
        return isset($this->params[$param]) && !empty($this->params[$param]);
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
        $xml->adminEmail = $this->adminEmail;
        $xml->earliestDatestamp = $this->earliestDatestamp;
        $xml->deletedRecord = 'transient';
        $xml->granularity = 'YYYY-MM-DDThh:mm:ssZ';
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
        $this->metadataFormats['oai_dc'] = [
            'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/'];
        $this->metadataFormats['marc21'] = [
            'schema' => 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
            'namespace' => 'http://www.loc.gov/MARC21/slim'];
    }

    /**
     * Load data from the OAI section of config.ini.  (This is called by the
     * constructor and is only a separate method to allow easy override by child
     * classes).
     *
     * @param \Zend\Config\Config $config VuFind configuration
     *
     * @return void
     */
    protected function initializeSettings(\Zend\Config\Config $config)
    {
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

        // Initialize custom sets queries:
        if (isset($config->OAI->set_query)) {
            $this->setQueries = $config->OAI->set_query->toArray();
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
        $until = $this->normalizeDate($params['until'], '23:59:59');
        if (!$this->listRecordsValidateDates($from, $until)) {
            return;
        }

        // Copy the cursor from the parameters so we can track our current position
        // separately from our initial position!
        $currentCursor = $params['cursor'];

        // The template for displaying a single record varies based on the verb:
        $xml = new SimpleXMLElement("<{$verb} />");

        // The verb determines whether we're returning headers only or full records:
        $headersOnly = ($verb != 'ListRecords');

        // Get deleted records in the requested range (if applicable):
        $deleted = $this->listRecordsGetDeleted($from, $until)->toArray();
        $deletedCount = count($deleted);
        if ($currentCursor < $deletedCount) {
            $limit = $currentCursor + $this->pageSize;
            $limit = $limit > $deletedCount ? $deletedCount : $limit;
            for ($i = $currentCursor; $i < $limit; $i++) {
                $this->attachDeleted($xml, $deleted[$i], $headersOnly);
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
        $set = $params['set'] ?? '';
        $result = $this->listRecordsGetNonDeleted(
            $from, $until, $solrOffset, $solrLimit, $set
        );
        $nonDeletedCount = $result->getResultTotal();
        $format = $params['metadataPrefix'];
        foreach ($result->getResults() as $doc) {
            if (!$this->attachNonDeleted($xml, $doc, $format, $headersOnly, $set)) {
                $this->unexpectedError('Cannot load document');
            }
            $currentCursor++;
        }

        // If our cursor didn't reach the last record, we need a resumption token!
        $listSize = $deletedCount + $nonDeletedCount;
        if ($listSize > $currentCursor) {
            $this->saveResumptionToken($xml, $params, $currentCursor, $listSize);
        } elseif ($solrOffset > 0) {
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
        if (null === $this->setField && empty($this->setQueries)) {
            return $this->showError('noSetHierarchy', 'Sets not supported');
        }

        // Begin building XML:
        $xml = new SimpleXMLElement('<ListSets />');

        // Load set field if applicable:
        if (null !== $this->setField) {
            // If we got this far, we can load all available set values.  For now,
            // we'll assume that this list is short enough to load in one response;
            // it may be necessary to implement a resumption token mechanism if this
            // proves not to be the case:
            $results = $this->resultsManager->get($this->searchClassId);
            try {
                $facets = $results->getFullFieldFacets([$this->setField]);
            } catch (\Exception $e) {
                $facets = null;
            }
            if (empty($facets) || !isset($facets[$this->setField]['data']['list'])) {
                $this->unexpectedError('Cannot find sets');
            }

            // Extract facet values from the Solr response:
            foreach ($facets[$this->setField]['data']['list'] as $x) {
                $set = $xml->addChild('set');
                $set->setSpec = $x['value'];
                $set->setName = $x['displayText'];
            }
        }

        // Iterate over custom sets:
        if (!empty($this->setQueries)) {
            foreach ($this->setQueries as $setName => $solrQuery) {
                $set = $xml->addChild('set');
                $set->setSpec = $solrQuery;
                $set->setName = $setName;
            }
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
        $tracker = $this->tableManager->get('ChangeTracker');
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
        $results = $this->resultsManager->get($this->searchClassId);
        $params = $results->getParams();
        $params->setLimit($limit);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $params->setSort('last_indexed asc', true);

        // Construct a range query based on last indexed time:
        $params->setOverrideQuery(
            'last_indexed:[' . date($this->iso8601, $from) . ' TO '
            . date($this->iso8601, $until) . ']'
        );

        // Apply filters as needed.
        if (!empty($set)) {
            if (isset($this->setQueries[$set])) {
                // Put parentheses around the query so that it does not get
                // parsed as a simple field:value filter.
                $params->addFilter('(' . $this->setQueries[$set] . ')');
            } elseif (null !== $this->setField) {
                $params->addFilter(
                    $this->setField . ':"' . addcslashes($set, '"') . '"'
                );
            }
        }

        // Perform a Solr search:
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
                if (!empty($params['until'])
                    && strlen($params['from']) > strlen($params['until'])
                ) {
                    $params['from'] = substr($params['from'], 0, 10);
                }
            }
            if (empty($params['until'])) {
                $params['until'] = date($this->iso8601);
                if (strlen($params['until']) > strlen($params['from'])) {
                    $params['until'] = substr($params['until'], 0, 10);
                }
            }
            if ($this->isBadDate($params['from'], $params['until'])) {
                throw new \Exception('badArgument:Bad Date Format');
            }
        }

        // If no set field is configured and a set parameter comes in, we have a
        // problem:
        if (null === $this->setField && empty($this->setQueries)
            && !empty($params['set'])
        ) {
            throw new \Exception('noSetHierarchy:Sets not supported');
        }

        if (!isset($params['metadataPrefix'])) {
            throw new \Exception('badArgument:Missing metadataPrefix');
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
     * @param int $from  String for start date.
     * @param int $until String for end date.
     *
     * @return bool      True if invalid, false if not.
     */
    protected function isBadDate($from, $until)
    {
        $dt = \DateTime::createFromFormat("Y-m-d", substr($until, 0, 10));
        if ($dt === false || array_sum($dt->getLastErrors())) {
            return true;
        }
        $dt = \DateTime::createFromFormat("Y-m-d", substr($from, 0, 10));
        if ($dt === false || array_sum($dt->getLastErrors())) {
            return true;
        }
        //check for different date granularity
        if (strpos($from, 'T') && strpos($from, 'Z')) {
            if (strpos($until, 'T') && strpos($until, 'Z')) {
                //this is good
            } else {
                return true;
            }
        } elseif (strpos($until, 'T') && strpos($until, 'Z')) {
            return true;
        }

        $from_time = $this->normalizeDate($from);
        $until_time = $this->normalizeDate($until, '23:59:59');
        if ($from_time > $until_time) {
            throw new \Exception('noRecordsMatch:from vs. until');
        }
        if ($from_time < $this->normalizeDate($this->earliestDatestamp)) {
            return true;
        }
        return false;
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
            try {
                return $this->recordLoader->load($id, $this->searchClassId);
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
        $search = $this->tableManager->get('OaiResumption');

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
     * @param string $time Default time to use if $date has no time attached
     *
     * @return integer     Unix timestamp (or false if $date invalid)
     */
    protected function normalizeDate($date, $time = '00:00:00')
    {
        // Remove timezone markers -- we don't want PHP to outsmart us by adjusting
        // the time zone!
        if (strlen($date) == 10) {
            $date .= ' ' . $time;
        } else {
            $date = str_replace(['T', 'Z'], [' ', ''], $date);
        }

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
        $search = $this->tableManager->get('OaiResumption');
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
