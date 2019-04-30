<?php
namespace TueFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use VuFindSearch\Query\QueryGroup;


class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder {
    protected $createExplainQuery = true;

    public function setCreateExplainQuery($enable)
    {
        $this->createExplainQuery = $enable;
    }

    public function build(AbstractQuery $query) {
        $params = parent::build($query);
        if ($this->createExplainQuery) {
            $query_terms =  $this->getLuceneHelper()->extractSearchTerms($query->getAllTerms());
            if (!empty($query_terms) && !($this->getLuceneHelper()->containsRanges($query->getAllTerms()))) {
                $query_terms_normalized =  \TueFind\Utility::isSurroundedByQuotes($query_terms) ?
                                                 $query_terms : '(' . $query_terms . ')';
                $params->set('explainOther', 'fulltext:' . $query_terms_normalized .  ' OR fulltext_unstemmed:' . $query_terms_normalized);
            }
        }
        return $params;
    }
         

}
