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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Connection;
use VuFind\Config\Reader as ConfigReader, VuFind\Log\Logger,
    VuFind\XSLT\Processor as XSLTProcessor;

/**
 * World Cat Utilities
 *
 * Class for accessing helpful WorldCat APIs.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class WorldCatUtils
{
    /**
     * Get the WorldCat ID from the config file.
     *
     * @return string
     */
    protected function getWorldCatId()
    {
        static $wcId = null;
        if (is_null($wcId)) {
            $config = ConfigReader::getConfig();
            $wcId = isset($config->WorldCat->id)
                ? $config->WorldCat->id : false;
        }
        return $wcId;
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
        $url = 'http://xisbn.worldcat.org/webservices/xid/isbn/' .
                urlencode(is_array($isbn) ? $isbn[0] : $isbn) .
               '?method=getEditions&format=csv';
        if ($wcId = $this->getWorldCatId()) {
            $url .= '&ai=' . urlencode($wcId);
        }

        // Print Debug code
        Logger::getInstance()->debug("XISBN: $url");

        // Fetch results
        $isbns = array();
        if ($fp = @fopen($url, "r")) {
            while (($data = fgetcsv($fp, 1000, ",")) !== false) {
                // Filter out non-ISBN characters and validate the length of
                // whatever is left behind; this will prevent us from treating
                // error messages like "invalidId" or "overlimit" as ISBNs.
                $isbn = preg_replace('/[^0-9xX]/', '', $data[0]);
                if (strlen($isbn) < 10) {
                    continue;
                }
                $isbns[] = $isbn;
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
        $url = 'http://xisbn.worldcat.org/webservices/xid/oclcnum/' .
                urlencode(is_array($oclc) ? $oclc[0] : $oclc) .
               '?method=getEditions&format=csv';
        if ($wcId = $this->getWorldCatId()) {
            $url .= '&ai=' . urlencode($wcId);
        }

        // Print Debug code
        Logger::getInstance()->debug("XOCLCNUM: $url");

        // Fetch results
        $results = array();
        if ($fp = @fopen($url, "r")) {
            while (($data = fgetcsv($fp, 1000, ",")) !== false) {
                // Filter out non-numeric characters and validate the length of
                // whatever is left behind; this will prevent us from treating
                // error messages like "invalidId" or "overlimit" as ISBNs.
                $current = preg_replace('/[^0-9]/', '', $data[0]);
                if (empty($current)) {
                    continue;
                }
                $results[] = $current;
            }
        }

        return $results;
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
        $url = 'http://xissn.worldcat.org/webservices/xid/issn/' .
                urlencode(is_array($issn) ? $issn[0] : $issn) .
               //'?method=getEditions&format=csv';
               '?method=getEditions&format=xml';
        if ($wcId = $this->getWorldCatId()) {
            $url .= '&ai=' . urlencode($wcId);
        }

        // Print Debug code
        Logger::getInstance()->debug("XISSN: $url");

        // Fetch results
        $issns = array();
        $xml = @file_get_contents($url);
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
        static $badChunks = array('jr', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii',
            'viii', 'ix', 'x', 'junior', 'senior', 'esq', 'mr', 'mrs', 'miss', 'dr');

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
        $name = trim(str_replace(array('"', ',', '-'), ' ', $name));
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
            return "local.Name=\"{$first}\"";
        } else {
            return "local.Name=\"{$last}\" and local.Name=\"{$first}\"";
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
            $subjects = array($subjects);
        }

        // Collect subjects for current name:
        $retVal = array();
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
        $query = $this->getIdentitiesQuery($name);
        if (!$query) {
            return false;
        }

        // Get the API response:
        $url = "http://worldcat.org/identities/search/PersonalIdentities" .
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
        $xml = @file_get_contents($url);

        // Translate XML to object:
        $data = simplexml_load_string($xml);

        // Give up if expected data is missing:
        if (!isset($data->records->record)) {
            return false;
        }

        // Loop through data and collect names and related subjects:
        $output = array();
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
        $data = @file_get_contents($url);

        // Extract plain MARCXML from the WorldCat response:
        $marcxml = XSLTProcessor::process('wcterms-marcxml.xsl', $data);

        // Try to parse the MARCXML into a File_MARC object; if this fails,
        // we probably have bad MARCXML, which may indicate an API failure
        // or an empty record set.  Just give up if this happens!
        try {
            $marc = new File_MARCXML($marcxml, File_MARCXML::SOURCE_STRING);
        } catch (File_MARC_Exception $e) {
            return false;
        }

        // Initialize arrays:
        $exact = array();
        $broader = array();
        $narrower = array();

        while ($record = $marc->next()) {
            // Get exact terms:
            $actual = $record->getField('150');
            if ($actual) {
                $main = $actual->getSubfield('a');
                if ($main) {
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

                    // Only save the actual term if it is not a subset of the
                    // requested term.
                    if (!stristr($term, $main)) {
                        $exact[] = $main;
                    }
                }
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
        return array(
            'exact' => array_unique($exact),
            'broader' => array_unique($broader),
            'narrower' => array_unique($narrower)
        );
    }
}