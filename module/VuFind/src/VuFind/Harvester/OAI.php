<?php
/**
 * OAI-PMH Harvest Tool
 *
 * PHP version 5
 *
 * Copyright (c) Demian Katz 2010.
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
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
namespace VuFind\Harvester;
use Zend\Console\Console;

/**
 * OAI Class
 *
 * This class harvests records via OAI-PMH using settings from oai.ini.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class OAI
{
    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * HTTP client's timeout
     *
     * @var int
     */
    protected $timeout = 60;

    /**
     * Combine harvested records (per OAI chunk size) into one (collection) file?
     *
     * @var bool
     */
    protected $combineRecords = false;

    /**
     * The wrapping XML tag to be used if combinedRecords is set to true
     *
     * @var string
     */
    protected $combineRecordsTag = '<collection>';

    /**
     * URL to harvest from
     *
     * @var string
     */
    protected $baseURL;

    /**
     * Target set(s) to harvest (null for all records)
     *
     * @var string|array
     */
    protected $set = null;

    /**
     * Metadata type to harvest
     *
     * @var string
     */
    protected $metadataPrefix = 'oai_dc';

    /**
     * OAI prefix to strip from ID values
     *
     * @var string
     */
    protected $idPrefix = '';

    /**
     * Regular expression searches
     *
     * @var array
     */
    protected $idSearch = [];

    /**
     * Replacements for regular expression matches
     *
     * @var array
     */
    protected $idReplace = [];

    /**
     * Directory for storing harvested files
     *
     * @var string
     */
    protected $basePath;

    /**
     * File for tracking last harvest date
     *
     * @var string
     */
    protected $lastHarvestFile;

    /**
     * File for tracking last harvest state (for continuing interrupted
     * connection).
     *
     * @var string
     */
    protected $lastStateFile;

    /**
     * Harvest end date (null for no specific end)
     *
     * @var string
     */
    protected $harvestEndDate;

    /**
     * Harvest start date (null for no specific start)
     *
     * @var string
     */
    protected $startDate = null;

    /**
     * Date granularity ('auto' to autodetect)
     *
     * @var string
     */
    protected $granularity = 'auto';

    /**
     * Tag to use for injecting IDs into XML (false for none)
     *
     * @var string|bool
     */
    protected $injectId = false;

    /**
     * Tag to use for injecting setSpecs (false for none)
     *
     * @var string|bool
     */
    protected $injectSetSpec = false;

    /**
     * Tag to use for injecting set names (false for none)
     *
     * @var string|bool
     */
    protected $injectSetName = false;

    /**
     * Tag to use for injecting datestamp (false for none)
     *
     * @var string|bool
     */
    protected $injectDate = false;

    /**
     * List of header elements to copy into body
     *
     * @var array
     */
    protected $injectHeaderElements = [];

    /**
     * Associative array of setSpec => setName
     *
     * @var array
     */
    protected $setNames = [];

    /**
     * Filename for logging harvested IDs (false for none)
     *
     * @var string|bool
     */
    protected $harvestedIdLog = false;

    /**
     * Should we display debug output?
     *
     * @var bool
     */
    protected $verbose = false;

    /**
     * Should we sanitize XML?
     *
     * @var bool
     */
    protected $sanitize = false;

    /**
     * Filename for logging bad XML responses (false for none)
     *
     * @var string|bool
     */
    protected $badXMLLog = false;

    /**
     * Username for HTTP basic authentication (false for none)
     *
     * @var string|bool
     */
    protected $httpUser = false;

    /**
     * Password for HTTP basic authentication (false for none)
     *
     * @var string|bool
     */
    protected $httpPass = false;

    /**
     * As we harvest records, we want to track the most recent date encountered
     * so we can set a start point for the next harvest.  (Unix timestamp format)
     *
     * @var int
     */
    protected $endDate = 0;

    /**
     * Constructor.
     *
     * @param string            $target   Target directory for harvest.
     * @param array             $settings OAI-PMH settings from oai.ini.
     * @param \Zend\Http\Client $client   HTTP client
     * @param string            $from     Harvest start date (omit to use
     * last_harvest.txt)
     * @param string            $until    Harvest end date (optional)
     */
    public function __construct($target, $settings, \Zend\Http\Client $client,
        $from = null, $until = null
    ) {
        // Store client:
        $this->client = $client;

        // Disable SSL verification if requested:
        if (isset($settings['sslverifypeer']) && !$settings['sslverifypeer']) {
            $this->client->setOptions(['sslverifypeer' => false]);
        }

        // Don't time out during harvest!!
        set_time_limit(0);

        // Set up base directory for harvested files:
        $this->setBasePath($target);

        // Check if there is a file containing a start date:
        $this->lastHarvestFile = $this->basePath . 'last_harvest.txt';
        $this->lastStateFile = $this->basePath . 'last_state.txt';

        // Set up start/end dates:
        $this->setStartDate(empty($from) ? $this->loadLastHarvestedDate() : $from);
        $this->setEndDate($until);

        // Save configuration:
        $this->setConfig($target, $settings);

        // Load set names if we're going to need them:
        if ($this->injectSetName) {
            $this->loadSetNames();
        }

        // Autoload granularity if necessary:
        if ($this->granularity == 'auto') {
            $this->loadGranularity();
        }
    }

    /**
     * Set an end date for the harvest (only harvest records BEFORE this date).
     *
     * @param string $date End date (YYYY-MM-DD format).
     *
     * @return void
     */
    public function setEndDate($date)
    {
        $this->harvestEndDate = $date;
    }
    /**
     * Set a start date for the harvest (only harvest records AFTER this date).
     *
     * @param string $date Start date (YYYY-MM-DD format).
     *
     * @return void
     */
    public function setStartDate($date)
    {
        $this->startDate = $date;
    }

    /**
     * Harvest all available documents.
     *
     * @return void
     */
    public function launch()
    {
        // Normalize sets setting to an array:
        $sets = (array)$this->set;
        if (empty($sets)) {
            $sets = [null];
        }

        // Load last state, if applicable (used to recover from server failure).
        if (file_exists($this->lastStateFile)) {
            $this->write("Found {$this->lastStateFile}; attempting to resume.\n");
            list($resumeSet, $resumeToken, $this->startDate)
                = explode("\t", file_get_contents($this->lastStateFile));
        }

        // Loop through all of the selected sets:
        foreach ($sets as $set) {
            // If we're resuming and there are multiple sets, find the right one.
            if (isset($resumeToken) && $resumeSet != $set) {
                continue;
            }

            // If we have a token to resume from, pick up there now...
            if (isset($resumeToken)) {
                $token = $resumeToken;
                unset($resumeToken);
            } else {
                // ...otherwise, start harvesting at the requested date:
                $token = $this->getRecordsByDate(
                    $this->startDate, $set, $this->harvestEndDate
                );
            }

            // Keep harvesting as long as a resumption token is provided:
            while ($token !== false) {
                // Save current state in case we need to resume later:
                file_put_contents(
                    $this->lastStateFile, "$set\t$token\t{$this->startDate}"
                );
                $token = $this->getRecordsByToken($token);
            }
        }

        // If we made it this far, all was successful, so we should clean up
        // the "last state" file.
        if (file_exists($this->lastStateFile)) {
            unlink($this->lastStateFile);
        }
    }

    /**
     * Set up directory structure for harvesting (support method for constructor).
     *
     * @param string $target The OAI-PMH target directory to create.
     *
     * @return void
     */
    protected function setBasePath($target)
    {
        // Get the base VuFind path:
        if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            $home = LOCAL_OVERRIDE_DIR;
        } else {
            $home = realpath(APPLICATION_PATH . '/..');
        }

        // Build the full harvest path:
        $this->basePath = $home . '/harvest/' . $target . '/';

        // Create the directory if it does not already exist:
        if (!is_dir($this->basePath)) {
            if (!mkdir($this->basePath)) {
                throw new \Exception(
                    "Problem creating directory {$this->basePath}."
                );
            }
        }
    }

    /**
     * Retrieve the date from the "last harvested" file and use it as our start
     * date if it is available.
     *
     * @return string
     */
    protected function loadLastHarvestedDate()
    {
        return (file_exists($this->lastHarvestFile))
            ? trim(current(file($this->lastHarvestFile))) : null;
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
        $date = str_replace(['T', 'Z'], [' ', ''], $date);

        // Translate to a timestamp:
        return strtotime($date);
    }

    /**
     * Save a date to the "last harvested" file.
     *
     * @param string $date Date to save.
     *
     * @return void
     */
    protected function saveLastHarvestedDate($date)
    {
        file_put_contents($this->lastHarvestFile, $date);
    }

    /**
     * Make an OAI-PMH request.  Die if there is an error; return a SimpleXML object
     * on success.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return object        SimpleXML-formatted response.
     */
    protected function sendRequest($verb, $params = [])
    {
        // Debug:
        if ($this->verbose) {
            $this->write(
                "Sending request: verb = {$verb}, params = " . print_r($params, true)
            );
        }

        // Set up retry loop:
        while (true) {
            // Set up the request:
            $this->client->resetParameters();
            $this->client->setUri($this->baseURL);
            $this->client->setOptions(['timeout' => $this->timeout]);

            // Set authentication, if necessary:
            if ($this->httpUser && $this->httpPass) {
                $this->client->setAuth($this->httpUser, $this->httpPass);
            }

            // Load request parameters:
            $query = $this->client->getRequest()->getQuery();
            $query->set('verb', $verb);
            foreach ($params as $key => $value) {
                $query->set($key, $value);
            }

            // Perform request and die on error:
            $result = $this->client->setMethod('GET')->send();
            if ($result->getStatusCode() == 503) {
                $delayHeader = $result->getHeaders()->get('Retry-After');
                $delay = is_object($delayHeader)
                    ? $delayHeader->getDeltaSeconds() : 0;
                if ($delay > 0) {
                    if ($this->verbose) {
                        $this->writeLine(
                            "Received 503 response; waiting {$delay} seconds..."
                        );
                    }
                    sleep($delay);
                }
            } else if (!$result->isSuccess()) {
                throw new \Exception('HTTP Error ' . $result->getStatusCode());
            } else {
                // If we didn't get an error, we can leave the retry loop:
                break;
            }
        }

        // If we got this far, there was no error -- send back response.
        return $this->processResponse($result->getBody());
    }

    /**
     * Log a bad XML response.
     *
     * @param string $xml Bad XML
     *
     * @return void
     */
    protected function logBadXML($xml)
    {
        $file = fopen($this->basePath . $this->badXMLLog, 'a');
        if (!$file) {
            throw new \Exception("Problem opening {$this->badXMLLog}.");
        }
        fputs($file, $xml . "\n\n");
        fclose($file);
    }

    /**
     * Sanitize XML.
     *
     * @param string $xml XML to sanitize
     *
     * @return string
     */
    protected function sanitizeXML($xml)
    {
        // Sanitize the XML if requested:
        $regex = '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u';
        $newXML = trim(preg_replace($regex, ' ', $xml, -1, $count));

        if ($count > 0 && $this->badXMLLog) {
            $this->logBadXML($xml);
        }

        return $newXML;
    }

    /**
     * Process an OAI-PMH response into a SimpleXML object.  Die if an error is
     * detected.
     *
     * @param string $xml OAI-PMH response XML.
     *
     * @return object     SimpleXML-formatted response.
     */
    protected function processResponse($xml)
    {
        // Sanitize if necessary:
        if ($this->sanitize) {
            $xml = $this->sanitizeXML($xml);
        }

        // Parse the XML (newer versions of LibXML require a special flag for
        // large documents, and responses may be quite large):
        $flags = LIBXML_VERSION >= 20900 ? LIBXML_PARSEHUGE : 0;
        $result = simplexml_load_string($xml, null, $flags);
        if (!$result) {
            throw new \Exception("Problem loading XML: {$xml}");
        }

        // Detect errors and die if one is found:
        if ($result->error) {
            $attribs = $result->error->attributes();

            // If this is a bad resumption token error and we're trying to
            // restore a prior state, we should clean up.
            if ($attribs['code'] == 'badResumptionToken'
                && file_exists($this->lastStateFile)
            ) {
                unlink($this->lastStateFile);
                throw new \Exception(
                    "Token expired; removing last_state.txt. Please restart harvest."
                );
            }
            throw new \Exception(
                "OAI-PMH error -- code: {$attribs['code']}, " .
                "value: {$result->error}"
            );
        }

        // If we got this far, we have a valid response:
        return $result;
    }

    /**
     * Get the filename for a specific record ID.
     *
     * @param string $id  ID of record to save.
     * @param string $ext File extension to use.
     *
     * @return string     Full path + filename.
     */
    protected function getFilename($id, $ext)
    {
        return $this->basePath . time() . '_' .
            preg_replace('/[^\w]/', '_', $id) . '.' . $ext;
    }

    /**
     * Create a tracking file to record the deletion of a record.
     *
     * @param string|array $ids ID(s) of deleted record(s).
     *
     * @return void
     */
    protected function saveDeletedRecords($ids)
    {
        $ids = (array)$ids; // make sure input is array format
        $filename = $this->getFilename($ids[0], 'delete');
        file_put_contents($filename, implode("\n", $ids));
    }

    /**
     * Save a record to disk.
     *
     * @param string $id     ID of record to save.
     * @param object $record Record to save (in SimpleXML format).
     *
     * @return void
     */
    protected function getRecordXML($id, $record)
    {
        if (!isset($record->metadata)) {
            throw new \Exception("Unexpected missing record metadata.");
        }

        // Extract the actual metadata from inside the <metadata></metadata> tags;
        // there is probably a cleaner way to do this, but this simple method avoids
        // the complexity of dealing with namespaces in SimpleXML:
        $xml = trim($record->metadata->asXML());
        preg_match('/^<metadata([^\>]*)>/', $xml, $extractedNs);
        $xml = preg_replace('/(^<metadata[^\>]*>)|(<\/metadata>$)/m', '', $xml);
        // remove all attributes from extractedNs that appear deeper in xml:
        $attributes = [];
        preg_match_all('/(^| )[^"]*"?[^"]*"/', $extractedNs[1], $attributes);
        $extractedAttributes = '';
        foreach ($attributes[0] as $attribute) {
            $attribute = trim($attribute);
            // if $attribute appears in xml, remove it:
            if (strstr($xml, $attribute)) {
                // echo "DEBUG: removing attribute: $attribute\n";
            } else {
                $extractedAttributes = ($extractedAttributes == '') ?
                    $attribute : $extractedAttributes . " " . $attribute;
            }
        }

        // If we are supposed to inject any values, do so now inside the first
        // tag of the file:
        $insert = '';
        if (!empty($this->injectId)) {
            $insert .= "<{$this->injectId}>" . htmlspecialchars($id) .
                "</{$this->injectId}>";
        }
        if (!empty($this->injectDate)) {
            $insert .= "<{$this->injectDate}>" .
                htmlspecialchars((string)$record->header->datestamp) .
                "</{$this->injectDate}>";
        }
        if (!empty($this->injectSetSpec)) {
            if (isset($record->header->setSpec)) {
                foreach ($record->header->setSpec as $current) {
                    $insert .= "<{$this->injectSetSpec}>" .
                        htmlspecialchars((string)$current) .
                        "</{$this->injectSetSpec}>";
                }
            }
        }
        if (!empty($this->injectSetName)) {
            if (isset($record->header->setSpec)) {
                foreach ($record->header->setSpec as $current) {
                    $name = $this->setNames[(string)$current];
                    $insert .= "<{$this->injectSetName}>" .
                        htmlspecialchars($name) .
                        "</{$this->injectSetName}>";
                }
            }
        }
        if (!empty($this->injectHeaderElements)) {
            foreach ($this->injectHeaderElements as $element) {
                if (isset($record->header->$element)) {
                    $insert .= $record->header->$element->asXML();
                }
            }
        }
        if (!empty($insert)) {
            $xml = preg_replace('/>/', '>' . $insert, $xml, 1);
        }
        $xml = $this->fixNamespaces(
            $xml, $record->getDocNamespaces(),
            $extractedAttributes
        );

        return trim($xml);
    }

    /**
     * Save a record to disk.
     *
     * @param string $id  Record ID to use for filename generation.
     * @param string $xml XML to save.
     *
     * @return void
     */
    protected function saveFile($id, $xml)
    {
        // Save our XML:
        file_put_contents($this->getFilename($id, 'xml'), trim($xml));
    }

    /**
     * Support method for saveRecord() -- fix namespaces in the top tag of the XML
     * document to compensate for bugs in the SimpleXML library.
     *
     * @param string $xml  XML document to clean up
     * @param array  $ns   Namespaces to check
     * @param string $attr Attributes extracted from the <metadata> tag
     *
     * @return string
     */
    protected function fixNamespaces($xml, $ns, $attr = '')
    {
        foreach ($ns as $key => $val) {
            if (!empty($key)
                && strstr($xml, $key . ':') && !strstr($xml, 'xmlns:' . $key)
                && !strstr($attr, 'xmlns:' . $key)
            ) {
                $attr .= ' xmlns:' . $key . '="' . $val . '"';
            }
        }
        if (!empty($attr) && !strpos($xml, $attr)) {
            $xml = preg_replace('/>/', $attr . '>', $xml, 1);
        }
        return $xml;
    }

    /**
     * Load date granularity from the server.
     *
     * @return void
     */
    protected function loadGranularity()
    {
        $this->write("Autodetecting date granularity... ");
        $response = $this->sendRequest('Identify');
        $this->granularity = (string)$response->Identify->granularity;
        $this->writeLine("found {$this->granularity}.");
    }

    /**
     * Load set list from the server.
     *
     * @return void
     */
    protected function loadSetNames()
    {
        $this->write("Loading set list... ");

        // On the first pass through the following loop, we want to get the
        // first page of sets without using a resumption token:
        $params = [];

        // Grab set information until we have it all (at which point we will
        // break out of this otherwise-infinite loop):
        while (true) {
            // Process current page of results:
            $response = $this->sendRequest('ListSets', $params);
            if (isset($response->ListSets->set)) {
                foreach ($response->ListSets->set as $current) {
                    $spec = (string)$current->setSpec;
                    $name = (string)$current->setName;
                    if (!empty($spec)) {
                        $this->setNames[$spec] = $name;
                    }
                }
            }

            // Is there a resumption token?  If so, continue looping; if not,
            // we're done!
            if (isset($response->ListSets->resumptionToken)
                && !empty($response->ListSets->resumptionToken)
            ) {
                $params['resumptionToken']
                    = (string)$response->ListSets->resumptionToken;
            } else {
                $this->writeLine("found " . count($this->setNames));
                return;
            }
        }
    }

    /**
     * Extract the ID from a record object (support method for processRecords()).
     *
     * @param object $record SimpleXML record.
     *
     * @return string        The ID value.
     */
    protected function extractID($record)
    {
        // Normalize to string:
        $id = (string)$record->header->identifier;

        // Strip prefix if found:
        if (substr($id, 0, strlen($this->idPrefix)) == $this->idPrefix) {
            $id = substr($id, strlen($this->idPrefix));
        }

        // Apply regular expression matching:
        if (!empty($this->idSearch)) {
            $id = preg_replace($this->idSearch, $this->idReplace, $id);
        }

        // Return final value:
        return $id;
    }

    /**
     * Save harvested records to disk and track the end date.
     *
     * @param object $records SimpleXML records.
     *
     * @return void
     */
    protected function processRecords($records)
    {
        $this->writeLine('Processing ' . count($records) . " records...");

        // Array for tracking successfully harvested IDs:
        $harvestedIds = [];

        // Array for tracking deleted IDs and string for tracking inner HTML
        // (both of these variables are used only when in 'combineRecords' mode):
        $deletedIds = [];
        $innerXML = '';

        // Loop through the records:
        foreach ($records as $record) {
            // Die if the record is missing its header:
            if (empty($record->header)) {
                throw new \Exception("Unexpected missing record header.");
            }

            // Get the ID of the current record:
            $id = $this->extractID($record);

            // Save the current record, either as a deleted or as a regular file:
            $attribs = $record->header->attributes();
            if (strtolower($attribs['status']) == 'deleted') {
                if ($this->combineRecords) {
                    $deletedIds[] = $id;
                } else {
                    $this->saveDeletedRecords($id);
                }
            } else {
                if ($this->combineRecords) {
                    $innerXML .= $this->getRecordXML($id, $record);
                } else {
                    $this->saveFile($id, $this->getRecordXML($id, $record));
                }
                $harvestedIds[] = $id;
            }

            // If the current record's date is newer than the previous end date,
            // remember it for future reference:
            $date = $this->normalizeDate($record->header->datestamp);
            if ($date && $date > $this->endDate) {
                $this->endDate = $date;
            }
        }

        if ($this->combineRecords) {
            if (!empty($harvestedIds)) {
                $this->saveFile($harvestedIds[0], $this->getCombinedXML($innerXML));
            }

            if (!empty($deletedIds)) {
                $this->saveDeletedRecords($deletedIds);
            }
        }

        // Do we have IDs to log and a log filename?  If so, log them:
        if (!empty($this->harvestedIdLog) && !empty($harvestedIds)) {
            $file = fopen($this->basePath . $this->harvestedIdLog, 'a');
            if (!$file) {
                throw new \Exception("Problem opening {$this->harvestedIdLog}.");
            }
            fputs($file, implode(PHP_EOL, $harvestedIds));
            fclose($file);
        }
    }

    /**
     * Support method for building combined XML document.
     *
     * @param string $innerXML XML for inside of document.
     *
     * @return string
     */
    protected function getCombinedXML($innerXML)
    {
        // Determine start and end tags from configuration:
        $start = $this->combineRecordsTag;
        $tmp = explode(' ', $start);
        $end = '</' . str_replace(['<', '>'], '', $tmp[0]) . '>';

        // Assemble the document:
        return $start . $innerXML . $end;
    }

    /**
     * Harvest records using OAI-PMH.
     *
     * @param array $params GET parameters for ListRecords method.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecords($params)
    {
        // Make the OAI-PMH request:
        $response = $this->sendRequest('ListRecords', $params);

        // Save the records from the response:
        if ($response->ListRecords->record) {
            $this->processRecords($response->ListRecords->record);
        }

        // If we have a resumption token, keep going; otherwise, we're done -- save
        // the end date.
        if (isset($response->ListRecords->resumptionToken)
            && !empty($response->ListRecords->resumptionToken)
        ) {
            return $response->ListRecords->resumptionToken;
        } else if ($this->endDate > 0) {
            $dateFormat = ($this->granularity == 'YYYY-MM-DD') ?
                'Y-m-d' : 'Y-m-d\TH:i:s\Z';
            $this->saveLastHarvestedDate(date($dateFormat, $this->endDate));
        }
        return false;
    }

    /**
     * Harvest records via OAI-PMH using date and set.
     *
     * @param string $from  Harvest start date (null for no specific start).
     * @param string $set   Set to harvest (null for all records).
     * @param string $until Harvest end date (null for no specific end).
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByDate($from = null, $set = null, $until = null)
    {
        $params = ['metadataPrefix' => $this->metadataPrefix];
        if (!empty($from)) {
            $params['from'] = $from;
        }
        if (!empty($set)) {
            $params['set'] = $set;
        }
        if (!empty($until)) {
            $params['until'] = $until;
        }
        return $this->getRecords($params);
    }

    /**
     * Harvest records via OAI-PMH using resumption token.
     *
     * @param string $token Resumption token.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByToken($token)
    {
        return $this->getRecords(['resumptionToken' => (string)$token]);
    }

    /**
     * Set configuration (support method for constructor).
     *
     * @param string $target   Target directory for harvest.
     * @param array  $settings Configuration
     *
     * @return void
     */
    protected function setConfig($target, $settings)
    {
        // Set up base URL:
        if (empty($settings['url'])) {
            throw new \Exception("Missing base URL for {$target}.");
        }
        $this->baseURL = $settings['url'];

        // Settings that may be mapped directly from $settings to class properties:
        $mappableSettings = [
            'set', 'metadataPrefix', 'idPrefix', 'idSearch', 'idReplace',
            'harvestedIdLog', 'injectId', 'injectSetSpec', 'injectSetName',
            'injectDate', 'injectHeaderElements', 'verbose', 'sanitize', 'badXMLLog',
            'httpUser', 'httpPass', 'timeout', 'combineRecords', 'combineRecordsTag',
        ];
        foreach ($mappableSettings as $current) {
            if (isset($settings[$current])) {
                $this->$current = $settings[$current];
            }
        }

        // Special case: $settings value does not match property value (for
        // readability):
        if (isset($settings['dateGranularity'])) {
            $this->granularity = $settings['dateGranularity'];
        }

        // Normalize injectHeaderElements to an array:
        if (!is_array($this->injectHeaderElements)) {
            $this->injectHeaderElements = [$this->injectHeaderElements];
        }
    }

    /**
     * Write a string to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function write($str)
    {
        // Bypass output when testing:
        if (defined('VUFIND_PHPUNIT_RUNNING')) {
            return;
        }
        Console::write($str);
    }

    /**
     * Write a string w/newline to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function writeLine($str)
    {
        // Bypass output when testing:
        if (defined('VUFIND_PHPUNIT_RUNNING')) {
            return;
        }
        Console::writeLine($str);
    }
}
