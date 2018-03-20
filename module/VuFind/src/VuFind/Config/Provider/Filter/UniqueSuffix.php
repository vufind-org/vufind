<?php

namespace VuFind\Config\Provider\Filter;

use Zend\EventManager\Filter\FilterIterator as Chain;

class UniqueSuffix
{
    public function __invoke($provider, array $items, Chain $chain)
    {
        $suffixes = array_map([$this, 'getSuffix'], $items);
        $result = array_values(array_combine($suffixes, $items));
        return $chain->isEmpty() ? $result
            : $chain->next($provider, $result, $chain);
    }

    protected function getSuffix(array $item)
    {
        $baseLen = strlen($item['base']) + 1;
        return substr_replace($item['path'], '', 0, $baseLen);
    }
}