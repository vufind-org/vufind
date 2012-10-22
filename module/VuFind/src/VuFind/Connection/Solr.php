<?php
/**
 * Solr HTTP Interface
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Solr
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
namespace VuFind\Connection;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Exception\Solr as SolrException, VuFind\Http\Client as HttpClient,
    VuFind\Solr\Utils as SolrUtils, Zend\Log\LoggerInterface,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Solr HTTP Interface
 *
 * @category VuFind2
 * @package  Solr
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class Solr implements ServiceLocatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * The host to connect to
     * @var string
     */
    protected $host;

    /**
     * The core being used on the host
     * @var string
     */
    protected $core;

    /**
     * The HTTP request object used for REST transactions
     * @var object \VuFind\Http\Client
     */
    protected $client;

    /**
     * An array of characters that are illegal in search strings
     */
    protected $illegal = array('!', ':', ';', '[', ']', '{', '}');

    /**
     * The path to the YAML file specifying available search types:
     */
    protected $searchSpecsFile = 'searchspecs.yaml';

    /**
     * An array of search specs pulled from $searchSpecsFile (above)
     */
    protected $searchSpecs = false;

    /**
     * Should boolean operators in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?
     */
    protected $caseSensitiveBooleans = true;

    /**
     * Should range operators (i.e. [a TO b]) in the search string be treated as
     * case-insensitive (false), or must they be ALL UPPERCASE (true)?  Note that
     * making this setting case insensitive not only changes the word "TO" to
     * uppercase but also inserts OR clauses to check for case insensitive matches
     * against the edges of the range...  i.e. ([a TO b] OR [A TO B]).
     */
    protected $caseSensitiveRanges = true;

    /**
     * Selected shard settings.
     */
    protected $solrShards = array();
    protected $solrShardsFieldsToStrip = array();

    /**
     * Defaults used to populate the $userSearchParams array when the user omits
     * keys from their options array.
     */
    protected $searchDefaults = array(
        'query' => '*:*', 'handler' => null, 'filter' => null, 'start' => 0,
        'limit' => 20, 'facet' => null, 'spell' => '', 'dictionary' => null,
        'sort' => null, 'fields' => '*,score', 'method' => 'POST',
        'highlight' => false, 'group' => array()
    );

    /**
     * User preferences for the current search (reinitialized every time search()
     * is called).
     */
    protected $userSearchParams;

    /**
     * Solr parameters derived from $userSearchParams (reinitialized every time
     * search() is called).
     */
    protected $solrSearchParams;

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param string $base The base URL for the local Solr Server
     * @param string $core The core to use on the specified server
     */
    public function __construct($base, $core = '')
    {
        $this->core = $core;
        $this->host = $base . (empty($this->core) ? '' : ('/' . $this->core));
        $this->client = new HttpClient(
            null, array('timeout' => $this->getHttpTimeout())
        );
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Does the current logger require debug messages?
     *
     * @return bool
     */
    protected function debugNeeded()
    {
        // There are three situations we need to worry about:
        // - Logger not set -- no debug needed
        // - Logger set but does not support debugNeeded() method -- assume debug
        // - Logger has debugNeeded() method -- defer to that
        if (!$this->logger) {
            return false;
        }
        return !method_exists($this->logger, 'debugNeeded')
            || $this->logger->debugNeeded();
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Is this object configured with case-sensitive boolean operators?
     *
     * @return boolean
     */
    public function hasCaseSensitiveBooleans()
    {
        return $this->caseSensitiveBooleans;
    }

    /**
     * Is this object configured with case-sensitive range operators?
     *
     * @return boolean
     */
    public function hasCaseSensitiveRanges()
    {
        return $this->caseSensitiveRanges;
    }

    /**
     * Get the search specifications loaded from the specified YAML file.
     *
     * @param string $handler The named search to provide information about (set
     * to null to get all search specifications)
     *
     * @return mixed Search specifications array if available, false if an invalid
     * search is specified.
     */
    protected function getSearchSpecs($handler = null)
    {
        // Only load specs once:
        if ($this->searchSpecs === false) {
            $this->searchSpecs = $this->getServiceLocator()->get('SearchSpecsReader')
                ->get($this->searchSpecsFile);
        }

        // Special case -- null $handler means we want all search specs.
        if (is_null($handler)) {
            return $this->searchSpecs;
        }

        // Return specs on the named search if found (easiest, most common case).
        if (isset($this->searchSpecs[$handler])) {
            return $this->searchSpecs[$handler];
        }

        // Check for a case-insensitive match -- this provides backward
        // compatibility with different cases used in early VuFind versions
        // and allows greater tolerance of minor typos in config files.
        foreach ($this->searchSpecs as $name => $specs) {
            if (strcasecmp($name, $handler) == 0) {
                return $specs;
            }
        }

        // If we made it this far, no search specs exist -- return false.
        return false;
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $id     The document to retrieve from Solr
     * @param array  $extras Extra parameters to pass to Solr (optional)
     *
     * @throws SolrException
     * @return string    The requested resource (or null if bad ID)
     */
    public function getRecord($id, $extras = array())
    {
        if ($this->debugNeeded()) {
            $this->debug('Get Record: '.$id);
        }

        // Query String Parameters
        $options = $extras + array('q' => 'id:"' . addcslashes($id, '"') . '"');
        $result = $this->select('GET', $options);

        return isset($result['response']['docs'][0]) ?
            $result['response']['docs'][0] : null;
    }

    /**
     * Get records similiar to one record
     * Uses MoreLikeThis Request Handler
     *
     * Uses SOLR MLT Query Handler
     *
     * @param string $id     A Solr document ID.
     * @param array  $extras Extra parameters to pass to Solr (optional)
     *
     * @throws SolrException
     * @return array     An array of query results similar to the specified record
     */
    public function getMoreLikeThis($id, $extras = array())
    {
        // Query String Parameters
        $options = $extras + array(
            'q' => 'id:"' . addcslashes($id, '"') . '"',
            'qt' => 'morelikethis'
        );
        $result = $this->select('GET', $options);

        return $result;
    }

     /**
      * Internal method to build query string from search parameters
      *
      * @param array  $structure The SearchSpecs-derived structure or substructure
      * defining the search, derived from the yaml file
      * @param array  $values    The various values in an array with keys
      * 'onephrase', 'and', 'or' (and perhaps others)
      * @param string $joiner    The operator used to combine generated clauses
      *
      * @throws SolrException
      * @return string           A search string suitable for adding to a query URL
      */
    protected function applySearchSpecs($structure, $values, $joiner = "OR")
    {
        $clauses = array();
        foreach ($structure as $field => $clausearray) {
            if (is_numeric($field)) {
                // shift off the join string and weight
                $sw = array_shift($clausearray);
                $internalJoin = ' ' . $sw[0] . ' ';
                // Build it up recursively
                $sstring = '(' .
                    $this->applySearchSpecs($clausearray, $values, $internalJoin) .
                    ')';
                // ...and add a weight if we have one
                $weight = $sw[1];
                if (!is_null($weight) && $weight && $weight > 0) {
                    $sstring .= '^' . $weight;
                }
                // push it onto the stack of clauses
                $clauses[] = $sstring;
            } else if (!$this->isStripped($field)) {
                // Otherwise, we've got a (list of) [munge, weight] pairs to deal
                // with
                foreach ($clausearray as $spec) {
                    // build a string like title:("one two")
                    $sstring = $field . ':(' . $values[$spec[0]] . ')';
                    // Add the weight if we have one. Yes, I know, it's redundant
                    // code.
                    $weight = $spec[1];
                    if (!is_null($weight) && $weight && $weight > 0) {
                        $sstring .= '^' . $weight;
                    }
                    // ..and push it on the stack of clauses
                    $clauses[] = $sstring;
                }
            }
        }

        // Join it all together
        return implode(' ' . $joiner . ' ', $clauses);
    }

    /**
     * _getStrippedFields -- internal method to read the fields that should get
     * stripped for the used shards from config file
     *
     * @return array An array containing any field that should be stripped from query
     */
    protected function getStrippedFields()
    {
        // Store stripped fields as a static variable so that we only need to
        // process the configuration settings once:
        static $strippedFields = false;
        if ($strippedFields === false) {
            $strippedFields = array();
            foreach ($this->solrShards as $index => $address) {
                if (array_key_exists($index, $this->solrShardsFieldsToStrip)) {
                    $parts = explode(',', $this->solrShardsFieldsToStrip[$index]);
                    foreach ($parts as $part) {
                        $strippedFields[] = trim($part);
                    }
                }
            }
            $strippedFields = array_unique($strippedFields);
        }

        return $strippedFields;
    }

    /**
     * _isStripped -- internal method to check if a field is stripped from query
     *
     * @param string $field The name of the field that should be checked for
     * stripping
     *
     * @return bool         A boolean value indicating whether the field should be
     * stripped (true) or not (false)
     */
    protected function isStripped($field)
    {
        // Never strip fields if shards are disabled.
        // Return true if the current field needs to be stripped.
        if (isset($this->solrShards)
            && in_array($field, $this->getStrippedFields())
        ) {
            return true;
        }
        return false;
    }

    /**
     * Given a field name and search string, return an array containing munged
     * versions of the search string for use in _applySearchSpecs().
     *
     * @param string $field   The YAML search spec field name to search
     * @param string $lookfor The string to search for in the field
     * @param array  $custom  Custom munge settings from YAML search specs
     * @param bool   $basic   Is $lookfor a basic (true) or advanced (false) query?
     *
     * @return  array         Array for use as _applySearchSpecs() values param
     */
    protected function buildMungeValues($field, $lookfor, $custom = null,
        $basic = true
    ) {
        // Only tokenize basic queries:
        if ($basic) {
            // Tokenize Input
            $tokenized = $this->tokenizeInput($lookfor);

            // Create AND'd and OR'd queries
            $andQuery = implode(' AND ', $tokenized);
            $orQuery = implode(' OR ', $tokenized);

            // Build possible inputs for searching:
            $values = array();
            $values['onephrase']
                = '"' . str_replace('"', '', implode(' ', $tokenized)) . '"';
            $values['and'] = $andQuery;
            $values['or'] = $orQuery;
        } else {
            // If we're skipping tokenization, we just want to pass $lookfor through
            // unmodified (it's probably an advanced search that won't benefit from
            // tokenization).  We'll just set all possible values to the same thing,
            // except that we'll try to do the "one phrase" in quotes if possible.
            // IMPORTANT: If we detect a boolean NOT, we MUST omit the quotes.
            $onephrase = (strstr($lookfor, '"') || strstr($lookfor, ' NOT '))
                ? $lookfor : '"' . $lookfor . '"';
            $values = array(
                'onephrase' => $onephrase, 'and' => $lookfor, 'or' => $lookfor
            );
        }

        // Apply custom munge operations if necessary:
        if (is_array($custom)) {
            foreach ($custom as $mungeName => $mungeOps) {
                $values[$mungeName] = $lookfor;

                // Skip munging of advanced queries:
                if ($basic) {
                    foreach ($mungeOps as $operation) {
                        switch($operation[0]) {
                        case 'append':
                            $values[$mungeName] .= $operation[1];
                            break;
                        case 'lowercase':
                            $values[$mungeName] = strtolower($values[$mungeName]);
                            break;
                        case 'preg_replace':
                            $values[$mungeName] = preg_replace(
                                $operation[1], $operation[2], $values[$mungeName]
                            );
                            break;
                        case 'uppercase':
                            $values[$mungeName] = strtoupper($values[$mungeName]);
                            break;
                        }
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Given a field name and search string, expand this into the necessary Lucene
     * query to perform the specified search on the specified field(s).
     *
     * @param string $field   The YAML search spec field name to search
     * @param string $lookfor The string to search for in the field
     * @param bool   $basic   Is $lookfor a basic (true) or advanced (false) query?
     *
     * @return string         The query
     */
    protected function buildQueryComponent($field, $lookfor, $basic = true)
    {
        // Load the YAML search specifications:
        $ss = $this->getSearchSpecs($field);

        // If we received a field spec that wasn't defined in the YAML file,
        // let's try simply passing it along to Solr.
        if ($ss === false) {
            return $field . ':(' . $lookfor . ')';
        }

        // If this is a basic query and we have Dismax settings, let's build
        // a Dismax subquery to avoid some of the ugly side effects of our Lucene
        // query generation logic.
        if ($basic && isset($ss['DismaxFields'])) {
            $qf = implode(' ', $ss['DismaxFields']);
            $dmParams = '';
            if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
                foreach ($ss['DismaxParams'] as $current) {
                    $dmParams .= ' ' . $current[0] . "='" .
                        addcslashes($current[1], "'") . "'";
                }
            }
            $dismaxQuery = '{!dismax qf="' . $qf . '"' . $dmParams . '}' . $lookfor;
            $baseQuery = '_query_:"' . addslashes($dismaxQuery) . '"';
        } else {
            // Munge the user query in a few different ways:
            $customMunge = isset($ss['CustomMunge']) ? $ss['CustomMunge'] : null;
            $values
                = $this->buildMungeValues($field, $lookfor, $customMunge, $basic);

            // Apply the $searchSpecs property to the data:
            $baseQuery = $this->applySearchSpecs($ss['QueryFields'], $values);
        }

        // Apply filter query if applicable:
        if (isset($ss['FilterQuery'])) {
            return "({$baseQuery}) AND ({$ss['FilterQuery']})";
        }

        return "($baseQuery)";
    }

    /**
     * Given a field name and search string known to contain advanced features
     * (as identified by isAdvanced()), expand this into the necessary Lucene
     * query to perform the specified search on the specified field(s).
     *
     * @param string $handler The YAML search spec field name to search
     * @param string $query   The string to search for in the field
     *
     * @return  string        The query
     */
    protected function buildAdvancedQuery($handler, $query)
    {
        $query = $this->buildAdvancedInnerQuery($handler, $query);

        // Apply boost query/boost function, if any:
        $ss = $this->getSearchSpecs($handler);
        $bq = array();
        if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
            foreach ($ss['DismaxParams'] as $current) {
                if ($current[0] == 'bq') {
                    $bq[] = $current[1];
                } else if ($current[0] == 'bf') {
                    // BF parameter may contain multiple space-separated functions
                    // with individual boosts.  We need to parse this into _val_
                    // query components:
                    $bfParts = explode(' ', $current[1]);
                    foreach ($bfParts as $bf) {
                        $bf = trim($bf);
                        if (!empty($bf)) {
                            $bfSubParts = explode('^', $bf, 2);
                            $boost = '"' . addcslashes($bfSubParts[0], '"') . '"';
                            if (isset($bfSubParts[1])) {
                                $boost .= '^' . $bfSubParts[1];
                            }
                            $bq[] = '_val_:' . $boost;
                        }
                    }
                }
            }
        }

        if (!empty($bq)) {
            $query = '(' . $query . ') AND (*:* OR ' . implode(' OR ', $bq) . ')';
        }

        return $query;
    }

    /**
     * Support method for buildAdvancedQuery -- build the inner portion of the
     * query; the calling method may then wrap this with additional settings.
     *
     * @param string $handler The YAML search spec field name to search
     * @param string $query   The string to search for in the field
     *
     * @return  string        The query
     */
    protected function buildAdvancedInnerQuery($handler, $query)
    {
        // Special case -- if the user wants all records but the current handler
        // has a filter query, apply the filter query:
        if (trim($query) == '*:*') {
            $ss = $this->getSearchSpecs($handler);
            if (isset($ss['FilterQuery'])) {
                return $ss['FilterQuery'];
            }
        }

        // Strip out any colons that are NOT part of a field specification:
        $query = preg_replace('/(\:\s+|\s+:)/', ' ', $query);

        // If the query already includes field specifications, we can't easily
        // apply it to other fields through our defined handlers, so we'll leave
        // it as-is:
        if (strstr($query, ':')) {
            return $query;
        }

        // Convert empty queries to return all values in a field:
        if (empty($query)) {
            $query = '[* TO *]';
        }

        // If the query ends in a question mark, the user may not really intend to
        // use the question mark as a wildcard -- let's account for that possibility
        if (substr($query, -1) == '?') {
            $query = "({$query}) OR (" . substr($query, 0, strlen($query) - 1) . ")";
        }

        // We're now ready to use the regular YAML query handler but with the
        // $basic parameter set to false so that we leave the advanced query
        // features unmolested.
        return $this->buildQueryComponent($handler, $query, false);
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
                    // Clean and validate input
                    $lookfor = $this->validateInput($params['lookfor']);

                    // Force boolean operators to uppercase if we are in a
                    // case-insensitive mode:
                    if (!$this->caseSensitiveBooleans) {
                        $lookfor = SolrUtils::capitalizeBooleans($lookfor);
                    }
                    // Adjust range operators if we are in a case-insensitive mode:
                    if (!$this->caseSensitiveRanges) {
                        $lookfor = SolrUtils::capitalizeRanges($lookfor);
                    }

                    if (isset($params['field']) && ($params['field'] != '')) {
                        if ($this->isAdvanced($lookfor)) {
                            $query .= $this->buildAdvancedQuery(
                                $params['field'], $lookfor
                            );
                        } else {
                            $query .= $this->buildQueryComponent(
                                $params['field'], $lookfor
                            );
                        }
                    } else {
                        $query .= $lookfor;
                    }
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
        if (!isset($query) || $query  == '') {
            $query = '*:*';
        }

        return $query;
    }

    /**
     * Normalize a sort option.
     *
     * @param string $sort The sort option.
     *
     * @return string      The normalized sort value.
     */
    protected function normalizeSort($sort)
    {
        // Break apart sort into field name and sort direction (note error
        // suppression to prevent notice when direction is left blank):
        @list($sortField, $sortDirection) = explode(' ', $sort);

        // Default sort order (may be overridden by switch below):
        $defaultSortDirection = 'asc';

        // Translate special sort values into appropriate Solr fields:
        switch ($sortField) {
        case 'year':
        case 'publishDate':
            $sortField = 'publishDateSort';
            $defaultSortDirection = 'desc';
            break;
        case 'author':
            $sortField = 'authorStr';
            break;
        case 'title':
            $sortField = 'title_sort';
            break;
        }

        // Normalize sort direction to either "asc" or "desc":
        $sortDirection = strtolower(trim($sortDirection));
        if ($sortDirection != 'desc' && $sortDirection != 'asc') {
            $sortDirection = $defaultSortDirection;
        }

        return $sortField . ' ' . $sortDirection;
    }

    /**
     * Support method for initSearchParams() -- set up sort preferences.
     *
     * @return void
     */
    protected function initSearchSort()
    {
        // Add Sorting
        $sort = $this->userSearchParams['sort'];
        if (!empty($sort)) {
            // There may be multiple sort options (ranked, with tie-breakers);
            // process each individually, then assemble them back together again:
            $sortParts = explode(',', $sort);
            for ($x = 0; $x < count($sortParts); $x++) {
                $sortParts[$x] = $this->normalizeSort($sortParts[$x]);
            }
            $this->solrSearchParams['sort'] = implode(',', $sortParts);
        }
    }

    /**
     * Support method for initSearchParams() -- set up field collapsing.
     *
     * @return void
     */
    protected function initFieldCollapse()
    {
        // Add Sorting
        $fields = $this->userSearchParams['group'];
        if (!empty($fields)) {
            // There may be multiple sort options (ranked, with tie-breakers);
            // process each individually, then assemble them back together again:
            $this->solrSearchParams['group'] = 'true';
            $this->solrSearchParams['group.field'] = $fields;
        }
    }

    /**
     * Add a value to the set of parameters to be sent to Solr.  This method
     * handles multiple values properly -- if you set the same parameter repeatedly,
     * ALL specified values will be retained.
     *
     * @param string $key   Name of parameter to set
     * @param string $value Value to set
     *
     * @return void
     */
    protected function addSolrSearchParam($key, $value)
    {
        if (isset($this->solrSearchParams[$key])) {
            if (!is_array($this->solrSearchParams[$key])) {
                $this->solrSearchParams[$key]
                    = array($this->solrSearchParams[$key]);
            }
            $this->solrSearchParams[$key][] = $value;
        } else {
            $this->solrSearchParams[$key] = $value;
        }
    }

    /**
     * Support method for initSearchParams() -- set up query, handler and filters.
     *
     * @return void
     */
    protected function initSearchQuery()
    {
        // Grab relevant user parameters:
        $query = $this->userSearchParams['query'];
        $filter = $this->userSearchParams['filter'];
        $handler = $this->userSearchParams['handler'];

        // Determine which handler to use
        if (!$this->isAdvanced($query)) {
            $ss = is_null($handler) ? null : $this->getSearchSpecs($handler);
            // Is this a Dismax search?
            if (isset($ss['DismaxFields'])) {
                // Specify the fields to do a Dismax search on and use the default
                // Dismax search handler so we can use appropriate user-specified
                // solrconfig.xml settings:
                $this->solrSearchParams['qf'] = implode(' ', $ss['DismaxFields']);
                $this->solrSearchParams['qt'] = 'dismax';

                // Load any custom Dismax parameters from the YAML search spec file:
                if (isset($ss['DismaxParams']) && is_array($ss['DismaxParams'])) {
                    foreach ($ss['DismaxParams'] as $current) {
                        $this->addSolrSearchParam($current[0], $current[1]);
                    }
                }

                // Apply search-specific filters if necessary:
                if (isset($ss['FilterQuery'])) {
                    if (is_array($filter)) {
                        $filter[] = $ss['FilterQuery'];
                    } else {
                        $filter = array($ss['FilterQuery']);
                    }
                }
            } else {
                // Not DisMax... but if we have a handler set, we may still need
                // to build a query using a setting in the YAML search specs or a
                // simple field name:
                if (!empty($handler)) {
                    $query = $this->buildQueryComponent($handler, $query);
                }
            }
        } else {
            // Force boolean operators and ranges to uppercase if we are in a
            // case-insensitive mode:
            if (!$this->caseSensitiveBooleans) {
                $query = SolrUtils::capitalizeBooleans($query);
            }
            if (!$this->caseSensitiveRanges) {
                $query = SolrUtils::capitalizeRanges($query);
            }

            // Process advanced search -- if a handler was specified, let's see
            // if we can adapt the search to work with the appropriate fields.
            if (!empty($handler)) {
                // If highlighting is enabled, we only want to use the inner query
                // for highlighting; anything added outside of this is a boost and
                // should be ignored for highlighting purposes!
                if ($this->userSearchParams['highlight']) {
                    $this->solrSearchParams['hl.q']
                        = $this->buildAdvancedInnerQuery($handler, $query);
                }
                $query = $this->buildAdvancedQuery($handler, $query);
            }
        }

        // Now that query and filters are fully processed, add them to the params:
        $this->solrSearchParams['q'] = $query;
        if (is_array($filter) && count($filter)) {
            $this->solrSearchParams['fq'] = $filter;
        }
    }

    /**
     * Support method for initSearchParams() -- set up facets.
     *
     * @return void
     */
    protected function initSearchFacets()
    {
        // Build Facet Options
        $facet = $this->userSearchParams['facet'];
        if (isset($facet['field']) && !empty($facet['field'])) {
            // Always use these values:
            $this->solrSearchParams['facet'] = 'true';
            $this->solrSearchParams['facet.mincount'] = 1;

            // Process convenience parameters (short VuFind-specific names):
            $this->solrSearchParams['facet.limit']
                = (isset($facet['limit'])) ? $facet['limit'] : null;
            unset($facet['limit']);
            $this->solrSearchParams['facet.field']
                = (isset($facet['field'])) ? $facet['field'] : null;
            unset($facet['field']);
            $this->solrSearchParams['facet.prefix']
                = (isset($facet['prefix'])) ? $facet['prefix'] : null;
            unset($facet['prefix']);
            $this->solrSearchParams['facet.sort']
                = (isset($facet['sort'])) ? $facet['sort'] : null;
            unset($facet['sort']);
            if (isset($facet['offset'])) {
                $this->solrSearchParams['facet.offset'] = $facet['offset'];
                unset($facet['offset']);
            }

            // Anything left at this point must be a native Solr parameter;
            // pass it through unmodified:
            foreach ($facet as $param => $value) {
                $this->solrSearchParams[$param] = $value;
            }
        }
    }

    /**
     * Support method for initSearchParams() -- set up spellcheck settings.
     *
     * @return void
     */
    protected function initSearchSpellCheck()
    {
        // Enable Spell Checking
        if (!empty($this->userSearchParams['spell'])) {
            $this->solrSearchParams['spellcheck'] = 'true';
            $this->solrSearchParams['spellcheck.q']
                = $this->userSearchParams['spell'];
            if (!empty($this->userSearchParams['dictionary'])) {
                $this->solrSearchParams['spellcheck.dictionary']
                    = $this->userSearchParams['dictionary'];
            }
        }
    }

    /**
     * Support method for initSearchParams() -- set up highlighting.
     *
     * @return void
     */
    protected function initSearchHighlight()
    {
        // Enable highlighting
        if ($this->userSearchParams['highlight']) {
            $this->solrSearchParams['hl'] = 'true';
            $this->solrSearchParams['hl.fl'] = '*';
            $this->solrSearchParams['hl.simple.pre'] = '{{{{START_HILITE}}}}';
            $this->solrSearchParams['hl.simple.post'] = '{{{{END_HILITE}}}}';
        }
    }

    /**
     * Initialize the _userSearchParams and _solrSearchParams arrays by combining
     * defaults with user-provided values.
     *
     * @param array $options Options from search() method.
     *
     * @return void
     */
    protected function initSearchParams($options)
    {
        // Combine user settings with defaults:
        $this->userSearchParams = array();
        foreach ($this->searchDefaults as $key => $default) {
            $this->userSearchParams[$key] = isset($options[$key])
                ? $options[$key] : $default;
        }

        // Prepare simple Solr parameters:
        $this->solrSearchParams = array(
            'rows' => $this->userSearchParams['limit'],
            'start' => $this->userSearchParams['start'],
            'fl' => $this->userSearchParams['fields']
        );

        // Update _solrSearchParams with various more complex settings:
        $this->initSearchSort();
        $this->initSearchQuery();
        $this->initSearchFacets();
        $this->initFieldCollapse();
        $this->initSearchSpellCheck();
        $this->initSearchHighlight();
    }

    /**
     * Execute a search.
     *
     * @param array $options Array of search options with any number of the following
     * keys:
     *  <ul>
     *    <li>query - The search query</li>
     *    <li>handler - The Query Handler to use (null for default)</li>
     *    <li>filter - The fields and values to filter results on</li>
     *    <li>start - The record to start with</li>
     *    <li>limit - The amount of records to return</li>
     *    <li>facet - An associative array of faceting options with some or all of
     *        these keys:
     *      <ul>
     *        <li>field - array of fields to show facet data for (REQUIRED)</li>
     *        <li>limit - number of values to show for each facet</li>
     *        <li>prefix - filter (only show facet values matching this prefix)</li>
     *        <li>sort - either 'count' or 'lex'</li>
     *        <li>offset - Offset into facet list (used for paging)</li>
     *        <li>facet.* - Native Solr facet parameters may also be used here</li>
     *      </ul>
     *    </li>
     *    <li>spell - Phrase to spell check</li>
     *    <li>dictionary - Spell check dictionary to use</li>
     *    <li>sort - Field name to use for sorting</li>
     *    <li>fields - A list of fields to be returned</li>
     *    <li>method - Method to use for sending request (GET/POST)</li>
     *    <li>highlight - Boolean indicating whether or not to highlight results</li>
     *    <li>group - Array of fields to use for field collapsing (optional)</li>
     *  </ul>
     *
     * @throws SolrException
     * @return array                  An array of query results
     */
    public function search($options = array())
    {
        $this->initSearchParams($options);

        // debug
        if ($this->debugNeeded()) {
            $debugMsg = 'Search options: ' . print_r($this->solrSearchParams, true);
            $this->debug($debugMsg);
        }

        return $this->select(
            $this->userSearchParams['method'], $this->solrSearchParams
        );
    }

    /**
     * Convert an array of fields into XML for saving to Solr.
     *
     * @param array $fields Array of fields to save
     *
     * @return string       XML document ready for posting to Solr.
     */
    public function getSaveXML($fields)
    {
        // Create XML Document
        $doc = new \DOMDocument('1.0', 'UTF-8');

        // Create add node
        $node = $doc->createElement('add');
        $addNode = $doc->appendChild($node);

        // Create doc node
        $node = $doc->createElement('doc');
        $docNode = $addNode->appendChild($node);

        // Add fields to XML docuemnt
        foreach ($fields as $field => $value) {
            // Normalize current value to an array for convenience:
            if (!is_array($value)) {
                $value = array($value);
            }
            // Add all non-empty values of the current field to the XML:
            foreach ($value as $current) {
                if ($current != '') {
                    $node = $doc->createElement(
                        'field', htmlspecialchars($current, ENT_COMPAT, 'UTF-8')
                    );
                    $node->setAttribute('name', $field);
                    $docNode->appendChild($node);
                }
            }
        }

        return $doc->saveXML();
    }

    /**
     * Save Record to Database
     *
     * @param string $xml XML document to post to Solr
     *
     * @throws SolrException
     * @return bool
     */
    public function saveRecord($xml)
    {
        if ($this->debugNeeded()) {
            $this->debug('Add Record');
        }

        return $this->update($xml);
    }

    /**
     * Delete all records in the index.
     *
     * @return boolean
     */
    public function deleteAll()
    {
        if ($this->debugNeeded()) {
            $this->debug('Delete ALL records from index');
        }

        // Build the delete XML
        $body = '<delete><query>*:*</query></delete>';

        // Attempt to post the XML:
        return $this->update($body);
    }

    /**
     * Delete Record from Database
     *
     * @param string $id ID for record to delete
     *
     * @return boolean
     */
    public function deleteRecord($id)
    {
        // Treat single-record deletion as a special case of multi-record deletion:
        return $this->deleteRecords(array($id));
    }

    /**
     * Delete Record from Database
     *
     * @param string $idList Array of IDs for record to delete
     *
     * @throws SolrException
     * @return boolean
     */
    public function deleteRecords($idList)
    {
        if ($this->debugNeeded()) {
            $this->debug('Delete Record List');
        }

        // Build the delete XML
        $body = '<delete>';
        foreach ($idList as $id) {
            $body .= '<id>' . htmlspecialchars($id) . '</id>';
        }
        $body .= '</delete>';

        // Attempt to post the XML:
        $result = $this->update($body);

        // Record the deletions in our change tracker database:
        foreach ($idList as $id) {
            $tracker = $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
                ->get('ChangeTracker');
            $tracker->markDeleted($this->core, $id);
        }

        return $result;
    }

    /**
     * Commit
     *
     * @throws SolrException
     * @return string
     */
    public function commit()
    {
        if ($this->debugNeeded()) {
            $this->debug('Commit');
        }

        return $this->update('<commit/>', array('timeout' => 600000));
    }

    /**
     * Optimize
     *
     * @throws SolrException
     * @return string
     */
    public function optimize()
    {
        if ($this->debugNeeded()) {
            $this->debug('Optimize');
        }

        return $this->update('<optimize/>', array('timeout' => 600000));
    }

    /**
     * Set the shards for distributed search
     *
     * @param array $shards      Array of shards in associative Name => URL format
     * @param array $stripFields Shard name => comma-separated list of fields to
     * strip from that shard (optional)
     *
     * @return void
     */
    public function setShards($shards, $stripFields = array())
    {
        // if only one shard is used, take its URL as SOLR-Host-URL
        if (count($shards) === 1) {
            $shardsKeys = array_keys($shards);
            $this->host = 'http://'.$shards[$shardsKeys[0]];
        }
        // always set the shards -- even if only one is selected, we may
        // need to filter fields and facets:
        $this->solrShards = $shards;
        $this->solrShardsFieldsToStrip = $stripFields;
    }

    /**
     * Strip facet settings that are illegal due to shard settings.
     *
     * @param array $value Current facet.field setting
     *
     * @return array       Filtered facet.field setting
     */
    protected function stripUnwantedFacets($value)
    {
        // Check the list of fields to strip and build an array of values that
        // may apply to facets.
        $badFacets = array();
        if (!empty($this->solrShards) && !empty($this->solrShardsFieldsToStrip)) {
            $shardNames = array_keys($this->solrShards);
            foreach ($this->solrShardsFieldsToStrip as $indexName => $facets) {
                if (in_array($indexName, $shardNames) === true) {
                    $badFacets = array_merge($badFacets, explode(",", $facets));
                }
            }
        }

        // No bad facets means no filtering necessary:
        if (empty($badFacets)) {
            return $value;
        }

        // Ensure that $value is an array:
        if (!is_array($value)) {
            $value = array($value);
        }

        // Rebuild the $value array, excluding all unwanted facets:
        $newValue = array();
        foreach ($value as $current) {
            if (!in_array($current, $badFacets)) {
                $newValue[] = $current;
            }
        }

        return $newValue;
    }

    /**
     * Process the body of a Solr error response.
     *
     * @param string $detail The body of the HTTP error response.
     *
     * @throws SolrException
     * @return void
     */
    protected function throwSolrError($detail)
    {
        // Attempt to extract the most useful error message from the response:
        if (preg_match("/<title>(.*)<\/title>/msi", $detail, $matches)) {
            $errorMsg = $matches[1];
        } else {
            $errorMsg = $detail;
        }
        throw new SolrException("Unexpected response -- " . $errorMsg);
    }

    /**
     * Submit REST Request to read data
     *
     * @param string $method HTTP Method to use: GET, POST,
     * @param array  $params Array of parameters for the request
     *
     * @throws SolrException
     * @return array         The Solr response
     */
    protected function select($method = 'GET', $params = array())
    {
        $this->client->resetParameters();
        $uri = $this->host . "/select/";

        $params['wt'] = 'json';
        $params['json.nl'] = 'arrarr';

        // Build query string for use with GET or POST:
        $query = array();
        if ($params) {
            foreach ($params as $function => $value) {
                if ($function != '') {
                    // Strip custom FacetFields when sharding makes it necessary:
                    if ($function === 'facet.field') {
                        $value = $this->stripUnwantedFacets($value);

                        // If we stripped all values, skip the parameter:
                        if (empty($value)) {
                            continue;
                        }
                    }
                    if (is_array($value)) {
                        foreach ($value as $additional) {
                            $additional = urlencode($additional);
                            $query[] = "$function=$additional";
                        }
                    } else {
                        $value = urlencode($value);
                        $query[] = "$function=$value";
                    }
                }
            }
        }

        // pass the shard parameter along to Solr if necessary:
        if (is_array($this->solrShards) && count($this->solrShards) > 1) {
            $query[] = 'shards=' . urlencode(implode(',', $this->solrShards));
        }
        $queryString = implode('&', $query);

        // debug
        if ($this->debugNeeded()) {
            $this->debug(
                $method . ' '
                . print_r($this->host . "/select/?" . $queryString, true)
            );
        }

        if ($method == 'GET') {
            $uri .= '?' . $queryString;
        } elseif ($method == 'POST') {
            $this->client->setEncType('application/x-www-form-urlencoded');
            $this->client->setRawBody($queryString);
        }

        // Send Request
        $this->client->setUri($uri);
        $result = $this->client->setMethod($method)->send();
        if (!$result->isSuccess()) {
            $this->throwSolrError($result->getBody());
        }
        return $this->process($result->getBody());
    }

    /**
     * Submit REST Request to write data
     *
     * @param string $xml     The command to execute
     * @param array  $options Extra options to pass to the HTTP client
     *
     * @throws SolrException
     * @return bool
     */
    protected function update($xml, $options = array())
    {
        $this->client->resetParameters();
        $this->client->setOptions($options);
        $this->client->setUri($this->host . "/update/");

        // debug
        if ($this->debugNeeded()) {
            $this->debug(
                'POST: ' . print_r($this->host . "/update/", true)
                . 'XML' . print_r($xml, true)
            );
        }

        // Set up XML
        $this->client->setEncType('text/xml; charset=utf-8');
        $this->client->setRawBody($xml);

        // Send Request
        $result = $this->client->setMethod('POST')->send();

        if (!$result->isSuccess()) {
            $this->throwSolrError($result->getBody());
        }

        return true;
    }

    /**
     * Perform normalization and analysis of Solr return value.
     *
     * @param array $result The raw response from Solr
     *
     * @throws SolrException
     * @return array        The processed response from Solr
     */
    protected function process($result)
    {
        // Catch errors from SOLR
        if (substr(trim($result), 0, 2) == '<h') {
            $errorMsg = substr($result, strpos($result, '<pre>'));
            $errorMsg = substr(
                $errorMsg, strlen('<pre>'), strpos($result, "</pre>")
            );
            $msg = 'Unable to process query<br />Solr Returned: ' . $errorMsg;
            throw new SolrException($msg);
        }
        $result = json_decode($result, true);

        // Inject highlighting details into results if necessary:
        if (isset($result['highlighting'])) {
            foreach ($result['response']['docs'] as $key => $current) {
                if (isset($result['highlighting'][$current['id']])) {
                    $result['response']['docs'][$key]['_highlighting']
                        = $result['highlighting'][$current['id']];
                }
            }
            // Remove highlighting section now that we have copied its contents:
            unset($result['highlighting']);
        }

        return $result;
    }

    /**
     * Input Tokenizer
     *
     * Tokenizes the user input based on spaces and quotes.  Then joins phrases
     * together that have an AND, OR, NOT present.
     *
     * @param string $input User's input string
     *
     * @return array        Tokenized array
     */
    public function tokenizeInput($input)
    {
        // Tokenize on spaces and quotes
        //preg_match_all('/"[^"]*"|[^ ]+/', $input, $words);
        preg_match_all('/"[^"]*"[~[0-9]+]*|"[^"]*"|[^ ]+/', $input, $words);
        $words = $words[0];

        // Join words with AND, OR, NOT
        $newWords = array();
        for ($i=0; $i<count($words); $i++) {
            if (($words[$i] == 'OR') || ($words[$i] == 'AND')
                || ($words[$i] == 'NOT')
            ) {
                if (count($newWords)) {
                    $newWords[count($newWords)-1] .= ' ' . $words[$i] . ' ' .
                        $words[$i+1];
                    $i = $i+1;
                }
            } else {
                $newWords[] = $words[$i];
            }
        }

        return $newWords;
    }

    /**
     * Input Validater
     *
     * Cleans the input based on the Lucene Syntax rules.
     *
     * @param string $input User's input string
     *
     * @return bool         Fixed input
     */
    public function validateInput($input)
    {
        // Normalize fancy quotes:
        $quotes = array(
            "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
            "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
            "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B" => "'", // ? (U+201B) in UTF-8
            "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
            "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
            "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
            "\xE2\x80\x9F" => '"', // ? (U+201F) in UTF-8
            "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
        );
        $input = strtr($input, $quotes);

        // If the user has entered a lone BOOLEAN operator, convert it to lowercase
        // so it is treated as a word (otherwise it will trigger a fatal error):
        switch(trim($input)) {
        case 'OR':
            return 'or';
        case 'AND':
            return 'and';
        case 'NOT':
            return 'not';
        }

        // If the string consists only of control characters and/or BOOLEANs with no
        // other input, wipe it out entirely to prevent weird errors:
        $operators = array('AND', 'OR', 'NOT', '+', '-', '"', '&', '|');
        if (trim(str_replace($operators, '', $input)) == '') {
            return '';
        }

        // Translate "all records" search into a blank string
        if (trim($input) == '*:*') {
            return '';
        }

        // Ensure wildcards are not at beginning of input
        if ((substr($input, 0, 1) == '*') || (substr($input, 0, 1) == '?')) {
            $input = substr($input, 1);
        }

        // Ensure all parens match
        $start = preg_match_all('/\(/', $input, $tmp);
        $end = preg_match_all('/\)/', $input, $tmp);
        if ($start != $end) {
            $input = str_replace(array('(', ')'), '', $input);
        }

        // Ensure ^ is used properly
        $cnt = preg_match_all('/\^/', $input, $tmp);
        $matches = preg_match_all('/.+\^[0-9]/', $input, $tmp);
        if (($cnt) && ($cnt !== $matches)) {
            $input = str_replace('^', '', $input);
        }

        // Remove unwanted brackets/braces that are not part of range queries.
        // This is a bit of a shell game -- first we replace valid brackets and
        // braces with tokens that cannot possibly already be in the query (due
        // to ^ normalization in the step above).  Next, we remove all remaining
        // invalid brackets/braces, and transform our tokens back into valid ones.
        // Obviously, the order of the patterns/merges array is critically
        // important to get this right!!
        $patterns = array(
            // STEP 1 -- escape valid brackets/braces
            '/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            '/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/' .
            ($this->caseSensitiveRanges ? '' : 'i'),
            // STEP 2 -- destroy remaining brackets/braces
            '/[\[\]\{\}]/',
            // STEP 3 -- unescape valid brackets/braces
            '/\^\^lbrack\^\^/', '/\^\^rbrack\^\^/',
            '/\^\^lbrace\^\^/', '/\^\^rbrace\^\^/');
        $matches = array(
            // STEP 1 -- escape valid brackets/braces
            '^^lbrack^^$1^^rbrack^^', '^^lbrace^^$1^^rbrace^^',
            // STEP 2 -- destroy remaining brackets/braces
            '',
            // STEP 3 -- unescape valid brackets/braces
            '[', ']', '{', '}');
        $input = preg_replace($patterns, $matches, $input);
        return $input;
    }

    /**
     * Does the provided query use advanced Lucene syntax features?
     *
     * @param string $query Query to test.
     *
     * @return bool
     */
    public function isAdvanced($query)
    {
        // Check for various conditions that flag an advanced Lucene query:
        if ($query == '*:*') {
            return true;
        }

        // The following conditions do not apply to text inside quoted strings,
        // so let's just strip all quoted strings out of the query to simplify
        // detection.  We'll replace quoted phrases with a dummy keyword so quote
        // removal doesn't interfere with the field specifier check below.
        $query = preg_replace('/"[^"]*"/', 'quoted', $query);

        // Check for field specifiers:
        if (preg_match("/[^\s]\:[^\s]/", $query)) {
            return true;
        }

        // Check for parentheses and range operators:
        if (strstr($query, '(') && strstr($query, ')')) {
            return true;
        }
        $rangeReg = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';
        if (!$this->caseSensitiveRanges) {
            $rangeReg .= "i";
        }
        if (preg_match($rangeReg, $query)) {
            return true;
        }

        // Build a regular expression to detect booleans -- AND/OR/NOT surrounded
        // by whitespace, or NOT leading the query and followed by whitespace.
        $boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)/';
        if (!$this->caseSensitiveBooleans) {
            $boolReg .= "i";
        }
        if (preg_match($boolReg, $query)) {
            return true;
        }

        // Check for wildcards and fuzzy matches:
        if (strstr($query, '*') || strstr($query, '?') || strstr($query, '~')) {
            return true;
        }

        // Check for boosts:
        if (preg_match('/[\^][0-9]+/', $query)) {
            return true;
        }

        return false;
    }

    /**
     * Remove illegal characters from the provided query.
     *
     * @param string $query Query to clean.
     *
     * @return string       Clean query.
     */
    public function cleanInput($query)
    {
        $query = trim(str_replace($this->illegal, '', $query));
        $query = strtolower($query);

        return $query;
    }

    /**
     * Obtain information from an alphabetic browse index.
     *
     * @param string $source    Name of index to search
     * @param string $from      Starting point for browse results
     * @param int    $page      Result page to return (starts at 0)
     * @param int    $page_size Number of results to return on each page
     * @param string $method    Method to use for connecting to Solr (GET or
     * POST)
     *
     * @return array
     */
    public function alphabeticBrowse($source, $from, $page, $page_size = 20,
        $method = 'GET'
    ) {
        $this->client->resetParameters();
        $uri = $this->host . "/browse";

        $query = array(
          'from='.urlencode($from),
          'json.nl=arrarr' ,
          'offset='.urlencode($page*$page_size),
          'rows='.urlencode($page_size),
          'source='.urlencode($source),
          'wt=json'
        );
        
        $queryString = implode('&', $query);

        if ($method == 'GET') {
            $uri .= '?' . $queryString;
        } elseif ($method == 'POST') {
            $this->client->setEncType('application/x-www-form-urlencoded');
            $this->client->setRawBody($queryString);
        }

        // Send Request
        $this->client->setUri($uri);
        $result = $this->client->setMethod($method)->send();
        if (!$result->isSuccess()) {
            $this->throwSolrError($result->getBody());
        }
        return $this->process($result->getBody());
    }



    /**
     * Extract terms from the Solr index.
     *
     * @param string $field Field to extract terms from
     * @param string $start Starting term to extract (blank for beginning of list)
     * @param int    $limit Maximum number of terms to return (-1 for no limit)
     *
     * @return array Associative array parsed from Solr JSON
     * response; meat of the response is in the ['terms'] element, which contains
     * an index named for the requested term, which in turn contains an associative
     * array of term => count in index.
     */
    public function getTerms($field, $start, $limit)
    {
        $this->client->setUri($this->host . '/term');

        $query = $this->client->getRequest()->getQuery();
        $query->set('terms', 'true');
        $query->set('terms.fl', $field);
        $query->set('terms.lower.incl', 'false');
        $query->set('terms.lower', $start);
        $query->set('terms.limit', $limit);
        $query->set('terms.sort', 'index');
        $query->set('wt', 'json');

        $result = $this->client->setMethod('GET')->send();
        $result = substr($result, strpos($result, '{'));
        try {
            // Process the JSON response:
            $data = $this->process($result);

            // Tidy the data into a more usable format:
            $info = array();
            for ($i=0;$i<count($data['terms']['id']);$i+=2) {
                $info[$data['terms']['id'][$i]] = $data['terms']['id'][$i+1];
            }
            return $info;
        } catch(\Exception $e) {
            return $result;
        }
    }

    /**
     * Get the HTTP timeout for communicating with the Solr server.
     *
     * @return int
     */
    public function getHttpTimeout()
    {
        $config = ConfigReader::getConfig();
        return isset($config->Index->timeout) ? $config->Index->timeout : 30;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Solr
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}