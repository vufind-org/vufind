<?php

namespace VuFindSearch\Feature;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;

/**
 * Similar to getIds Interface, but with sitemap-specific result fields
 */
interface GetSitemapFieldsInterface
{
    public function getSitemapFields(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    );
}
