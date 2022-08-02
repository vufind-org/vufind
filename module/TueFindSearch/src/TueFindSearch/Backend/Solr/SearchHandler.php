<?php

namespace TueFindSearch\Backend\Solr;

class SearchHandler extends \VuFindSearch\Backend\Solr\SearchHandler {
    // These range constants should be the same as in the solr config
    const TIME_ASPECTS_COMMAND = '/usr/local/bin/time_aspects_to_codes_tool';
    const TIME_ASPECTS_RANGE_MIN = 0;
    const TIME_ASPECTS_RANGE_MAX = 199999999999;
    const TIME_RANGE_PARSER = 'timeAspectRangeParser';
    const TIME_RANGE_BBOX_FIELD = 'time_aspect_bbox';
    const YEAR_RANGE_BBOX_FIELD = 'year_range_bbox';
    const YEAR_RANGE_MIN = -9999;
    const YEAR_RANGE_MAX = 9999;


    public function __construct(array $spec, $defaultDismaxHandler = 'dismax') {
        parent::__construct($spec, $defaultDismaxHandler);
        $this->specs['RangeType'] = $spec['RangeType'] ?? null;
    }

    protected function createQueryString($search, $advanced = false) {
        switch ($this->specs['RangeType']) {
            case QueryBuilder::TIME_RANGE_HANDLER:
                $rangeReferences = $this->parseTimeAspect($search);
                return $this->translateRangesToSearchString($rangeReferences, self::TIME_RANGE_PARSER);
            case QueryBuilder::TIME_RANGE_BBOX_HANDLER:
                return $this->getTimeRangeQuery($search);
            case QueryBuilder::YEAR_RANGE_BBOX_HANDLER:
                return $this->getYearRangeQuery($search);
            default:
                return parent::createQueryString($search, $advanced);
        }
    }

    protected function getTimeAspectsCommand($searchQuery) {
        return implode(' ', [
            self::TIME_ASPECTS_COMMAND,
            escapeshellarg($searchQuery)
        ]);
    }

    protected function getTimeAspects($searchQuery) {
        exec($this->getTimeAspectsCommand($searchQuery), $output, $returnVal);
        return explode('_', $output[0]);
    }

    protected function getBBoxQuery($field, $from, $to): string
    {
        return '{!field f=' . $field . ' score=overlapRatio queryTargetProportion=0.3}Intersects(ENVELOPE(' . $from . ',' . $to . ',1,0))';
    }

    protected function getYearRangeQuery($params) {
        $rawRanges = $params->get('q');
        $rawRange = $rawRanges[0];
        if ($rawRange == '*:*')
            return $this->getBBoxQuery(self::YEAR_RANGE_BBOX_FIELD, self::YEAR_RANGE_MIN, self::YEAR_RANGE_MAX);

        if (strpos('-', $rawRange) === false)
            $rawRange = $rawRange . '-' . $rawRange;
        $parts = explode('-', $rawRange);
        if ($parts[0] == '')
            $parts[0] = self::YEAR_RANGE_MIN;
        if ($parts[1] == '')
            $parts[1] = self::YEAR_RANGE_MAX;

        return $this->getBBoxQuery(self::YEAR_RANGE_BBOX_FIELD, $parts[0], $parts[1]);
    }

    protected function getTimeRangeQuery($search) {
        if ($search == '*:*')
            return $this->getBBoxQuery(self::TIME_RANGE_BBOX_FIELD, self::TIME_ASPECTS_RANGE_MIN, self::TIME_ASPECTS_RANGE_MAX);
        $timeRange = $this->getTimeAspects($search);
        return $this->getBBoxQuery(self::TIME_RANGE_BBOX_FIELD, $timeRange[0], $timeRange[1]);
    }

    protected function parseTimeAspect($search) {
        if (!empty($search)) {
            $cmd = $this->getTimeAspectsCommand($search);
            exec($cmd, $output, $return_var);
            return $output;
        }
        return [];
    }

    protected function translateRangesToSearchString($rangeReferences, $rangeParser) {
        if (empty($rangeReferences)) {
            // if no references were found for given query, search for a range which doesn't exist to get no result.
            $rangeReferences = ["999999999_999999999"];
        }
        $searchString = implode(',', $rangeReferences);

        $query = '{!' . $rangeParser .'}' . $searchString;
        $wrappedQuery = '_query_:"' . addslashes($query) . '"';
        return $wrappedQuery;
    }
}
