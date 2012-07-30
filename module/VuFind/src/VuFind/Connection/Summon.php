<?php
/**
 * Summon Search API Interface (VuFind implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2009.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
namespace VuFind\Connection;
use SerialsSolutions\Summon\Zend2 as BaseSummon,
    VuFind\Config\Reader as ConfigReader,
    VuFind\Http\Client as HttpClient,
    VuFind\Log\Logger,
    VuFind\Solr\Utils as SolrUtils;

/**
 * Summon Search API Interface (VuFind implementation)
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class Summon extends BaseSummon
{
    /**
     * Should boolean operators in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?
     */
    protected $caseSensitiveBooleans = true;

    /**
     * Will we include snippets in responses?
     * @var bool
     */
    protected $snippets = false;

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param string $apiId   Summon API ID
     * @param string $apiKey  Summon API Key
     * @param array  $options Associative array of additional options; legal keys:
     *    <ul>
     *      <li>authedUser - is the end-user authenticated?</li>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>host - base URL of Summon API</li>
     *      <li>sessionId - Summon session ID to apply</li>
     *      <li>version - API version to use</li>
     *    </ul>
     */
    public function __construct($apiId, $apiKey, $options = array())
    {
        $config = ConfigReader::getConfig('Summon');

        // Store preferred boolean behavior:
        if (!isset($options['caseSensitiveBooleans'])
            && isset($config->General->case_sensitive_bools)
        ) {
            $this->caseSensitiveBooleans = $config->General->case_sensitive_bools;
        } else {
            $this->caseSensitiveBooleans = $options['caseSensitiveBooleans'];
        }

        // Set default snippet behavior if necessary:
        if (isset($config->General->snippets)) {
            $this->snippets = $config->General->snippets;
        }

        // Set default debug behavior:
        $this->logger = Logger::getInstance();
        if (!isset($options['debug'])) {
            $options['debug'] = $this->logger->debugNeeded();
        }

        $timeout = isset($config->General->timeout)
            ? $config->General->timeout : 30;
        parent::__construct(
            $apiId, $apiKey, $options, new HttpClient(
                null, array('timeout' => $timeout)
            )
        );
    }

    /**
     * Print a message if debug is enabled.
     *
     * @param string $msg Message to print
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        if ($this->debug) {
            $this->logger->debug("<pre>{$msg}</pre>\n");
        }
    }

    /**
     * Build basic Query string from search parameters (support method for
     * buildQuery)
     *
     * @param array $params An array of search parameters
     *
     * @return string
     */
    protected function buildBasicQuery($params)
    {
        // Clean and validate input -- note that index may be in a
        // different field depending on whether this is a basic or
        // advanced search.
        $lookfor = $params['lookfor'];
        if (isset($params['field'])) {
            $index = $params['field'];
        } else if (isset($params['index'])) {
            $index = $params['index'];
        } else {
            $index = 'AllFields';
        }

        // Force boolean operators to uppercase if we are in a
        // case-insensitive mode:
        if (!$this->caseSensitiveBooleans) {
            $lookfor = SolrUtils::capitalizeBooleans($lookfor);
        }

        // Prepend the index name, unless it's the special "AllFields"
        // index:
        return ($index != 'AllFields') ? "{$index}:($lookfor)" : $lookfor;
    }

    /**
     * Build Query string from search parameters
     *
     * @param array $search An array of search parameters
     *
     * @return string       The query
     */
    public function buildQuery($search)
    {
        $groups   = array();
        $excludes = array();
        if (is_array($search)) {
            $query = '';

            foreach ($search as $params) {
                // Advanced Search
                if (isset($params['group'])) {
                    $thisGroup = array();
                    // Process each search group
                    foreach ($params['group'] as $group) {
                        // Build this group individually as a basic search
                        $thisGroup[] = $this->buildQuery(array($group));
                    }
                    // Is this an exclusion (NOT) group or a normal group?
                    if ($params['group'][0]['bool'] == 'NOT') {
                        $excludes[] = join(" OR ", $thisGroup);
                    } else {
                        $groups[] = join(
                            " " . $params['group'][0]['bool'] . " ", $thisGroup
                        );
                    }
                }

                // Basic Search
                if (isset($params['lookfor']) && $params['lookfor'] != '') {
                    $query .= $this->buildBasicQuery($params);
                }
            }
        }

        // Put our advanced search together
        if (count($groups) > 0) {
            $query = "(" . join(") " . $search[0]['join'] . " (", $groups) . ")";
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $query .= " NOT ((" . join(") OR (", $excludes) . "))";
        }

        // Ensure we have a valid query to this point
        return isset($query) ? $query : '';
    }

    /**
     * Perform normalization and analysis of Summon return value.
     *
     * @param array $input The raw response from Summon
     *
     * @throws SerialsSolutions_Summon_Exception
     * @return array       The processed response from Summon
     */
    protected function process($input)
    {
        $result = parent::process($input);

        // Process highlighting/snippets:
        foreach ($result['documents'] as $i => $current) {
            // Remove snippets if not desired:
            if (!$this->snippets) {
                unset($result['documents'][$i]['Snippet']);
            }
        }

        return $result;
    }
}
