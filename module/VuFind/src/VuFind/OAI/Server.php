<?php

/**
 * OAI Server class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2018-2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\OAI;

use SimpleXMLElement;
use VuFind\Db\Entity\ChangeTrackerEntityInterface;
use VuFind\Db\Service\ChangeTrackerServiceInterface;
use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\SimpleXML;
use VuFindApi\Formatter\RecordFormatter;

use function count;
use function in_array;
use function intval;
use function strlen;

/**
 * OAI Server class
 *
 * This class provides OAI server functionality.
 *
 * @category VuFind
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
     * Record link helper (optional)
     *
     * @var \VuFind\View\Helper\Root\RecordLinker
     */
    protected $recordLinkerHelper = null;

    /**
     * Set queries
     *
     * @var array
     */
    protected $setQueries = [];

    /*
     * Default query used when a set is not specified
     *
     * @var string
     */
    protected $defaultQuery = '';

    /*
     * Record formatter
     *
     * @var RecordFormatter
     */
    protected $recordFormatter = null;

    /**
     * Fields to return when the 'vufind' format is requested. Empty array means the
     * format is disabled.
     *
     * @var array
     */
    protected $vufindApiFields = [];

    /**
     * Filter queries specific to the requested record format
     *
     * @var array
     */
    protected $recordFormatFilters = [];

    /**
     * Limit on display of deleted records (in days); older deleted records will not
     * be returned by the server. Set to null for no limit.
     *
     * @var int
     */
    protected $deleteLifetime = null;

    /**
     * Should we use cursorMarks for Solr retrieval? Normally this is the best
     * option, but it is incompatible with some other Solr features and may need
     * to be disabled in rare circumstances (e.g. when using field collapsing/
     * result grouping).
     *
     * @var bool
     */
    protected $useCursorMark = true;

    /**
     * List of possible valid OAI-PMH error codes
     *
     * @var string[]
     */
    protected $legalErrorCodes = [
        'cannotDisseminateFormat',
        'idDoesNotExist',
        'badArgument',
        'badVerb',
        'noMetadataFormats',
        'noRecordsMatch',
        'badResumptionToken',
        'noSetHierarchy',
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $resultsManager    Search manager for retrieving records
     * @param \VuFind\Record\Loader                $recordLoader      Record loader
     * @param ChangeTrackerServiceInterface        $trackerService    ChangeTracker Service
     * @param OaiResumptionServiceInterface        $resumptionService Database service for resumption tokens
     */
    public function __construct(
        protected \VuFind\Search\Results\PluginManager $resultsManager,
        protected \VuFind\Record\Loader $recordLoader,
        protected ChangeTrackerServiceInterface $trackerService,
        protected OaiResumptionServiceInterface $resumptionService
    ) {
    }

    /**
     * Initialize settings
     *
     * @param \Laminas\Config\Config $config  VuFind configuration
     * @param string                 $baseURL The base URL for the OAI server
     * @param array                  $params  The incoming OAI-PMH parameters (i.e.
     * $_GET)
     *
     * @return void
     */
    public function init(\Laminas\Config\Config $config, $baseURL, array $params)
    {
        $this->baseURL = $baseURL;
        $parts = parse_url($baseURL);
        $this->baseHostURL = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $this->baseHostURL .= $parts['port'];
        }
        $this->params = $params;
        $this->initializeSettings($config); // Load config.ini settings
    }

    /**
     * Add a record linker helper (optional -- allows enhancement of some metadata
     * with VuFind-specific links).
     *
     * @param \VuFind\View\Helper\Root\RecordLinker $helper Helper to set
     *
     * @return void
     */
    public function setRecordLinkerHelper($helper)
    {
        $this->recordLinkerHelper = $helper;
    }

    /**
     * Add a record formatter (optional -- allows the vufind record format to be
     * returned).
     *
     * @param RecordFormatter $formatter Record formatter
     *
     * @return void
     */
    public function setRecordFormatter($formatter)
    {
        $this->recordFormatter = $formatter;
        // Reset metadata formats so they can be reinitialized; the formatter
        // may enable additional options.
        $this->metadataFormats = [];
    }

    /**
     * Get the current UTC date/time in ISO 8601 format.
     *
     * @param string $time Time string to represent as UTC (default = 'now')
     *
     * @return string
     */
    protected function getUTCDateTime($time = 'now')
    {
        // All times must be in UTC, so translate the current time to the
        // appropriate time zone:
        $utc = new \DateTime($time, new \DateTimeZone('UTC'));
        return date_format($utc, $this->iso8601);
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
     * @param SimpleXMLElement             $xml           XML to update
     * @param ChangeTrackerEntityInterface $trackerEntity ChangeTracker entity
     * @param bool                         $headerOnly    Only attach the header?
     *
     * @return void
     */
    protected function attachDeleted($xml, $trackerEntity, $headerOnly = false)
    {
        // Deleted records only have a header, no metadata. However, depending
        // on the context we are attaching them, they may or may not need a
        // <record> tag wrapping the header.
        $record = $headerOnly ? $xml : $xml->addChild('record');
        $this->attachRecordHeader(
            $record,
            $this->prefixID($trackerEntity->getId()),
            date($this->iso8601, $trackerEntity->getDeleted()->getTimestamp()),
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
    protected function attachRecordHeader(
        $xml,
        $id,
        $date,
        $sets = [],
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
     * Support method for attachNonDeleted() to build the VuFind metadata for
     * a record driver.
     *
     * @param object $record A record driver object
     *
     * @return string
     */
    protected function getVuFindMetadata($record)
    {
        // Root node
        $recordDoc = new \DOMDocument();
        $vufindFormat = $this->getMetadataFormats()['oai_vufind_json'];
        $rootNode = $recordDoc->createElementNS(
            $vufindFormat['namespace'],
            'oai_vufind_json:record'
        );
        $rootNode->setAttribute(
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $rootNode->setAttribute(
            'xsi:schemaLocation',
            $vufindFormat['namespace'] . ' ' . $vufindFormat['schema']
        );
        $recordDoc->appendChild($rootNode);

        // Add oai_dc part
        $oaiDc = new \DOMDocument();
        $oaiDc->loadXML(
            $record->getXML('oai_dc', $this->baseHostURL, $this->recordLinkerHelper)
        );
        $rootNode->appendChild(
            $recordDoc->importNode($oaiDc->documentElement, true)
        );

        // Add VuFind metadata
        $records = $this->recordFormatter->format(
            [$record],
            $this->vufindApiFields
        );
        $metadataNode = $recordDoc->createElementNS(
            $vufindFormat['namespace'],
            'oai_vufind_json:metadata'
        );
        $metadataNode->setAttribute('type', 'application/json');
        $metadataNode->appendChild(
            $recordDoc->createCDATASection(json_encode($records[0]))
        );
        $rootNode->appendChild($metadataNode);

        return $recordDoc->saveXML();
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
    protected function attachNonDeleted(
        $container,
        $record,
        $format,
        $headerOnly = false,
        $set = ''
    ) {
        // Get the XML (and display an error if it is unsupported):
        if ($format === false) {
            $xml = '';      // no metadata if in header-only mode!
        } elseif ('oai_vufind_json' === $format && $this->supportsVuFindMetadata()) {
            $xml = $this->getVuFindMetadata($record);   // special case
        } else {
            $xml = $record
                ->getXML($format, $this->baseHostURL, $this->recordLinkerHelper);
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
            $date = $this->getUTCDateTime('now');
        }

        // Set up header (inside or outside a <record> container depending on
        // preferences):
        $recXml = $headerOnly ? $container : $container->addChild('record');
        $this->attachRecordHeader(
            $recXml,
            $this->prefixID($record->getUniqueID()),
            $date,
            $sets
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
        $response = $this->createResponse();
        $xml = $response->addChild('GetRecord');

        // Retrieve the record from the index
        if ($record = $this->loadRecord($this->params['identifier'])) {
            $success = $this->attachNonDeleted(
                $xml,
                $record,
                $this->params['metadataPrefix']
            );
            if (!$success) {
                return $this->showError('cannotDisseminateFormat', 'Unknown Format');
            }
        } else {
            // No record in index -- is this deleted?

            $row = $this->trackerService->getChangeTrackerEntity(
                $this->core,
                $this->stripID($this->params['identifier'])
            );
            if (!empty($row) && !empty($row->getDeleted())) {
                $this->attachDeleted($xml, $row);
            } else {
                // Not deleted and not found in index -- error!
                return $this->showError('idDoesNotExist', 'Unknown Record');
            }
        }

        // Display the record:
        return $response->asXML();
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
        $response = $this->createResponse();
        $xml = $response->addChild('Identify');
        $xml->repositoryName = $this->repositoryName;
        $xml->baseURL = $this->baseURL;
        $xml->protocolVersion = '2.0';
        $xml->adminEmail = $this->adminEmail;
        $xml->earliestDatestamp = $this->earliestDatestamp;
        $xml->deletedRecord = 'transient';
        $xml->granularity = 'YYYY-MM-DDThh:mm:ssZ';
        if (!empty($this->idNamespace)) {
            $id = $xml->addChild('description')->addChild(
                'oai-identifier',
                null,
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

        return $response->asXML();
    }

    /**
     * Does the current configuration support the VuFind metadata format (using
     * the API's record formatter.
     *
     * @return bool
     */
    protected function supportsVuFindMetadata()
    {
        return !empty($this->vufindApiFields) && null !== $this->recordFormatter;
    }

    /**
     * Initialize data about metadata formats. (This is called on demand and is
     * defined as a separate method to allow easy override by child classes).
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

        if ($this->supportsVuFindMetadata()) {
            $this->metadataFormats['oai_vufind_json'] = [
                'schema' => 'https://vufind.org/xsd/oai_vufind_json-1.0.xsd',
                'namespace' => 'http://vufind.org/oai_vufind_json-1.0',
            ];
        } else {
            unset($this->metadataFormats['oai_vufind_json']);
        }
    }

    /**
     * Get metadata formats; initialize the list if necessary.
     *
     * @return array
     */
    protected function getMetadataFormats()
    {
        if (empty($this->metadataFormats)) {
            $this->initializeMetadataFormats();
        }
        return $this->metadataFormats;
    }

    /**
     * Load data from the OAI section of config.ini. (This is called by the
     * constructor and is only a separate method to allow easy override by child
     * classes).
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return void
     */
    protected function initializeSettings(\Laminas\Config\Config $config)
    {
        // Override default repository name if configured:
        if (isset($config->OAI->repository_name)) {
            $this->repositoryName = $config->OAI->repository_name;
        }

        // Override default ID namespace if configured:
        if (isset($config->OAI->identifier)) {
            $this->idNamespace = $config->OAI->identifier;
        }

        // Override page size if configured:
        if (isset($config->OAI->page_size)) {
            $this->pageSize = $config->OAI->page_size;
        }

        // Use either OAI-specific or general email address; we must have SOMETHING.
        $this->adminEmail = $config->OAI->admin_email ?? $config->Site->email;

        // Use a Solr field to determine sets, if configured:
        if (isset($config->OAI->set_field)) {
            $this->setField = $config->OAI->set_field;
        }

        // Initialize custom sets queries:
        if (isset($config->OAI->set_query)) {
            $this->setQueries = $config->OAI->set_query->toArray();
        }

        // Use a default query, if configured:
        if (isset($config->OAI->default_query)) {
            $this->defaultQuery = $config->OAI->default_query;
        }

        // Initialize VuFind API format fields:
        $this->vufindApiFields = array_filter(
            explode(
                ',',
                $config->OAI->vufind_api_format_fields ?? ''
            )
        );

        // Initialize filters specific to requested metadataPrefix:
        if (isset($config->OAI->record_format_filters)) {
            $this->recordFormatFilters
                = $config->OAI->record_format_filters->toArray();
        }

        // Initialize delete lifetime, if set:
        if (isset($config->OAI->delete_lifetime)) {
            $this->deleteLifetime = intval($config->OAI->delete_lifetime);
        }

        // Change cursormark behavior if necessary:
        $cursor = $config->OAI->use_cursor ?? true;
        if (!$cursor || strtolower($cursor) === 'false') {
            $this->useCursorMark = false;
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
        $response = $this->createResponse();
        $xml = $response->addChild('ListMetadataFormats');
        foreach ($this->getMetadataFormats() as $prefix => $details) {
            if (
                $record === false
                || $record->getXML($prefix) !== false
                || ('oai_vufind_json' === $prefix && $this->supportsVuFindMetadata())
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
        return $response->asXML();
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
            if (count($parts) != 2 || !in_array($parts[0], $this->legalErrorCodes)) {
                throw $e;
            }
            return $this->showError($parts[0], $parts[1]);
        }

        // Normalize the provided dates into Unix timestamps. Depending on whether
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

        $response = $this->createResponse();
        $xml = $response->addChild($verb);

        // The verb determines whether we're returning headers only or full records:
        $headersOnly = ($verb != 'ListRecords');

        // Apply the delete lifetime limit to the from date if necessary:
        $deleteCutoff = $this->deleteLifetime
            ? strtotime('-' . $this->deleteLifetime . ' days') : 0;
        $deleteFrom = ($deleteCutoff < $from) ? $from : $deleteCutoff;

        // Get deleted records in the requested range (if applicable):
        $deletedCount = $this->listRecordsGetDeletedCount($deleteFrom, $until);
        if ($deletedCount > 0 && $currentCursor < $deletedCount) {
            $deleted = $this
                ->listRecordsGetDeleted($deleteFrom, $until, $currentCursor);
            foreach ($deleted as $current) {
                $this->attachDeleted($xml, $current, $headersOnly);
                $currentCursor++;
            }
        }

        // Figure out how many non-deleted records we need to display:
        $recordLimit = ($params['cursor'] + $this->pageSize) - $currentCursor;
        // Depending on cursormark mode, we either need to get the latest mark or
        // else calculate a Solr offset.
        if ($this->useCursorMark) {
            $offset = $cursorMark = $params['cursorMark'] ?? '';
        } else {
            $cursorMark = ''; // always empty for checks below
            $offset = ($currentCursor >= $deletedCount)
                ? $currentCursor - $deletedCount : 0;
        }
        $format = $params['metadataPrefix'];

        // Get non-deleted records from the Solr index:
        $set = $params['set'] ?? '';
        $result = $this->listRecordsGetNonDeleted(
            $from,
            $until,
            $offset,
            $recordLimit,
            $format,
            $set
        );
        $nonDeletedCount = $result->getResultTotal();
        foreach ($result->getResults() as $doc) {
            $this->attachNonDeleted($xml, $doc, $format, $headersOnly, $set);
            $currentCursor++;
        }
        // We only need a cursor mark if we fetched results from Solr; if our
        // $recordLimit is 0, it means that we're still in the process of
        // retrieving deleted records, and we're only hitting Solr to obtain a
        // total record count. Therefore, we don't want to change the cursor
        // mark yet, or it will break pagination of deleted records.
        $nextCursorMark = $recordLimit > 0 ? $result->getCursorMark() : '';

        // If our cursor didn't reach the last record, we need a resumption token!
        $listSize = $deletedCount + $nonDeletedCount;
        if (
            $listSize > $currentCursor
            && ('' === $cursorMark || $nextCursorMark !== $cursorMark)
        ) {
            $this->saveResumptionToken(
                $xml,
                $params,
                $currentCursor,
                $listSize,
                $nextCursorMark
            );
        } elseif ($params['cursor'] > 0) {
            // If we reached the end of the list but there is more than one page, we
            // still need to display an empty <resumptionToken> tag:
            $token = $xml->addChild('resumptionToken');
            $token->addAttribute('completeListSize', $listSize);
            $token->addAttribute('cursor', $params['cursor']);
        }

        return $response->asXML();
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
                'badResumptionToken',
                'Invalid resumption token'
            );
        }

        // If no set field is enabled, we can't provide a set list:
        if (null === $this->setField && empty($this->setQueries)) {
            return $this->showError('noSetHierarchy', 'Sets not supported');
        }

        // Begin building XML:
        $response = $this->createResponse();
        $xml = $response->addChild('ListSets');

        // Load set field if applicable:
        if (null !== $this->setField) {
            // If we got this far, we can load all available set values. For now,
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
                $set->setName = $set->setSpec = $setName;
                $set->setDescription = $solrQuery;
            }
        }

        // Display the list:
        return $response->asXML();
    }

    /**
     * Get an object containing the next page of deleted records from the specified
     * date range.
     *
     * @param int $from          Start date.
     * @param int $until         End date.
     * @param int $currentCursor Offset into result set
     *
     * @return ChangeTrackerEntityInterface[]
     */
    protected function listRecordsGetDeleted($from, $until, $currentCursor)
    {
        return $this->trackerService->getDeletedEntities(
            $this->core,
            \DateTime::createFromFormat('U', $from),
            \DateTime::createFromFormat('U', $until),
            $currentCursor,
            $this->pageSize
        );
    }

    /**
     * Get a count of all deleted records in the specified date range.
     *
     * @param int $from  Start date.
     * @param int $until End date.
     *
     * @return int
     */
    protected function listRecordsGetDeletedCount($from, $until)
    {
        return $this->trackerService->getDeletedCount(
            $this->core,
            \DateTime::createFromFormat('U', $from),
            \DateTime::createFromFormat('U', $until)
        );
    }

    /**
     * Get an array of information on non-deleted records in the specified range.
     *
     * @param int    $from   Start date.
     * @param int    $until  End date.
     * @param mixed  $offset Solr offset, or cursorMark for the position in the full
     * result list (depending on settings).
     * @param int    $limit  Max number of full records to return.
     * @param string $format Requested record format
     * @param string $set    Set to limit to (empty string for none).
     *
     * @return \VuFind\Search\Base\Results Search result object.
     */
    protected function listRecordsGetNonDeleted(
        $from,
        $until,
        $offset,
        $limit,
        $format,
        $set = ''
    ) {
        // Set up search parameters:
        $results = $this->resultsManager->get($this->searchClassId);
        $params = $results->getParams();
        $params->setLimit($limit);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        $params->setSort('last_indexed asc, id asc', true);

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
        } elseif ($this->defaultQuery) {
            // Put parentheses around the query so that it does not get
            // parsed as a simple field:value filter.
            $params->addFilter('(' . $this->defaultQuery . ')');
        }

        if (!empty($this->recordFormatFilters[$format])) {
            $params->addFilter($this->recordFormatFilters[$format]);
        }

        // Perform a Solr search:
        if ($this->useCursorMark) {
            $results->overrideStartRecord(1);
            $results->setCursorMark($offset);
        } else {
            $results->overrideStartRecord($offset + 1);
        }

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
                if (
                    !empty($params['until'])
                    && strlen($params['from']) > strlen($params['until'])
                ) {
                    $params['from'] = substr($params['from'], 0, 10);
                }
            }
            if (empty($params['until'])) {
                $params['until'] = $this->getUTCDateTime('now +1 day');
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
        if (
            null === $this->setField && empty($this->setQueries)
            && !empty($params['set'])
        ) {
            throw new \Exception('noSetHierarchy:Sets not supported');
        }

        // Validate set parameter:
        if (
            !empty($params['set']) && null === $this->setField
            && !isset($this->setQueries[$params['set']])
        ) {
            throw new \Exception('badArgument:Invalid set specified');
        }

        if (!isset($params['metadataPrefix'])) {
            throw new \Exception('badArgument:Missing metadataPrefix');
        }

        // Validate requested metadata format:
        $prefixes = array_keys($this->getMetadataFormats());
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
        $dt = \DateTime::createFromFormat('Y-m-d', substr($until, 0, 10));
        if (!$this->dateTimeCreationSuccessful($dt)) {
            return true;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', substr($from, 0, 10));
        if (!$this->dateTimeCreationSuccessful($dt)) {
            return true;
        }
        // Check for different date granularity
        if (strpos($from, 'T') && strpos($from, 'Z')) {
            if (strpos($until, 'T') && strpos($until, 'Z')) {
                // This is good
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
     * Check if a DateTime was successfully created without errors or warnings
     *
     * @param \DateTime|false $dt DateTime or false (return value of createFromFormat)
     *
     * @return bool
     */
    protected function dateTimeCreationSuccessful(\DateTime|false $dt): bool
    {
        // Return value false is always an error:
        if (false === $dt) {
            return false;
        }
        $errors = $dt->getLastErrors();
        // getLastErrors returns false if no errors on PHP 8.2 and later:
        if (false === $errors) {
            return true;
        }
        // getLastErrors returns an array with no errors on PHP 8.1:
        return empty($errors['errors']) && empty($errors['warnings']);
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
                'badArgument',
                'End date must be after start date'
            );
        }
        if ($from < $this->normalizeDate($this->earliestDatestamp)) {
            return $this->showError(
                'badArgument',
                'Start date must be after earliest date'
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
        // Clean up expired records before doing our search:
        $this->resumptionService->removeExpired();

        // Load the requested token if it still exists:
        if ($row = $this->resumptionService->findToken($token)) {
            parse_str($row->getResumptionParameters(), $params);
            return $params;
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
     * @param string           $cursorMark    cursorMark for the position in the full
     * results list.
     *
     * @return void
     */
    protected function saveResumptionToken(
        $xml,
        $params,
        $currentCursor,
        $listSize,
        $cursorMark
    ) {
        // Save the old cursor position before overwriting it for storage in the
        // database!
        $oldCursor = $params['cursor'];
        $params['cursor'] = $currentCursor;
        $params['cursorMark'] = $cursorMark;

        // Save everything to the database:
        $expire = time() + 24 * 60 * 60;
        $token = $this->resumptionService->createAndPersistToken($params, $expire)->getId();

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
        // Certain errors should not echo parameters:
        $echoParams = !($code == 'badVerb' || $code == 'badArgument');
        $response = $this->createResponse($echoParams);

        $xml = $response->addChild('error', htmlspecialchars($message));
        if (!empty($code)) {
            $xml['code'] = $code;
        }

        return $response->asXML();
    }

    /**
     * Create an OAI-PMH response (shared support method used by various
     * response-specific methods).
     *
     * @param bool $echoParams Include params in <request> tag?
     *
     * @return SimpleXMLElement
     */
    protected function createResponse($echoParams = true)
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
        $xml->responseDate = $this->getUTCDateTime('now');
        $xml->request = $this->baseURL;
        if ($echoParams) {
            foreach ($this->params as $key => $value) {
                $xml->request[$key] = $value;
            }
        }

        return $xml;
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
        if (str_starts_with($id, $prefix)) {
            return substr($id, strlen($prefix));
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
