<?php
/**
 * World Cat Utilities
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Connection;
use File_MARCXML, VuFind\XSLT\Processor as XSLTProcessor, Zend\Config\Config;

/**
 * World Cat Utilities
 *
 * Class for accessing helpful WorldCat APIs.
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class WorldCatUtils implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * WorldCat configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * Should we silently ignore HTTP failures?
     *
     * @var bool
     */
    protected $silent;

    /**
     * Current server IP address
     *
     * @var string
     */
    protected $ip;

    /**
     * Constructor
     *
     * @param Config|string     $config WorldCat configuration (either a full Config
     * object, or a string containing the id setting).
     * @param \Zend\Http\Client $client HTTP client
     * @param bool              $silent Should we silently ignore HTTP failures?
     * @param string            $ip     Current server IP address (optional, but
     * needed for xID token hashing
     */
    public function __construct($config, \Zend\Http\Client $client, $silent = true,
        $ip = null
    ) {
        // Legacy compatibility -- prior to VuFind 2.4, this parameter was a string.
        if (!($config instanceof Config)) {
            $config = new Config(['id' => $config]);
        }
        $this->config = $config;
        $this->client = $client;
        $this->silent = $silent;
        $this->ip = $ip;
    }

    /**
     * Retrieve data over HTTP.
     *
     * @param string $url URL to access.
     *
     * @return string
     * @throws \Exception
     */
    protected function retrieve($url)
    {
        try {
            $response = $this->client->setUri($url)->setMethod('GET')->send();
            if ($response->isSuccess()) {
                return $response->getBody();
            }
            throw new \Exception('HTTP error');
        } catch (\Exception $e) {
            if (!$this->silent) {
                throw $e;
            }
        }
        return null;
    }

    /**
     * Get the WorldCat ID from the config file.
     *
     * @return string
     */
    protected function getWorldCatId()
    {
        return isset($this->config->id) ? $this->config->id : false;
    }

    /**
     * Build a url to use in querying OCLC's xID service.
     *
     * @param string $base      base url with no querystring
     * @param string $tokenVar  config file variable holding the token
     * @param string $secretVar config file variable holding the secret
     * @param string $format    data format for api response
     *
     * @return string
     */
    protected function buildXIdUrl($base, $tokenVar, $secretVar, $format)
    {
        $token = isset($this->config->$tokenVar)
            ? $this->config->$tokenVar : false;
        $secret = isset($this->config->$secretVar)
            ? $this->config->$secretVar : false;
        $querystr = '?method=getEditions&format=' . $format;
        if ($token && $secret) {
            $hash = md5($base . '|' . $this->ip . '|' . $secret);
            $querystr .= '&token=' . $token . '&hash=' . $hash;
        } if ($wcId = $this->getWorldCatId()) {
            $querystr .= '&ai=' . urlencode($wcId);
        }
        $base .= $querystr;
        return $base;
    }

    /**
     * Retrieve results from the index using the XISBN service.
     *
     * @param string $isbn ISBN of main record
     *
     * @return array       ISBNs for related items (may be empty).
     */
    public function getXISBN($isbn)
    {
        // Build URL
        $base = 'http://xisbn.worldcat.org/webservices/xid/isbn/' .
                urlencode(is_array($isbn) ? $isbn[0] : $isbn);
        $url = $this->buildXIdUrl($base, 'xISBN_token', 'xISBN_secret', 'json');

        // Print Debug code
        $this->debug("XISBN: $url");
        $response = json_decode($this->retrieve($url));

        // Fetch results
        $isbns = [];
        if (isset($response->list)) {
            foreach ($response->list as $line) {
                // Filter out non-ISBN characters and validate the length of
                // whatever is left behind; this will prevent us from treating
                // error messages like "invalidId" or "overlimit" as ISBNs.
                $isbn = preg_replace(
                    '/[^0-9xX]/', '', isset($line->isbn[0]) ? $line->isbn[0] : ''
                );
                if (strlen($isbn) >= 10) {
                    $isbns[] = $isbn;
                }
            }
        }
        return $isbns;
    }

    /**
     * Retrieve results from the index using the XOCLCNUM service.
     *
     * @param string $oclc OCLC number of main record
     *
     * @return array       ISBNs for related items (may be empty).
     */
    public function getXOCLCNUM($oclc)
    {
        // Build URL
        $base = 'http://xisbn.worldcat.org/webservices/xid/oclcnum/' .
                urlencode(is_array($oclc) ? $oclc[0] : $oclc);
        $url = $this->buildXIdUrl($base, 'xISBN_token', 'xISBN_secret', 'json');

        // Print Debug code
        $this->debug("XOCLCNUM: $url");
        $response = json_decode($this->retrieve($url));

        // Fetch results
        $results = [];
        if (isset($response->list)) {
            foreach ($response->list as $line) {
                $values = isset($line->oclcnum) ? $line->oclcnum : [];
                foreach ($values as $data) {
                    // Filter out non-numeric characters and validate the length of
                    // whatever is left behind; this will prevent us from treating
                    // error messages like "invalidId" or "overlimit" as ISBNs.
                    $current = preg_replace('/[^0-9]/', '', $data);
                    if (!empty($current)) {
                        $results[] = $current;
                    }
                }
            }
        }

        return array_unique($results);
    }

    /**
     * Retrieve results from the index using the XISSN service.
     *
     * @param string $issn ISSN of main record
     *
     * @return array       ISSNs for related items (may be empty).
     */
    public function getXISSN($issn)
    {
        // Build URL
        $base = 'http://xissn.worldcat.org/webservices/xid/issn/' .
                urlencode(is_array($issn) ? $issn[0] : $issn);
        $url = $this->buildXIdUrl($base, 'xISSN_token', 'xISSN_secret', 'xml');

        // Print Debug code
        $this->debug("XISSN: $url");

        // Fetch results
        $issns = [];
        $xml = $this->retrieve($url);
        if (!empty($xml)) {
            $data = simplexml_load_string($xml);
            if (!empty($data) && isset($data->group->issn)
                && count($data->group->issn) > 0
            ) {
                foreach ($data->group->issn as $issn) {
                    $issns[] = (string)$issn;
                }
            }
        }

        return $issns;
    }

    /**
     * Support function for getIdentitiesQuery(); is the provided name component
     * worth considering as a first or last name?
     *
     * @param string $current Name chunk to examine.
     *
     * @return boolean        Should we use this as a name?
     */
    protected function isUsefulNameChunk($current)
    {
        // Some common prefixes and suffixes that we do not want to treat as first
        // or last names:
        static $badChunks = ['jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii',
            'viii', 'ix', 'x', 'junior', 'senior', 'esq', 'mr', 'mrs', 'miss', 'dr'];

        // Clean up the input string:
        $current = str_replace('.', '', strtolower($current));

        // We don't want to use empty, numeric or known bad strings!
        if (empty($current) || is_numeric($current)
            || in_array($current, $badChunks)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Support function for getRelatedIdentities() -- parse a name into a query
     * for WorldCat Identities.
     *
     * @param string $name Name to parse.
     *
     * @return mixed       False if useless string; Identities query otherwise.
     */
    protected function getIdentitiesQuery($name)
    {
        // Clean up user query and try to find name components within it:
        $name = trim(str_replace(['"', ',', '-'], ' ', $name));
        $parts = explode(' ', $name);
        $first = $last = '';
        foreach ($parts as $current) {
            $current = trim($current);
            // Do we want to store this chunk?
            if ($this->isUsefulNameChunk($current)) {
                // Is the first name empty?  If so, save this there.
                if (empty($first)) {
                    $first = $current;
                } else if (strlen($current) > 2 || empty($last)) {
                    // If this isn't the first name, we always want to save it as the
                    // last name UNLESS it's an initial, in which case we'll only
                    // save it if we don't already have something better!
                    $last = $current;
                }
            }
        }

        // Fail if we found no useful name components; otherwise, build up the query
        // based on whether we found a first name only or both first and last names:
        if (empty($first) && empty($last)) {
            return false;
        } else if (empty($last)) {
            return "local.PersonalName=\"{$first}\"";
        } else {
            return "local.PersonalName=\"{$last}\" "
                . "and local.PersonalName=\"{$first}\"";
        }
    }

    /**
     * Support method for getRelatedIdentities() -- extract subject headings from
     * the current node of the Identities API response.
     *
     * @param array $current Current response node.
     *
     * @return array         Extracted subject headings.
     */
    protected function processIdentitiesSubjects($current)
    {
        // Normalize subjects array if it has only a single entry:
        $subjects = isset($current->fastHeadings->fast) ?
            $current->fastHeadings->fast : null;
        if (isset($subjects->tag)) {
            $subjects = [$subjects];
        }

        // Collect subjects for current name:
        $retVal = [];
        if (!is_null($subjects) && count($subjects) > 0) {
            foreach ($subjects as $currentSubject) {
                if ($currentSubject['tag'] == '650') {
                    $text = (string)$currentSubject;
                    if (!empty($text)) {
                        // Double dash will cause problems with Solr searches, so
                        // represent subject heading subdivisions differently:
                        $retVal[] = str_replace('--', ': ', $text);
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Get the URL to perform a related identities query.
     *
     * @param string $query      Query
     * @param int    $maxRecords Max # of records to read from API (more = slower).
     *
     * @return string
     */
    protected function getRelatedIdentitiesUrl($query, $maxRecords)
    {
        return "http://worldcat.org/identities/search/PersonalIdentities" .
            "?query=" . urlencode($query) .
            "&version=1.1" .
            "&operation=searchRetrieve" .
            "&recordSchema=info%3Asrw%2Fschema%2F1%2FIdentities" .
            "&maximumRecords=" . intval($maxRecords) .
            "&startRecord=1" .
            "&resultSetTTL=300" .
            "&recordPacking=xml" .
            "&recordXPath=" .
            "&sortKeys=holdingscount";
    }

    /**
     * Given a name string, get related identities.  Inspired by Eric Lease
     * Morgan's Name Finder demo (http://zoia.library.nd.edu/sandbox/name-finder/).
     * Return value is an associative array where key = author name and value =
     * subjects used in that author's works.
     *
     * @param string $name       Name to search for (any format).
     * @param int    $maxRecords Max # of records to read from API (more = slower).
     *
     * @return mixed             False on error, otherwise array of related names.
     */
    public function getRelatedIdentities($name, $maxRecords = 10)
    {
        // Build the WorldCat Identities API query:
        if (!($query = $this->getIdentitiesQuery($name))) {
            return false;
        }

        // Get the API response and translate it into an object:
        $data = simplexml_load_string(
            $this->retrieve($this->getRelatedIdentitiesUrl($query, $maxRecords))
        );

        // Give up if expected data is missing:
        if (!isset($data->records->record)) {
            return false;
        }

        // Loop through data and collect names and related subjects:
        $output = [];
        foreach ($data->records->record as $current) {
            // Build current name string:
            $current = isset($current->recordData->Identity->nameInfo) ?
                $current->recordData->Identity->nameInfo : null;
            if (isset($current['type']) && $current['type'] == 'personal'
                && !empty($current->rawName->suba)
            ) {
                $currentName = $current->rawName->suba .
                    (isset($current->rawName->subd) ?
                        ', ' . $current->rawName->subd : '');

                // Get subject list for current identity; if the current name is a
                // duplicate of a previous name, merge the subjects together:
                $subjects = $this->processIdentitiesSubjects($current);
                $output[$currentName] = isset($output[$currentName])
                    ? array_unique(array_merge($output[$currentName], $subjects))
                    : $subjects;
            }
        }

        return $output;
    }

    /**
     * Given a subject term, get related (broader/narrower/alternate) terms.
     * Loosely adapted from Eric Lease Morgan's Term Finder demo (see
     * http://zoia.library.nd.edu/sandbox/term-finder/).  Note that this is
     * intended as a fairly fuzzy search -- $term need not be an exact subject
     * heading; this function will return best guess matches in the 'exact'
     * key, possible broader terms in the 'broader' key and possible narrower
     * terms in the 'narrower' key of the return array.
     *
     * @param string $term       Term to get related terms for.
     * @param string $vocabulary Vocabulary to search (default = LCSH; see OCLC docs
     * for other options).
     * @param int    $maxRecords Max # of records to read from API (more = slower).
     *
     * @return mixed             False on error, otherwise array of related terms,
     * keyed by category.
     */
    public function getRelatedTerms($term, $vocabulary = 'lcsh', $maxRecords = 10)
    {
        // Strip quotes from incoming term:
        $term = str_replace('"', '', $term);

        // Build the request URL:
        $url = "http://tspilot.oclc.org/" . urlencode($vocabulary) . "/?" .
            // Search for the user-supplied term in both preferred and alternative
            // fields!
            "query=oclcts.preferredTerm+%3D+%22" . urlencode($term) .
                "%22+OR+oclcts.alternativeTerms+%3D+%22" . urlencode($term) . "%22" .
            "&version=1.1" .
            "&operation=searchRetrieve" .
            "&recordSchema=info%3Asrw%2Fschema%2F1%2Fmarcxml-v1.1" .
            "&maximumRecords=" . intval($maxRecords) .
            "&startRecord=1" .
            "&resultSetTTL=300" .
            "&recordPacking=xml" .
            "&recordXPath=" .
            "&sortKeys=recordcount";

        // Get the API response:
        $data = $this->retrieve($url);

        // Extract plain MARCXML from the WorldCat response:
        $marcxml = XSLTProcessor::process('wcterms-marcxml.xsl', $data);

        // Try to parse the MARCXML into a File_MARC object; if this fails,
        // we probably have bad MARCXML, which may indicate an API failure
        // or an empty record set.  Just give up if this happens!
        try {
            $marc = new \File_MARCXML($marcxml, File_MARCXML::SOURCE_STRING);
        } catch (\File_MARC_Exception $e) {
            return false;
        }

        // Initialize arrays:
        $exact = [];
        $broader = [];
        $narrower = [];

        while ($record = $marc->next()) {
            // Get exact terms; only save it if it is not a subset of the requested
            // term.
            $main = $this->getExactTerm($record);
            if ($main && !stristr($term, $main)) {
                $exact[] = $main;
            }

            // Get broader/narrower terms:
            $related = $record->getFields('550');
            foreach ($related as $current) {
                $type = $current->getSubfield('w');
                $value = $current->getSubfield('a');
                if ($type && $value) {
                    $type = (string)$type->getData();
                    $value = (string)$value->getData();
                    if ($type == 'g') {
                        // Don't save exact matches to the user-entered term:
                        if (strcasecmp($term, $value) != 0) {
                            $broader[] = $value;
                        }
                    } else if ($type == 'h') {
                        // Don't save exact matches to the user-entered term:
                        if (strcasecmp($term, $value) != 0) {
                            $narrower[] = $value;
                        }
                    }
                }
            }
        }

        // Send back everything we found, sorted and filtered for uniqueness; note
        // that we do NOT sort FAST results since they support relevance ranking.
        // As of this writing, other vocabularies do not support relevance.
        if ($vocabulary !== 'fast') {
            natcasesort($exact);
            natcasesort($broader);
            natcasesort($narrower);
        }
        return [
            'exact' => array_unique($exact),
            'broader' => array_unique($broader),
            'narrower' => array_unique($narrower)
        ];
    }

    /**
     * Extract an exact term from a MARC record.
     *
     * @param \File_MARC_Record $record MARC record
     *
     * @return string
     */
    protected function getExactTerm($record)
    {
        // Get exact terms:
        $actual = $record->getField('150');
        if (!$actual || !($main = $actual->getSubfield('a'))) {
            return false;
        }

        // Some versions of File_MARCXML seem to have trouble returning
        // strings properly (giving back XML objects instead); let's
        // cast to string to be sure we get what we expect!
        $main = (string)$main->getData();

        // Add subdivisions:
        $subdivisions = $actual->getSubfields('x');
        if ($subdivisions) {
            foreach ($subdivisions as $current) {
                $main .= ', ' . (string)$current->getData();
            }
        }
        return $main;
    }
}
