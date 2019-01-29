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



    public function __construct(\Elasticsearch\ClientBuilder $builder, \VuFind\Log\Logger $logger) {
        $this->es = $builder::create()->setHosts([$this->base_url])->build();
        $this->logger = $logger;
    }


    protected function getFulltext($search_query) {
        $params = [
             'index' => $this->index,
             'type' => '_doc',
             'id' => 'eClyV2gB1ukGMpO-bbil'
        ];
        $response = $this->es->get($params);
        $source = array_key_exists('_source', $response) ? $response['_source'] : false;
        if ($source == false)
            return false;
        return [ $source['document_id'], $source['document_chunk'] ];
        return false;
    }


    public function loadAction()
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);
        $search_query = ($parameters['search_query']);
        if (empty($search_query))
            return new JsonModel([
                'status' => 'EMPTY QUERY'
                ]);
        $snippets = $this->getFulltext($search_query);
        if (empty($snippets))
            return new JsonModel([
                 'status' => 'NO RESULTS'
                ]);

        //$this->logger->log(Logger::NOTICE, 'Fulltext Snippet ' . json_encode($snippets));
        return new JsonModel([
               'status' => 'SUCCESS',
               'snippets' =>  $snippets
               ]);
    }
}
