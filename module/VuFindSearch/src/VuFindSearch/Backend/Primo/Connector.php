<?php

/**
 * Primo Central connector.
 *
 * PHP version 8
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
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Primo;

use Laminas\Http\Client as HttpClient;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function strlen;

/**
 * Primo Central connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 *
 * @deprecated Use RestConnector instead
 */
class Connector implements ConnectorInterface, \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindSearch\Backend\Feature\ConnectorCacheTrait;

    /**
     * HTTP client used for API transactions
     *
     * @var HttpClient
     */
    public $client;

    /**
     * Institution code
     *
     * @var string
     */
    protected $inst;

    /**
     * Base URL for API
     *
     * @var string
     */
    protected $host;

    /**
     * Response for an empty search
     *
     * @var array
     */
    protected static $emptyQueryResponse = [
        'recordCount' => 0,
        'documents' => [],
        'facets' => [],
        'error' => 'empty_search_disallowed',
    ];

    /**
     * Regular expression to match highlighted terms
     *
     * @var string
     */
    protected $highlightRegEx = '{<span[^>]*>([^<]*?)</span>}si';

    /**
     * Constructor
     *
     * Sets up the Primo API Client
     *
     * @param string     $url    Primo API URL (either a host name and port or a full
     * path to the brief search including a trailing question mark)
     * @param string     $inst   Institution code
     * @param HttpClient $client HTTP client
     */
    public function __construct($url, $inst, $client)
    {
        $parts = parse_url($url);
        if (empty($parts['path']) || $parts['path'] == '/') {
            $parts['path'] = '/PrimoWebServices/xservice/search/brief';
        }
        $this->host = $parts['scheme'] . '://' . $parts['host']
            . (!empty($parts['port']) ? ':' . $parts['port'] : '')
            . $parts['path'] . '?';
        if (!empty($parts['query'])) {
            $this->host .= $parts['query'] . '&';
        }

        $this->inst = $inst;
        $this->client = $client;
    }

    /**
     * Execute a search. Adds all the querystring parameters into
     * $this->client and returns the parsed response
     *
     * @param string $institution Institution
     * @param array  $terms       Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array  $params      Associative array of optional arguments:
     *     phrase      bool:   true if it's a quoted phrase (default false)
     *     onCampus    bool:   (default true)
     *     didyoumean  bool:   (default false)
     *     filterList  array:  (field, value) pairs to filter results (def null)
     *     pageNumber  string: index of first record (default 1)
     *     limit       string: number of records to return (default 20)
     *     sort        string: value to be used by for sorting (default null)
     *     highlight   bool:   whether to highlight search term matches in records
     *     highlightStart string: Prefix for a highlighted term
     *     highlightEnd   string: Suffix for a Highlighted term
     *     Anything in $params not listed here will be ignored.
     *
     * Note: some input parameters accepted by Primo are not implemented here:
     *  - dym (did you mean)
     *  - more (get more)
     *  - lang (specify input language so engine can do lang. recognition)
     *  - displayField (has to do with highlighting somehow)
     *
     * @throws \Exception
     * @return array             An array of query results
     *
     * @link http://www.exlibrisgroup.org/display/PrimoOI/Brief+Search
     */
    public function query($institution, $terms, $params = null)
    {
        // defaults for params
        $args = [
            'phrase' => false,
            'onCampus' => true,
            'didYouMean' => false,
            'filterList' => null,
            'pcAvailability' => false,
            'pageNumber' => 1,
            'limit' => 20,
            'sort' => null,
            'highlight' => false,
            'highlightStart' => '',
            'highlightEnd' => '',
        ];
        if (isset($params)) {
            $args = array_merge($args, $params);
        }

        $result = $this->performSearch($institution, $terms, $args);
        return $result;
    }

    /**
     * Support method for query() -- perform inner search logic
     *
     * @param string $institution Institution
     * @param array  $terms       Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array  $args        Associative array of optional arguments (see query
     * method for more information)
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    protected function performSearch($institution, $terms, $args)
    {
        // we have to build a querystring because I think adding them
        //   incrementally is implemented as a dictionary, but we are allowed
        //   multiple querystring parameters with the same key.
        $qs = [];

        // QUERYSTRING: query (search terms)
        // re: phrase searches, turns out we can just pass whatever we got
        //   to primo and they will interpret it correctly.
        //   leaving this flag in b/c it's not hurting anything, but we
        //   don't currently have a situation where we need to use "exact"
        $precision = 'contains';
        if ($args['phrase']) {
            $precision = 'exact';
        }
        // determine which primo index to search

        //default index is any and initialize lookfor to an empty string
        $lookin  = 'any';
        $lookfor = '';

        if (is_array($terms)) {
            foreach ($terms as $thisTerm) {
                //set the index to search
                switch ($thisTerm['index']) {
                    case 'AllFields':
                        $lookin = 'any';
                        break;
                    case 'Title':
                        $lookin = 'title';
                        break;
                    case 'Author':
                        $lookin = 'creator';
                        break;
                    case 'Subject':
                        $lookin = 'sub';
                        break;
                    case 'Abstract':
                        $lookin = 'desc';
                        break;
                    case 'ISSN':
                        $lookin = 'issn';
                        break;
                }

                //set the lookfor terms to search
                $lookfor = str_replace(',', ' ', $thisTerm['lookfor']);

                //set precision
                if (array_key_exists('op', $thisTerm) && !empty($thisTerm['op'])) {
                    $precision = $thisTerm['op'];
                }

                $qs[] = "query=$lookin,$precision," . urlencode($lookfor);
            }
        }

        // continue only if lookfor is not an empty string
        if (strlen($lookfor) > 0) {
            // It's a giant nested thing!  This is because we really have to
            // have a query to send to primo or it hates us

            // QUERYSTRING: institution
            $qs[] = "institution=$institution";

            // QUERYSTRING: onCampus
            if ($args['onCampus']) {
                $qs[] = 'onCampus=true';
            } else {
                $qs[] = 'onCampus=false';
            }

            // QUERYSTRING: didYouMean
            if ($args['didYouMean']) {
                $qs[] = 'dym=true';
            } else {
                $qs[] = 'dym=false';
            }

            // QUERYSTRING: query (filter list)
            // Date-related TODO:
            //   - provide additional support / processing for [x to y] limits?
            //   - sys/Summon.php messes with publication date to enable date
            //     range facet control in the interface. look for injectPubDate
            if (!empty($args['filterList'])) {
                foreach ($args['filterList'] as $current) {
                    $facet = $current['field'];
                    $facetOp = $current['facetOp'];
                    $values = $current['values'];
                    $values = array_map(
                        function ($value) {
                            return urlencode(str_replace(',', ' ', $value));
                        },
                        $values
                    );
                    if ('OR' === $facetOp) {
                        $qs[] = "query_inc=facet_$facet,exact," .
                            implode(',', $values);
                    } elseif ('NOT' === $facetOp) {
                        $qs[] = "query_exc=facet_$facet,exact," .
                            implode(',', $values);
                    } else {
                        foreach ($values as $value) {
                            $qs[] = "query_inc=facet_$facet,exact,$value";
                        }
                    }
                }
            }

            // QUERYSTRING: pcAvailability
            // by default, PrimoCentral only returns matches,
            // which are available via Holdingsfile
            // pcAvailability = false
            // By setting this value to true, also matches, which
            // are NOT available via Holdingsfile are returned
            // (yes, right, set this to true - that's ExLibris Logic)
            if ($args['pcAvailability']) {
                $qs[] = 'pcAvailability=true';
            }

            // QUERYSTRING: indx (start record)
            $recordStart = ($args['pageNumber'] - 1) * $args['limit'] + 1;
            $qs[] = "indx=$recordStart";

            // TODO: put bulksize in conf file?  set a reasonable cap...
            //   or is it better to grab each set of 20 through this api module?
            //   Look at how vufind/Summon does this...
            // QUERYSTRING: bulkSize (limit, # of records to return)
            $qs[] = 'bulkSize=' . $args['limit'];

            // QUERYSTRING: sort
            // Looks like the possible values are "popularity" or "scdate"
            // omit the field for default sorting
            if (isset($args['sort']) && ($args['sort'] != 'relevance')) {
                $qs[] = 'sortField=' . $args['sort'];
            }

            // Highlighting
            $qs[] = 'highlight=' . (empty($args['highlight']) ? 'false' : 'true');

            // QUERYSTRING: loc
            // all primocentral queries need this
            $qs[] = 'loc=adaptor,primo_central_multiple_fe';

            // Send Request
            $result = $this->call(implode('&', $qs), $args);
        } else {
            return self::$emptyQueryResponse;
        }

        return $result;
    }

    /**
     * Small wrapper for sendRequest, process to simplify error handling.
     *
     * @param string $qs        Query string
     * @param array  $params    Request parameters
     * @param string $method    HTTP method
     * @param bool   $cacheable Whether the request is cacheable
     *
     * @return object    The parsed primo data
     * @throws \Exception
     */
    protected function call($qs, $params = [], $method = 'GET', $cacheable = true)
    {
        $this->debug("{$method}: {$this->host}{$qs}");
        $this->client->resetParameters();
        $baseUrl = null;
        if ($method == 'GET') {
            $baseUrl = $this->host . $qs;
        } elseif ($method == 'POST') {
            throw new \Exception('POST not supported');
        }

        $this->client->setUri($baseUrl);
        $this->client->setMethod($method);
        // Check cache:
        $resultBody = null;
        $cacheKey = null;
        if ($cacheable && $this->cache) {
            $cacheKey = $this->getCacheKey($this->client);
            $resultBody = $this->getCachedData($cacheKey);
        }
        if (null === $resultBody) {
            // Send request:
            $result = $this->client->send();
            $resultBody = $result->getBody();
            if (!$result->isSuccess()) {
                throw new \Exception($resultBody);
            }
            if ($cacheKey) {
                $this->putCachedData($cacheKey, $resultBody);
            }
        }
        return $this->process($resultBody, $params);
    }

    /**
     * Translate Primo's XML into array of arrays.
     *
     * @param string $data   The raw xml from Primo
     * @param array  $params Request parameters
     *
     * @return array The processed response from Primo
     */
    protected function process($data, $params = [])
    {
        // make sure data exists
        if (strlen($data) == 0) {
            throw new \Exception('Primo did not return any data');
        }

        // Load API content as XML objects
        $sxe = new \SimpleXmlElement($data);

        if ($sxe === false) {
            throw new \Exception('Error while parsing the document');
        }

        // some useful data about these results
        $totalhitsarray = $sxe->xpath('//@TOTALHITS');

        // if totalhits is missing but we have a message, this is an error
        // situation.
        if (!isset($totalhitsarray[0])) {
            $messages = $sxe->xpath('//@MESSAGE');
            $message = isset($messages[0])
                ? (string)$messages[0] : 'TOTALHITS attribute missing.';
            throw new \Exception($message);
        } else {
            $totalhits = (int)$totalhitsarray[0];
        }
        // TODO: would these be useful?
        //$firsthit = $sxe->xpath('//@FIRSTHIT');
        //$lasthit = $sxe->xpath('//@LASTHIT');

        // Register the 'sear' namespace at the top level to avoid problems:
        $sxe->registerXPathNamespace(
            'sear',
            'http://www.exlibrisgroup.com/xsd/jaguar/search'
        );

        // Get the available namespaces. The Primo API uses multiple namespaces.
        // Will be used to navigate the DOM for elements that have namespaces
        $namespaces = $sxe->getNameSpaces(true);

        // Get results set data and add to $items array
        // This foreach grabs all the child elements of sear:DOC,
        //   except those with namespaces
        $items = [];

        $docset = $sxe->xpath('//sear:DOC');
        if (empty($docset) && isset($sxe->JAGROOT->RESULT->DOCSET->DOC)) {
            $docset = $sxe->JAGROOT->RESULT->DOCSET->DOC;
        }

        foreach ($docset as $doc) {
            $item = [];
            // Due to a bug in the primo API, the first result has
            //   a namespace (prim:) while the rest of the results do not.
            //   Those child elements do not get added to $doc.
            //   If the bib parent element (PrimoNMBib) is missing for a $doc,
            //   that means it has the prim namespace prefix.
            // So first set the right prefix
            $prefix = $doc;
            if ($doc->PrimoNMBib != 'true' && isset($namespaces['prim'])) {
                // Use the namespace prefix to get those missing child
                //   elements out of $doc.
                $prefix = $doc->children($namespaces['prim']);
            }
            // Now, navigate the DOM and set values to the array
            // cast to (string) to get the element's value not an XML object
            $item['recordid']
                = substr((string)$prefix->PrimoNMBib->record->control->recordid, 3);
            $item['title']
                = (string)$prefix->PrimoNMBib->record->display->title;
            $item['format'] = [(string)$prefix->PrimoNMBib->record->display->type];
            // creators
            $creator
                = trim((string)$prefix->PrimoNMBib->record->display->creator);
            if (strlen($creator) > 0) {
                $item['creator'] = array_map('trim', explode(';', $creator));
            }
            // subjects
            $subject
                = trim((string)$prefix->PrimoNMBib->record->display->subject);
            if (strlen($subject) > 0) {
                $item['subjects'] = explode(';', $subject);
            }
            $item['ispartof']
                = (string)$prefix->PrimoNMBib->record->display->ispartof;
            // description is sort of complicated and will be processed after
            // highlighting tags are handled.
            $description = isset($prefix->PrimoNMBib->record->display->description)
                ? (string)$prefix->PrimoNMBib->record->display->description
                : (string)$prefix->PrimoNMBib->record->search->description;
            $item['description'] = $description;
            // and the rest!
            $item['language']
                = (string)$prefix->PrimoNMBib->record->display->language;
            $item['source']
                = implode('; ', (array)$prefix->PrimoNMBib->record->display->source);
            $item['identifier']
                = (string)$prefix->PrimoNMBib->record->display->identifier;
            $item['fulltext']
                = (string)$prefix->PrimoNMBib->record->delivery->fulltext;

            $item['issn'] = [];
            foreach ($prefix->PrimoNMBib->record->search->issn as $issn) {
                $item['issn'][] = (string)$issn;
            }

            //Are these two needed?
            //$item['publisher'] =
            //    (string)$prefix->PrimoNMBib->record->display->publisher;
            //$item['peerreviewed'] =
            //    (string)$prefix->PrimoNMBib->record->display->lds50;

            // Get the URL, which has a separate namespace
            $sear = $doc->children($namespaces['sear']);
            $item['url'] = !empty($sear->LINKS->openurl)
                ? (string)$sear->LINKS->openurl
                : (string)$sear->GETIT->attributes()->GetIt2;

            // Container data
            $addata = $prefix->PrimoNMBib->record->addata;
            $item['container_title'] = (string)$addata->jtitle;
            $item['container_volume'] = (string)$addata->volume;
            $item['container_issue'] = (string)$addata->issue;
            $item['container_start_page'] = (string)$addata->spage;
            $item['container_end_page'] = (string)$addata->epage;
            foreach ($addata->eissn as $eissn) {
                if (!in_array((string)$eissn, $item['issn'])) {
                    $item['issn'][] = (string)$eissn;
                }
            }
            foreach ($addata->issn as $issn) {
                if (!in_array((string)$issn, $item['issn'])) {
                    $item['issn'][] = (string)$issn;
                }
            }
            foreach ($addata->doi as $doi) {
                $item['doi_str_mv'][] = (string)$doi;
            }

            $processCitations = function ($data): array {
                $result = [];
                foreach ($data as $item) {
                    $result[] = 'cdi_' . (string)$item;
                }
                return $result;
            };

            // These require the cdi_ prefix in search, so add it right away:
            $item['cites'] = $processCitations($prefix->PrimoNMBib->record->display->cites ?? []);
            $item['cited_by'] = $processCitations($prefix->PrimoNMBib->record->display->citedby ?? []);

            // Remove dash-less ISSNs if there are corresponding dashed ones
            // (We could convert dash-less ISSNs to dashed ones, but try to stay
            // true to the metadata)
            $callback = function ($issn) use ($item) {
                return strlen($issn) != 8
                    || !in_array(
                        substr($issn, 0, 4) . '-' . substr($issn, 4),
                        $item['issn']
                    );
            };
            $item['issn'] = array_values(array_filter($item['issn'], $callback));

            // Always process highlighting data as it seems Primo sometimes returns
            // it (e.g. for CDI search) even if highlight parameter is set to false.
            $this->processHighlighting($item, $params);

            // Fix description now that highlighting is done:
            $item['description'] = $this->processDescription($item['description']);

            $item['fullrecord'] = $prefix->PrimoNMBib->record->asXml();
            $items[] = $item;
        }

        // Set up variables with needed attribute names
        // Makes matching attributes and getting their values easier
        $att = 'NAME';
        $key = 'KEY';
        $value = 'VALUE';

        // Get facet data and add to multidimensional $facets array
        // Start by getting XML for each FACET element,
        //  which has the name of the facet as an attribute.
        // We only get the first level of elements
        //   because child elements have a namespace prefix
        $facets = [];

        $facetSet = $sxe->xpath('//sear:FACET');
        if (empty($facetSet)) {
            if (!empty($sxe->JAGROOT->RESULT->FACETLIST)) {
                $facetSet = $sxe->JAGROOT->RESULT->FACETLIST
                    ->children($namespaces['sear']);
            }
        }

        foreach ($facetSet as $facetlist) {
            // Set first level of array with the facet name
            $facet_name = (string)$facetlist->attributes()->$att;

            // Use the namespace prefix to get second level child elements
            //   (the facet values) out of $facetlist.
            $sear_facets = $facetlist->children($namespaces['sear']);
            foreach ($sear_facets as $facetvalues) {
                // Second level of the array is facet values and their counts
                $facet_key = (string)$facetvalues->attributes()->$key;
                $facets[$facet_name][$facet_key]
                    = (string)$facetvalues->attributes()->$value;
            }
        }

        $didYouMean = [];
        $suggestions = $sxe->xpath('//sear:QUERYTRANSFORMS');
        foreach ($suggestions as $suggestion) {
            $didYouMean[] = (string)$suggestion->attributes()->QUERY;
        }

        return [
            'recordCount' => $totalhits,
            'documents' => $items,
            'facets' => $facets,
            'didYouMean' => $didYouMean,
        ];
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string  $recordId  The document to retrieve from the Primo API
     * @param ?string $inst_code Institution code (optional)
     * @param bool    $onCampus  Whether the user is on campus
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    public function getRecord(string $recordId, $inst_code = null, $onCampus = false)
    {
        if ('' === $recordId) {
            return self::$emptyQueryResponse;
        }
        // Query String Parameters
        $qs   = [];
        // There is currently (at 2015-12-17) a problem with Primo fetching
        // records that have colons in the id (e.g.
        // doaj_xmloai:doaj.org/article:94935655971c4917aab4fcaeafeb67b9).
        // According to Ex Libris support we must use contains search without
        // quotes for the time being.
        // Escaping the - character causes problems getting records like
        // wj10.1111/j.1475-679X.2011.00421.x
        $qs[] = 'query=rid,contains,'
            . urlencode(addcslashes($recordId, '":()'));
        $qs[] = "institution=$inst_code";
        $qs[] = 'onCampus=' . ($onCampus ? 'true' : 'false');
        $qs[] = 'indx=1';
        $qs[] = 'bulkSize=1';
        $qs[] = 'loc=adaptor,primo_central_multiple_fe';
        // pcAvailability=true is needed for records, which
        // are NOT in the PrimoCentral Holdingsfile.
        // It won't hurt to have this parameter always set to true.
        // But it'd hurt to have it not set in case you want to get
        // a record, which is not in the Holdingsfile.
        $qs[] = 'pcAvailability=true';

        // Send Request
        $result = $this->call(implode('&', $qs));

        return $result;
    }

    /**
     * Retrieves multiple documents specified by the ID.
     *
     * @param array   $recordIds The documents to retrieve from the Primo API
     * @param ?string $inst_code Institution code (optional)
     * @param bool    $onCampus  Whether the user is on campus
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    public function getRecords($recordIds, $inst_code = null, $onCampus = false)
    {
        // Callback function for formatting IDs:
        $formatIds = function ($id) {
            return addcslashes($id, '":()');
        };

        // Query String Parameters
        if ($recordIds) {
            $qs   = [];
            $recordIds = array_map($formatIds, $recordIds);
            $qs[] = 'query=rid,contains,' . urlencode(implode(' OR ', $recordIds));
            $qs[] = "institution=$inst_code";
            $qs[] = 'onCampus=' . ($onCampus ? 'true' : 'false');
            $qs[] = 'indx=1';
            $qs[] = 'bulkSize=' . count($recordIds);
            $qs[] = 'loc=adaptor,primo_central_multiple_fe';
            // pcAvailability=true is needed for records, which
            // are NOT in the PrimoCentral Holdingsfile.
            // It won't hurt to have this parameter always set to true.
            // But it'd hurt to have it not set in case you want to get
            // a record, which is not in the Holdingsfile.
            $qs[] = 'pcAvailability=true';

            // Send Request
            $result = $this->call(implode('&', $qs));
        } else {
            return self::$emptyQueryResponse;
        }

        return $result;
    }

    /**
     * Get the institution code based on user IP. If user is coming from
     * off campus return
     *
     * @return string
     */
    public function getInstitutionCode()
    {
        return $this->inst;
    }

    /**
     * Process highlighting tags of the record fields
     *
     * @param array $record Record data
     * @param array $params Request params
     *
     * @return void
     */
    protected function processHighlighting(&$record, $params)
    {
        $highlight = !empty($params['highlight']);
        $startTag = $params['highlightStart'] ?? '';
        $endTag = $params['highlightEnd'] ?? '';

        $highlightFields = [
            'title' => 'title',
            'creator' => 'author',
            'description' => 'description',
        ];

        $hilightDetails = [];
        foreach ($record as $field => $fieldData) {
            $values = (array)$fieldData;

            // Collect highlighting details:
            if (isset($highlightFields[$field])) {
                $highlightedValues = [];
                foreach ($values as $value) {
                    $count = 0;
                    $value = preg_replace(
                        $this->highlightRegEx,
                        "$startTag$1$endTag",
                        $value,
                        -1,
                        $count
                    );
                    if ($count) {
                        // Account for double tags. Yes, it's possible.
                        $value = preg_replace(
                            $this->highlightRegEx,
                            '$1',
                            $value
                        );
                        $highlightedValues[] = $value;
                    }
                }
                if ($highlightedValues) {
                    $hilightDetails[$highlightFields[$field]] = $highlightedValues;
                }
            }

            // Strip highlighting tags from all fields:
            foreach ($values as &$value) {
                $value = preg_replace(
                    $this->highlightRegEx,
                    '$1',
                    $value
                );
                // Account for double tags. Yes, it's possible.
                $value = preg_replace(
                    $this->highlightRegEx,
                    '$1',
                    $value
                );
            }
            // Unset reference:
            unset($value);
            $record[$field] = is_array($fieldData) ? $values : $values[0];

            if ($highlight) {
                $record['highlightDetails'] = $hilightDetails;
            }
        }
    }

    /**
     * Fix the description field by removing tags etc.
     *
     * @param string $description Description
     *
     * @return string
     */
    protected function processDescription($description)
    {
        // Sometimes the entire article is in the description, so just take a chunk
        // from the beginning.
        $description = trim(mb_substr($description, 0, 2500, 'UTF-8'));
        // These may contain all kinds of metadata, and just stripping
        // tags mushes it all together confusingly.
        $description = str_replace('<P>', '<p>', $description);
        $paragraphs = explode('<p>', $description);
        foreach ($paragraphs as &$value) {
            // Strip tags, trim so array_filter can get rid of
            // entries that would just have spaces
            $value = trim(strip_tags($value));
        }
        $paragraphs = array_filter($paragraphs);
        // Now join paragraphs using line breaks
        return implode('<br>', $paragraphs);
    }
}
