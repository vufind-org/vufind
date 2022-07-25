<?php

namespace TueFindSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;

class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder {

    const FULLTEXT_ONLY = 'FulltextOnly';
    const FULLTEXT_WITH_SYNONYMS_HANDLER = 'FulltextWithSynonyms';
    const FULLTEXT_ALL_SYNONYMS_HANDLER = 'FulltextAllSynonyms';
    const FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms_all';
    const FULLTEXT_TOC_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_toc_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_TOC_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_toc_synonyms_all';
    const FULLTEXT_ABSTRACT_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_abstract_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_ABSTRACT_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_abstract_synonyms_all';
    const FULLTEXT_SUMMARY_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_summary_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_SUMMARY_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_summary_synonyms_all';
    const FULLTEXT_TYPE_FULLTEXT = "Fulltext";
    const FULLTEXT_TYPE_ABSTRACT = "Abstract";
    const FULLTEXT_TYPE_TOC = "Table of Contents";
    const FULLTEXT_TYPE_SUMMARY = "Summary";

    const TIME_RANGE_HANDLER = 'TimeRangeSearch';
    const TIME_RANGE_BBOX_HANDLER = 'TimeRangeBBox';
    const YEAR_RANGE_BBOX_HANDLER = 'YearRangeBBox';

    protected $includeFulltextSnippets = false;
    protected $selectedFulltextTypes = [];

    public function setIncludeFulltextSnippets($enable)
    {
        $this->includeFulltextSnippets = $enable;
    }


    public function setSelectedFulltextTypes($selected_fulltext_types)
    {
        $this->selectedFulltextTypes = $selected_fulltext_types;
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


    protected function getSynonymQueryField($handler, $fulltext_type)
    {
        switch ($handler) {
            case self::FULLTEXT_WITH_SYNONYMS_HANDLER: {
                switch ($fulltext_type) {
                    case self::FULLTEXT_TYPE_FULLTEXT:
                         return self::FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD;
                    case self::FULLTEXT_TYPE_TOC:
                        return self::FULLTEXT_TOC_ONE_LANGUAGE_SYNONYMS_FIELD;
                    case self::FULLTEXT_TYPE_ABSTRACT:
                        return self::FULLTEXT_ABSTRACT_ONE_LANGUAGE_SYNONYMS_FIELD;
                    case self::FULLTEXT_TYPE_SUMMARY:
                        return self::FULLTEXT_SUMMARY_ONE_LANGUAGE_SYNONYMS_FIELD;
                    break;
                }
            }
            case self::FULLTEXT_ALL_SYNONYMS_HANDLER: {
                switch ($fulltext_type) {
                     case self::FULLTEXT_TYPE_FULLTEXT:
                        return self::FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD;
                     case self::FULLTEXT_TYPE_TOC:
                         return self::FULLTEXT_TOC_ALL_LANGUAGE_SYNONYMS_FIELD;
                     case self::FULLTEXT_TYPE_ABSTRACT:
                         return self::FULLTEXT_ABSTRACT_ALL_LANGUAGE_SYNONYMS_FIELD;
                     case self::FULLTEXT_TYPE_SUMMARY:
                         return self::FULLTEXT_SUMMARY_ALL_LANGUAGE_SYNONYMS_FIELD;
                     break;
                }
            }
        }
        return "";
    }


    protected function getSynonymsPartialExpressionOrEmpty($search_handler, $query_terms, $previous_expression_empty)
    {
       $synonyms_expression = "";
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_FULLTEXT, $this->selectedFulltextTypes)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ?  '' : ' OR ') .
                                      $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_FULLTEXT) . ':' . $query_terms
                                      : '';
           $previous_expression_empty = false;
       }
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_TOC, $this->selectedFulltextTypes)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ? '' : ' OR ') .
                                      $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_TOC) . ':' . $query_terms
                                      : '';
           $previous_expression_empty = false;
       }
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_ABSTRACT, $this->selectedFulltextTypes)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ? '' : ' OR ') .
                                    $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_ABSTRACT) . ':' . $query_terms
                                    : '';
           $previous_expression_empty = false;
       }
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_SUMMARY, $this->selectedFulltextTypes)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ? '' : ' OR ') .
                                    $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_SUMMARY) . ':' . $query_terms
                                    : '';
           $previous_expression_empty = false;
       }
       return $synonyms_expression;
    }


    protected function getHandler($query)
    {
        if ($query instanceof \VuFindSearch\Query\Query)
            return $query->getHandler();
        if ($query instanceof \VuFindSearch\Query\QueryGroup)
            return $query->getReducedHandler();
        return "";
    }


    protected function assembleFulltextTypesQuery($handler, $query_terms)
    {
         $query_string = "";
         if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_FULLTEXT, $this->selectedFulltextTypes))
             $query_string =  (empty($query_string) ? '' : ' OR ') .
                               'fulltext:' . $query_terms .
                               ' OR fulltext_unstemmed:' . $query_terms;
         if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_TOC, $this->selectedFulltextTypes))
             $query_string .= (empty($query_string) ? '' : ' OR ') .
                               'fulltext_toc:' . $query_terms .
                               ' OR fulltext_toc_unstemmed:' . $query_terms;
         if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_ABSTRACT, $this->selectedFulltextTypes))
             $query_string .= (empty($query_string) ? '' : ' OR ') .
                               'fulltext_abstract:' . $query_terms .
                               ' OR fulltext_abstract_unstemmed:' . $query_terms;
         if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_SUMMARY, $this->selectedFulltextTypes))
             $query_string .= (empty($query_string) ? '' : ' OR ') .
                               'fulltext_summary:' . $query_terms .
                               ' OR fulltext_summary_unstemmed:' . $query_terms;
         $query_string .= $this->getSynonymsPartialExpressionOrEmpty($handler, $query_terms, empty($query_string));
         return $query_string;
    }

    public function build(AbstractQuery $query)
    {
        $params = parent::build($query);
        if ($this->includeFulltextSnippets) {
            if (!empty($this->selectedFulltextTypes)) {
                $query_terms = !empty($query->getString()) ? $query->getString() :  '[* TO *]';
                $fulltext_type_query_filter = $this->assembleFulltextTypesQuery($this->getHandler($query),
                                                                                $query_terms);
                if (!empty($fulltext_type_query_filter))
                    $params->set('fq', $fulltext_type_query_filter);
            }
        }
        return $params;
    }

    public function setSpecs(array $specs)
    {
        parent::setSpecs($specs);
        $this->specs[strtolower(self::TIME_RANGE_HANDLER)] = new SearchHandler(['RangeType' => self::TIME_RANGE_HANDLER]);
        $this->specs[strtolower(self::TIME_RANGE_BBOX_HANDLER)] = new SearchHandler(['RangeType' => self::TIME_RANGE_BBOX_HANDLER]);
        $this->specs[strtolower(self::YEAR_RANGE_BBOX_HANDLER)] = new SearchHandler(['RangeType' => self::YEAR_RANGE_BBOX_HANDLER]);
    }
}
