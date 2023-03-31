<?php

/**
 * World Cat Utilities
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Connection;

use Laminas\Config\Config;

/**
 * World Cat Utilities
 *
 * Class for accessing helpful WorldCat APIs.
 *
 * @category VuFind
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class WorldCatUtils implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * WorldCat configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
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
     * @param Config|string        $config WorldCat configuration (either a full
     * Config object, or a string containing the id setting).
     * @param \Laminas\Http\Client $client HTTP client
     * @param bool                 $silent Should we silently ignore HTTP failures?
     * @param string               $ip     Current server IP address (optional, but
     * needed for xID token hashing
     */
    public function __construct(
        $config,
        \Laminas\Http\Client $client,
        $silent = true,
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
     * Support function for getIdentitiesQuery(); is the provided name component
     * worth considering as a first or last name?
     *
     * @param string $current Name chunk to examine.
     *
     * @return bool           Should we use this as a name?
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
        if (
            empty($current) || is_numeric($current)
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
                } elseif (strlen($current) > 2 || empty($last)) {
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
        } elseif (empty($last)) {
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
        $subjects = $current->fastHeadings->fast ?? null;
        if (isset($subjects->tag)) {
            $subjects = [$subjects];
        }

        // Collect subjects for current name:
        $retVal = [];
        if (null !== $subjects && count($subjects) > 0) {
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
            $current = $current->recordData->Identity->nameInfo ?? null;
            if (
                isset($current['type']) && $current['type'] == 'personal'
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
}
