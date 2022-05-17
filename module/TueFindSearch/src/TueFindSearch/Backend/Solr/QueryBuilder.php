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

    const TIME_ASPECTS_COMMAND = '/usr/local/bin/time_aspects_to_codes_tool';

    // These range constants should be the same as in the solr config
    const TIME_ASPECTS_RANGE_MIN = 0;
    const TIME_ASPECTS_RANGE_MAX = 199999999999;
    const YEAR_RANGE_MIN = -9999;
    const YEAR_RANGE_MAX = 9999;

    protected $includeFulltextSnippets = false;
    protected $selectedFulltextTypes = [];

    protected function getTimeAspectsCommand($searchQuery)
    {
        return implode(' ', [
            self::TIME_ASPECTS_COMMAND,
            escapeshellarg($searchQuery)
        ]);
    }

    protected function getTimeAspects($searchQuery)
    {
        exec($this->getTimeAspectsCommand($searchQuery), $output, $returnVal);
        return explode('_', $output[0]);
    }

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


    protected function getBBoxQuery($field, $from, $to): string
    {
        return '{!field f=' . $field . ' score=overlapRatio queryTargetProportion=0.3}Intersects(ENVELOPE(' . $from . ',' . $to . ',1,0))';
    }


    protected function getYearRangeQuery($params, $field, $rangeMin, $rangeMax)
    {
        $rawRanges = $params->get('q');
        $rawRange = $rawRanges[0];
        if ($rawRange == '*:*')
            return $this->getBBoxQuery($field, self::YEAR_RANGE_MIN, self::YEAR_RANGE_MAX);

        if (strpos('-', $rawRange) === false)
            $rawRange = $rawRange . '-' . $rawRange;
        $parts = explode('-', $rawRange);
        if ($parts[0] == '')
            $parts[0] = $rangeMin;
        if ($parts[1] == '')
            $parts[1] = $rangeMax;

        return $this->getBBoxQuery($field, $parts[0], $parts[1]);
    }


    protected function getTimeRangeQuery($params, $field) {
        $searchString = $params->get('q')[0];
        if ($searchString == '*:*')
            return $this->getBBoxQuery($field, self::TIME_ASPECTS_RANGE_MIN, self::TIME_ASPECTS_RANGE_MAX);
        $timeRange = $this->getTimeAspects($searchString);
        return $this->getBBoxQuery($field, $timeRange[0], $timeRange[1]);
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

        if (method_exists($query, 'getHandler') && $query->getHandler() == 'YearRangeBBox') {
            $params->set('q', $this->getYearRangeQuery($params, 'year_range_bbox', '-9999', '9999'));
        }
        if (method_exists($query, 'getHandler') && $query->getHandler() == 'TimeRangeBBox') {
            $params->set('q', $this->getTimeRangeQuery($params, 'time_aspect_bbox'));
        }

        return $params;
    }
}
