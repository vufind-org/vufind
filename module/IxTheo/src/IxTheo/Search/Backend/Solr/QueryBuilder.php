<?php

namespace IxTheo\Search\Backend\Solr;

class QueryBuilder extends \TueFindSearch\Backend\Solr\QueryBuilder
{
    const BIBLE_RANGE_HANDLER = 'BibleRangeSearch';
    const CANONES_RANGE_HANDLER = 'CanonesRangeSearch';

    public function setSpecs(array $specs) {
        parent::setSpecs($specs);
        $this->specs[strtolower(self::BIBLE_RANGE_HANDLER)] = new SearchHandler(['RangeType' => self::BIBLE_RANGE_HANDLER]);
        $this->specs[strtolower(self::CANONES_RANGE_HANDLER)] = new SearchHandler(['RangeType' => self::CANONES_RANGE_HANDLER]);
    }
}
