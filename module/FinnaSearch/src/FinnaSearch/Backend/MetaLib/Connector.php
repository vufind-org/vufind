<?php
/**
 * MetaLib connector.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\MetaLib;
use Finna\Db\Table\MetaLibSearch,
    \VuFindSearch\Backend\Solr\LuceneSyntaxHelper,
    ZfcRbac\Service\AuthorizationService,
    Zend\Http\Client as HttpClient,
    Zend\Http\Request,
    Zend\Log\LoggerInterface,
    Zend\Session\Container as SessionContainer;

/**
 * MetaLib connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
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
     * MetaLib user
     *
     * @var string
     */
    public $user;

    /**
     * MetaLib password
     *
     * @var string
     */
    public $pass;

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
     * Lucene query syntax helper
     *
     * @var \VuFindSearch\Backend\Solr\LuceneSyntaxHelper
     */
    protected $luceneHelper;

    /**
     * Session container
     *
     * @var \VuFind\Session\Container
     */
    protected $session;

    /**
     * Table for cached search results
     *
     * @var \Finna\Db\Table\MetaLibSearch
     */
    protected $table;

    /**
     * Authorization service
     *
     * @var Zend\Service\AuthorizationService
     */
    protected $authService;

    /**
     * Configured MetaLib search sets
     *
     * @var array
     */
    protected $sets;

    /**
     * Constructor
     *
     * Sets up the MetaLib Client
     *
     * @param string               $institution  MetaLib institution code
     * @param string               $url          MetaLib API url institution code
     * @param string               $user         MetaLib API user
     * @param string               $pass         MetaLib API password
     * @param HttpClient           $client       HTTP client
     * @param MetaLibSearch        $table        Table for cached search results
     * @param AuthorizationService $auth         Authorization service
     * @param array                $sets         MetaLib search sets
     * @param LuceneSyntaxHelper   $luceneHelper Lucene syntax helper
     */
    public function __construct(
        $institution, $url, $user, $pass,
        HttpClient $client,
        MetaLibSearch $table, AuthorizationService $auth,
        $sets,
        LuceneSyntaxHelper $luceneHelper = null
    ) {
        $this->inst = $institution;
        $this->host = $url;
        $this->client = $client;
        $this->luceneHelper = $luceneHelper;
        $this->user = $user;
        $this->pass = $pass;
        $this->table = $table;
        $this->auth = $auth;
        $this->sets = $sets;
        $this->session = new SessionContainer('MetaLib');
    }

    /**
     * Execute a search.
     *
     * @param string $institution Institution
     * @param array  $terms       Search terms
     * @param array  $params      Optional search arguments
     *
     * @throws \Exception
     * @return array              An array of query results
     */
    public function query($institution, $terms, $params = null)
    {
        try {
            $sessionId = $this->getSession();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        $args = [];
        if (isset($params['irdInfo'])) {
            try {
                $result = $this->getIRDInfo($params['irdInfo']);
                $result['documents'] = [];
            } catch (\Exception $e) {
                $this->debug($e->getMessage());
                return ['error' => $e->getMessage()];
            }
        } else {
            if (isset($params)) {
                $args = array_merge($args, $params);
            }
            try {
                $result = $this->performSearch($institution, $terms, $args);
            } catch (\Exception $e) {
                $this->debug($e->getMessage());
                return [
                    'recordCount' => 0,
                    'documents' => [],
                    'facets' => [],
                    'error' => $e->getMessage()
                ];
            }
        }
        return $result;
    }

    /**
     * Support method for query() -- perform inner search logic
     *
     * @param string $institution Institution code
     * @param array  $terms       Search terms
     * @param array  $args        Search arguments
     *
     * @throws \Exception
     * @return array Results
     */
    protected function performSearch($institution, $terms, $args)
    {
        $qs = $this->buildQuery($terms);
        if (!$qs) {
            throw new \Exception('Search terms are required');
        }

        $authorized = $this->auth->isGranted('finna.authorized');
        $irdList = $args['searchSet'];

        if (strncmp($irdList, '_ird:', 5) != 0) {
            if (array_key_exists($irdList, $this->sets)) {
                $irdList = $this->sets[$irdList]['ird_list'];
            } else {
                $irdList = current($this->sets)['ird_list'];
            }
        } else {
            $irdList = substr($irdList, 5);
        }
        $irdList = explode(',', $irdList);
        $irdData = $this->getIRDInfos($irdList, $authorized);

        if (empty($irdData['allowed'])) {
            return [
                'recordCount' => 0,
                'failedDatabases' => $irdData['failed'],
                'disallowedDatabases' => $irdData['disallowed'],
                'successDatabases' => []
            ];
        }

        $options = [];
        $options['find_base/find_base_001'] = $irdData['allowed'];
        $options['find_request_command'] = $qs;

        $sessionId = $this->getSession();

        $options['requester_ip'] = $_SERVER['REMOTE_ADDR'];
        $options['session_id'] = $this->getSession();
        $options['wait_flag'] = 'Y';

        $limit = 20;
        $start = !empty($args['pageNumber']) ? $args['pageNumber'] : 1;

        $queryId
            = md5(implode(',', $irdList) . '_' . $qs . '_' . $start . '_' . $limit);

        $findResults = $this->getCachedResults($queryId);

        if ($findResults !== false
            && empty($findResults['failedDatabases'])
            && empty($findResults['disallowedDatabases'])
        ) {
            return $findResults;
        }
        try {
            $databases = $this->getDatabases($options, $irdData);
            $records
                = $this->searchDatabases(
                    $databases['databases'], $sessionId, $queryId, $limit, $start
                );
            $results = [
                'query' => ['pageNumber' => $start, 'pageSize' => $limit],
                'totalRecords' => $databases['totalRecords'],
                'documents' => $records,
                'successDatabases' => $databases['successes'],
                'failedDatabases' => $databases['failed'],
                'disallowedDatabases' => $irdData['disallowed']
            ];
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $this->putCachedResults($queryId, $results);
        return $results;
    }

    /**
     * Support method for performSearch() -- fetch databases
     *
     * @param array $options      Search options
     * @param array $irdInfoArray IRD info
     *
     * @return array Results
     */
    protected function getDatabases($options, $irdInfoArray)
    {
        $findResults = $this->call('find_request', $options);

        $databases = [];
        $failed = [];
        $successes = [];
        $totalRecords = 0;
        foreach ($findResults->find_response->base_info as $baseInfo) {
            $databaseName = (string)$baseInfo->full_name;
            $databaseInfo
                = isset($irdInfoArray[$databaseName])
                ? $irdInfoArray[$databaseName] : (string)$baseInfo->full_name;
            if ($baseInfo->find_status != 'DONE') {
                $this->logger->err(
                    'MetaLib search in ' . $baseInfo->base_001
                    . ' (' . $baseInfo->full_name . ') failed: '
                    . $baseInfo->find_error_text
                );
                $failed[] = $databaseInfo;
            }
            $count = ltrim((string)$baseInfo->no_of_documents, ' 0');
            if ($count === '') {
                continue;
            }
            $totalRecords += $count;
            $databases[] = [
                'ird' => (string)$baseInfo->base_001,
                'count' => $count,
                'set' => (string)$baseInfo->set_number,
                'records' => []
            ];
            $successes[] = $databaseInfo;
        }
        return compact('databases', 'successes', 'failed', 'totalRecords');
    }

    /**
     * Support method for performSearch() -- search in databases
     *
     * @param array  $databases Databases
     * @param string $sessionId Session id
     * @param string $queryId   Query id
     * @param int    $limit     Result limit
     * @param int    $start     Result start index
     *
     * @throws \Exception
     * @return array Results
     */
    protected function searchDatabases(
        $databases, $sessionId, $queryId, $limit, $start
    ) {
        $documents = [];
        $databaseCount = count($databases);
        if ($databaseCount > 0) {
            // Sort the array by number of results
            usort(
                $databases,
                function ($a, $b) {
                    return $a['count'] - $b['count'];
                }
            );

            // Find cut points where a database is exhausted of results
            $sum = 0;
            for ($k = 0; $k < $databaseCount; $k++) {
                $sum
                    += ($databases[$k]['count'] -
                        ($k > 0 ? $databases[$k - 1]['count'] : 0))
                        * ($databaseCount - $k)
                    ;
                $databases[$k]['cut'] = $sum;
            }

            // Find first item for the given page
            $firstRecord = ($start - 1) * $limit;
            $i = 0;
            $iCount = false;
            for ($k = 0; $k < $databaseCount; $k++) {
                if ($iCount === false || $databases[$k]['count'] < $iCount) {
                    if ($databases[$k]['cut'] > $firstRecord) {
                        $i = $k;
                        $iCount = $databases[$k]['count'];
                    }
                }
            }
            $l = $databases[$i]['cut'] - $firstRecord - 1;
            if ($l < 0) {
                throw new \Exception('Invalid page index');
            }
            $m = $l % ($databaseCount - $i);
            $startDB = $databaseCount - $m - 1;
            $startRecord
                = floor(
                    $databases[$i]['count'] - ($l + 1)
                    / ($databaseCount - $i) + 1
                ) - 1;

            // Loop until we have enough record indices
            // or run out of records from any of the databases
            $currentDB = $startDB;
            $currentRecord = $startRecord;
            $haveRecords = true;
            for ($count = 0; $count < $limit;) {
                if ($databases[$currentDB]['count'] > $currentRecord) {
                    $databases[$currentDB]['records'][] = $currentRecord + 1;
                    ++$count;
                    $haveRecords = true;
                }
                if (++$currentDB >= $databaseCount) {
                    if (!$haveRecords) {
                        break;
                    }
                    $haveRecords = false;
                    $currentDB = 0;
                    ++$currentRecord;
                }
            }

            // Fetch records
            $baseIndex = 0;
            for ($i = 0; $i < $databaseCount; $i++) {
                $database = $databases[($startDB + $i) % $databaseCount];
                ++$baseIndex;

                if (empty($database['records'])) {
                    continue;
                }

                $params = [
                    'session_id' => $sessionId,
                    'present_command' => [
                        'set_number' => $database['set'],
                        'set_entry'
                            => $database['records'][0]
                               . '-' . end($database['records']),
                        'view' => 'full',
                        'format' => 'marc'
                    ]
                ];
                $result = $this->call('present_request', $params);

                // Go through the records one by one. If there is a MOR tag
                // in the record, it means that a single record present
                // command is needed to fetch full record.
                $currentDocs = [];
                $recIndex = -1;
                foreach ($result->present_response->record as $record) {
                    ++$recIndex;
                    $record->registerXPathNamespace(
                        'm', 'http://www.loc.gov/MARC21/slim'
                    );
                    if ($record->xpath("./m:controlfield[@tag='MOR']")) {
                        $params = [
                            'session_id' => $sessionId,
                            'present_command' => [
                                'set_number' => $database['set'],
                                'set_entry' => $database['records'][$recIndex],
                                'view' => 'full',
                                'format' => 'marc'
                             ]
                        ];

                        $singleResult = $this->call('present_request', $params);
                        $currentDocs[]
                            = $this->process(
                                $singleResult->present_response->record[0]
                            );
                    } else {
                        $currentDocs[] = $this->process($record);
                    }
                }

                $docIndex = 0;
                foreach ($currentDocs as $doc) {
                    $foundRecords = true;
                    $documents[sprintf('%09d_%09d', $docIndex++, $baseIndex)] = $doc;
                }
            }

            ksort($documents);
            $documents = array_values($documents);

            $i = 1;
            foreach ($documents as $key => $doc) {
                $documents[$key]['id'] = "{$queryId}_{$i}";
                $i++;
            }
        }
        return $documents;
    }

    /**
     * Build Query string from search parameters
     *
     * @param array $terms Search terms
     *
     * @return string Query
     */
    protected function buildQuery($terms)
    {
        $groups   = [];
        $excludes = [];
        if (is_array($terms)) {
            $query = '';
            $advanced = isset($terms[0]['op']);
            $operator = $advanced ? $terms[0]['op'] : null;
            $negated = isset($terms[0]['negated']) && $terms[0]['negated'];
            $queries = [];
            foreach ($terms as $params) {
                // Advanced Search
                if ($advanced) {
                    // Build this group individually as a basic search
                    $queries[] = $this->buildBasicQuery($params);
                } else if (isset($params['lookfor']) && $params['lookfor'] != '') {
                    return $this->buildBasicQuery($params);
                }
            }
            if ($advanced) {
                $query = join(
                    ' ' . $operator . ' ', $queries
                );
                if ($negated) {
                    $query = " NOT ($query)";
                }
            }
        }
        // Ensure we have a valid query to this point
        return isset($query) ? $query : '';
    }

    /**
     * Support method for buildQuery() -- Build a basic query
     *
     * @param array $params Search parameters
     *
     * @return string Query
     */
    protected function buildBasicQuery($params)
    {
        $query = '';
        if (!empty($params['lookfor'])) {
            // Basic Search
            // Clean and validate input -- note that index may be in a
            // different field depending on whether this is a basic or
            // advanced search.
            $lookfor = $params['lookfor'];
            $index = !empty($params['index']) ? $params['index'] : 'AllFields';

            // Force boolean operators to uppercase if we are in a
            // case-insensitive mode:
            if ($this->luceneHelper) {
                $lookfor = $this->luceneHelper->capitalizeBooleans($lookfor);
            }
            $map = [
                'AllFields' => 'WRD',
                'Title' => 'WTI',
                'Author' => 'WAU',
                'Subject' => 'WSU',
                'isbn' => 'ISBN',
                'issn' => 'ISSN'
            ];

            if (isset($map[$index])) {
                $index = $map[$index];
            } else if (!array_key_exists($index, $map)) {
                $index = 'WRD';
            }
            $query .= "{$index}=($lookfor)";
        }
        // Ensure we have a valid query to this point
        return isset($query) ? $query : '';
    }

    /**
     * Call MetaLib X-server
     *
     * @param string $operation X-Server operation
     * @param array  $params    URL Parameters
     *
     * @return mixed simpleXMLElement
     * @throws \Exception
     */
    protected function call($operation, $params)
    {
        $this->debug(
            "Call: {$this->host}: {$operation}: " . var_export($params, true)
        );

        // Declare UTF-8 encoding so that SimpleXML won't encode characters.
        $xml = simplexml_load_string(
            '<?xml version="1.0" encoding="UTF-8"?><x_server_request/>'
        );
        $op = $xml->addChild($operation);
        $this->paramsToXml($op, $params);

        $this->client->resetParameters();
        $this->client->setUri($this->host);
        $this->client->setParameterPost(['xml' => $xml->asXML()]);

        $result = $this->client->setMethod('POST')->send();

        if (!$result->isSuccess()) {
            throw new \Exception($result->getBody());
        }

        $xml = $result->getBody();
        // Remove invalid XML fields (these were encountered in record Ppro853_304965
        // from FIN05707)
        $xml = preg_replace(
            '/<controlfield tag="   ">.*?<\/controlfield>/', '', $xml
        );

        if ($xml = simplexml_load_string($xml)) {
            $errors = $xml->xpath('//local_error | //global_error');
            if (!empty($errors)) {
                if ($errors[0]->error_code == 6026) {
                    throw new \Exception('Search timed out');
                }
                throw new \Exception($errors[0]->asXML());
            }
            $result = $xml;
        }

        return $result;
    }

    /**
     * Convert array of X-Server call parameters to XML
     *
     * @param simpleXMLElement $node  The target node
     * @param array            $array Array to convert
     *
     * @return void
     */
    protected function paramsToXml($node, $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $path = explode('/', $key, 2);
                if (isset($path[1])) {
                    foreach ($value as $single) {
                        $child = $node->addChild($path[0]);
                        $child->addChild($path[1], $single);
                    }
                } else {
                    $child = $node->addChild($path[0]);
                    foreach ($value as $vkey => $single) {
                        $child->addChild($vkey, $single);
                    }
                }
            } else {
                $node->addChild($key, $value);
            }
        }
    }

    /**
     * Perform normalization and analysis of MetaLib return value
     * (a single record)
     *
     * @param simplexml $record The xml record from MetaLib
     *
     * @return array The processed record array
     */
    protected function process($record)
    {
        $record->registerXPathNamespace('m', 'http://www.loc.gov/MARC21/slim');

        // TODO: can we get anything reliable from MetaLib results for format?
        $format = '';
        $title = $this->getSingleValue($record, '245ab', ' : ');
        if ($addTitle = $this->getSingleValue($record, '245h')) {
            $title .= " $addTitle";
        }
        $author = $this->getSingleValue($record, '100a');
        $addAuthors = $this->getMultipleValues($record, '700a');
        $sources = $this->getMultipleValues($record, 'SIDt');
        $year = $this->getSingleValue($record, 'YR a');
        $languages = $this->getMultipleValues($record, '041a');
        $publishers = $this->getMultipleValues($record, '260b');

        $urls = [];
        $res = $record->xpath("./m:datafield[@tag='856']");
        foreach ($res as $value) {
            $value->registerXPathNamespace('m', 'http://www.loc.gov/MARC21/slim');
            $url = $value->xpath("./m:subfield[@code='u']");
            if ($url) {
                $desc = $value->xpath("./m:subfield[@code='y']");
                if ($desc) {
                    $urls[(string)$url[0]] = (string)$desc[0];
                } else {
                    $urls[(string)$url[0]] = (string)$url[0];
                }
            }
        }

        $proxy = false;
        $ird = $this->getSingleValue($record, 'SIDd');
        if ($ird) {
            $info = $this->getIRDInfo($ird);
            $proxy = $info['proxy'] == 'Y';
        }

        $openurlParams = [];
        $opu = $this->getSingleValue($record, 'OPUa');
        if ($opu) {
            $opuxml = simplexml_load_string($opu);
            $opuxml->registerXPathNamespace('ctx', 'info:ofi/fmt:xml:xsd:ctx');
            $opuxml->registerXPathNamespace('rft', ''); //info:ofi/fmt:xml:xsd');
            foreach ($opuxml->xpath('//*') as $element) {
                if (in_array($element->getName(), ['journal', 'author'])) {
                    continue;
                }
                $value = trim((string)$element);
                if ($value) {
                    $openurlParams[$element->getName()] = $value;

                    // OpenURL might have many nicely parsed elements we can use
                    switch ($element->getName()) {
                    case 'date':
                        if (empty($year)) {
                            $year = $value;
                        }
                        break;
                    case 'volume':
                        $volume = $value;
                        break;
                    case 'issue':
                        $issue = $value;
                        break;
                    case 'spage':
                        $startPage = $value;
                        break;
                    case 'epage':
                        $endPage = $value;
                        break;
                    }
                }
            }
        }

        $isbn = $this->getMultipleValues($record, '020a');
        $issn = $this->getMultipleValues($record, '022a');
        $snippet = $this->getMultipleValues($record, '520a');
        $subjects = $this->getMultipleValues(
            $record,
            '600abcdefghjklmnopqrstuvxyz'
            . ':610abcdefghklmnoprstuvxyz'
            . ':611acdefghjklnpqstuvxyz'
            . ':630adefghklmnoprstvxyz'
            . ':650abcdevxyz',
            ' : '
        );
        $notes = $this->getMultipleValues($record, '500a');
        $field773g = $this->getSingleValue($record, '773g');

        $matches = [];
        if (preg_match('/(\d*)\s*\((\d{4})\)\s*:\s*(\d*)/', $field773g, $matches)) {
            if (!isset($volume)) {
                $volume = $matches[1];
            }
            if (!isset($issue)) {
                $issue = $matches[3];
            }
        } elseif (preg_match('/(\d{4})\s*:\s*(\d*)/', $field773g, $matches)) {
            if (!isset($volume)) {
                $volume = $matches[1];
            }
            if (!isset($issue)) {
                $issue = $matches[2];
            }
        }
        if (preg_match('/,\s*\w\.?\s*([\d,\-]+)/', $field773g, $matches)) {
            $pages = explode('-', $matches[1]);
            if (!isset($startPage)) {
                $startPage = $pages[0];
            }
            if (isset($pages[1]) && !isset($endPage)) {
                $endPage = $pages[1];
            }
        }
        $hostTitle = explode('. ', $this->getSingleValue($record, '773t'), 2);

        $year = str_replace('^^^^', '', $year);
        return [
            'title' => $title,
            'author' => $author ? $author : null,
            'author2' => $addAuthors,
            'source' => $sources[0],
            'publisher' => $publishers,
            'main_date_str' => $year ? $year : null,
            'publishDate' => $year ? [$year] : null,
            'container_title' => $hostTitle ? $hostTitle[0] : null,
            'openUrl' =>
               !empty($openurlParams) ? http_build_query($openurlParams) : null,
            'url' => $urls,
            'proxy' => $proxy,
            'fullrecord' => $record->asXML(),
            'id' => '',
            'recordtype' => 'marc',
            'format' => [$format],
            'isbn' => $isbn,
            'issn' => $issn,
            'ispartof' => "{$hostTitle[0]}, {$field773g}",
            'language' => $languages,
            'topic' => $subjects,
            'description' => $snippet,
            'notes' => $notes,
            'container_volume' => isset($volume) ? $volume : '',
            'container_issue' => isset($issue) ? $issue : '',
            'container_start_page' => isset($startPage) ? $startPage : '',
            'container_end_page' => isset($endPage) ? $endPage : ''
        ];
    }

    /**
     * Return the contents of a single MARC data field
     *
     * @param simpleXMLElement $xml       MARC Record
     * @param string           $fieldspec Field and subfields (e.g. '245ab')
     * @param string           $glue      Delimiter used between subfields
     *
     * @return string
     */
    protected function getSingleValue($xml, $fieldspec, $glue = '')
    {
        $values = $this->getMultipleValues($xml, $fieldspec, $glue);
        if ($values) {
            return $values[0];
        }
        return '';
    }

    /**
     * Return URL for the local search interface from MARC field 856
     *
     * @param simpleXMLElement $xml MARC Record
     *
     * @return string
     */
    protected function getUrl($xml)
    {
        $values = [];
        $xpath = "./m:datafield[@tag='856' and @ind2='1']";
        $res = $xml->xpath($xpath);
        foreach ($res as $datafield) {
            $strings = [];
            foreach ($datafield->subfield as $subfield) {
                if (strstr('u', (string)$subfield['code'])) {
                    return (string)$subfield;
                }
            }
        }

        return '';
    }

    /**
     * Return the contents of MARC data fields as an array
     *
     * @param simpleXMLElement $xml        MARC Record
     * @param string           $fieldspecs Fields and subfields (e.g. '100a:700a')
     * @param string           $glue       Delimiter used between subfields
     *
     * @return array
     */
    protected function getMultipleValues($xml, $fieldspecs, $glue = '')
    {
        $values = [];
        foreach (explode(':', $fieldspecs) as $fieldspec) {
            $field = substr($fieldspec, 0, 3);
            $subfields = substr($fieldspec, 3);
            $xpath = "./m:datafield[@tag='$field']";

            $res = $xml->xpath($xpath);
            foreach ($res as $datafield) {
                $strings = [];
                foreach ($datafield->subfield as $subfield) {
                    if (strstr($subfields, (string)$subfield['code'])) {
                        $strings[] .= (string)$subfield;
                    }
                }
                if ($strings) {
                    $values[] = implode($glue, $strings);
                }
            }
        }
        return $values;
    }

    /**
     * Get information regarding the IRD
     *
     * @param array   $irds       IRD IDs
     * @param boolean $authorized Is the user authorized
     *
     * @return array Array with 'allowed', 'disallowed', 'failed' and 'info'.
     */
    protected function getIRDInfos($irds, $authorized)
    {
        $allowed = $disallowed = $failed = $info = [];
        foreach ($irds as $ird) {
            try {
                $irdInfo = $this->getIRDInfo($ird);

                if (strcasecmp($irdInfo['access'], 'guest') != 0 && !$authorized) {
                    $disallowed[] = $irdInfo['name'];
                } else {
                    $allowed[] = $ird;
                    $info[$irdInfo['name']] = $irdInfo;
                }
            } catch (\Exception $e) {
                $disallowed[] = $ird;
            }
        }
        return compact('allowed', 'disallowed', 'failed', 'info');
    }

    /**
     * Get information regarding the IRD
     *
     * @param string $ird IRD ID
     *
     * @return array Array with e.g. 'name' and 'access'
     * @throws \Exception
     */
    public function getIRDInfo($ird)
    {
        $queryId = md5($ird);
        $cached = $this->getCachedResults($queryId);
        if ($cached) {
            return $cached;
        }
        $sessionId = $this->getSession();

        // Do the source locate request
        $params = [
            'session_id' => $sessionId,
            'locate_command' => "IDN=$ird",
            'source_full_info_flag' => 'Y'
        ];

        try {
            $result = $this->call('source_locate_request', $params);
            $info = [];
            $info['name']
                = (string)$result->source_locate_response
                    ->source_full_info->source_info->source_short_name;
            $record = $result->source_locate_response->source_full_info->record;
            $record->registerXPathNamespace('m', 'http://www.loc.gov/MARC21/slim');

            $institute = $this->getSingleValue($record, 'AF1a');
            if ($institute !== $this->getInstitutionCode()) {
                throw new \Exception(
                    "Failed to get IRD info for $ird"
                    . "(owner institution: $institute, own institute: "
                    . $this->getInstitutionCode()
                );
            }
            $info['access'] = $this->getSingleValue($record, 'AF3a');
            $info['proxy'] = $this->getSingleValue($record, 'PXYa');
            $info['searchable']
                = $this->getSingleValue($record, 'TARa')
                && $this->getSingleValue($record, 'TARf') == 'Y'
            ;
            $info['url'] = $this->getUrl($record);
            $this->putCachedResults($queryId, $info);
            return $info;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Return current session id (if valid) or create a new session
     *
     * @return string session id
     * @throws \Exception
     */
    protected function getSession()
    {
        $sessionId = '';

        if (isset($this->session->MetaLibSessionID)) {
            // Check for valid session
            $params = [
                'session_id' => $this->session->MetaLibSessionID,
                'view' => 'customize',
                'logical_set' => 'ml_sys_info',
                'parameter_name' => 'ML_VERSION'
            ];
            try {
                $result = $this->call('retrieve_metalib_info_request', $params);
                $sessionId = $this->session->MetaLibSessionID;
            } catch (\Exception $e) {
            }
        }

        if (!$sessionId) {
            // Login to establish a session
            $params = [
                'user_name' => $this->user,
                'user_password' => $this->pass
            ];
            try {
                $result = $this->call('login_request', $params);
                if ($result->login_response->auth != 'Y') {
                    $this->debug(
                        'X-Server login failed: ' . var_export($params, true)
                    );
                    throw new \Exception('X-Server login failed');
                }
            } catch (\Exception $e) {
                throw $e;
            }
            $sessionId = (string)$result->login_response->session_id;
            $this->session->MetaLibSessionID = $sessionId;
        }
        return $sessionId;
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $id The document to retrieve from the MetaLib API
     *
     * @throws \Exception
     * @return string The requested resource
     */
    public function getRecord($id)
    {
        list($queryId, $index) = explode('_', $id);
        $result = $this->getCachedResults($queryId);
        if ($index < 1 || $index > count($result['documents'])) {
            throw new \Exception('Invalid record id');
        }
        $result['documents'] = array_slice($result['documents'], $index - 1, 1);

        return $result['documents'][0];
    }

    /**
     * Return MetaLib institution code.
     *
     * @return string
     */
    public function getInstitutionCode()
    {
        return $this->inst;
    }

    /**
     * Set logger instance
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return search results from cache
     *
     * @param string $queryId Query identifier (hash)
     *
     * @return mixed Search results array | false
     */
    protected function getCachedResults($queryId)
    {
        if ($row = $this->table->getRowBySearchId($queryId)) {
            return $row->getSearchObject();
        }
        return false;
    }

    /**
     * Add search results into the cache
     *
     * @param string $queryId Query identifier (hash)
     * @param array  $results Search results
     *
     * @return void
     */
    protected function putCachedResults($queryId, $results)
    {
        $this->table->saveMetaLibSearch($results, $queryId);
    }
}
