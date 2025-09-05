<?php

/**
 * Primo Central connector (REST API).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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

use Laminas\Session\Container as SessionContainer;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Primo Central connector (REST API).
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
class RestConnector implements ConnectorInterface, \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindSearch\Backend\Feature\ConnectorCacheTrait;

    /**
     * HTTP client factory
     *
     * @var callable
     */
    protected $clientFactory;

    /**
     * Primo JWT API URL
     *
     * @var string
     */
    protected $jwtUrl;

    /**
     * Primo REST API search URL
     *
     * @var string
     */
    protected $searchUrl;

    /**
     * Institution code
     *
     * @var string
     */
    protected $inst;

    /**
     * Session container
     *
     * @var SessionContainer
     */
    protected $session;

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
     * Mappings from VuFind index names to Primo
     *
     * @var array
     */
    protected $indexMappings = [
        'AllFields' => 'any',
        'Title' => 'title',
        'Author' => 'creator',
        'Subject' => 'sub',
        'Abstract' => 'desc',
        'ISSN' => 'issn',
    ];

    /**
     * Legacy sort mappings
     *
     * @var array
     */
    protected $sortMappings = [
        'scdate' => 'date',
        'screator' => 'author',
        'stitle' => 'title',
    ];

    /**
     * Constructor
     *
     * Sets up the Primo API Client
     *
     * @param string           $jwtUrl        Primo JWT API URL
     * @param string           $searchUrl     Primo REST API search URL
     * @param string           $instCode      Institution code (used as view ID, i.e. the
     * vid parameter unless specified in the URL)
     * @param callable         $clientFactory HTTP client factory
     * @param SessionContainer $session       Session container
     */
    public function __construct(
        string $jwtUrl,
        string $searchUrl,
        string $instCode,
        callable $clientFactory,
        SessionContainer $session
    ) {
        $this->jwtUrl = $jwtUrl;
        $this->searchUrl = $searchUrl;
        $this->inst = $instCode;

        $this->clientFactory = $clientFactory;
        $this->session = $session;
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
        // Ensure limit is at least 1 since Primo seems to be flaky with 0:
        $args['limit'] = max(1, $args['limit']);

        return $this->performSearch($terms, $args);
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
        $qs = [];
        $index = 'rid';
        // For some reason Alma records cannot be fetched using rid, so try to cope:
        if (preg_match('/^alma\d/', $recordId)) {
            $index = 'mms_id';
            $recordId = substr($recordId, 4);
        }
        // It would be tempting to use 'exact' matching here, but it does not work
        // with all record IDs, so need to use 'contains'. Contrary to the old
        // brief search API, quotes are necessary here for all IDs to work.
        $qs['q'] = $index . ',exact,"' . str_replace(';', ' ', $recordId) . '"';
        $qs['offset'] = '0';
        $qs['limit'] = '1';
        // pcAvailability=true is needed for records, which
        // are NOT in the PrimoCentral Holdingsfile.
        // It won't hurt to have this parameter always set to true.
        // But it'd hurt to have it not set in case you want to get
        // a record, which is not in the Holdingsfile.
        $qs['pcAvailability'] = 'true';

        return $this->processResponse($this->call(http_build_query($qs)));
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
     * Support method for query() -- perform inner search logic
     *
     * @param array $terms Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array $args  Associative array of optional arguments (see query method for more information)
     *
     * @throws \Exception
     * @return array       An array of query results
     */
    protected function performSearch($terms, $args)
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
        $precision = $args['phrase'] ? 'exact' : 'contains';

        $primoQuery = [];
        if (is_array($terms)) {
            foreach ($terms as $thisTerm) {
                $lookfor = str_replace(';', ' ', $thisTerm['lookfor']);
                if (!$lookfor) {
                    continue;
                }
                // Set the index to search
                $index = $this->indexMappings[$thisTerm['index']] ?? 'any';

                // Set precision
                if (array_key_exists('op', $thisTerm) && !empty($thisTerm['op'])) {
                    $precision = $thisTerm['op'];
                }

                $primoQuery[] = "$index,$precision,$lookfor";
            }
        }

        // Return if we don't have any query terms:
        if (!$primoQuery && empty($args['filterList'])) {
            return self::$emptyQueryResponse;
        }

        if ($primoQuery) {
            $qs['q'] = implode(';', $primoQuery);
        }

        // QUERYSTRING: query (filter list)
        // Date-related TODO:
        //   - provide additional support / processing for [x to y] limits?
        if (!empty($args['filterList'])) {
            $multiFacets = [];
            $qInclude = [];
            $qExclude = [];
            foreach ($args['filterList'] as $current) {
                $facet = $current['field'];
                $facetOp = $current['facetOp'];
                $values = $current['values'];

                foreach ($values as $value) {
                    if ('OR' === $facetOp) {
                        $multiFacets[] = "facet_$facet,include,$value";
                    } elseif ('NOT' === $facetOp) {
                        $qExclude[] = "facet_$facet,exact,$value";
                    } else {
                        $qInclude[] = "facet_$facet,exact,$value";
                    }
                }
            }
            if ($multiFacets) {
                $qs['multiFacets'] = implode('|,|', $multiFacets);
            }
            if ($qInclude) {
                $qs['qInclude'] = implode('|,|', $qInclude);
            }
            if ($qExclude) {
                $qs['qExclude'] =  implode('|,|', $qExclude);
            }
        }

        // QUERYSTRING: pcAvailability
        // by default, Primo Central only returns matches,
        // which are available via Holdingsfile
        // pcAvailability = false
        // By setting this value to true, also matches, which
        // are NOT available via Holdingsfile are returned
        // (yes, right, set this to true - that's ExLibris Logic)
        if ($args['pcAvailability']) {
            $qs['pcAvailability'] = 'true';
        }

        // QUERYSTRING: offset and limit
        $recordStart = ($args['pageNumber'] - 1) * $args['limit'];
        $qs['offset'] = $recordStart;
        $qs['limit'] = $args['limit'];

        // QUERYSTRING: sort
        // Possible values are rank (default), title, author or date.
        $sort = $args['sort'] ?? null;
        if ($sort && 'relevance' !== $sort) {
            // Map legacy sort options:
            $qs['sort'] = $this->sortMappings[$sort] ?? $sort;
        }

        return $this->processResponse($this->call(http_build_query($qs)), $args);
    }

    /**
     * Small wrapper for sendRequest, process to simplify error handling.
     *
     * @param string $qs Query string
     *
     * @return string Result body
     * @throws \Exception
     */
    protected function call(string $qs): string
    {
        $url = $this->getUrl($this->searchUrl);
        $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
        $this->debug("GET: $url");
        $client = ($this->clientFactory)($url);
        $client->setMethod('GET');
        // Check cache:
        $resultBody = null;
        $cacheKey = null;
        if ($this->cache) {
            $cacheKey = $this->getCacheKey($client);
            $resultBody = $this->getCachedData($cacheKey);
        }
        if (null === $resultBody) {
            if ($jwt = $this->getJWT()) {
                $client->setHeaders(
                    [
                        'Authorization' => [
                            "Bearer $jwt",
                        ],
                    ]
                );
            }
            // Send request:
            $result = $client->send();
            if ($jwt && $result->getStatusCode() === 403) {
                // Reset JWT and try again:
                $jwt = $this->getJWT(true);
                $client->setHeaders(
                    [
                        'Authorization' => [
                            "Bearer $jwt",
                        ],
                    ]
                );
                $result = $client->send();
            }
            $resultBody = $result->getBody();
            if (!$result->isSuccess()) {
                $this->logError("Request $url failed with error code " . $result->getStatusCode() . ": $resultBody");
                throw new \Exception($resultBody);
            }
            if ($cacheKey) {
                $this->putCachedData($cacheKey, $resultBody);
            }
        }
        return $resultBody;
    }

    /**
     * Translate Primo's JSON into array of arrays.
     *
     * @param string $data   The raw xml from Primo
     * @param array  $params Request parameters
     *
     * @return array The processed response from Primo
     */
    protected function processResponse(string $data, array $params = []): array
    {
        // Make sure data exists
        if ('' === $data) {
            throw new \Exception('Primo did not return any data');
        }

        // Parse API response
        $response = json_decode($data);

        if (false === $response) {
            throw new \Exception('Error while parsing Primo response');
        }

        $totalhits = (int)$response->info->total;
        $items = [];
        foreach ($response->docs as $doc) {
            $item = [];
            $pnx = $doc->pnx;
            $addata = $pnx->addata;
            $control = $pnx->control;
            $display = $pnx->display;
            $search = $pnx->search ?? null;
            $item['recordid'] = $this->getRecordId($control->recordid[0]);
            $item['title'] = $display->title[0] ?? '';
            $item['format'] = $display->type ?? [];
            // creators (use the search fields instead of display (if available) to get them as an array instead of a
            // long string):
            if ($search->creator ?? null) {
                $item['creator'] = array_map('trim', $search->creator);
            } elseif ($creators = $display->creator ?? null) {
                if (is_string($creators)) {
                    $creators = explode(';', $creators);
                }
                $item['creator'] = array_map(
                    function ($s) {
                        return trim($this->getFirstSubfield($s));
                    },
                    $creators
                );
            }
            foreach ($display->contributor ?? [] as $contributor) {
                $item['author2'][] = $this->getFirstSubfield($contributor);
            }
            // subjects (use the search fields, if available, instead of display to get them as an array instead of a
            // long string):
            if ($search->subject ?? null) {
                $item['subjects'] = $search->subject;
            } elseif ($subjects = $display->subject ?? null) {
                $item['subjects'] = (array)$subjects;
            }
            $item['ispartof'] = $display->ispartof[0] ?? '';
            $item['description'] = $display->description[0]
                ?? $search->description[0]
                ?? '';
            // and the rest!
            $item['language'] = $display->language[0] ?? '';
            $item['source'] = implode('; ', $display->source ?? []);
            $item['identifier'] = $display->identifier[0] ?? '';
            $item['fulltext'] = $pnx->delivery->fulltext[0] ?? '';
            $item['isbn'] = array_values(array_unique($addata->isbn ?? []));
            $item['issn'] = $search->issn ?? [];
            $item['publisher'] = $display->publisher ?? [];
            $item['peer_reviewed'] = ($display->lds50[0] ?? '') === 'peer_reviewed';
            $openurl = $pnx->links->openurl[0] ?? '';
            $item['url'] = $openurl && !str_starts_with($openurl, '$')
                ? $openurl
                : ($pnx->GetIt2->link ?? '');

            $processCitations = function (array $data): array {
                return array_map(
                    function ($s) {
                        return "cdi_$s";
                    },
                    $data
                );
            };

            // These require the cdi_ prefix in search, so add it right away:
            $item['cites'] = $processCitations($display->cites ?? []);
            $item['cited_by'] = $processCitations($display->citedby ?? []);

            // Container data
            $item['container_title'] = $addata->jtitle[0] ?? '';
            $item['container_volume'] = $addata->volume[0] ?? '';
            $item['container_issue'] = $addata->issue[0] ?? '';
            $item['container_start_page'] = $addata->spage[0] ?? '';
            $item['container_end_page'] = $addata->epage[0] ?? '';
            foreach ($addata->eissn ?? [] as $eissn) {
                if (!in_array($eissn, $item['issn'])) {
                    $item['issn'][] = $eissn;
                }
            }
            foreach ($addata->issn ?? [] as $issn) {
                if (!in_array($issn, $item['issn'])) {
                    $item['issn'][] = $issn;
                }
            }
            $item['doi_str_mv'] = $addata->doi ?? [];

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

            $this->processHighlighting($item, $params, $response->highlights);

            // Fix description now that highlighting is done:
            $item['description'] = $this->processDescription($item['description']);

            $item['fullrecord'] = json_decode(json_encode($pnx), true);
            $items[] = $item;
        }

        // Add active filters to the facet list (Primo doesn't return them):
        $facets = [];
        foreach ($params['filterList'] ?? [] as $current) {
            if ('NOT' === $current['facetOp']) {
                continue;
            }
            $field = $current['field'];
            foreach ($current['values'] as $value) {
                $facets[$field][$value] = null;
            }
        }

        // Process received facets
        foreach ($response->facets as $facet) {
            // Handle facet values as strings to ensure that numeric values stay
            // intact (no array_combine etc.):
            foreach ($facet->values as $value) {
                $facets[$facet->name][(string)$value->value] = $value->count;
            }
            uasort(
                $facets[$facet->name],
                function ($a, $b) {
                    // Put the selected facets (with null as value) on the top:
                    return ($b ?? PHP_INT_MAX) <=> ($a ?? PHP_INT_MAX);
                }
            );
        }

        // Apparently there's no "did you mean" data in the response..

        return [
            'recordCount' => $totalhits,
            'documents' => $items,
            'facets' => $facets,
            'didYouMean' => [],
            'error' => $response->info->errorDetails->errorMessages[0] ?? [],
        ];
    }

    /**
     * Process highlighting tags of the record fields
     *
     * @param array     $record    Record data
     * @param array     $params    Request params
     * @param \StdClass $highlight Highlighting data
     *
     * @return void
     */
    protected function processHighlighting(array &$record, array $params, \StdClass $highlight): void
    {
        if (empty($params['highlight'])) {
            return;
        }

        $startTag = $params['highlightStart'] ?? '';
        $endTag = $params['highlightEnd'] ?? '';

        $highlightFields = [
            'title' => 'title',
            'creator' => 'author',
            'description' => 'description',
        ];

        $hilightDetails = [];

        foreach ($highlightFields as $primoField => $field) {
            if (
                ($highlightValues = $highlight->$primoField ?? null)
                && !empty($record[$field])
            ) {
                $match = implode(
                    '|',
                    array_map(
                        function ($s) {
                            return preg_quote($s, '/');
                        },
                        $highlightValues
                    )
                );
                $hilightDetails[$field] = array_map(
                    function ($s) use ($match, $startTag, $endTag) {
                        return preg_replace("/(\b|-|–)($match)(\b|-|–)/", "$1$startTag$2$endTag$3", $s);
                    },
                    (array)$record[$field]
                );
            }
        }

        $record['highlightDetails'] = $hilightDetails;
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

    /**
     * Get a JWT token for the session
     *
     * @param bool $renew Whether to renew the token
     *
     * @return string
     */
    protected function getJWT(bool $renew = false): string
    {
        if (!$this->jwtUrl) {
            return '';
        }

        if (!$renew && isset($this->session->jwt)) {
            return $this->session->jwt;
        }
        $client = ($this->clientFactory)($this->getUrl($this->jwtUrl));
        $result = $client->setMethod('GET')->send();
        $resultBody = $result->getBody();
        if (!$result->isSuccess()) {
            $this->logError(
                "Request {$this->jwtUrl} failed with error code " . $result->getStatusCode() . ": $resultBody"
            );
            throw new \Exception($resultBody);
        }
        $this->session->jwt = trim($resultBody, '"');
        return $this->session->jwt;
    }

    /**
     * Build a URL from a configured one
     *
     * @param string $url URL
     *
     * @return string
     */
    protected function getUrl(string $url): string
    {
        return str_replace('{{INSTCODE}}', urlencode($this->inst), $url);
    }

    /**
     * Get a working identifier from an identifier of a search result
     *
     * @param string $id Identifier
     *
     * @return string
     */
    protected function getRecordId(string $id): string
    {
        // CDI records may have a TN_ prefix that must be stripped:
        return str_starts_with($id, 'TN_') ? substr($id, 3) : $id;
    }

    /**
     * Get first subfield from a field that can contain subfields
     *
     * Example field: 'Mattila, Jaakko$$QMattila, Jaakko'
     *
     * @param string $field Field
     *
     * @return string
     */
    protected function getFirstSubfield(string $field): string
    {
        return false !== ($p = strpos($field, '$$')) ? substr($field, 0, $p) : $field;
    }
}
