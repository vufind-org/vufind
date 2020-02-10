<?php
namespace TueFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;


class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder {

    const FULLTEXT_ONLY = 'FulltextOnly';
    const FULLTEXT_WITH_SYNONYMS_HANDLER = 'FulltextWithSynonyms';
    const FULLTEXT_ALL_SYNONYMS_HANDLER = 'FulltextAllSynonyms';
    const FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms_all';
    protected $createExplainQuery = false;


    public function setCreateExplainQuery($enable)
    {
        $this->createExplainQuery = $enable;
    }


    protected function useSynonyms($handler)
    {
        switch ($handler) {
            case self::FULLTEXT_WITH_SYNONYMS_HANDLER:
                return true;
            case self::FULLTEXT_ALL_SYNONYMS_HANDLER:
                return true;
         }
         return false;
    }


    protected function getSynonymQueryField($handler)
    {
        switch ($handler) {
            case self::FULLTEXT_WITH_SYNONYMS_HANDLER:
                return self::FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD;
            case self::FULLTEXT_ALL_SYNONYMS_HANDLER:
                return self::FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD;
        }
        return "";
    }


    protected function getSynonymsPartialExpressionOrEmpty($search_handler, $query_terms) {
       return $this->useSynonyms($search_handler) ? ' OR ' . $this->getSynonymQueryField($search_handler) . ':' . $query_terms : '';
    }


    protected function getHandler($query) {
        if ($query instanceof \VuFindSearch\Query\Query)
            return $query->getHandler();
        if ($query instanceof \VuFindSearch\Query\QueryGroup)
            return $query->getReducedHandler();
        return "";
    }


    public function build(AbstractQuery $query)
    {
        $params = parent::build($query);
        if ($this->createExplainQuery) {
            $query_terms =  $this->getLuceneHelper()->extractSearchTerms($query->getAllTerms());
            if (!empty($query_terms) && !($this->getLuceneHelper()->containsRanges($query->getAllTerms()))) {
                $query_terms_normalized =  \TueFind\Utility::isSurroundedByQuotes($query_terms) ?
                                                 $query_terms : '(' . $query_terms . ')';
                $params->set('explainOther', 'fulltext:' . $query_terms_normalized .
                                             ' OR fulltext_unstemmed:' . $query_terms_normalized .
                                             ' OR fulltext_toc:' . $query_terms_normalized .
                                             ' OR fulltext_abstract:' . $query_terms_normalized .
                                             $this->getSynonymsPartialExpressionOrEmpty($this->getHandler($query), $query_terms_normalized));
            }
        }
        return $params;
    }
}
