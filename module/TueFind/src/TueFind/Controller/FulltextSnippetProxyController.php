<?php
/**
 * Proxy Controller Module
 *
 * @category    TueFind
 * @author      Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @copyright   2019 Universtitätsbibliothek Tübingen
 */
namespace TueFind\Controller;

use Laminas\View\Model\JsonModel;


/**
 * Proxy for Fulltext Snippets in Elasticsearch
 * @package  Controller
 */

class FulltextSnippetProxyController extends \VuFind\Controller\AbstractBase implements \VuFind\I18n\Translator\TranslatorAwareInterface
{

    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $base_url; //Elasticsearch host and port (host:port)
    protected $index; //Elasticsearch index
    protected $page_index; //Elasticssearch index with single HTML pages
    protected $es; // Elasticsearch interface
    protected $logger;
    protected $maxSnippets = 5;
    protected $text_type_to_description_map;
    const FIELD = 'full_text';
    const DOCUMENT_ID = 'id';
    const TEXT_TYPE = 'text_type';
    const highlightStartTag = '<span class="highlight">';
    const highlightEndTag = '</span>';
    const fulltextsnippetIni = 'fulltextsnippet';
    const MAX_SNIPPETS_DEFAULT = 3;
    const MAX_SNIPPETS_VERBOSE = 1000;
    const PHRASE_LIMIT = 10000000;
    const FRAGMENT_SIZE_DEFAULT = 300;
    const FRAGMENT_SIZE_VERBOSE = 700;
    const ORDER_DEFAULT = 'none';
    const ORDER_VERBOSE = 'score';
    const esHighlightTag = 'em';
    // must match definitions in TuelibMixin.java
    const description_to_text_type_map = [ 'Fulltext' => '1', 'Table of Contents' => '2',
                                           'Abstract' => '4', 'Summary' => '8', 'List of References' => '16',
                                           'Unknown' => '0' ];
    const CONTENT_LENGTH_TARGET_UPPER_LIMIT = 100;
    const CONTENT_LENGTH_TARGET_LOWER_LIMIT = 20;
    const CHUNK_LENGTH_MIN_SIZE = 10;
    const DOTS = '...';


    public function __construct(\Elasticsearch\ClientBuilder $builder, \Laminas\ServiceManager\ServiceLocatorInterface $sm, \VuFind\Log\Logger $logger) {
        parent::__construct($sm);
        $this->logger = $logger;
        $config = $this->getConfig(self::fulltextsnippetIni);
        $this->base_url = isset($config->Elasticsearch->base_url) ? $config->Elasticsearch->base_url : 'localhost:9200';
        $this->index = isset($config->Elasticsearch->index) ? $config->Elasticsearch->index : 'full_text_cache';
        $this->page_index = isset($config->Elasticsearch->page_index) ? $config->Elasticsearch->page_index : 'full_text_cache_html';
        $this->es = $builder::create()->setHosts([$this->base_url])->build();
        $this->text_type_to_description_map = array_flip(self::description_to_text_type_map);
    }


    protected function getCurrentLang() {
        $this->setTranslator($this->serviceLocator->get(\Laminas\Mvc\I18n\Translator::class));
        return $this->getTranslatorLocale();
    }


    protected function selectSynonymAnalyzer($synonyms) {
        if ($synonyms == "all")
            return 'synonyms_all';
        if ($synonyms == "lang")
            return 'synonyms_' . $this->getCurrentLang();
        return 'fulltext_analyzer';
    }


    protected function mapTextTypeDescriptionsToTypes(array $text_descriptions) : array {
        return array_filter(array_map(
                            function ($description) {
                                return self::description_to_text_type_map[$description] ?? null;
                            },
                            $text_descriptions));
    }


    protected function getTextTypesFilter($types_filter) : array {
        $text_types = $this->mapTextTypeDescriptionsToTypes(explode(',', $types_filter));
        if (empty($text_types))
            return [];
        if (count($text_types) == 1)
            return  [ 'match' => [ self::TEXT_TYPE => $text_types[0] ]];
        return  [ 'bool' => [ 'should' =>
                                  array_map(function ($text_type) { return [ 'match' => [ self::TEXT_TYPE => $text_type ]]; },
                                             $text_types)

                            ]
                ];
    }


