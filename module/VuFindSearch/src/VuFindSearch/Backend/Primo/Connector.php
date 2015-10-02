<?php

/**
 * Primo Central connector.
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
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Primo;
use Zend\Http\Client as HttpClient;

/**
 * Primo Central connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Spencer Lamm <slamm1@swarthmore.edu>
 * @author   Anna Headley <aheadle1@swarthmore.edu>
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Connector implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * The HTTP_Request object used for API transactions
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
     * Debug status
     *
     * @var bool
     */
    public $debug = false;

    /**
     * Constructor
     *
     * Sets up the Primo API Client
     *
     * @param string     $apiId  Primo API ID
     * @param string     $inst   Institution code
     * @param HttpClient $client HTTP client
     * @param int        $port   API connection port
     */
    public function __construct($apiId, $inst, $client, $port = 1701)
    {
        $this->host = "http://$apiId.hosted.exlibrisgroup.com:{$port}/"
            . "PrimoWebServices/xservice/search/brief?";
        $this->inst = $inst;
        $this->client = $client;
    }

    /**
     * Execute a search.  adds all the querystring parameters into
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
     *     returnErr   bool:   false to fail on error; true to return empty
     *                         empty result set with an error field (def true)
     *     Anything in $params not listed here will be ignored.
     *
     * Note: some input parameters accepted by Primo are not implemented here:
     *  - dym (did you mean)
     *  - highlight
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
            "phrase" => false,
            "onCampus" => true,
            "didYouMean" => false,
            "filterList" => null,
            "pcAvailability" => false,
            "pageNumber" => 1,
            "limit" => 20,
            "sort" => null,
            "returnErr" => true,
        ];
        if (isset($params)) {
            $args = array_merge($args, $params);
        }

        // run search, deal with exceptions
        try {
            $result = $this->performSearch($institution, $terms, $args);
        } catch (\Exception $e) {
            if ($args["returnErr"]) {
                $this->debug($e->getMessage());
                return [
                    'recordCount' => 0,
                    'documents' => [],
                    'facets' => [],
                    'error' => $e->getMessage()
                ];
            } else {
                throw $e;
            }
        }
        return $result;
    }

    /**
     * Support method for query() -- perform inner search logic
     *
     * @param string $institution Institution
     * @param array  $terms       Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array  $args        Associative array of optional arguments:
     *     phrase      bool:   true if it's a quoted phrase (default false)
     *     onCampus    bool:   (default true)
     *     didyoumean  bool:   (default false)
     *     filterList  array:  (field, value) pairs to filter results (def null)
     *     pageNumber  string: index of first record (default 1)
     *     limit       string: number of records to return (default 20)
     *     sort        string: value to be used by for sorting (default null)
     *     returnErr   bool:   false to fail on error; true to return empty
     *                         empty result set with an error field (def true)
     *     Anything in $args   not listed here will be ignored.
     *
     * Note: some input parameters accepted by Primo are not implemented here:
     *  - dym (did you mean)
     *  - highlight
     *  - more (get more)
     *  - lang (specify input language so engine can do lang. recognition)
     *  - displayField (has to do with highlighting somehow)
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
        $precision = "contains";
        if ($args["phrase"]) {
            $precision = "exact";
        }
        // determine which primo index to search

        //default index is any and initialize lookfor to an empty string
        $lookin  = "any";
        $lookfor = "";

        if (is_array($terms)) {
            foreach ($terms as $thisTerm) {
                //set the index to search
                switch ($thisTerm['index']) {
                case "AllFields":
                    $lookin = "any";
                    break;
                case "Title":
                    $lookin = "title";
                    break;
                case "Author":
                    $lookin = "creator";
                    break;
                case "Subject":
                    $lookin = "sub";
                    break;
                case "Abstract":
                    $lookin = "desc";
                    break;
                case "ISSN":
                    $lookin = "issn";
                    break;
                }

                //set the lookfor terms to search
                $lookfor = preg_replace('/,/', '+', $thisTerm['lookfor']);

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
            if ($args["onCampus"]) {
                $qs[] = "onCampus=true";
            } else {
                $qs[] = "onCampus=false";
            }

            // QUERYSTRING: didYouMean
            if ($args["didYouMean"]) {
                $qs[] = "dym=true";
            } else {
                $qs[] = "dym=false";
            }

            // QUERYSTRING: query (filter list)
            // Date-related TODO:
            //   - provide additional support / processing for [x to y] limits?
            //   - sys/Summon.php messes with publication date to enable date
            //     range facet control in the interface. look for injectPubDate
            if (!empty($args["filterList"])) {
                foreach ($args["filterList"] as $facet => $values) {
                    foreach ($values as $value) {
                        $thisValue = preg_replace('/,/', '+', $value);
                        $qs[] = "query=facet_" . $facet . ",exact,"
                            . urlencode($thisValue);
                    }
                }
            }

            // QUERYSTRING: pcAvailability
            // by default, PrimoCentral only returns matches,
            // which are available via Holdingsfile
            // pcAvailability = false
            // By setting this value to true, also matches, which
            // are NOT available via Holdingsfile are returned
            // (yes, right, set this to true - thats ExLibris Logic)
            if ($args["pcAvailability"]) {
                $qs[] = "pcAvailability=true";
            }

            // QUERYSTRING: indx (start record)
            $recordStart = $args["pageNumber"];
            if ($recordStart != 1) {
                $recordStart = ($recordStart * 10) + 1;
            }
            $qs[] = "indx=$recordStart";

            // TODO: put bulksize in conf file?  set a reasonable cap...
            //   or is it better to grab each set of 20 through this api module?
            //   Look at how vufind/Summon does this...
            // QUERYSTRING: bulkSize (limit, # of records to return)
            $qs[] = "bulkSize=" . $args["limit"];

            // QUERYSTRING: sort
            // Looks like the possible values are "popularity" or "scdate"
            // omit the field for default sorting
            if (isset($args["sort"]) && ($args["sort"] != 'relevance')) {
                $qs[] = "sortField=" . $args["sort"];
            }

            // QUERYSTRING: loc
            // all primocentral queries need this
            $qs[] = "loc=adaptor,primo_central_multiple_fe";

            if ($this->debug) {
                print "URL: " . implode('&', $qs);

            }

            // Send Request
            $result = $this->call(implode('&', $qs));
        } else {
            throw new \Exception('Primo API does not accept a null query');
        }
        return $result;
    }

    /**
     * Small wrapper for sendRequest, process to simplify error handling.
     *
     * @param string $qs     Query string
     * @param string $method HTTP method
     *
     * @return object    The parsed primo data
     * @throws \Exception
     */
    protected function call($qs, $method = 'GET')
    {
        $this->debug("{$method}: {$this->host}{$qs}");
        $this->client->resetParameters();
        if ($method == 'GET') {
            $baseUrl = $this->host . $qs;
        } elseif ($method == 'POST') {
            throw new \Exception('POST not supported');
        }

        // Send Request
        $this->client->setUri($baseUrl);
        $result = $this->client->setMethod($method)->send();
        if (!$result->isSuccess()) {
            throw new \Exception($result->getBody());
        }
        return $this->process($result->getBody());
    }

    /**
     * Translate Primo's XML into array of arrays.
     *
     * @param array $data The raw xml from Primo
     *
     * @return array      The processed response from Primo
     */
    protected function process($data)
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
        $totalhitsarray = $sxe->xpath("//@TOTALHITS");

        // if totalhits is missing but we have a message, this is an error
        // situation.
        if (!isset($totalhitsarray[0])) {
            $messages = $sxe->xpath("//@MESSAGE");
            $message = isset($messages[0])
                ? (string)$messages[0] : "TOTALHITS attribute missing.";
            throw new \Exception($message);
        } else {
            $totalhits = (int)$totalhitsarray[0];
        }
        // TODO: would these be useful?
        //$firsthit = $sxe->xpath('//@FIRSTHIT');
        //$lasthit = $sxe->xpath('//@LASTHIT');

        // Register the 'sear' namespace at the top level to avoid problems:
        $sxe->registerXPathNamespace(
            'sear', 'http://www.exlibrisgroup.com/xsd/jaguar/search'
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
            // format
            $item['format'] = ucwords(
                str_replace(
                    '_', ' ', (string)$prefix->PrimoNMBib->record->display->type
                )
            );
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
            // description is sort of complicated
            // TODO: sometimes the entire article is in the description.
            $description = isset($prefix->PrimoNMBib->record->display->description)
                ? (string)$prefix->PrimoNMBib->record->display->description
                : (string)$prefix->PrimoNMBib->record->search->description;
            $description = trim(mb_substr($description, 0, 2500, 'UTF-8'));
            // these may contain all kinds of metadata, and just stripping
            //   tags mushes it all together confusingly.
            $description = str_replace("P>", "p>", $description);
            $d_arr = explode("<p>", $description);
            foreach ($d_arr as &$value) {
                // strip tags, trim so array_filter can get rid of
                // entries that would just have spaces
                $value = trim(strip_tags($value));
            }
            $d_arr = array_filter($d_arr);
            // now all paragraphs are converted to linebreaks
            $description = implode("<br>", $d_arr);
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
            'didYouMean' => $didYouMean
        ];
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $recordId  The document to retrieve from the Primo API
     * @param string $inst_code Institution code (optional)
     *
     * @throws \Exception
     * @return string    The requested resource
     */
    public function getRecord($recordId, $inst_code = null)
    {
        // Query String Parameters
        if (isset($recordId)) {
            $qs   = [];
            $qs[] = "query=rid,exact,\"$recordId\"";
            $qs[] = "institution=$inst_code";
            $qs[] = "onCampus=true";
            $qs[] = "indx=1";
            $qs[] = "bulkSize=1";
            $qs[] = "loc=adaptor,primo_central_multiple_fe";

            // Send Request
            $result = $this->call(implode('&', $qs));
        } else {
            throw new \Exception('Primo API does not accept a null query');
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
}
