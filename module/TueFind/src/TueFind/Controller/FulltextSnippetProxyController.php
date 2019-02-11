<?php
/**
 * Proxy Controller Module
 *
 * @category    TueFind
 * @author      Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @copyright   2019 Universtitätsbibliothek Tübingen
 */
namespace TueFind\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;
use Elasticsearch\ClientBuilder;
use \Exception as Exception;
use Zend\Log\Logger as Logger;
use Zend\View\Model\JsonModel;


/**
 * Proxy for Fulltext Snippets in Elasticsearch
 * @package  Controller
 */

class FulltextSnippetProxyController extends \VuFind\Controller\AbstractBase
{

    protected $base_url = 'nu.ub.uni-tuebingen.de:9200';
    protected $index = 'fulltext';
    protected $es; // Elasticsearch interface
    protected $logger;
    const FIELD = 'document_chunk';
    const DOCUMENT_ID = 'document_id';
    const highlightStartTag = '<span class="highlight">';
    const highlightEndTag = '</span>';


    public function __construct(\Elasticsearch\ClientBuilder $builder, \VuFind\Log\Logger $logger) {
        $this->es = $builder::create()->setHosts([$this->base_url])->build();
        $this->logger = $logger;
    }


    protected function getFulltext($doc_id, $search_query) {
        // Is this an ordinary query or a phrase query (surrounded by quotes) ?
        $is_phrase_query = \TueFind\Utility::isSurroundedByQuotes($search_query);
        $params = [
             'index' => $this->index,
             'type' => '_doc',
             'body' => [
                 '_source' => false,
                 'query' => [
                     'bool' => [
                           'must' => [
                               [ $is_phrase_query ? 'match_phrase' : 'match' => [ self::FIELD => $search_query ] ],
                               [ 'match' => [ self::DOCUMENT_ID => $doc_id ] ]
                           ]
                      ]
                 ],
                 'highlight' => [
                     'fields' => [
                          self::FIELD => [
                               'fragmenter' => 'sentence',
                               'phrase_limit' => '3'
                           ]
                      ]
                 ]
             ]
        ];

        $response = $this->es->search($params);
        return $this->extractSnippets($response);
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
        foreach ($hits as $hit) {
            if (array_key_exists('highlight', $hit))
                $highlight_results = $hit['highlight'][self::FIELD];
            foreach ($highlight_results as $highlight_result) {
                array_push($snippets, $highlight_result);
            }
        }
        return empty($snippets) ? false : $this->formatHighlighting($snippets);
    }


    protected function formatHighlighting($snippets) {
        $formatted_snippets = [];
        foreach ($snippets as $snippet)
            array_push($formatted_snippets, str_replace(['<em>', '</em>'], [self::highlightStartTag, self::highlightEndTag], $snippet));
        return $formatted_snippets;
    }


    public function loadAction()
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
        $snippets = $this->getFulltext($doc_id, $search_query);
        if (empty($snippets))
            return new JsonModel([
                 'status' => 'NO RESULTS'
                ]);

        return new JsonModel([
               'status' => 'SUCCESS',
               'snippets' =>  $snippets
               ]);
    }
}
