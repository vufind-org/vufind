<?php

namespace KrimDok\Search\Solr;

class Params extends \VuFind\Search\Solr\Params
{

    /**
     * use "author_facet" instead of parent's "authorStr"
     *
     * @param string $sort Sort parameter
     *
     * @return string
     */
    protected function normalizeSort($sort)
    {
        static $table = [
            'year' => ['field' => 'publishDateSort', 'order' => 'desc'],
            'publishDateSort' => ['field' => 'publishDateSort', 'order' => 'desc'],
            'author' => ['field' => 'author_sort', 'order' => 'asc'],
            'author_facet' => ['field' => 'author_sort', 'order' => 'asc'],
            'title' => ['field' => 'title_sort', 'order' => 'asc'],
            'relevance' => ['field' => 'score', 'order' => 'desc'],
            'callnumber' => ['field' => 'callnumber-sort', 'order' => 'asc'],
        ];
        $normalized = [];
        foreach (explode(',', $sort) as $component) {
            $parts = explode(' ', trim($component));
            $field = reset($parts);
            $order = next($parts);
            if (isset($table[$field])) {
                $normalized[] = sprintf(
                    '%s %s',
                    $table[$field]['field'],
                    $order ?: $table[$field]['order']
                );
            } else {
                $normalized[] = sprintf(
                    '%s %s',
                    $field,
                    $order ?: 'asc'
                );
            }
        }
        return implode(',', $normalized);
    }

}