    protected function assembleMustQueryParts($doc_id, $search_query, $synonym_analyzer, $text_types_filter) {
        $must_query_parts = [ !empty($text_types_filter) ?
                                      [ 'bool' => [ 'must' => [  [ 'match' => [ self::DOCUMENT_ID => $doc_id ] ], $text_types_filter ] ] ] :
                                      [ 'match' => [ self::DOCUMENT_ID => $doc_id ] ]
                            ];
        // c.f. https://stackoverflow.com/questions/2202435/php-explode-the-string-but-treat-words-in-quotes-as-a-single-word (200525)
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $search_query, $subqueries, PREG_PATTERN_ORDER);
        $subquery_parts = [];
        foreach (array_values($subqueries[0]) as $subquery) {
           $is_phrase_query = \TueFind\Utility::isSurroundedByQuotes($subquery);
           $subquery_parts[] = [ $is_phrase_query ? 'match_phrase' : 'match' =>
                                  [ self::FIELD =>  [ 'query' => $subquery, 'analyzer' => $synonym_analyzer ] ]
                               ];
        }
        $must_query_parts[] = [ 'bool' => [ 'must' => $subquery_parts ] ];
        return $must_query_parts;
    }


    protected function getQueryParams($doc_id, $search_query, $verbose, $synonyms, $paged_results, $types_filter) {
        $this->maxSnippets = $verbose ? self::MAX_SNIPPETS_VERBOSE : self::MAX_SNIPPETS_DEFAULT;
        $index = $paged_results ? $this->page_index : $this->index;
        $synonym_analyzer = $this->selectSynonymAnalyzer($synonyms);
        $text_types_filter = !empty($types_filter) ? $this->getTextTypesFilter($types_filter) : [];
        $params = [
            'index' => $index,
            'body' => [
                '_source' => $paged_results ? [ "id", "full_text", "page", "text_type" ] : ["text_type"],
                'size' => '100',
                'sort' => $paged_results && $verbose ? [ self::TEXT_TYPE => 'asc', 'page' => 'asc' ] : [ '_score' ],
                'query' => [
                    'bool' => [
                        'must' => $this->assembleMustQueryParts($doc_id, $search_query, $synonym_analyzer, $text_types_filter),
                        'must_not' => [ 'term' => [ '_index' => $index . '_write' ] ]
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        self::FIELD => [
                            'type' => 'unified',
                            'fragment_size' => $verbose ? self::FRAGMENT_SIZE_VERBOSE : self::FRAGMENT_SIZE_DEFAULT,
                            'phrase_limit' => self::PHRASE_LIMIT,
                            'number_of_fragments' => $paged_results ? 0 : $this->maxSnippets, /* For page oriented approach get whole page */
                            'order' => $verbose ? self::ORDER_VERBOSE : self::ORDER_DEFAULT,
                        ]
                    ]
                ]
            ]
        ];
        return $params;
    }


    protected function getFulltext($doc_id, $search_query, $verbose, $synonyms, $types_filter) {
        // Is this an ordinary query or a phrase query (surrounded by quotes) ?
        $params = $this->getQueryParams($doc_id, $search_query, $verbose,
                                        $synonyms , false /*return paged results*/, $types_filter);
        $response = $this->es->search($params);
        $snippets = $this->extractSnippets($response);
        if ($snippets == false)
            return false;
        $results['snippets'] = $snippets['snippets'];
        return $results;
    }


    protected function getPagedAndFormattedFulltext($doc_id, $search_query, $verbose, $synonyms, $types_filter) {
        $params = $this->getQueryParams($doc_id, $search_query, $verbose, $synonyms, true, $types_filter);
        $response = $this->es->search($params);
        $snippets = $this->extractSnippets($response);
        if ($snippets == false)
            return false;
        $results['snippets'] = $snippets['snippets'];
        return $results;
    }


    protected function extractStyle($html_page) {
        $dom = new \DOMDocument();
        $dom->loadHTML($html_page, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $style_object = $xpath->query('/html/head/style');
        $style = $dom->saveHTML($style_object->item(0));
        return $style;
    }


    // Needed because each page has its own classes that we finally have to import
    // So try to avoid clashes by prefixing them with id and page
    protected function normalizeCSSClasses($doc_id, $page, $object) {
        // Replace patterns '.ftXXX{' or 'class=\n?"ftXXX"
        $object = preg_replace('/(?<=class="|class=\n"|\.)ft(\d+)(?=[{"])/', '_' . $doc_id . '_' . $page . '_ft\1', $object);
        return $object;
    }


    protected function hasIntersectionWithPreviousEnd($xpath, &$previous_sibling_right, $node, $left_sibling_path, $right_sibling_path) {
        $left_siblings = $xpath->query($left_sibling_path);
        if ($left_siblings->count()) {
            $left_sibling = $left_siblings->item(0);
            if (isset($previous_sibling_right) && $previous_sibling_right && $left_sibling->isSameNode($previous_sibling_right)) {
                $previous_sibling_right = $xpath->query($right_sibling_path)->item(0) ?? false;
                return true;
            }
            $previous_sibling_right = $xpath->query($right_sibling_path)->item(0) ?? false;
        }
        else
            $previous_sibling_right = $node;
        return false;
    }


    protected function isSkipSiblings($node) {
        $text_content_length = strlen($node->textContent);
        return $text_content_length >= self::CONTENT_LENGTH_TARGET_UPPER_LIMIT &&
               !$text_content_length <= self::CONTENT_LENGTH_TARGET_LOWER_LIMIT;
    }


    protected function chunkTooSmall($node) {
        return strlen($node->textContent) < self::CHUNK_LENGTH_MIN_SIZE;
    }


    protected function assembleSnippet($dom, $node, $left_sibling, $right_sibling, $snippet_tree) {
        $skip_siblings = $this->isSkipSiblings($node);
        if (!is_null($left_sibling) && !$skip_siblings) {
            if (!$this->chunkTooSmall($left_sibling)) {
                $import_node_left = $snippet_tree->importNode($left_sibling, true);
                $snippet_tree->appendChild($import_node_left);
            }
        }
        $import_node = $snippet_tree->importNode($node, true /*deep*/);
        $snippet_tree->appendChild($import_node);
        if (!is_null($right_sibling) && !$skip_siblings) {
            if (!$this->chunkTooSmall($right_sibling)) {
                $import_node_right = $snippet_tree->importNode($right_sibling, true /*deep*/);
                $snippet_tree->appendChild($import_node_right);
            }
        }
        return $snippet_tree;
    }


    protected function containsHighlightedPart($xpath, $node) {
        return is_null($node) || $node == false ? false : $xpath->query('./' . self::esHighlightTag, $node)->count();
    }


    protected function array_key_last($array) {
        if (!function_exists("array_key_last")) {
            if (!is_array($array) || empty($array))
                return NULL;
            return array_keys($array)[count($array)-1];
        }
        return array_key_last($array);
    }


    protected function extractSnippetParagraph($snippet_page) {
        $dom = new \DOMDocument();
        $dom->loadHTML($snippet_page, LIBXML_NOERROR /*Needed since ES highlighting does not address nesting of tags properly*/);
        $dom->normalizeDocument(); //Hopefully get rid of strange empty textfields caused by whitespace nodes that prevent proper navigation
        $xpath = new \DOMXPath($dom);
        $highlight_nodes =  $xpath->query('//' . self::esHighlightTag);
        $snippet_trees = [];
        $previous_highlight_parent_node = null;
        $previous_sibling_right = null; // This variable is passed as reference to hasIntersectionWithPreviousEnd and thus transfers status during the iterations
        foreach ($highlight_nodes as $highlight_node) {
            $parent_node = $highlight_node->parentNode;
            if (is_null($parent_node))
                continue;
            $parent_node_path = $parent_node->getNodePath();
            // Make sure we do not get different snippets if we have several highlights in the same paragraph
            if (isset($previous_highlight_parent_node) && $parent_node->isSameNode($previous_highlight_parent_node))
                continue;
            $previous_highlight_parent_node = $parent_node;
            // Make sure we do not get different snippets if the previous right sibling is identical to the current highlight node
            if ($this->containsHighlightedPart($xpath, $previous_sibling_right ?? null) &&
                $this->hasIntersectionWithPreviousEnd($xpath, $previous_sibling_right, $parent_node, $parent_node_path, $parent_node_path))
                continue;
            $left_sibling_path = $parent_node_path . '/preceding-sibling::p[1]';
            $right_sibling_path = $parent_node_path . '/following-sibling::p[1]';
            $left_sibling = $xpath->query($left_sibling_path)->item(0);
            $right_sibling = $xpath->query($right_sibling_path)->item(0);
            $has_intersection = $this->hasIntersectionWithPreviousEnd($xpath,
                                                                      $previous_sibling_right,
                                                                      $parent_node,
                                                                      $left_sibling_path,
                                                                      $right_sibling_path);
            $snippet_tree = $this->assembleSnippet($dom,
                                                   $parent_node,
                                                   $has_intersection ? null : $left_sibling,
                                                   $right_sibling,
                                                   $has_intersection ? array_pop($snippet_trees) : new \DOMDocument);

            array_push($snippet_trees, $snippet_tree);
        }

        array_walk($snippet_trees, function($snippet_tree, $index) use ($snippet_trees) {
                                            if ($index != $this->array_key_last($snippet_trees))
                                                $snippet_tree->appendChild($snippet_tree->createTextNode(self::DOTS));
                                            return $snippet_tree; } );

        $snippets_html = array_map(function($snippet_tree) { return $snippet_tree->saveHTML(); }, $snippet_trees );

        return implode("", $snippets_html);

    }


    protected function extractSnippetTextType($hit) {
       $this->setTranslator($this->serviceLocator->get(\Laminas\Mvc\I18n\Translator::class));

       if (isset($hit['_source']['text_type'])) {
           $text_type_description = $this->text_type_to_description_map[$hit['_source']['text_type']];
           return $this->translate($text_type_description);
       }
       return $this->translate("Unknown");
    }


    protected function extractSnippets($response) {
        $top_level_hits = [];
        $hits = [];
        $highlight_results = [];
        if (empty($response))
            return false;
        if (array_key_exists('hits', $response))
            $top_level_hits = $response['hits'];
        if (empty($top_level_hits))
            return false;
        //second order hits
        if (array_key_exists('hits', $top_level_hits))
           $hits = $top_level_hits['hits'];
        if (empty($top_level_hits))
            return false;

        $snippets = [];
        $pages = [];
        if (count($hits) > $this->maxSnippets)
            $hits = array_slice($hits, 0, $this->maxSnippets);
        foreach ($hits as $hit) {
            if (array_key_exists('highlight', $hit))
                $highlight_results = $hit['highlight'][self::FIELD];
            if (count($highlight_results) > $this->maxSnippets);
                $highlight_results = array_slice($highlight_results, 0, $this->maxSnippets);
            foreach ($highlight_results as $highlight_result) {
                // Handle pages or generic highlight snippets accordingly
                if (isset($hit['_source']['page'])) {
                    $doc_id = $hit['_source']['id'];
                    $page = $hit['_source']['page'];
                    $style = $this->extractStyle($hit['_source']['full_text']);
                    $style = $this->normalizeCSSClasses($doc_id, $page, $style);
                    $snippet_page = $this->normalizeCSSClasses($doc_id, $page, $highlight_result);
                    $snippet_page = preg_replace('/(<[^>]+) style=[\\s]*".*?"/i', '$1', $snippet_page); //remove styles with absolute positions
                    // Disable links to avoid failing internal references
                    $snippet_page = preg_replace('/<a[^>]*?>/i','<a style="color:inherit; text-decoration:inherit; cursor:inherit">', $snippet_page);
                    $snippet = $this->extractSnippetParagraph($snippet_page);
                    array_push($snippets, [ 'snippet' => $snippet, 'page' => $page, 'text_type' => $this->extractSnippetTextType($hit), 'style' => $style ]);
                } else {
                    array_push($snippets, [ 'snippet' => $highlight_result, 'text_type' => $this->extractSnippetTextType($hit) ]);
                }
            }
        }
        if (empty($snippets))
            return false;

        $results['snippets'] = $this->formatHighlighting($snippets);
        return $results;
    }


    protected function formatHighlighting($snippets) {
        $formatted_snippets = [];
        foreach ($snippets as $snippet)
            array_push($formatted_snippets, str_replace(['<em>', '</em>'], [self::highlightStartTag, self::highlightEndTag], $snippet));
        return $formatted_snippets;
    }


    public function loadAction() : JsonModel
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);
        $doc_id = $parameters['doc_id'];
        if (empty($doc_id))
            return new JsonModel([
               'status' => 'EMPTY DOC_ID'
                ]);
        $search_query = $parameters['search_query'];
        if (empty($search_query))
            return new JsonModel([
                'status' => 'EMPTY QUERY'
                ]);
        $verbose = isset($parameters['verbose']) && $parameters['verbose'] == '1' ? true : false;
        $synonyms = isset($parameters['synonyms']) && preg_match('/lang|all/', $parameters['synonyms']) ? $parameters['synonyms'] : "";
        $types_filter = isset($parameters['fulltext_types']) ? $parameters['fulltext_types'] :
                        implode(',', array_keys(self::description_to_text_type_map)); // Iterate over all possible types
        $snippets['snippets'] = [];
        foreach (explode(',', $types_filter) as $type_filter) {
            try {
                $html_snippets = $this->getPagedAndFormattedFulltext($doc_id, $search_query, $verbose, $synonyms, $type_filter);
                if (!empty($html_snippets)) {
                    $snippets['snippets'] = array_merge($snippets['snippets'], $html_snippets['snippets']);
                    continue;
                }
                // Use non-paged text as fallback
                $text_snippets = $this->getFulltext($doc_id, $search_query, $verbose, $synonyms, $type_filter);
                if (!empty($text_snippets))
                    $snippets['snippets'] = array_merge($snippets['snippets'], $text_snippets['snippets']);
            }
            catch (\Exception $e) {
                error_log($e);
                return new JsonModel([
                    'status' => 'PROXY_ERROR'
                ]);
            }
        }
        if (empty($snippets['snippets'])) {
            return new JsonModel([
                'status' => 'NO RESULTS'
            ]);
        }
        // Deduplicate snippets (array_values for fixing indices)
        $snippets['snippets'] = array_values(array_unique($snippets['snippets'], SORT_REGULAR));

        return new JsonModel([
               'status' => 'SUCCESS',
               'snippets' => $snippets['snippets']
               ]);
    }
}
